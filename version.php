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
 * Tool to create an attendance course with test activities and a badge for completion.
 *
 * @package   local_attendance
 * @copyright 2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2025121800; // The current module version (Date: YYYYMMDDXX)
$plugin->requires  = 2025041400; // Requires this Moodle version.
$plugin->component = 'local_attendance';
$plugin->release = 'v5.0-r1';
$plugin->supported = [500, 501];
$plugin->maturity = MATURITY_STABLE;
