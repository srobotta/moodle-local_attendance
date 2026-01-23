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
    /**
     * Path to uploaded CSV file.
     * @var string|null
     */
    private $csvFile;

    /**
     * Array of uploaded content files.
     * @var array|null
     */
    private $contentFiles;

    /**
     * Define the form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'csvfile', get_string('form_label_uploadcsvfile', 'local_attendance'),
            null, ['accepted_types' => ['.csv']]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement('select', 'delimiter', get_string('form_label_csvdelimiter', 'local_attendance'), [
            csv_import::DELIMITER_COMMA => get_string('form_opt_csvdelimitercomma', 'local_attendance'),
            csv_import::DELIMITER_SEMICOLON => get_string('form_opt_csvdelimitersemicolon', 'local_attendance'),
            csv_import::DELIMITER_TAB => get_string('form_opt_csvdelimitertab', 'local_attendance'),
        ]);
        $mform->setDefault('delimiter', csv_import::DELIMITER_COMMA);
        $mform->addElement('text', 'coursesuffix', get_string('form_label_coursesuffix', 'local_attendance'));
        $mform->setType('coursesuffix', PARAM_TEXT);
        $mform->setDefault('coursesuffix', get_string('form_value_coursesuffix', 'local_attendance'));
        $mform->addHelpButton('coursesuffix', 'form_label_coursesuffix', 'local_attendance');
        $mform->addElement(
            'filemanager',
            'contentfiles',
            get_string('form_label_contentfiles', 'local_attendance'),
            null,
            ['subdirs' => 0, 'maxfiles' => 20, 'accepted_types' => '*']
        );
        $mform->addHelpButton('contentfiles', 'form_label_contentfiles', 'local_attendance');
        $this->add_action_buttons(true, get_string('form_btn_import', 'local_attendance'));
    }

    /**
     * Get the CSV delimiter selected in the form.
     * @return string CSV delimiter.
     */
    public function getCsvDelimiter(): string {
        $data = $this->get_data();
        return $data->delimiter ?? csv_import::DELIMITER_COMMA;
    }

    /**
     * Get the course suffix entered in the form.
     * @return string Course suffix.
     */
    public function getCourseSuffix(): string {
        $data = $this->get_data();
        return $data->coursesuffix ?? get_string('form_value_coursesuffix', 'local_attendance');
    }

    /**
     * Get the content files uploaded via the form as an array with
     * key = filename and value = path to temporary file.
     *
     * @return array Array of stored_file objects.
     */
    public function getContentFiles(): array {
        if ($this->contentFiles !== null) {
            return $this->contentFiles;
        }
        $this->contentFiles = $this->getUploadedFiles('contentfiles');
        return $this->contentFiles;
    }

    /**
     * Get the path to the uploaded CSV file.
     * @return string Path to temporary CSV file.
     */
    public function getCsvFile(): string {
        if ($this->csvFile !== null) {
            return $this->csvFile;
        }
        $files = $this->getUploadedFiles('csvfile');
        $this->csvFile = reset($files);
        return $this->csvFile;
    }

    /**
     * Get uploaded files from a filepicker or filemanager form element.
     * Store the file in a temporary location and return the paths and filenames.
     * @param string $fieldname
     * @return array Array with key = filename and value = path to temporary file.
     * @throws \moodle_exception
     */
    protected function getUploadedFiles(string $fieldname): array {
        global $USER;
        $data = $this->get_data();
        if (!isset($data->{$fieldname})) {
            return [];
        }
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $storedfiles = [];
        $files = $fs->get_area_files($context->id, 'user', 'draft', $data->{$fieldname});
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $tempfile = $file->copy_content_to_temp();
            if ($tempfile === false) {
                throw new \moodle_exception('ex_fileuploadfailed', 'local_attendance');
            }
            $storedfiles[$file->get_filename()] = $tempfile;
        }
        return $storedfiles;
    }

    /**
     * Cleanup temporary files created during upload.
     * @return void
     */
    public function cleanupFiles(): void {
        if (!is_null($this->csvFile)) {
            @unlink($this->csvFile);
            $this->csvFile = null;
        }
        if (!is_null($this->contentFiles)) {
            foreach ($this->contentFiles as $file) {
                @unlink($file);
            }
            $this->contentFiles = null;
        }
    }
}