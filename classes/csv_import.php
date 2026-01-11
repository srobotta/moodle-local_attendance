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
 * CSV import handler for courses and modules. Receives the csv file
 * reads it line by line and delegates the processing to the import_handler.
 *
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_import {

    public const SEPARATOR_COMMA = ',';
    public const SEPARATOR_SEMICOLON = ';';
    public const SEPARATOR_TAB = "\t";

    public const CMD_COURSE_COLUMNS = 'COURSE_COLUMNS';
    public const CMD_MODULE_COLUMNS = 'MODULE_COLUMNS';
    public const CMD_BADGE_COLUMNS = 'BADGE_COLUMNS';
    public const CMD_COURSE = 'COURSE';
    public const CMD_MODULE = 'MODULE';
    public const CMD_BADGE = 'BADGE';
    public const CMD_USE_COURSE = 'USE_COURSE';
    public const CMD_SKIP_LINE = 'SKIP_LINE';

    private import_handler $handler;
    private string $separator;
    private array $columns;

    private array $log;

    /**
     * Constructor.
     * @param import_handler|null $handler
     * @param string $separator
     */
    public function __construct(
        ?import_handler $handler = null,
        $separator = self::SEPARATOR_COMMA
    ) {
        if ($handler !== null) {
            $this->handler = $handler;
        }
        $this->separator = $separator;
        $this->columns = [];
        $this->log = [];
    }

    /**
     * Import the given CSV file.
     * @param string $filepath
     * @throws \Exception
     */
    public function importFile(string $filepath): void {
        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new \Exception('Could not open file: ' . $filepath);
        }

        $currentline = 0;
        $currentCmd = null;
        $currentCourse = null;

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
            $fields = str_getcsv($line, $this->separator);

            if (!$this->isValidCommand($fields[0])) {
                $this->log(get_string(
                    'csv_import_invalidcommand',
                    'local_attendance',
                    ['cmd' => $fields[0], 'line' => $currentline]
                ), 1);
                continue;
            }
            if ($currentCmd === self::CMD_SKIP_LINE &&
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
            $currentCmd = \array_shift($fields);

            if (str_contains($currentCmd, '_COLUMNS')) {
                $this->mapColumns($currentCmd, $fields);
                continue;
            }
            if ($currentCmd === self::CMD_COURSE || $currentCmd === self::CMD_USE_COURSE) {
                if (empty($this->columns[self::CMD_COURSE_COLUMNS])) {
                    $this->log(get_string(
                        'csv_import_coursecolmissing',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                $dataMapped = $this->mapFields(self::CMD_COURSE_COLUMNS, $fields);
                try {
                    $currentCourse = $currentCmd === self::CMD_COURSE
                        ? $this->handler->createCourse($dataMapped)
                        : $this->handler->useCourse($dataMapped);
                    $this->log(get_string(
                        'csv_import_ok_course',
                        'local_attendance',
                        [
                            'line' => $currentline,
                            'cmd' => $currentCmd,
                            'id' => $currentCourse->id,
                            'name' => $currentCourse->fullname,
                            'url' => new \moodle_url('/course/view.php', ['id' => $currentCourse->id]),
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
            if ($currentCmd === self::CMD_MODULE) {
                if (!\array_key_exists(self::CMD_MODULE_COLUMNS, $this->columns)) {
                    $this->log(get_string(
                        'csv_import_modulecolmissing',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                if (empty($currentCourse)) {
                    $this->log(get_string(
                        'csv_import_needcoursefirst',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                $dataMapped = $this->mapFields(self::CMD_MODULE_COLUMNS, $fields);
                try {
                    $module = $this->handler->createModule($dataMapped);
                    $this->log(get_string(
                            'csv_import_ok_module',
                            'local_attendance',
                            [
                                'line' => $currentline,
                                'modulename' => $module->getEntityName(),
                                'id' => $module->getId(),
                                'name' => $module->getName(),
                                'url' => $module->getUrl(),
                                'info' => $module->getAdditionalData(),
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
            if ($currentCmd === self::CMD_BADGE) {
                if (!\array_key_exists(self::CMD_BADGE_COLUMNS, $this->columns)) {
                    $this->log(get_string(
                        'csv_import_badgecolmissing',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                if (empty($currentCourse)) {
                    $this->log(get_string(
                        'csv_import_needcoursefirst',
                        'local_attendance',
                        ['line' => $currentline]
                    ), 1);
                    continue;
                }
                $dataMapped = $this->mapFields(self::CMD_BADGE_COLUMNS, $fields);
                try {
                    $badge = $this->handler->createBadge($dataMapped);
                    $this->log(get_string(
                            'csv_import_ok_badge',
                            'local_attendance',
                            [
                                'line' => $currentline,
                                'id' => $badge->getId(),
                                'name' => $badge->getName(),
                                'url' => $badge->getUrl(),
                                'info' => $badge->getAdditionalData(),
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
    protected function mapColumns(string $command, array $fields): void {
        $this->columns[$command] = array_map('trim', $fields);
    }

    /**
     * Map CSV fields to column names.
     * @param string $type The type of mapping (course, module, badge).
     * @param array $fields The CSV fields.
     * @return array Mapped associative array.
     */
    protected function mapFields(string $type, array $fields): array {
        $mapped = [];
        if (!\array_key_exists($type, $this->columns)) {
            throw new \moodle_exception('csv_import_invalidcommand', 'local_attendance', '', $type);
        }
        foreach ($this->columns[$type] as $index => $columnName) {
            $mapped[$columnName] = $fields[$index] ?? null;
        }
        return $mapped;
    }

    /**
     * Check if the given command is valid.
     * @param string|array $cdata Command data.
     * @return bool True if valid, false otherwise.
     */
    public function isValidCommand(string|array $cdata): bool {
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
    public function hasError(): bool {
        return !empty(array_filter($this->log, fn($entry) => $entry['level'] > 0));
    }

    /**
     * Get the log messages.
     * @return array The log messages.
     */
    public function getLog(bool $errorsOnly = false): array {
        if ($errorsOnly) {
            return \array_map(fn($entry) => $entry['message'], array_filter($this->log, fn($entry) => $entry['level'] > 0));
        }
        return \array_map(fn($entry) => $entry['message'], $this->log);
    }
}