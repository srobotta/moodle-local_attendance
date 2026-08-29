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
 * Unit tests for the local_attendance\modcreate class in local_attendance plugin
 * to create a url and a page activity. Also use the beforemodule and aftermodule
 * parameters.
 * @package    local_attendance
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_page_url_test extends \advanced_testcase {
    /**
     * The course object where the activities are created in.
     * @var \stdClass
     */
    protected \stdClass $course;

    /**
     * The course modules created during set up, indexed by section number.
     * @var array
     */
    protected array $modules = [];

    /**
     * Set up
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course([
            'shortname' => 'at-course',
            'fullname' => 'Test At Course',
            'summary' => 'DESC',
            'summaryformat' => FORMAT_MOODLE,
            'initsections' => 1,
        ], [
            'createsections' => true,
        ]);
        $PAGE->set_course($this->course);

        // Create a few modules spread across two different sections.
        $generator = $this->getDataGenerator();
        $this->modules[1] = [
            $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Page 1.1'], ['section' => 1]),
            $generator->create_module('url', [
                'course' => $this->course->id,
                'name' => 'URL 1.2',
                'externalurl' => 'https://example.com',
            ], ['section' => 1]),
        ];
        $this->modules[2] = [
            $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Page 2.1'], ['section' => 2]),
            $generator->create_module('url', [
                'course' => $this->course->id,
                'name' => 'URL 2.2',
                'externalurl' => 'https://example.org',
            ], ['section' => 2]),
        ];
    }

    /**
     * Helper method to trigger import with a csv row and return the result
     * of the import log, quiz instance and course module.
     *
     * @param array $csvrow The CSV row to use for creating the quiz.
     * @return array An array containing the created mod_quiz object, the quiz record, and the course module.
     */
    protected function run_csv_test(array $csvrow): array {
        global $DB;
        $mod = new modcreate();
        $log = $mod->use_course($this->course)->set_row($csvrow)->create($csvrow);
        $modname = $csvrow['module'];
        $mod = $DB->get_record($modname, ['id' => $log->get_id()], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance($modname, $mod->id, $this->course->id);
        return [$log, $mod, $cm];
    }

    /**
     * Test creating url course with very basic fields.
     * @covers \local_attendance\modcreate::create()
     */
    public function test_create_url_exception(): void {
        $csvrow = [
            'module' => 'url',
            'name' => 'Link to Padlet',
            'aftermodule' => 123,
            'externalurl' => 'https://padlet.com',
        ];
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('ex_invalidcm', 'local_attendance'));
        $this->run_csv_test($csvrow);
    }

    /**
     * Test creating mod ulr to be inserted after a certain module.
     * @covers \local_attendance\modcreate::create()
     */
    public function test_create_url_aftermodule(): void {
        $csvrow = [
            'module' => 'url',
            'name' => 'Link to Padlet',
            'aftermodule' => $this->modules[1][0]->cmid,
            'externalurl' => 'https://padlet.com',
        ];
        [$log, $modurl, $cm] = $this->run_csv_test($csvrow);
        $this->assertEquals('Link to Padlet', $log->get_name());
        $this->assertEquals('Link to Padlet', $modurl->name);
        $this->assertEquals('https://padlet.com', $modurl->externalurl);

        // The new url should have been inserted right after "URL 1.1" in section 1.
        $modinfo = get_fast_modinfo($this->course);
        $sections = $modinfo->get_sections();
        $this->assertEquals([
            $this->modules[1][0]->cmid,
            $cm->id,
            $this->modules[1][1]->cmid,
        ], array_values($sections[1]));
    }

    /**
     * Test creating mod ulr to be inserted after a certain module.
     * @covers \local_attendance\modcreate::create()
     */
    public function test_create_page_beforemodule(): void {
        $csvrow = [
            'module' => 'page',
            'name' => 'Test new page',
            'beforemodule' => $this->modules[2][1]->cmid,
            'page' => '<p>This is my <strong>new content</strong>.</p>It looks good!</p>',
        ];
        [$log, $modpage, $cm] = $this->run_csv_test($csvrow);
        $this->assertEquals('Test new page', $log->get_name());
        $this->assertEquals('Test new page', $modpage->name);
        $this->assertEquals('<p>This is my <strong>new content</strong>.</p>It looks good!</p>', $modpage->content);
        $this->assertEquals(FORMAT_HTML, $modpage->contentformat);

        // The new page should have been inserted right after "URL 2.1" in section 2.
        $modinfo = get_fast_modinfo($this->course);
        $sections = $modinfo->get_sections();
        $this->assertEquals([
            $this->modules[2][0]->cmid,
            $cm->id,
            $this->modules[2][1]->cmid,
        ], array_values($sections[2]));
    }

    /**
     * Test creating mod ulr to be inserted after a certain module.
     * @covers \local_attendance\modcreate::create()
     */
    public function test_create_page_moodleformat(): void {
        $csvrow = [
            'module' => 'page',
            'name' => 'Some other page',
            'aftermodule' => $this->modules[2][1]->cmid,
            'page' => 'Some other content with no HTML included.',
        ];
        [$log, $modpage, $cm] = $this->run_csv_test($csvrow);
        $this->assertEquals('Some other page', $log->get_name());
        $this->assertEquals('Some other page', $modpage->name);
        $this->assertEquals('Some other content with no HTML included.', $modpage->content);
        $this->assertEquals(FORMAT_PLAIN, $modpage->contentformat);

        // The new page should have been inserted right after "URL 2.1" in section 2.
        $modinfo = get_fast_modinfo($this->course);
        $sections = $modinfo->get_sections();
        $this->assertEquals([
            $this->modules[2][0]->cmid,
            $this->modules[2][1]->cmid,
            $cm->id,
        ], array_values($sections[2]));
    }
}
