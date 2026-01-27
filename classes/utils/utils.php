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

    /**
     * Convert a value to COMPLETION_AGGREGATION_ANY or COMPLETION_AGGREGATION_ALL.
     * Empty values are converted to COMPLETION_AGGREGATION_ALL.
     * @param string $key The name of the field (for error messages).
     * @param array $data The data array containing the value.
     * @return int
     */
    public static function anyOrAll(string $key, array $data): int {
        if (!\array_key_exists($key, $data) || empty(trim((string)$data[$key]))) {
            return COMPLETION_AGGREGATION_ALL;
        }
        $value = strtolower(trim((string)$data[$key]));
        $validValues = ['all', 'any', (string)COMPLETION_AGGREGATION_ALL, (string)COMPLETION_AGGREGATION_ANY];
        if (!in_array($value, $validValues)) {
            $a = [
                'value' => $data[$key],
                'column' => $key
            ];
            throw new \moodle_exception('ex_invalidvalue', 'local_attendance', '', $a);
        }
        if ($value === 'any' || $value == (string)COMPLETION_AGGREGATION_ANY) {
            return COMPLETION_AGGREGATION_ANY;
        }
        return COMPLETION_AGGREGATION_ALL;
    }

    /**
     * Parse a date/time value from the data array.
     * @param string $key The name of the field (for error messages).
     * @param array $data The data array containing the value.
     * @return int The timestamp.
     * @throws \moodle_exception If the value cannot be parsed.
     */
    public static function parseDateTime(string $key, array $data): int {
        $strVal = trim($data[$key]);
        $intVal = (int)$data[$key];
        if ((string)$intVal === $strVal) {
            // Value is an integer timestamp.
            return $intVal;
        }
        $timestamp = strtotime($strVal);
        if (!empty($timestamp)) {
            // We got a valid timestamp from the string.
            return $timestamp;
        }
        throw new \moodle_exception(
            'ex_invalidvalue',
            'local_attendance',
            '',
            ['value' => $data[$key], 'column' => $key]
        );
    }
}