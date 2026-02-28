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
 * Behat data generator for local_attendance.
 *
 * @package     local_attendance
 * @category    test
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Behat data generator for local_attendance.
 *
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_attendance_generator extends behat_generator_base {

    /**
     * Get the list of creatable entities for attendance.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'attendance course setup' => [
                'singular' => 'attendance course setup',
                'datagenerator' => 'attendance_course_setup',
                'required' => ['sourcecourse'],
                'switchids' => [
                    'sourcecourse' => 'sourcecourse',
                ],
            ],
        ];
    }

    /**
     * Get the course id from its shortname or fullname.
     *
     * @param string $courseref
     * @return int
     */
    protected function get_sourcecourse_id($courseref) {
        global $DB;

        if (!$id = $DB->get_field('course', 'id', ['shortname' => $courseref])) {
            if (!$id = $DB->get_field('course', 'id', ['fullname' => $courseref])) {
                throw new Exception('The specified course with shortname or fullname "' . $courseref . '" does not exist');
            }
        }
        return $id;
    }
}
