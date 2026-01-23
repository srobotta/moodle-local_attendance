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
require_once('../../config.php');

use local_attendance\csv_import;
use local_attendance\import_handler;
use local_attendance\upload_form;

global $OUTPUT, $USER, $PAGE, $CFG;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/attendance/index.php'));
$PAGE->set_pagelayout('admin');

if (!$course = get_site()) {
    error("Could not find a top-level course!");
}

require_login($course);

$mform = new upload_form();

// Output the page.
$PAGE->set_title(get_string('pluginname', 'local_attendance'));
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pluginname', 'local_attendance'), 2);

if ($mform->is_submitted() && $mform->is_validated()) {

    $importHandler = new import_handler((object)[
        'suffix' => $mform->getCourseSuffix(),
        'files' => $mform->getContentFiles(),
    ]);
    $csvImport = new csv_import($importHandler, $mform);
    $csvImport->importCsvFile();
    $mform->cleanupFiles();
    if (!$csvImport->hasError()) {
        echo $OUTPUT->notification(get_string('importsuccess', 'local_attendance'), 'notifysuccess');
    } else {
        echo $OUTPUT->notification(get_string('importfailed', 'local_attendance'), 'notifyalert');
    }
    echo $OUTPUT->heading(get_string('importlog', 'local_attendance'), 3);
    echo '<pre>';
    foreach ($csvImport->getLog() as $logentry) {
        echo $logentry . "\n";
    }
    echo '</pre>';
} else {
    $mform->display();
}

echo $OUTPUT->footer();
