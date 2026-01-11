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

/**
 * Interface for module creation classes. This interface must be implemented when
 * creating own logic for creating modules during the import process.
 * A specific implementation for the use case of this plugin is provided in
 * \local_attendance\mod\quiz.
 *
 * @package    local_attendance
 * @copyright  2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface modcreate_interface {
    /**
     * Use an existing course where the activity will be created.
     * Also, you may change course settings here.
     * @param \stdClass $course
     * @return modcreate_interface
     */
    public function useCourse(\stdClass $course): modcreate_interface;

    /**
     * Create a new activity in the course.
     * The parameter contains originally contains the associative array from
     * the import file which might have been modiefied by the caller.
     * @param array $data
     * @return modcreate_interface
     */
    public function create(array $data): modcreate_interface;

    /**
     * Set the current CSV row data as it comes from the import file.
     * @param array $row
     * @return modcreate_interface
     */
    public function setRow(array $row): modcreate_interface;

    /**
     * Get the URL to the created module.
     * @return string
     */
    public function getUrl(): string;

    /**
     * Get the display name of the created module.
     * @return string
     */
    public function getName(): string;

    /**
     * Get the course module ID of the created module.
     * @return int
     */
    public function getCmId(): int;

    /**
     * Get the instance ID of the created module.
     * @return int
     */
    public function getId(): int;

    /**
     * Get the technical module name of the created module or other entity.
     * @return string
     */
    public function getEntityName(): string;

    /**
     * Get additional info about the created module for the log.
     * The returned string should be something like a JSON object or key=value pairs.
     * @return string
     */
    public function getAdditionalData(): string;
}