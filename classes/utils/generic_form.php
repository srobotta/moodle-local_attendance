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

/**
 * A generic form handler to extract field names and predefined values from a Moodle form
 * when this is just initialized but not submitted.
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generic_form {
    /**
     * The original moodleform instance.
     * @var \moodleform
     */
    protected $moodleform;

    /**
     * Constructor.
     * @param \moodleform $moodleform
     */
    public function __construct(\moodleform $moodleform) {
        $this->moodleform = $moodleform;
    }

    /**
     * Get all fields from the form with their predefined values.
     * @return \stdClass
     */
    public function get_fields(): ?\stdClass {
        $fields = new \stdClass();
        $reflection = new \ReflectionClass($this->moodleform);
        $htmlForm = $reflection->getProperty('_form');
        $form = $htmlForm->getValue($this->moodleform);
        foreach ($form->_elements as $element) {
                $res = $this->getNameAndValueFromElement($element);
                if ($res === null) {
                    continue;
                }
                if ($res instanceof \stdClass) {
                    // For grouped elements, merge all fields.
                    foreach (\array_keys(get_object_vars($res)) as $subname) {
                        $fields->{$subname} = $res->{$subname};
                    }
                    continue;
                }
                [$name, $value] = $res;
                $fields->{$name} = $value;
        }
        return $fields;
    }

    /**
     * Get the value from a Moodle form element.
     * The returned value is an array of name and value, or a stdClass for grouped elements or null if this
     * element is to be skipped.
     * @param mixed $element
     * @return array|\stdClass|null
     */
    protected function getNameAndValueFromElement($element): array|\stdClass|null {
        // Headlines and non-named elements (aka html) have no value.
        $name = $element->getName();
        if (!$name || $element instanceof \MoodleQuickForm_header || $element instanceof \MoodleQuickForm_submit) {
            return null;
        }
        if ($element instanceof \MoodleQuickForm_editor) {
            $value = $element->getValue();
            // The intro field is named introeditor in the form, but we want intro as field name.
            if ($name === 'introeditor') {
                $name = 'intro';
            }
            if (\array_key_exists('text', $value) && $value['text'] === null) {
                return null;
            }
            return (object)[
                $name => $value['text'],
                $name . 'format' => $value['format'],
            ];
        }
        if ($element instanceof \MoodleQuickForm_date_selector) {
            $values = $element->getValue();
            $value = strtotime(
                    reset($values['year']) . '-' . reset($values['month']) . '-' . reset($values['day']) . ' 00:00:00'
                ) ?: null;
            if ($value === null) {
                return null;
            }
            return [$name, $value];
        }
        if ($element instanceof \MoodleQuickForm_date_time_selector) {
            $values = $element->getValue();
            $value = strtotime(
                reset($values['year']) . '-' . reset($values['month']) . '-' . reset($values['day']) . ' ' .
                reset($values['hour']) . ':' . reset($values['minute']) . ':00'
            ) ?: null;
            if ($value === null) {
                return null;
            }
            return [$name, $value];
        }
        if ($element instanceof \MoodleQuickForm_duration) {
            $values = $element->getValue();
            $seconds = $values['number'];
            foreach (\array_keys($values['timeunit']) as $multiplier) {
                $seconds *= $multiplier;
            }
            return [$name, $seconds];
        }
        if ($element instanceof \MoodleQuickForm_group) {
            $elements = $element->getElements();
            $fields = new \stdClass();
            foreach ($elements as $el) {
                [$innerName, $innerValue] = $this->getNameAndValueFromElement($el);
                if ($innerValue === null) {
                    continue;
                }
                $fields->{$innerName} = $innerValue;
            }
            return $fields;
        }
        if ($element instanceof \MoodleQuickForm_filemanager ||
            $element instanceof \MoodleQuickForm_filepicker) {
            // Skip file elements.
            return null;
        }
        if ($element instanceof \MoodleQuickForm_hidden) {
            // No sesskey and CSRF elements.
            if ($element->getName() === 'sesskey' || str_starts_with($element->getName(), '_qf__')) {
                return null;
            }
        }
        if ($element instanceof \MoodleQuickForm_text && empty($element->getValue())) {
            return [$name, ''];
        }
        $value = $element->getValue();
        return [$name, is_array($value) ? reset($value) : $value];
    }
}