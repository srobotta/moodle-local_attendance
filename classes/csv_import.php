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

use local_attendance\form\import_interface as form;

/**
 * CSV import handler for courses and modules. Receives the csv file
 * reads it line by line and delegates the processing to the import_handler.
 *
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_import {

    public const DELIMITER_COMMA = ',';
    public const DELIMITER_SEMICOLON = ';';
    public const DELIMITER_TAB = "\t";

    public const CMD_COURSE_COLUMNS = 'COURSE_COLUMNS';
    public const CMD_MODULE_COLUMNS = 'MODULE_COLUMNS';
    public const CMD_BADGE_COLUMNS = 'BADGE_COLUMNS';
    public const CMD_COURSE = 'COURSE';
    public const CMD_MODULE = 'MODULE';
    public const CMD_BADGE = 'BADGE';
    public const CMD_USE_COURSE = 'USE_COURSE';
    public const CMD_SKIP_LINE = 'SKIP_LINE';

    private import_handler $handler;
    private form $form;
    private array $columns;

    private array $log;

    /**
     * Constructor.
     * @param import_handler $handler
     * @param form $form
     */
    public function __construct(
        import_handler $handler,
        form $form,
    ) {
        $this->handler = $handler;
        $this->form = $form;
        $this->columns = [];
        $this->log = [];
    }

    /**
     * Import the given CSV file.
     * @throws \Exception
     */
    public function import_csv_file(): void {
        $filepath = $this->form->get_csv_file();
        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new \Exception('Could not open file: ' . $filepath);
        }

        $currentline = 0;
        $currentcmd = null;
        $currentcourse = null;

        if (!$this->handler) {
            $this->handler = new import_handler();
        }

        while (($line = fgets($handle, 4096)) !== false) {
            $currentline++;
            $line = trim($line);
            if ($line === '') {
                continue; // Skip empty lines.
            }
            if (str_starts_with($line, '#')) {
                continue; // Skip comment lines.
            }
            $fields = str_getcsv($line, $this->form->get_csv_delimiter(), '"', '\\');

            if (!$this->is_valid_command($fields[0])) {
                $this->log(get_string(
                    'csv_import_invalidcommand',
                    'local_attendance',
                    ['cmd' => $fields[0], 'line' => $currentline]
                ), 1);
                continue;
            }
            if ($currentcmd === self::CMD_SKIP_LINE &&
                !\in_array($fields[0], [self::CMD_COURSE, self::CMD_USE_COURSE, self::CMD_COURSE_COLUMNS])
            ) {
                // Skip processing subsequent module lines until next course/column definition.
                $this->log(get_string(
                    'csv_import_skipline',
                    'local_attendance',
                    ['line' => $currentline]
                ), 1);
                continue;
            }
            $currentcmd = \array_shift($fields);

            if (str_contains($currentcmd, '_COLUMNS')) {
                $this->map_columns($currentcmd, $fields);
                continue;
            }
            if ($currentcmd === self::CMD_COURSE || $currentcmd === self::CMD_USE_COURSE) {
                if (empty($this->columns[self::CMD_COURSE_COLUMNS])) {
                    $this->log(get_string(
                        'csv_import_coursecolmissing',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                $datamapped = $this->map_fields(self::CMD_COURSE_COLUMNS, $fields);
                try {
                    $currentcourse = $currentcmd === self::CMD_COURSE
                        ? $this->handler->create_course($datamapped)
                        : $this->handler->use_course($datamapped);
                    $this->log(get_string(
                        'csv_import_ok_course',
                        'local_attendance',
                        [
                            'line' => $currentline,
                            'cmd' => $currentcmd,
                            'id' => $currentcourse->id,
                            'name' => $currentcourse->fullname,
                            'url' => new \moodle_url('/course/view.php', ['id' => $currentcourse->id]),
                            'info' => '',
                        ]
                    ));
                } catch (\Exception $e) {
                    $this->log(get_string(
                        'csv_importexception',
                        'local_attendance',
                        ['line' => $currentline, 'message' => $e->getMessage()]
                    ), 1);
                    // When a course error occurs, stop processing modules for this course.
                    $currentCmd = self::CMD_SKIP_LINE;
                }
                continue;
            }
            if ($currentcmd === self::CMD_MODULE) {
                if (!\array_key_exists(self::CMD_MODULE_COLUMNS, $this->columns)) {
                    $this->log(get_string(
                        'csv_import_modulecolmissing',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                if (empty($currentcourse)) {
                    $this->log(get_string(
                        'csv_import_needcoursefirst',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                $datamapped = $this->map_fields(self::CMD_MODULE_COLUMNS, $fields);
                try {
                    $module = $this->handler->create_module($datamapped);
                    $this->log(get_string(
                            'csv_import_ok_module',
                            'local_attendance',
                            [
                                'line' => $currentline,
                                'modulename' => $module->get_entity_name(),
                                'id' => $module->get_id(),
                                'name' => $module->get_name(),
                                'url' => $module->get_url(),
                                'info' => $module->get_additional_data(),
                            ]
                    ));
                } catch (\Exception $e) {
                    $this->log(get_string(
                        'csv_importexception',
                        'local_attendance',
                        ['line' => $currentline, 'message' => $e->getMessage()]
                    ), 1);
                }
            }
            if ($currentcmd === self::CMD_BADGE) {
                if (!\array_key_exists(self::CMD_BADGE_COLUMNS, $this->columns)) {
                    $this->log(get_string(
                        'csv_import_badgecolmissing',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                if (empty($currentcourse)) {
                    $this->log(get_string(
                        'csv_import_needcoursefirst',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                $datamapped = $this->map_fields(self::CMD_BADGE_COLUMNS, $fields);
                try {
                    $badge = $this->handler->create_badge($datamapped);
                    $this->log(get_string(
                            'csv_import_ok_badge',
                            'local_attendance',
                            [
                                'line' => $currentline,
                                'id' => $badge->get_id(),
                                'name' => $badge->get_name(),
                                'url' => $badge->get_url(),
                                'info' => $badge->get_additional_data(),
                            ]
                    ));
                } catch (\Exception $e) {
                    $this->log(get_string(
                        'csv_importexception',
                        'local_attendance',
                        ['line' => $currentline, 'message' => $e->getMessage()]
                    ), 1);
                }
            }
        }
        fclose($handle);
    }

    /**
     * Map CSV columns for a specific command.
     * @param string $command
     * @param array $fields
     */
    protected function map_columns(string $command, array $fields): void {
        $this->columns[$command] = array_map('trim', $fields);
    }

    /**
     * Map CSV fields to column names.
     * @param string $type The type of mapping (course, module, badge).
     * @param array $fields The CSV fields.
     * @return array Mapped associative array.
     */
    protected function map_fields(string $type, array $fields): array {
        $mapped = [];
        if (!\array_key_exists($type, $this->columns)) {
            throw new \moodle_exception('csv_import_invalidcommand', 'local_attendance', '', $type);
        }
        foreach ($this->columns[$type] as $index => $name) {
            $mapped[$name] = $fields[$index] ?? null;
        }
        return $mapped;
    }

    /**
     * Check if the given command is valid.
     * @param string|array $cdata Command data.
     * @return bool True if valid, false otherwise.
     */
    public function is_valid_command(string|array $cdata): bool {
        $cmd = is_array($cdata) ? $cdata[0] : $cdata;
        return in_array($cmd, [
            self::CMD_COURSE_COLUMNS,
            self::CMD_MODULE_COLUMNS,
            self::CMD_COURSE,
            self::CMD_MODULE,
            self::CMD_USE_COURSE,
            self::CMD_BADGE_COLUMNS,
            self::CMD_BADGE,
        ]);
    }

    /**
     * Log a message with an optional level. 
     * @param string $message The message to log.
     * @param int|null $level The log level (optional).
     */
    protected function log(string $message, ?int $level = 0): void {
        $this->log[] = [
            'level' => $level,
            'message' => $message
        ];
    }

    /**
     * Check if there are any error log entries.
     * @return bool True if there are errors, false otherwise.
     */
    public function has_error(): bool {
        return !empty(array_filter($this->log, fn($entry) => $entry['level'] > 0));
    }

    /**
     * Get the log messages.
     * @param bool $errorsOnly If true, only return error messages. Otherwise, return all messages.
     * @return array The log messages.
     */
    public function get_log(bool $errorsOnly = false): array {
        if ($errorsOnly) {
            return \array_map(fn($entry) => $entry['message'], array_filter($this->log, fn($entry) => $entry['level'] > 0));
        }
        return \array_map(fn($entry) => $entry['message'], $this->log);
    }
}