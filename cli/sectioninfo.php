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
 * This script finds the course id, section id and position of the activity
 * given by the url.
 *
 * @package    local_attendance
 * @subpackage cli
 * @copyright  2026 Stephan Robotta (stephan.robotta@bfh.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_attendance\utils\utils;

// Now get cli option.
[$options, $other] = cli_get_params(
    [
        'help' => false,
        'delimiter' => null,
    ],
    [
        'd' => 'delimiter',
        'h' => 'help',
    ]
);

if (empty($other)) {
    cli_error('No url given', 1);
}

if ($options['help']) {
    $help =
    "Usage: php sectioninfo.php [options] url|file ...

Options:
-d, --delimiter         CSV delimiter for output (default is ,)
-h, --help              Display this help message.

Example:
\$sudo -u www-data /usr/bin/php public/local/attendance/cli/sectioninfo.php 'https://example.com/mod/page/view.php?id=223' \
  /path/to/courses.csv /path/to/contentfile1.pdf
";

    echo $help;
    die;
}

$delimiter = $options['delimiter'] ?? ',';
$urlstocheck = [];

foreach ($other as $item) {
    if (file_exists($item)) {
        $urls = explode(PHP_EOL, file_get_contents($item) ?? '');
        if (!is_array($urls)) {
            continue;
        }
        $urlstocheck += $urls;
        continue;
    }
    $urlstocheck[] = $item;
}

// Write the CSV header.
echo implode($delimiter, ['url', 'course', 'section', 'position']) . PHP_EOL;
foreach ($urlstocheck as $url) {
    $url = trim($url);
    $info = utils::get_section_info($url);
    if ($info) {
        echo implode($delimiter, [$url, $info->course, $info->section, $info->position]) . PHP_EOL;
    } else {
        echo $url . str_repeat($delimiter, 3) . PHP_EOL;
    }
}
