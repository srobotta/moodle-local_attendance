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
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Determine the text format (plain or html) based on the content of the input string.
     * @param string $input
     * @return array ['text' => string, 'format' => int]
     */
    public static function get_text_and_format(string $input): array {
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
    public static function merge_data(moodleform $mform, array $data): \stdClass {
        $genericform = new generic_form($mform);
        $formdata = $genericform->get_fields();
        foreach ($data as $key => $value) {
            if (property_exists($formdata, $key)) {
                if (\is_array($formdata->{$key}) && \array_key_exists('text', $formdata->{$key})) {
                    $formdata->{$key} = self::get_text_and_format($value);
                    continue;
                }
                $formdata->{$key} = $value;
            }
        }
        return $formdata;
    }

    /**
     * Convert a value to COMPLETION_AGGREGATION_ANY or COMPLETION_AGGREGATION_ALL.
     * Empty values are converted to COMPLETION_AGGREGATION_ALL.
     * @param string $key The name of the field (for error messages).
     * @param array $data The data array containing the value.
     * @return int
     */
    public static function any_or_all(string $key, array $data): int {
        if (!\array_key_exists($key, $data) || empty(trim((string)$data[$key]))) {
            return COMPLETION_AGGREGATION_ALL;
        }
        $value = strtolower(trim((string)$data[$key]));
        $validvalues = ['all', 'any', (string)COMPLETION_AGGREGATION_ALL, (string)COMPLETION_AGGREGATION_ANY];
        if (!in_array($value, $validvalues)) {
            $a = [
                'value' => $data[$key],
                'column' => $key,
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
    public static function parse_datetime(string $key, array $data): int {
        $strval = trim($data[$key]);
        $intval = (int)$data[$key];
        if ((string)$intval === $strval) {
            // Value is an integer timestamp.
            return $intval;
        }
        $timestamp = strtotime($strval);
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

    /**
     * Check if an option is set in the given data and also is enabled, when the value is one of
     * '1', 'true', 'yes', 'y', 'x' (case-insensitive)
     * @param string $key The name of the field
     * @param array $data The data array representing the CSV row
     * @return bool True if option is enabled, false otherwise.
     */
    public static function is_set_and_enabled(string $key, array $data): bool {
        if (!\array_key_exists($key, $data)) {
            return false;
        }
        $yes = strtolower(get_string('yes'));
        return \in_array(strtolower($data[$key]), ['1', 'true', 'y', 'x', $yes], true);
    }

    /**
     * Get information about course section by it's url.
     * The returned information is wrapped in an object and contains the
     * properties:
     *  - course: with the course id
     *  - section: with the section number
     *  - position: with the position within that section.
     *
     * @param string|int $url
     * @return ?object
     */
    public static function get_section_info(string|int $url): ?object {
        global $DB;

        // 1. Get the course module ID (cmid) from the URL parameter or directly when numeric.
        if (is_int($url)) {
            $cmid = $url;
        } else if (is_numeric($url)) {
            $cmid = (int)$url;
        } else {
            $query = parse_url($url, PHP_URL_QUERY);
            $cmid = 0;
            foreach (explode('&', $query) as $tuple) {
                $kv = explode('=', $tuple);
                if ($kv[0] === 'id' && array_key_exists(1, $kv)) {
                    $cmid = (int)$kv[1];
                }
            }
        }
        // A valid cmid must be greater than 0, e.g. a positive id in the database.
        if (!($cmid > 0)) {
            return null;
        }

        // 2. Fetch the course module record to get the Course ID
        try {
            $cmrecord = $DB->get_record('course_modules', ['id' => $cmid], 'course', MUST_EXIST);
            $courseid = $cmrecord->course;
        } catch (\Exception $e) {
            return null;
        }

        // 3. Load the course modinfo
        $modinfo = get_fast_modinfo($courseid);

        // 4. Retrieve the activity's module info object
        $cm = $modinfo->get_cm($cmid);

        // 5. Get the section details
        $sectionnum = $cm->sectionnum; // The section number (e.g., Topic 1, Week 2, etc.)

        // 6. Find the exact position within that section
        $sectioninfo = $modinfo->get_section_info($sectionnum);
        $sequence = explode(',', $sectioninfo->sequence);
        $position = array_search($cmid, $sequence); // 0-indexed position inside the section

        return (object)[
            'course' => $courseid,
            'section' => $sectionnum,
            'position' => $position + 1,
        ];
    }
}
