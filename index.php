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
//

/**
 * Displays the import form to upload a csv file and assets.
 *
 * @package       local_attendance
 * @copyright     2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
$pageurl = new moodle_url('/local/attendance/index.php');
$context = context_system::instance();
require_capability('local/attendance:view', $context);

global $OUTPUT, $USER, $PAGE, $CFG;

use local_attendance\csv_import;
use local_attendance\import_handler;
use local_attendance\form\upload as upload_form;

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');

if (!$course = get_site()) {
    throw new \moodle_exception("Could not find a top-level course!");
}

$mform = new upload_form();

// Output the page.
$PAGE->set_title(get_string('pluginname', 'local_attendance'));
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pluginname', 'local_attendance'), 2);

if ($mform->is_submitted() && $mform->is_validated()) {
    // Importing the file and creating courses needs quite some ressources.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_HUGE);

    $handler = new import_handler((object)[
        'suffix' => $mform->get_course_suffix(),
        'files' => $mform->get_content_files(),
    ]);
    $csvimport = new csv_import($handler, $mform);
    $csvimport->import_csv_file();
    $mform->cleanup_files();
    if (!$csvimport->has_error()) {
        echo $OUTPUT->notification(get_string('importsuccess', 'local_attendance'), 'notifysuccess');
    } else {
        echo $OUTPUT->notification(get_string('importfailed', 'local_attendance'), 'notifyalert');
    }
    echo $OUTPUT->heading(get_string('importlog', 'local_attendance'), 3);
    echo '<pre>';
    foreach ($csvimport->get_log() as $logentry) {
        echo $logentry . "\n";
    }
    echo '</pre>';
    echo $OUTPUT->single_button($pageurl, get_string('back'), 'get');
} else {
    $mform->display();
}

echo $OUTPUT->footer();
