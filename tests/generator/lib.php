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

/**
 * Data generator for local_attendance plugin.
 *
 * @package     local_attendance
 * @category    test
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator class for local_attendance plugin.
 *
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_attendance_generator extends component_generator_base {

    /**
     * Process the attendance course setup, which enrols users with specified enrolment methods.
     *
     * @param array $data
     * @return void
     */
    public function create_attendance_course_setup($data) {
        global $DB;

        $courseid = $data['sourcecourse'];

        // Get enrolment methods for this course.
        $enrols = enrol_get_instances($courseid, true);
        $selfenrol = null;
        $manualenrol = null;

        foreach ($enrols as $enrol) {
            if ($enrol->enrol === 'self') {
                $selfenrol = $enrol;
            }
            if ($enrol->enrol === 'manual') {
                $manualenrol = $enrol;
            }
        }

        // Enrol first student with self enrolment.
        if (!empty($data['student1username'])) {
            $user1 = $DB->get_record('user', ['username' => $data['student1username']], '*', MUST_EXIST);
            if ($selfenrol) {
                $plugin = enrol_get_plugin('self');
                if ($plugin) {
                    $plugin->enrol_user($selfenrol, $user1->id);
                }
            }
        }

        // Enrol second student with manual enrolment.
        if (!empty($data['student2username'])) {
            $user2 = $DB->get_record('user', ['username' => $data['student2username']], '*', MUST_EXIST);
            if ($manualenrol) {
                $plugin = enrol_get_plugin('manual');
                if ($plugin) {
                    $plugin->enrol_user($manualenrol, $user2->id);
                }
            }
        }

        // Enrol trainer with trainer role.
        if (!empty($data['trainerusername'])) {
            $trainer = $DB->get_record('user', ['username' => $data['trainerusername']], '*', MUST_EXIST);
            if ($manualenrol) {
                $plugin = enrol_get_plugin('manual');
                if ($plugin) {
                    // Find the trainer role.
                    if ($trainerrole = $DB->get_record('role', ['shortname' => 'trainer'])) {
                        $plugin->enrol_user($manualenrol, $trainer->id, $trainerrole->id);
                    } else {
                        // Fallback to editingteacher role.
                        if ($editteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'])) {
                            $plugin->enrol_user($manualenrol, $trainer->id, $editteacherrole->id);
                        }
                    }
                }
            }
        }
    }
}
