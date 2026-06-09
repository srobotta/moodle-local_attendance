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
 * This script allows you to view and change the emailstop flag of any user.
 *
 * @package    local_attendance
 * @subpackage cli
 * @copyright  2026 Stephan Robotta (stephan.robotta@bfh.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_attendance\form\cli as form;
use local_attendance\csv_import;
use local_attendance\import_handler;
use local_attendance\utils\path;

// Store here the CSV file to import.
$csvFile = null;

// Now get cli option.
[$options, $files] = cli_get_params(
    [
        'help' => false,
        'del' => null,
        'suffix' => null,
    ],
    [
        'd' => 'del',
        'h' => 'help',
        's' => 'suffix',
    ]
);

if (!empty($files)) {
    $csvFile = \array_shift($files);
}

if ($options['help']) {
    $help =
    "Usage: php importcsv.php [options] csvfile [contentfile1 contentfile2 ...]

Options:
-d, --del               CSV delimiter.
-h, --help              Display this help message.
-s, --suffix            Course suffix.

Example:
\$sudo -u www-data /usr/bin/php public/local/attendance/cli/importcsv.php -d=\; -s='-2026' /path/to/courses.csv /path/to/contentfile1.pdf
";

    echo $help;
    die;
}

if (empty($csvFile)) {
    cli_error('CSV file is required. Use --help for usage information.');
}

// Map filenames of content files and csv file.
$csvfile = path::resolve_path($csvFile);
$contentfiles = [];
foreach ($files as $file) {
    $fname = basename($file);
    $contentfiles[$fname] = path::resolve_path($file);
}

// Check if CSV file exists.
if (!file_exists($csvfile)) {
    cli_error('CSV file not found: ' . $csvfile);
}

// Set user to site admin for permission checks and to avoid issues with file access.
$admin = get_admin();
\core\session\manager::set_user($admin);

$mform = new form($csvfile, $contentfiles, $options['suffix'] ?? null, $options['del'] ?? null);
$importHandler = new import_handler((object)[
    'suffix' => $mform->get_course_suffix(),
    'files' => $mform->get_content_files(),
]);
$csvImport = new csv_import($importHandler, $mform);
$csvImport->import_csv_file();
$mform->cleanup_files();
if (!$csvImport->has_error()) {
    echo get_string('importsuccess', 'local_attendance');
} else {
    echo get_string('importfailed', 'local_attendance');
}
echo PHP_EOL;
foreach ($csvImport->get_log() as $logentry) {
    echo $logentry . PHP_EOL;
}
if ($csvImport->has_error()) {
    exit(1);
}
exit(0);