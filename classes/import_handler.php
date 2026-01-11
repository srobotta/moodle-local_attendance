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

use core\plugininfo\mod;
use local_attendance\content\badge;

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
    private ?\stdClass $badge = null;

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
        global $DB;
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
        throw new \moodle_exception('ex_nosourcecourse', 'local_attendance');
    }

    /**
     * Create a course based on the current course used.
     * @param array $data
     * @return \stdClass
     * @throws \moodle_exception
     */
    public function createCourse(array $data): \stdClass {
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

        // Check permissions before creating the course.
        $catcontext = \context_coursecat::instance($newData['category']);
        require_capability('moodle/course:create', $catcontext);
        $newCourse = create_course((object)$newData);
        // Copy course enrolments from the source course.
        if (!\array_key_exists('noparticipants', $newData)) {
            $this->copyCourseEnrolments($newCourse);
        } else {
            unset($newData['noparticipants']);
        }
        $this->sourceCourse = $this->course;
        $this->course = $newCourse;
        return $this->course;
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
     * Copy enrolments from the current course to the new course.
     * @param \stdClass $newcourse
     * @return void
     */
    public function copyCourseEnrolments(\stdClass $newcourse): void {
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
     * Create a badge in the current course.
     * @param array $data
     * @return modcreate_interface
     * @throws \moodle_exception
     */
    public function createBadge(array $data): modcreate_interface {
        if (!\array_key_exists('imagecaption', $data)) {
            $data['imagecaption'] = $this->sourceCourse->shortname ?? $this->course->shortname ?? '';
        }
        $badge = new badge();
        $badge->useCourse($this->course)->setRow($data)->create($data);
        return $badge;
    }
}