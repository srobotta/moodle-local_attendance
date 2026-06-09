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
 * Unit tests for the import_handler createCourse() function in local_attendance plugin.
 *
 * @package    local_attendance
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class createcourse_test extends \advanced_testcase {
    /**
     * The source course object that serves as a base for attendance course creation.
     * @var \stdClass
     */
    protected \stdClass $sourcecourse;

    /**
     * The category in which the source course is created.
     * @var \core_course_category
     */
    protected \core_course_category $category;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a test category.
        $this->category = $this->getDataGenerator()->create_category([
            'name' => 'Test Category',
        ]);

        // Create a source course.
        $this->sourcecourse = $this->getDataGenerator()->create_course([
            'shortname' => 'source-course',
            'fullname' => 'Source Test Course',
            'summary' => 'This is a source course',
            'summaryformat' => FORMAT_MOODLE,
            'category' => $this->category->id,
            'visible' => 1,
            'format' => 'topics',
            'startdate' => 1735689600, // 2025-01-01
            'enddate' => 1735776000, // 2025-01-02
            'initsections' => 1,
        ], [
            'createsections' => true,
        ]);
        $PAGE->set_course($this->sourcecourse);
    }

    /**
     * Helper method to test course creation and return the created course.
     *
     * @param array $csvrow The CSV row data for course creation.
     * @param \stdClass|null $options Options to pass to the import handler.
     * @return \stdClass The created course object.
     */
    protected function run_csv_test(array $csvrow, ?\stdClass $options = null): \stdClass {
        $handler = new import_handler($options);
        return $handler->create_course($csvrow);
    }

    /**
     * Test creating a course with basic fields by source_course_id.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_source_course_id(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'name' => 'Attendance Course',
            'shortname' => 'att-course',
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $this->assertEquals('Attendance Course', $newcourse->fullname);
        $this->assertEquals('att-course', $newcourse->shortname);
        $this->assertEquals($this->sourcecourse->category, $newcourse->category);
    }

    /**
     * Test creating a course with source_course_short.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_source_course_short(): void {
        $csvrow = [
            'source_course_short' => $this->sourcecourse->shortname,
            'name' => 'Attendance Via Shortname',
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $this->assertEquals('Attendance Via Shortname', $newcourse->fullname);
    }

    /**
     * Test creating a course with source_course_url.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_source_course_url(): void {
        global $CFG;
        $url = $CFG->wwwroot . '/course/view.php?id=' . $this->sourcecourse->id;
        $csvrow = [
            'source_course_url' => $url,
            'name' => 'Attendance Via URL',
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $this->assertEquals('Attendance Via URL', $newcourse->fullname);
    }

    /**
     * Test creating a course with default fields inherited from source course.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_inherited_fields(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Fields should be inherited from source course.
        $this->assertEquals($this->sourcecourse->category, $newcourse->category);
        $this->assertEquals($this->sourcecourse->visible, $newcourse->visible);
        $this->assertEquals($this->sourcecourse->format, $newcourse->format);
        $this->assertEquals($this->sourcecourse->startdate, $newcourse->startdate);
        $this->assertEquals($this->sourcecourse->enddate, $newcourse->enddate);
    }

    /**
     * Test creating a course with custom visibility override.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_custom_visible(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'visible' => 0,
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $this->assertEquals(0, $newcourse->visible);
    }

    /**
     * Test creating a course with custom category override.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_custom_category(): void {
        $newcategory = $this->getDataGenerator()->create_category([
            'name' => 'New Attendance Category',
        ]);
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'category' => $newcategory->id,
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $this->assertEquals($newcategory->id, $newcourse->category);
    }

    /**
     * Test creating a course with custom format.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_custom_format(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'format' => 'weeks',
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $this->assertEquals('weeks', $newcourse->format);
    }

    /**
     * Test creating a course with custom start and end dates.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_custom_dates(): void {
        $newstartdate = 1740134400; // 2025-02-21
        $newenddate = 1740220800;   // 2025-02-22
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'startdate' => $newstartdate,
            'enddate' => $newenddate,
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $this->assertEquals($newstartdate, $newcourse->startdate);
        $this->assertEquals($newenddate, $newcourse->enddate);
    }

    /**
     * Test creating a course with default suffix when shortname is not provided.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_default_shortname_suffix(): void {
        $options = new \stdClass();
        $options->suffix = 'attendance-2025';
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
        ];
        $newcourse = $this->run_csv_test($csvrow, $options);
        $this->assertStringContainsString('attendance-2025', $newcourse->shortname);
    }

    /**
     * Test creating a course with section names.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_section_names(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'section_name_1' => 'Obligatory Modules',
            'section_name_2' => 'Optional Modules',
        ];
        $newcourse = $this->run_csv_test($csvrow);
        $modinfo = get_fast_modinfo($newcourse);
        $sections = $modinfo->get_section_info_all();

        $this->assertEquals('Obligatory Modules', $sections[0]->name);
        $this->assertEquals('Optional Modules', $sections[1]->name);
    }

    /**
     * Test creating a course with a link back to source course.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_link_new_course(): void {
        global $DB;
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'link_new_course' => 'Go to Attendance Course',
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Check that a URL module was created in the source course linking to the new course.
        $urlmodule = $DB->get_record('url', ['course' => $this->sourcecourse->id]);
        $this->assertNotNull($urlmodule);
        $this->assertStringContainsString((string)$newcourse->id, $urlmodule->externalurl);
    }

    /**
     * Test creating a course with link to specific section.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_link_to_section(): void {
        global $DB;
        // Create a section in the source course first.
        course_create_section($this->sourcecourse->id);

        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'link_new_course' => 'Attendance',
            'link_new_course_section' => 2,
        ];
        $this->run_csv_test($csvrow);

        // Check that the URL module was created.
        $urlmodule = $DB->get_record('url', ['course' => $this->sourcecourse->id]);
        $this->assertNotNull($urlmodule);
    }

    /**
     * Test creating a course with copy participants option.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_copy_participants(): void {
        // Enrol a user in the source course.
        $user = $this->getDataGenerator()->create_user([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
        $this->getDataGenerator()->enrol_user($user->id, $this->sourcecourse->id);

        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'copyparticipants' => 1,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Check that the user is enrolled in the new course.
        $this->assertTrue(is_enrolled(\context_course::instance($newcourse->id), $user));
    }

    /**
     * Test creating a course without copy participants.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_without_copy_participants(): void {
        // Enrol a user in the source course.
        $user = $this->getDataGenerator()->create_user([
            'username' => 'testuser2',
            'email' => 'test2@example.com',
        ]);
        $this->getDataGenerator()->enrol_user($user->id, $this->sourcecourse->id);

        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // User should NOT be enrolled in the new course.
        $this->assertFalse(is_enrolled(\context_course::instance($newcourse->id), $user));
    }

    /**
     * Test creating a course with meta enrolment.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_meta_enrolment(): void {
        global $DB;
        // Enable meta enrolment plugin if available.
        enrol_is_enabled('meta') || $this->markTestSkipped('Meta enrolment plugin not enabled');

        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'metaenrolment' => 1,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Check that meta enrolment was added.
        $enrolments = $DB->get_records('enrol', [
            'courseid' => $newcourse->id,
            'enrol' => 'meta',
        ]);
        $this->assertNotEmpty($enrolments);
        $enrol = reset($enrolments);
        $this->assertEquals($this->sourcecourse->id, $enrol->customint1);
    }

    /**
     * Test creating a course with grade completion criteria.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_grade_completion_criteria(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'completion_criteria_grade' => 8.0,
            'completion_criteria_overall_aggregation' => 'all',
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Verify the completion info was set.
        $completion = new \completion_info($newcourse);
        $criteria = $completion->get_criteria();
        $this->assertEquals(1, count($criteria));
        $criterion = reset($criteria);
        $this->assertEquals(8.0, $criterion->gradepass);
        $this->assertEquals(COMPLETION_CRITERIA_TYPE_GRADE, $criterion->criteriatype);
        $this->assertNull($criterion->timeend);
    }

    /**
     * Test creating a course with date completion criteria.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_date_completion_criteria(): void {
        $completedate = '2027-12-31';
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'completion_criteria_date' => $completedate,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Verify completion info was set.
        $completion = new \completion_info($newcourse);
        $criteria = $completion->get_criteria();
        $this->assertEquals(1, count($criteria));
        $criterion = reset($criteria);
        $this->assertNull($criterion->gradepass);
        $this->assertEquals(COMPLETION_CRITERIA_TYPE_DATE, $criterion->criteriatype);
        $this->assertEquals(strtotime($completedate), $criterion->timeend);
    }

    /**
     * Test creating a course with duration completion criteria.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_duration_completion_criteria(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'completion_criteria_duration' => 30,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Verify completion info was set.
        $completion = new \completion_info($newcourse);
        $criteria = $completion->get_criteria();
        $this->assertEquals(1, count($criteria));
        $criterion = reset($criteria);
        $this->assertEquals(COMPLETION_CRITERIA_TYPE_DURATION, $criterion->criteriatype);
    }

    /**
     * Test creating a course with self completion criteria.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_self_completion_criteria(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'completion_criteria_self' => 1,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Verify completion info was set.
        $completion = new \completion_info($newcourse);
        $criteria = $completion->get_criteria();
        $this->assertEquals(1, count($criteria));
        $criterion = reset($criteria);
        $this->assertEquals(COMPLETION_CRITERIA_TYPE_SELF, $criterion->criteriatype);
    }

    /**
     * Test creating a course with unenrol completion criteria.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_unenrol_completion_criteria(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'completion_criteria_unenrol' => 1,
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Verify completion info was set.
        $completion = new \completion_info($newcourse);
        $criteria = $completion->get_criteria();
        $this->assertEquals(1, count($criteria));
        $criterion = reset($criteria);
        $this->assertEquals(COMPLETION_CRITERIA_TYPE_UNENROL, $criterion->criteriatype);
    }

    /**
     * Test that multiple courses can be created from the same source course.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_multiple_courses_from_same_source(): void {
        $csvrow1 = [
            'source_course_id' => $this->sourcecourse->id,
            'shortname' => 'att-course-1',
            'name' => 'Attendance Course 1',
        ];
        $newcourse1 = $this->run_csv_test($csvrow1);

        $csvrow2 = [
            'source_course_id' => $this->sourcecourse->id,
            'shortname' => 'att-course-2',
            'name' => 'Attendance Course 2',
        ];
        $newcourse2 = $this->run_csv_test($csvrow2);

        $this->assertNotEquals($newcourse1->id, $newcourse2->id);
        $this->assertEquals('att-course-1', $newcourse1->shortname);
        $this->assertEquals('att-course-2', $newcourse2->shortname);
    }

    /**
     * Test creating a course with invalid source course ID throws exception.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_invalid_source_course_id(): void {
        $this->expectException(\moodle_exception::class);
        $csvrow = [
            'source_course_id' => 99999,
        ];
        $this->run_csv_test($csvrow);
    }

    /**
     * Test creating a course with invalid source course short name throws exception.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_invalid_source_course_short(): void {
        $this->expectException(\moodle_exception::class);
        $csvrow = [
            'source_course_short' => 'nonexistent-course',
        ];
        $this->run_csv_test($csvrow);
    }

    /**
     * Test creating a course without any source course identifier throws exception.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_without_source_course_throws_exception(): void {
        $this->expectException(\moodle_exception::class);
        $csvrow = [
            'name' => 'Orphan Course',
        ];
        $this->run_csv_test($csvrow);
    }

    /**
     * Test creating a course with combined completion criteria.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_with_combined_completion_criteria(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'completion_criteria_grade' => 8.0,
            'completion_criteria_date' => '2025-12-31',
            'completion_criteria_overall_aggregation' => 'any',
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Verify completion info was set.
        $completion = new \completion_info($newcourse);
        $criteria = \array_values($completion->get_criteria());
        $this->assertEquals(2, count($criteria));
        $this->assertEquals(2, $criteria[0]->criteriatype); // Date criterion.
        $this->assertEquals(6, $criteria[1]->criteriatype); // Grade criterion.
    }

    /**
     * Test that shortname suffix is used when shortname is not explicitly set.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_shortname_suffix_default(): void {
        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            // Do not set shortname.
        ];
        $newcourse = $this->run_csv_test($csvrow);

        // Shortname should have a suffix added (either from options or timestamp).
        $this->assertNotEquals($this->sourcecourse->shortname, $newcourse->shortname);
        $this->assertStringStartsWith($this->sourcecourse->shortname, $newcourse->shortname);
    }

    /**
     * Test course is created successfully with all major field types.
     * @covers \local_attendance\import_handler::create_course()
     */
    public function test_create_course_comprehensive(): void {
        $newcategory = $this->getDataGenerator()->create_category([
            'name' => 'Attendance Categories',
        ]);

        $csvrow = [
            'source_course_id' => $this->sourcecourse->id,
            'name' => 'Comprehensive Test Course',
            'shortname' => 'comp-test',
            'category' => $newcategory->id,
            'visible' => 0,
            'format' => 'topics',
            'section_name_1' => 'Week 1',
            'section_name_2' => 'Week 2',
        ];
        $newcourse = $this->run_csv_test($csvrow);

        $this->assertEquals('Comprehensive Test Course', $newcourse->fullname);
        $this->assertEquals('comp-test', $newcourse->shortname);
        $this->assertEquals($newcategory->id, $newcourse->category);
        $this->assertEquals(0, $newcourse->visible);
        $this->assertEquals('topics', $newcourse->format);

        // Verify sections were created.
        $modinfo = get_fast_modinfo($newcourse);
        $sections = $modinfo->get_section_info_all();
        $this->assertEquals('Week 1', $sections[0]->name);
        $this->assertEquals('Week 2', $sections[1]->name);
    }
}
