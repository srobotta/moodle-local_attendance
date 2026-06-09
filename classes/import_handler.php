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

defined('MOODLE_INTERNAL') || die();

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
    /**
     * The course that the attendance course is created for.
     * @var \stdClass|null
     **/
    private ?\stdClass $sourcecourse = null;

    /**
     * The options from the form or CLI arguments.
     * @var \stdClass|null
     */
    private ?\stdClass $options = null;

    /**
     * The new attendance course that is created.
     * @var \stdClass|null
     **/
    private ?\stdClass $course = null;

    /**
     * Constructor.
     * @param \stdClass|null $options
     */
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
    public function use_course(array $data): \stdClass {
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
                'column' => 'source_course_url',
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
    public function create_course(array $data): \stdClass {
        global $CFG;
        $newdata = $data;
        $this->use_course($data);
        if (!\array_key_exists('shortname', $newdata)) {
            $newdata['shortname'] = $this->course->shortname
                . '-' . ($this->options->suffix ?? time());
        }
        if (!\array_key_exists('name', $newdata)) {
            $newdata['fullname'] = $this->course->fullname
                . ' (' . ($this->options->suffix ?? get_string('form_label_coursesuffix', 'local_attendance')) . ')';
        } else {
            $newdata['fullname'] = $newdata['name'];
            unset($newdata['name']);
        }
        // Fields that might be overwritten and are otherwise taken from the source course.
        $fieldsfromsource = ['category', 'visible', 'format', 'startdate', 'enddate'];
        foreach ($fieldsfromsource as $field) {
            if (!\array_key_exists($field, $newdata)) {
                $newdata[$field] = $this->course->$field;
            }
        }
        // Remove numsections if present to use default, otherwise there could be conflicts.
        if (\array_key_exists('numsections', $newdata)) {
            unset($newdata['numsections']);
        }
        // However, check if section_name_X fields are present to create those sections later.
        $newsectiondata = $this->get_new_section_data($newdata);

        // Check permissions before creating the course.
        $catcontext = \context_coursecat::instance($newdata['category']);
        require_capability('moodle/course:create', $catcontext);
        $newcourse = create_course((object)$newdata);

        // Create sections in the new course if any section names were given.
        $this->create_sections($newcourse, $newsectiondata);

        // Link the new course in the source course by adding a URL module in the old course.
        if (\array_key_exists('link_new_course', $newdata)) {
            $modlink = [
                'module' => 'url',
                'name' => $newdata['link_new_course'],
                'externalurl' => $CFG->wwwroot . '/course/view.php?id=' . $newcourse->id,
                'section' => 0,
            ];
            unset($newdata['link_new_course']);
            if (\array_key_exists('link_new_course_section', $newdata)) {
                $modlink['section'] = (int)$newdata['link_new_course_section'];
                unset($newdata['link_new_course_section']);
            }
            if (\array_key_exists('link_new_course_section_position', $newdata)) {
                $modlink['section_pos'] = (int)$newdata['link_new_course_section_position'];
                unset($newdata['link_new_course_section_position']);
            }
            $this->create_module($modlink);
        }

        // Check whether to add meta enrolment.
        if (utils::is_set_and_enabled('metaenrolment', $newdata)) {
            $this->add_meta_enrolment($newcourse);
        }
        // Check whether to copy participants.
        if (utils::is_set_and_enabled('copyparticipants', $newdata)) {
            $this->copy_course_participants($newcourse);
        }
        // Now delete the options if they had been set but maybe not enabled.
        if (\array_key_exists('metaenrolment', $newdata)) {
            unset($newdata['metaenrolment']);
        }
        if (\array_key_exists('copyparticipants', $newdata)) {
            unset($newdata['copyparticipants']);
        }
        // Set course completions.
        foreach (\array_keys($newdata) as $key) {
            if (str_starts_with($key, 'completion_criteria_')) {
                $this->add_course_completion($newcourse, $newdata);
                break;
            }
        }
        // Done, we rembember the source course and return the new course.
        $this->sourcecourse = $this->course;
        $this->course = $newcourse;
        return $this->course;
    }

    /**
     * Get new section data from the CSV row, in case there are any.
     * @param array $newdata from the CSV
     * @return array of sectionnum => sectionname
     * @throws \moodle_exception
     */
    protected function get_new_section_data(array &$newdata): array {
        $newsectiondata = [];
        foreach (\array_keys($newdata) as $key) {
            if (str_starts_with($key, 'section_name_')) {
                $sectionparts = explode('_', $key);
                if (count($sectionparts) === 3) {
                    $sectionnum = (int)$sectionparts[2] - 1;
                    if ($sectionnum < 0) {
                        $a = [
                            'value' => $sectionparts[2],
                            'column' => $key,
                        ];
                        throw new \moodle_exception('ex_invalidvalue', 'local_attendance', '', $a);
                    }
                    $sectionname = $newdata[$key];
                    if (trim($sectionname) === '') {
                        $a = [
                            'value' => $sectionname,
                            'column' => $key,
                        ];
                        throw new \moodle_exception('ex_invalidvalue', 'local_attendance', '', $a);
                    }
                    $newsectiondata[$sectionnum] = $sectionname;
                    unset($newdata[$key]);
                }
            }
        }
        if (empty($newsectiondata)) {
            return [];
        }
        for ($i = 0; $i < max(\array_keys($newsectiondata)) + 1; $i++) {
            if (!\array_key_exists($i, $newsectiondata)) {
                $newsectiondata[$i] = '';
            }
        }
        ksort($newsectiondata);
        return $newsectiondata;
    }

    /**
     * Create sections in the new course based on the given data.
     * @param \stdClass $newcourse
     * @param array $newsectiondata
     * @return void
     */
    protected function create_sections(\stdClass $newcourse, array $newsectiondata): void {
        $existingssections = get_fast_modinfo($newcourse)->get_section_info_all();
        foreach ($newsectiondata as $secnum => $secname) {
            $section = !\array_key_exists($secnum, $existingssections)
                ? course_create_section($newcourse->id)
                : $existingssections[$secnum];
            if (!empty($secname)) {
                course_update_section($newcourse->id, $section, ['name' => $secname]);
            }
        }
    }

    /**
     * Add course completion criteria to the new course.
     * @param \stdClass $newcourse
     * @param array $newdata
     * @return void
     */
    protected function add_course_completion(\stdClass $newcourse, array $newdata): void {
        // @codingStandardsIgnoreLine
        global $CFG, $COMPLETION_CRITERIA_TYPES;
        // Classes must be loaded here.
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_self.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_date.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_unenrol.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_duration.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_grade.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_role.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_course.php');

        // Prepare data object for criteria.
        $data = [
            'id' => $newcourse->id,
        ];
        $data['overall_aggregation'] = \array_key_exists('completion_criteria_overall_aggregation', $newdata)
            ? utils::any_or_all('completion_criteria_overall_aggregation', $newdata) : COMPLETION_AGGREGATION_ALL;
        $data['activity_aggregation'] = 0;
        if (\array_key_exists('completion_criteria_activity', $newdata)) {
            $activityids = \array_map('intval', explode(',', $newdata['completion_criteria_activity']));
            if (!empty($activityids)) {
                $data['criteria_activity'] = array_fill_keys($activityids, 1);
                $data['activity_aggregation'] = utils::any_or_all('completion_criteria_activity_aggregation', $newdata);
            }
        }
        $data['course_aggregation'] = 0;
        if (\array_key_exists('completion_criteria_course', $newdata)) {
            $data['criteria_course'] = \array_map('intval', explode(',', $newdata['completion_criteria_course']));
            if (!empty($data['criteria_course'])) {
                $data['course_aggregation'] = utils::any_or_all('completion_criteria_course_aggregation', $newdata);
            }
        }
        $data['role_aggregation'] = 0;
        if (\array_key_exists('completion_criteria_role', $newdata)) {
            $roles = \array_map('intval', explode(',', $newdata['completion_criteria_role']));
            if (!empty($roles)) {
                $data['criteria_role'] = array_fill_keys($roles, 1);
                $data['role_aggregation'] = utils::any_or_all('completion_criteria_role_aggregation', $newdata);
            }
        }
        // Simple criteria value mapping.
        foreach (['date', 'duration', 'grade'] as $criterion) {
            $keycriterion = 'completion_criteria_' . $criterion;
            if (\array_key_exists($keycriterion, $newdata)) {
                $data['criteria_' . $criterion] = 1;
                $keyvalue = 'criteria_' . $criterion . '_' . ($criterion === 'duration' ? 'days' : 'value');
                $data[$keyvalue] = $criterion === 'date'
                    ? utils::parse_datetime($keycriterion, $newdata)
                    : $newdata[$keycriterion];
            }
        }
        foreach (['unenrol', 'self'] as $criterion) {
            if (\array_key_exists('completion_criteria_' . $criterion, $newdata)) {
                $data['criteria_' . $criterion] = (int)$newdata['completion_criteria_' . $criterion] === 1 ? 1 : 0;
            }
        }
        $data = (object)$data;

        $completion = new \completion_info($newcourse);
        // Delete old criteria.
        $completion->clear_criteria(false);

        // Loop through each criteria type and run its update_config() method.
        // @codingStandardsIgnoreLine
        foreach ($COMPLETION_CRITERIA_TYPES as $type) {
            $class = '\\completion_criteria_' . $type;
            $criterion = new $class();
            $criterion->update_config($data);
        }

        // Handle overall aggregation.
        $aggdata = [
            'course' => $data->id,
            'criteriatype' => null,
        ];
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod($data->overall_aggregation);
        $aggregation->save();

        // Handle aggregation types.
        $aggregationtypes = [
            COMPLETION_CRITERIA_TYPE_ACTIVITY => $data->activity_aggregation,
            COMPLETION_CRITERIA_TYPE_COURSE => $data->course_aggregation,
            COMPLETION_CRITERIA_TYPE_ROLE => $data->role_aggregation,
        ];
        foreach ($aggregationtypes as $type => $method) {
            $aggdata['criteriatype'] = $type;
            $aggregation = new \completion_aggregation($aggdata);
            $aggregation->setMethod($method);
            $aggregation->save();
        }

        // Trigger an event for course module completion changed.
        $event = \core\event\course_completion_updated::create([
            'courseid' => $newcourse->id,
            'context' => \context_course::instance($newcourse->id),
        ]);
        $event->trigger();
    }

    /**
     * Create a module in the current course.
     * @param array $data
     * @return modcreate_interface
     * @throws \moodle_exception
     */
    public function create_module(array $data): modcreate_interface {
        // Which module to add, load the correct class.
        if (str_contains($data['module'], '_')) {
            $modnameparts = explode('_', $data['module']);
            $modclassname = implode('_', \array_slice($modnameparts, 0, 2))
                . '\\mod\\' . implode('\\', \array_slice($modnameparts, 2));
            try {
                $modclass = new $modclassname();
            } catch (\Exception $e) {
                throw new \moodle_exception('ex_invalidmoduleclass', 'local_attendance', '', $modclassname);
            }
            if (!($modclass instanceof modcreate_interface)) {
                throw new \moodle_exception('ex_invalidimplements', 'local_attendance', '', $modclassname);
            }
        } else {
            $modclass = new modcreate();
        }

        // In the class that creates the module, use the current course and create the module from the data.
        $modclass->use_course($this->course);
        try {
            return $modclass->set_row($data)->create($data);
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
    public function copy_course_participants(\stdClass $newcourse): void {
        $contextfrom = \context_course::instance($this->course->id);
        $enrols = enrol_get_instances($newcourse->id, true);

        foreach ($enrols as $enrol) {
            $enrolplugin = enrol_get_plugin($enrol->enrol);
            if ($enrolplugin === null) {
                continue;
            }

            $enrolledusers = \array_keys(get_enrolled_users($contextfrom, '', 0, 'u.id'));
            $users = get_users_roles($contextfrom, $enrolledusers);
            foreach ($users as $userid => $roles) {
                if (!\in_array($userid, $enrolledusers)) {
                    continue; // Skip not enrolled.
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
    public function add_meta_enrolment(\stdClass $newcourse): void {
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
        throw new \moodle_exception('ex_metaenrolmentnotpossible', 'local_attendance');
    }

    /**
     * Create a badge in the current course.
     * @param array $data
     * @return modcreate_interface
     * @throws \moodle_exception
     */
    public function create_badge(array $data): modcreate_interface {
        if (!\array_key_exists('imagecaption', $data)) {
            $data['imagecaption'] = $this->sourcecourse->shortname ?? $this->course->shortname ?? '';
        }
        if (\array_key_exists('imagefile', $data)) {
            if (!isset($this->options->files) || !\array_key_exists($data['imagefile'], $this->options->files)) {
                throw new \moodle_exception('ex_filemissing', 'local_attendance', '', $data['imagefile']);
            }
            $data['imagefile'] = $this->options->files[$data['imagefile']];
        }
        $badge = new badge();
        $badge->use_course($this->course)->set_row($data)->create($data);
        return $badge;
    }
}
