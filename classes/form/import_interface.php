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

/**
 * Interface for uploade/provided data to do the import.
 *
 * @package    local_attendance
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface import_interface {
    /**
     * Get the CSV delimiter selected in the form.
     * @return string CSV delimiter.
     */
    public function getCsvDelimiter(): string;

    /**
     * Get the course suffix entered in the form.
     * @return string Course suffix.
     */
    public function getCourseSuffix(): string;

    /**
     * Get the content files uploaded via the form as an array with
     * key = filename and value = path to temporary file.
     *
     * @return array Array of stored_file objects.
     */
    public function getContentFiles(): array;

    /**
     * Get the path to the CSV file to be imported.
     * @return string Path to CSV file.
     */
    public function getCsvFile(): string;

    /**
     * Cleanup any temporary files if necessary.
     */
    public function cleanupFiles(): void;
}
