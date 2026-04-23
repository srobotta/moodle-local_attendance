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

namespace local_attendance\form;

use DB;
use local_attendance\csv_import;

/**
 * Pseudo form class for CLI import. This class implements the import_interface,
 * so it can be used in the csv_import class.
 *
 * @package     local_attendance
 * @copyright   2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cli implements import_interface {
    /**
     * CSV file from cli argument.
     * @var string|null
     */
    private $csvFile;

    /**
     * Array of content files from cli argument.
     * @var array|null
     */
    private $contentFiles;

    /**
     * Course suffix from cli argument.
     * @var string|null
     */
    private $courseSuffix;

    /**
     * CSV delimiter from cli argument.
     * @var string|null
     */
    private $delimiter;


    /**
     * Constructor for CLI form. Initializes the form data based on the provided arguments.
     *
     * @param string $csvFile Path to the CSV file to import.
     * @param array|null $contentFiles Array of content files to be used in the import, with key = filename and value = path to temporary file.
     * @param string|null $courseSuffix Optional course suffix to append to created courses.
     * @param string|null $delimiter Optional CSV delimiter to use for parsing the CSV file.
     */
    public function __construct(string $csvFile, ?array $contentFiles, ?string $courseSuffix, ?string $delimiter) {
        $this->csvFile = $csvFile;
        $this->contentFiles = $contentFiles;
        $this->courseSuffix = $courseSuffix;
        $this->delimiter = $delimiter;
    }

    /**
     * Get the CSV delimiter selected in the form.
     * @return string CSV delimiter.
     */
    public function getCsvDelimiter(): string {
        return $this->delimiter ?? csv_import::DELIMITER_COMMA;
    }

    /**
     * Get the course suffix entered in the form.
     * @return string Course suffix.
     */
    public function getCourseSuffix(): string {
        return $this->courseSuffix ?? get_string('form_value_coursesuffix', 'local_attendance');
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
        return [];
    }

    /**
     * Get the path to the uploaded CSV file.
     * @return string Path to temporary CSV file.
     */
    public function getCsvFile(): string {
        return $this->csvFile;
    }

    /**
     * Cleanup temporary files created during upload.
     * @return void
     */
    public function cleanupFiles(): void {
        // No temporary files to clean up in CLI context.
    }
}
