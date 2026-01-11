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
 * Language strings
 *
 * @package    local_attendance
 * @copyright  2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Attendance Course Creator';
$string['uploadcsvfile'] = 'Upload CSV file';
$string['csvseparator'] = 'CSV Separator';
$string['csvseparatorcomma'] = 'Comma (,)';
$string['csvseparatorsemicolon'] = 'Semicolon (;)';
$string['csvseparatortab'] = 'Tab (\t)';
$string['coursesuffix'] = 'Course generic suffix';
$string['coursesuffix_help'] = 'This suffix is attached to the course name and in lower case letters to the course short name. Can be overridden in the import file.';
$string['coursesuffixvalue'] = 'Attendance';
$string['import'] = 'Import';
$string['importsuccess'] = 'Import successful';
$string['importfailed'] = 'Import completed with errors';
$string['importlog'] = 'Import Log';

$string['ex_invalidmoduleclass'] = 'Invalid module specified, could not instantiate class "{$a}".';
$string['ex_invalidimplements'] = 'The module class "{$a}" does not implement the required interface.';
$string['ex_modulecreationfailed'] = 'Module creation failed.';
$string['ex_invalidvalue'] = 'Invalid value "{$a->value}" for column "{$a->column}".';
$string['ex_invalidpasswdrule'] = 'Invalid rule "{$a->rule}" for password generation, csv file for wordlist not found.';
$string['ex_invalidpasswdrulecontent'] = 'Invalid content in wordlist "{$a->file}" for password generation.';
$string['ex_missingfield'] = 'Missing required field "{$a->field}".';
$string['ex_nosourcecourse'] = 'No source course defined in course data.';

$string['csv_importexception'] = 'Line {$a->line}: An exception occurred: {$a->message}';
$string['csv_import_invalidcommand'] = 'Line {$a->line}: Invalid command {$a->cmd} in CSV file.';
$string['csv_import_coursecolmissing'] = 'Line {$a->line}: Course columns must be defined first before using them.';
$string['csv_import_modulecolmissing'] = 'Line {$a->line}: Module columns must be defined first before using them.';
$string['csv_import_badgecolmissing'] = 'Line {$a->line}: Badge columns must be defined first before using them.';
$string['csv_import_missingcolumn'] = 'Line {$a->line}: Missing required column "{$a->column}".';
$string['csv_import_needcoursefirst'] = 'Line {$a->line}: Course must be defined in COURSE or USE_COURSE before adding modules.';
$string['csv_import_skipline'] = 'Line {$a->line}: skipped because of earlier error.';
$string['csv_import_ok_course'] = 'Line {$a->line}: Course id: {$a->id}, name: "{$a->name}" link: {$a->url}, additional info: {$a->info}';
$string['csv_import_ok_module'] = 'Line {$a->line}: Module {$a->modulename} {$a->id}, name: "{$a->name}", link: {$a->url}, additional info: {$a->info}';
$string['csv_import_ok_badge'] = 'Line {$a->line}: Badge name: "{$a->name}", link: {$a->url}, additional info: {$a->info}';

$string['col_questionname'] = 'Attendance question';
$string['col_questiontext'] = 'Did you attend the class?';
$string['col_generalfeedback'] = 'Please answer yes if you attended the class.';

$string['badge_defaultname'] = 'Attendance Badge';
$string['badge_defaultdescription'] = 'This badge is awarded for attending the course {$a}.';