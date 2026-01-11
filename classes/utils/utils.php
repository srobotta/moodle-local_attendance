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

namespace local_attendance\utils;

use moodleform;

/**
 * Utility functions for the attendance plugin.
 *
 * @package     local_attendance\utils
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Determine the text format (plain or html) based on the content of the input string.
     * @param string $input
     * @return array ['text' => string, 'format' => int]
     */
    public static function getTextAndFormat(string $input): array {
        // Default format is plain.
        $format = FORMAT_PLAIN;
        if (!empty(trim($input))) {
            $stripped = strip_tags($input);
            if ($stripped !== $input) {
                // Contains HTML tags.
                $format = FORMAT_HTML;
            }
        }
        return ['text' => $input, 'format' => $format];

    }

    /**
     * Merge the data from the form with the data from the import (array). Reference
     * for merging is the form data. It is overwritten with the import data where keys match.
     * @param moodleform $mform
     * @param array $data
     * @return \stdClass
     */
    public static function mergeData(moodleform $mform, array $data): \stdClass {
        $genericForm = new generic_form($mform);
        $formData = $genericForm->get_fields();
        foreach ($data as $key => $value) {
            if (property_exists($formData, $key)) {
                $formData->{$key} = $value;    
            }
        }
        return $formData;
    }
}