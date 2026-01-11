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

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for uploading CSV file for attendance course creation
 *
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'csvfile', get_string('uploadcsvfile', 'local_attendance'),
            null, ['accepted_types' => ['.csv']]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement('select', 'separator', get_string('csvseparator', 'local_attendance'), [
            csv_import::SEPARATOR_COMMA => get_string('csvseparatorcomma', 'local_attendance'),
            csv_import::SEPARATOR_SEMICOLON => get_string('csvseparatorsemicolon', 'local_attendance'),
            csv_import::SEPARATOR_TAB => get_string('csvseparatortab', 'local_attendance'),
        ]);
        $mform->setDefault('separator', csv_import::SEPARATOR_COMMA);
        $mform->addElement('text', 'coursesuffix', get_string('coursesuffix', 'local_attendance'));
        $mform->setType('coursesuffix', PARAM_TEXT);
        $mform->setDefault('coursesuffix', get_string('coursesuffixvalue', 'local_attendance'));
        $mform->addHelpButton('coursesuffix', 'coursesuffix', 'local_attendance');

        $this->add_action_buttons(true, get_string('import', 'local_attendance'));
    }
}