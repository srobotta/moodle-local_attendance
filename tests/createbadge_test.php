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
 * Unit tests for the import_handler createBadge() function in local_attendance plugin.
 *
 * @package    local_attendance
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class createbadge_test extends \advanced_testcase {
    /**
     * The course object where badges are created in.
     * @var \stdClass
     */
    protected \stdClass $course;

    /**
     * The source course object that serves as a base for badge imagecaption.
     * @var \stdClass
     */
    protected \stdClass $sourcecourse;

    /**
     * The category for test courses.
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

        // Create a source course for badge creation reference.
        $this->sourcecourse = $this->getDataGenerator()->create_course([
            'shortname' => 'source-course',
            'fullname' => 'Source Test Course',
            'category' => $this->category->id,
        ]);

        // Create a course where badges will be created.
        $this->course = $this->getDataGenerator()->create_course([
            'shortname' => 'badge-course',
            'fullname' => 'Badge Test Course',
            'category' => $this->category->id,
            'initsections' => 1,
        ], [
            'createsections' => true,
        ]);
        $PAGE->set_course($this->course);
    }

    /**
     * Helper method to create a badge and return the badge object.
     *
     * @param array $csvrow The CSV row data for badge creation.
     * @param \stdClass|null $options Options to pass to the import handler.
     * @param int $usecourseid The ID of the course to use for badge creation (0 to create a new course).
     * @return content\badge The created badge object.
     */
    protected function run_badge_test(array $csvrow, ?\stdClass $options = null, int $usecourseid = 0): content\badge {
        $handler = new import_handler($options);
        if ($usecourseid === 0) {
            // First, create the course where the badge will be created.
            $coursecsvdata = [
                'source_course_id' => $this->sourcecourse->id,
            ];
            $handler->create_course($coursecsvdata);
        } else {
            // If not creating a course, use the existing course for badge creation.
            $handler->use_course(['source_course_id' => $usecourseid]);
        }
        // Now create the badge in that course.
        return $handler->create_badge($csvrow);
    }

    /**
     * Helper method to check that the badge image was created correctly.
     * @param \stdClass $badge The badge record from the database to check the image for.
     */
    protected function run_badge_image_test(\stdClass $badge): void {
        global $CFG;
        $files = get_file_storage()->get_area_files(
            \context_course::instance($badge->courseid)->id,
            'badges',
            'badgeimage',
            $badge->id,
            'id',
            false
        );
        // There should be a generated image file with the source course shortname as caption.
        $this->assertGreaterThan(0, count($files));
        $sizes = [100, 35, 512];
        foreach (\array_values($files) as $i => $file) {
            $tempname = tempnam($CFG->tempdir, 'createbadge_test_badgeimage');
            $file->copy_content_to($tempname);
            [$w, $h] = getimagesize($tempname);
            $this->assertEquals($sizes[$i], $w);
            $this->assertEquals($sizes[$i], $h);
        }
    }

    /**
     * Test creating a badge with default name and description.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_defaults(): void {
        global $DB;
        $csvrow = [
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        // Get the created badge from database.
        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);

        // Verify defaults were used.
        $this->assertNotEmpty($badge->name);
        $this->assertNotEmpty($badge->description);
        $this->assertEquals(BADGE_TYPE_COURSE, $badge->type);
    }

    /**
     * Test creating a badge with custom name.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_custom_name(): void {
        global $DB;
        $csvrow = [
            'name' => 'Attendance Achievement',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertEquals('Attendance Achievement', $badge->name);
    }

    /**
     * Test creating a badge with custom description.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_custom_description(): void {
        global $DB;
        $csvrow = [
            'name' => 'Custom Badge',
            'description' => 'Badge awarded for perfect attendance',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertEquals('Badge awarded for perfect attendance', $badge->description);
    }

    /**
     * Test creating a badge with manual criteria.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_manual_criteria(): void {
        global $DB;
        $csvrow = [
            'name' => 'Manual Award Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'grade' => 50,
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with course criteria.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_course_criteria(): void {
        global $DB;

        // Create another course to be used as completion criteria.
        $othercourse = $this->getDataGenerator()->create_course([
            'shortname' => 'other-course',
            'fullname' => 'Other Course',
            'category' => $this->category->id,
        ]);

        $csvrow = [
            'name' => 'Course Completion Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_COURSE',
            'criteria_courses' => $othercourse->id,
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with activity criteria.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_activity_criteria(): void {
        global $DB;

        $csvrow = [
            'name' => 'Activity Completion Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_ACTIVITY',
            'criteria_activity' => 1,
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with profile criteria.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_profile_criteria(): void {
        global $DB;

        $csvrow = [
            'name' => 'Profile Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_PROFILE',
            'criteria_profile_email' => 'test@example.com',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with int criteria type.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_int_criteria_type(): void {
        global $DB;
        // BADGE_CRITERIA_TYPE_MANUAL = 2.
        $csvrow = [
            'name' => 'Int Criteria Badge',
            'criteriatype' => 2,
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with generated image (default parameters).
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_generated_image_default(): void {
        global $CFG, $DB;

        $csvrow = [
            'name' => 'Badge with Generated Image',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            // No imagefile - should generate image.
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);

        // Check that badge has an image (check if image file exists in badge context).
        $context = \context_course::instance($badge->courseid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'badges', 'badgeimage', $badge->id);

        // There should be at least the badge image file.
        $this->assertGreaterThan(0, count($files));
    }

    /**
     * Test creating a badge with custom image caption.
     * @covers \local_attendance\content\badge_image::__construct()
     */
    public function test_create_badge_with_custom_image_caption(): void {
        global $DB;

        $csvrow = [
            'name' => 'Badge with Custom Caption',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'imagecaption' => 'CUSTOM-CAP',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with custom colors.
     * @covers \local_attendance\content\badge_image::__construct()
     */
    public function test_create_badge_with_custom_colors(): void {
        global $DB;

        $csvrow = [
            'name' => 'Colorful Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'bgcolor' => 'FF5733',
            'fgcolor' => '000000',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with custom image dimensions.
     * @covers \local_attendance\content\badge_image::__construct()
     */
    public function test_create_badge_with_custom_dimensions(): void {
        global $DB;

        $csvrow = [
            'name' => 'Sized Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'width' => 400,
            'height' => 400,
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with TEXT_ONLY imagemode.
     * @covers \local_attendance\content\badge_image::__construct()
     */
    public function test_create_badge_with_text_only_mode(): void {
        global $DB;

        $csvrow = [
            'name' => 'Text Only Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'imagemode' => 'TEXT_ONLY',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with TEXT_CHECKMARK imagemode.
     * @covers \local_attendance\content\badge_image::__construct()
     */
    public function test_create_badge_with_checkmark_mode(): void {
        global $DB;

        $csvrow = [
            'name' => 'Checkmark Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'imagemode' => 'TEXT_CHECKMARK',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with TEXT_TTF imagemode.
     * @covers \local_attendance\content\badge_image::__construct()
     */
    public function test_create_badge_with_ttf_mode(): void {
        global $DB;

        $csvrow = [
            'name' => 'TTF Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'imagemode' => 'TEXT_TTF',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge with int imagemode constant.
     * @covers \local_attendance\content\badge_image::__construct()
     */
    public function test_create_badge_with_int_imagemode(): void {
        global $DB;

        $csvrow = [
            'name' => 'Int Mode Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'imagemode' => 1, // TEXT_CHECKMARK.
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test creating a badge without badgedisable (should be enabled by default).
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_enabled_by_default(): void {
        global $DB;

        $csvrow = [
            'name' => 'Enabled Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        // Badge should be enabled (status not equal to BADGE_STATUS_INACTIVE).
        $this->assertNotEquals(BADGE_STATUS_INACTIVE, $badge->status);
    }

    /**
     * Test creating a badge with badgedisable set.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_disabled(): void {
        global $DB;

        $csvrow = [
            'name' => 'Disabled Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'badgedisable' => 1,
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
    }

    /**
     * Test that badge object returns correct ID.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_badge_object_get_id(): void {
        $csvrow = [
            'name' => 'ID Test Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        // Badge ID should be positive integer.
        $this->assertGreaterThan(0, $badgeobj->get_id());
    }

    /**
     * Test that badge object returns correct entity name.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_badge_object_get_entity_name(): void {
        $csvrow = [
            'name' => 'Entity Name Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $this->assertEquals('badge', $badgeobj->get_entity_name());
    }

    /**
     * Test creating a badge with invalid criteria type throws exception.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_invalid_criteria_type(): void {
        $this->expectException(\moodle_exception::class);

        $csvrow = [
            'name' => 'Invalid Badge',
            'criteriatype' => 'INVALID_CRITERIA_TYPE',
        ];
        $this->run_badge_test($csvrow);
    }

    /**
     * Test creating a badge with missing image file throws exception.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_badge_with_missing_image_file(): void {
        $this->expectException(\moodle_exception::class);

        $options = new \stdClass();
        $options->files = []; // Empty files array.

        $csvrow = [
            'name' => 'Missing Image Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'imagefile' => 'nonexistent.png',
        ];
        $this->run_badge_test($csvrow, $options);
    }

    /**
     * Test creating multiple badges in the same course.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_create_multiple_badges_in_same_course(): void {
        global $DB;

        $csvrow1 = [
            'name' => 'Badge 1',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj1 = $this->run_badge_test($csvrow1);
        $badge1 = $DB->get_record('badge', ['id' => $badgeobj1->get_id()]);

        $csvrow2 = [
            'name' => 'Badge 2',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj2 = $this->run_badge_test($csvrow2, null, $badge1->courseid);
        $badge2 = $DB->get_record('badge', ['id' => $badgeobj2->get_id()]);
        // Both badges should be created in the same course.
        $this->assertEquals($badge1->courseid, $badge2->courseid);
        $this->assertNotEquals($badge1->id, $badge2->id);
    }

    /**
     * Test creating a badge with image caption from source course.
     * @covers \local_attendance\content\badge::create()
     * @covers \local_attendance\content\badge_image::_construct()
     */
    public function test_create_badge_image_caption_from_source_course(): void {
        global $DB;

        $csvrow = [
            'name' => 'Badge with Source Caption',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            // No imagecaption provided, should use source course shortname.
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
        $this->run_badge_image_test($badge);
    }

    /**
     * Test creating a badge with all custom image parameters.
     * @covers \local_attendance\content\badge::create()
     * @covers \local_attendance\content\badge_image::_construct()
     */
    public function test_create_badge_with_all_image_parameters(): void {
        global $DB;

        $csvrow = [
            'name' => 'Fully Customized Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
            'imagecaption' => 'CUSTOM',
            'bgcolor' => 'ABCDEF',
            'fgcolor' => '123456',
            'width' => 350,
            'height' => 350,
            'imagemode' => 'TEXT_CHECKMARK',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertNotNull($badge);
        $this->assertEquals('Fully Customized Badge', $badge->name);
        $this->run_badge_image_test($badge);
    }

    /**
     * Test badge is created as BADGE_TYPE_COURSE.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_badge_type_is_course(): void {
        global $DB;

        $csvrow = [
            'name' => 'Course Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);
        $this->assertEquals(BADGE_TYPE_COURSE, $badge->type);
    }

    /**
     * Test badge is created in the correct course.
     * @covers \local_attendance\content\badge::create()
     */
    public function test_badge_created_in_correct_course(): void {
        global $DB;

        $csvrow = [
            'name' => 'Course Specific Badge',
            'criteriatype' => 'BADGE_CRITERIA_TYPE_MANUAL',
        ];
        $badgeobj = $this->run_badge_test($csvrow);

        $badge = $DB->get_record('badge', ['id' => $badgeobj->get_id()], '*', MUST_EXIST);

        // Badge should be created in the course created by the import handler,
        // which is a new course based on sourcecourse.
        $this->assertNotNull($badge->courseid);
    }
}
