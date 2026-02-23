<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_attendance;

use local_attendance\content\badge;
use local_attendance\utils\utils;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/edit_form.php');

/**
 * Handler for importing attendance courses and modules from CSV.
 *
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_handler {

    private ?\stdClass $sourceCourse = null;
    private ?\stdClass $options = null;
    private ?\stdClass $course = null;

    public function __construct(?\stdClass $options = null) {
        if ($options !== null) {
            $this->options = $options;
        } else {
            $this->options = new \stdClass();
        }
    }

    /**
     * Load an existing course based on the data from the CSV.
     * @param array $data
     * @return \stdClass
     * @throws \moodle_exception
     */
    public function useCourse(array $data): \stdClass {
        global $CFG, $DB;
        if (\array_key_exists('source_course_id', $data)) {
            $this->course = get_course($data['source_course_id']);
            unset($data['source_course_id']);
            return $this->course;
        }
        if (\array_key_exists('source_course_short', $data)) {
            $this->course = $DB->get_record(
                'course',
                ['shortname' => $data['source_course_short']],
                '*',
                MUST_EXIST
            );
            unset($data['source_course_short']);
            return $this->course;
        }
        if (\array_key_exists('source_course_url', $data)) {
            if (str_contains($data['source_course_url'], $CFG->wwwroot . '/course/view.php')) {
                [$foo, $query] = explode('?', $data['source_course_url'], 2);
                if (!is_null($query) && preg_match('/\bid=(\d+)\b$/', $query, $matches)) {
                    $this->course = get_course($matches[1]);
                    unset($data['source_course_url']);
                    return $this->course;
                }
            }
            $a = [
                'value' => $data['source_course_url'],
                'column' => 'source_course_url'
            ];
            throw new \moodle_exception('ex_invalidvalue', 'local_attendance', '', $a);
        }
        throw new \moodle_exception('ex_nosourcecourse', 'local_attendance');
    }

    /**
     * Create a course based on the current course used.
     * @param array $data
     * @return \stdClass
     * @throws \moodle_exception
     */
    public function createCourse(array $data): \stdClass {
        global $CFG;
        $newData = $data;
        $this->useCourse($data);
        if (!\array_key_exists('shortname', $newData)) {
            $newData['shortname'] = $this->course->shortname . '-' . $this->options->suffix;
        }
        if (!\array_key_exists('name', $newData)) {
            $newData['fullname'] = $this->course->fullname . ' (' . $this->options->suffix . ')';
        }
        // Fields that might be overwritten and are otherwise taken from the source course.
        $fieldsFromSource = ['category', 'visible', 'format', 'startdate', 'enddate'];
        foreach ($fieldsFromSource as $field) {
            if (!\array_key_exists($field, $newData)) {
                $newData[$field] = $this->course->$field;;
            }
        }
        // Remove numsections if present to use default, otherwise there could be conflicts.
        if (\array_key_exists('numsections', $newData)) {
            unset($newData['numsections']);
        }
        // However, check if section_name_X fields are present to create those sections later.
        $newSectionData = $this->getNewSectionData($newData);

        // Check permissions before creating the course.
        $catcontext = \context_coursecat::instance($newData['category']);
        require_capability('moodle/course:create', $catcontext);
        $newCourse = create_course((object)$newData);

        // Create sections in the new course if any section names were given.
        $this->createSections($newCourse, $newSectionData);

        // Link the new course in the source course by adding a URL module in the old course.
        if (\array_key_exists('link_new_course', $newData)) {
            $modLink= [
                'module' => 'url',
                'name' => $newData['link_new_course'],
                'externalurl' => $CFG->wwwroot . '/course/view.php?id=' . $newCourse->id,
                'section' => 0,
            ];
            unset($newData['link_new_course']);
            if (\array_key_exists('link_new_course_section', $newData)) {
                $modLink['section'] = (int)$newData['link_new_course_section'];
                unset($newData['link_new_course_section']);
            }
            if (\array_key_exists('link_new_course_section_position', $newData)) {
                $modLink['section_pos'] = (int)$newData['link_new_course_section_position'];
                unset($newData['link_new_course_section_position']);
            }
            $this->createModule($modLink);
        }

        // Check whether to add meta enrolment.
        if (utils::isSetAndEnabled('metaenrolment', $newData)) {
            $this->addMetaEnrolment($newCourse);
        }
        // Check whether to copy participants.
        if (utils::isSetAndEnabled('copyparticipants', $newData)) {
            $this->copyCourseParticipants($newCourse);
        }
        // Now delete the options if they had been set but maybe not enabled.
        if (\array_key_exists('metaenrolment', $newData)) {
            unset($newData['metaenrolment']);
        }
        if (\array_key_exists('copyparticipants', $newData)) {
            unset($newData['copyparticipants']);
        }
        // Set course completions.
        foreach (\array_keys($newData) as $key) {
            if (str_starts_with($key, 'completion_criteria_')) {
                $this->addCourseCompletion($newCourse, $newData);
                break;
            }
        }
        // Done, we rembember the source course and return the new course.
        $this->sourceCourse = $this->course;
        $this->course = $newCourse;
        return $this->course;
    }

    /**
     * Get new section data from the CSV row, in case there are any.
     * @param array $newData from the CSV
     * @return array of sectionnum => sectionname
     * @throws \moodle_exception
     */
    protected function getNewSectionData(array &$newData): array {
        $newSectionData = [];
        foreach (\array_keys($newData) as $key) {
            if (str_starts_with($key, 'section_name_')) {
                $sectionparts = explode('_', $key);
                if (count($sectionparts) === 3) {
                    $sectionnum = (int)$sectionparts[2] - 1;
                    if ($sectionnum < 0) {
                        $a = [
                            'value' => $sectionparts[2],
                            'column' => $key
                        ];
                        throw new \moodle_exception('ex_invalidvalue', 'local_attendance', '', $a);
                    }
                    $sectionname = $newData[$key];
                    if (trim($sectionname) === '') {
                        $a = [
                            'value' => $sectionname,
                            'column' => $key
                        ];
                        throw new \moodle_exception('ex_invalidvalue', 'local_attendance', '', $a);
                    }
                    $newSectionData[$sectionnum] = $sectionname;
                    unset($newData[$key]);
                }
            }
        }
        for ($i = 0; $i < max(\array_keys($newSectionData)) + 1; $i++) {
            if (!\array_key_exists($i, $newSectionData)) {
                $newSectionData[$i] = '';
            }
        }
        ksort($newSectionData);
        return $newSectionData;
    }

    /**
     * Create sections in the new course based on the given data.
     * @param \stdClass $newCourse
     * @param array $newSectionData
     * @return void
     */
    protected function createSections(\stdClass $newCourse, array $newSectionData): void {
        $existingsSections = get_fast_modinfo($newCourse)->get_section_info_all();
        foreach ($newSectionData as $sectionNum => $sectionName) {
            $section = !\array_key_exists($sectionNum, $existingsSections)
                ? course_create_section($newCourse->id)
                : $existingsSections[$sectionNum];
            if (!empty($sectionName)) {
                course_update_section($newCourse->id, $section, ['name' => $sectionName]);
            }
        }
    }

    /**
     * Add course completion criteria to the new course.
     * @param \stdClass $newCourse
     * @param array $newData
     * @return void
     */
    protected function addCourseCompletion(\stdClass $newCourse, array $newData): void {
        global $CFG;
        // Classes must be loaded here.
        require_once($CFG->libdir.'/completionlib.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_self.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_date.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_unenrol.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_duration.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_grade.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_role.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_course.php');

        // Prepare data object for criteria.
        $data = [
            'id' => $newCourse->id,
        ];
        $data['overall_aggregation'] = \array_key_exists('completion_criteria_overall_aggregation', $newData)
            ? utils::anyOrAll('completion_criteria_overall_aggregation', $newData) : COMPLETION_AGGREGATION_ALL;
        $data['activity_aggregation'] = 0;
        if (\array_key_exists('completion_criteria_activity', $newData)) {
            $activityIds = \array_map('intval', explode(',', $newData['completion_criteria_activity']));
            if (!empty($activityIds)) {
                $data['criteria_activity'] = array_fill_keys($activityIds, 1);
                $data['activity_aggregation'] = utils::anyOrAll('completion_criteria_activity_aggregation', $newData);
            }
        }
        $data['course_aggregation'] = 0;
        if (\array_key_exists('completion_criteria_course', $newData)) {
            $data['criteria_course'] = \array_map('intval', explode(',', $newData['completion_criteria_course']));
            if (!empty($data['criteria_course'])) {
                $data['course_aggregation'] = utils::anyOrAll('completion_criteria_course_aggregation', $newData);
            }
        }
        $data['role_aggregation'] = 0;
        if (\array_key_exists('completion_criteria_role', $newData)) {
            $roles = \array_map('intval', explode(',', $newData['completion_criteria_role']));
            if (!empty($roles)) {
                $data['criteria_role'] = array_fill_keys($roles, 1);
                $data['role_aggregation'] = utils::anyOrAll('completion_criteria_role_aggregation', $newData);
            }
        }
        // Simple criteria value mapping
        foreach (['date', 'duration', 'grade'] as $criterium) {
            if (\array_key_exists('completion_criteria_' . $criterium, $newData)) {
                $data['criteria_' . $criterium] = 1;
                $data['criteria_' . $criterium . '_value'] = $criterium === 'date'
                    ? utils::parseDateTime('completion_criteria_' . $criterium, $newData)
                    : $newData['completion_criteria_' . $criterium];
            }
        }
        foreach (['unenrol', 'self'] as $criterium) {
            if (\array_key_exists('completion_criteria_' . $criterium, $newData)) {
                $data['criteria_' . $criterium] = (int)$newData['completion_criteria_' . $criterium] === 1 ? 1 : 0;
            }
        }
        $data = (object)$data;

        $completion = new \completion_info($newCourse);
        // Delete old criteria.
        $completion->clear_criteria(false);

        // Loop through each criteria type and run its update_config() method.
        global $COMPLETION_CRITERIA_TYPES;
        foreach ($COMPLETION_CRITERIA_TYPES as $type) {
            $class = '\\completion_criteria_'.$type;
            $criterion = new $class();
            $criterion->update_config($data);
        }

        // Handle overall aggregation.
        $aggdata = [
            'course' => $data->id,
            'criteriatype' => null
        ];
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod($data->overall_aggregation);
        $aggregation->save();

        // Handle aggregation types.
        $aggregationTypes = [
            COMPLETION_CRITERIA_TYPE_ACTIVITY => $data->activity_aggregation,
            COMPLETION_CRITERIA_TYPE_COURSE => $data->course_aggregation,
            COMPLETION_CRITERIA_TYPE_ROLE => $data->role_aggregation,
        ];
        foreach ($aggregationTypes as $type => $method) {
            $aggdata['criteriatype'] = $type;
            $aggregation = new \completion_aggregation($aggdata);
            $aggregation->setMethod($method);
            $aggregation->save();
        }

        // Trigger an event for course module completion changed.
        $event = \core\event\course_completion_updated::create([
            'courseid' => $newCourse->id,
            'context' => \context_course::instance($newCourse->id)
        ]);
        $event->trigger();
    }

    /**
     * Create a module in the current course.
     * @param array $data
     * @return modcreate_interface
     * @throws \moodle_exception
     */
    public function createModule(array $data): modcreate_interface {
        // Which module to add, load the correct class.
        if (str_contains($data['module'], '_')) {
            $modNameParts = explode('_', $data['module']);
            $modClassName = implode('_', \array_slice($modNameParts, 0, 2)) . '\\mod\\' . implode('\\', \array_slice($modNameParts, 2));
            try {
                $modClass = new $modClassName();
            } catch (\Exception $e) {
                throw new \moodle_exception('ex_invalidmoduleclass', 'local_attendance', '', $modClassName);
            }
            if (!($modClass instanceof modcreate_interface)) {
                throw new \moodle_exception('ex_invalidimplements', 'local_attendance', '', $modClassName);
            }
        } else {
            $modClass = new modcreate();
        }

        // In the class that creates the module, use the current course and create the module from the data.
        $modClass->useCourse($this->course);
        try {
            return $modClass->setRow($data)->create($data);
        } catch (\Exception $e) {
            debugging($e->getMessage());
            throw new \moodle_exception('ex_modulecreationfailed', 'local_attendance');
        }
    }

    /**
     * Copy enroled participants from the current course to the new course.
     * @param \stdClass $newcourse
     * @return void
     */
    public function copyCourseParticipants(\stdClass $newcourse): void {
        $contextFrom = \context_course::instance($this->course->id);
        $enrols = enrol_get_instances($newcourse->id, true);

        foreach ($enrols as $enrol) {
            $enrolplugin = enrol_get_plugin($enrol->enrol);
            if ($enrolplugin === null) {
                continue;
            }

            $enrolledUsers = \array_keys(get_enrolled_users($contextFrom, '', 0, 'u.id'));
            $users = get_users_roles($contextFrom, $enrolledUsers);
            foreach ($users as $userid => $roles) {
                if (!\in_array($userid, $enrolledUsers)) {
                    continue; // skip not enrolled
                }
                foreach ($roles as $role) {
                    $enrolplugin->enrol_user($enrol, $userid, $role->roleid);
                }
            }
            break;
        }
    }

    /**
     * Add meta enrolment to the new course from the source course.
     * @param \stdClass $newcourse
     * @return void
     */
    public function addMetaEnrolment(\stdClass $newcourse): void {
        $plugins = enrol_get_plugins(true);
        foreach ($plugins as $plugin) {
            if ($plugin->get_name() === 'meta') {
                $enrols = enrol_get_instances($newcourse->id, true);
                foreach ($enrols as $enrol) {
                    if ($enrol->enrol === 'meta') {
                        // Meta enrolment already exists.
                        return;
                    }
                }
                $plugin->add_instance($newcourse, ['customint1' => $this->course->id]);
                return;
            }
        }
    }

    /**
     * Create a badge in the current course.
     * @param array $data
     * @return modcreate_interface
     * @throws \moodle_exception
     */
    public function createBadge(array $data): modcreate_interface {
        if (!\array_key_exists('imagecaption', $data)) {
            $data['imagecaption'] = $this->sourceCourse->shortname ?? $this->course->shortname ?? '';
        }
        if (\array_key_exists('imagefile', $data)) {
            if (!isset($this->options->files) || !\array_key_exists($data['imagefile'], $this->options->files)) {
                throw new \moodle_exception('ex_filemissing', 'local_attendance', '', $data['imagefile']);
            }
            $data['imagefile'] = $this->options->files[$data['imagefile']];
        }
        $badge = new badge();
        $badge->useCourse($this->course)->setRow($data)->create($data);
        return $badge;
    }
}