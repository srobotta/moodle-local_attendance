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
// Current working directory for resolving relative paths.
$cwd = $_SERVER['PWD'] ?? '';
echo "Current working directory: $cwd" . PHP_EOL;
if (str_starts_with($csvFile, DIRECTORY_SEPARATOR) || substr($csvFile, 1, 2) === ':' . DIRECTORY_SEPARATOR) {
    // Absolute path, do not prepend cwd.
} else {
    $csvFile = $cwd . DIRECTORY_SEPARATOR . $csvFile;
}
$contentFiles = [];
foreach ($files as $file) {
    $fname = basename($file);        
    if (str_starts_with($path, DIRECTORY_SEPARATOR) || substr($path, 1, 2) === ':' . DIRECTORY_SEPARATOR) {
        // Absolute path, do not prepend cwd.
        $contentFiles[$fname] = $file;
    } else {
        $contentFiles[$fname] = $cwd . DIRECTORY_SEPARATOR . $file;
    }
}

// Check if CSV file exists.
if (!file_exists($csvFile)) {
    cli_error('CSV file not found: ' . $csvFile);
}

// Set user to site admin for permission checks and to avoid issues with file access.
$admin = get_admin();
\core\session\manager::set_user($admin);

$mform = new form($csvFile, $contentFiles, $options['suffix'] ?? null, $options['del'] ?? null, $cwd);
$importHandler = new import_handler((object)[
    'suffix' => $mform->getCourseSuffix(),
    'files' => $mform->getContentFiles(),
]);
$csvImport = new csv_import($importHandler, $mform);
$csvImport->importCsvFile();
$mform->cleanupFiles();
if (!$csvImport->hasError()) {
    echo get_string('importsuccess', 'local_attendance');
} else {
    echo get_string('importfailed', 'local_attendance');
}
echo PHP_EOL;
foreach ($csvImport->getLog() as $logentry) {
    echo $logentry . PHP_EOL;
}
if ($csvImport->hasError()) {
    exit(1);
}
exit(0);