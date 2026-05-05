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

use mod_quiz\quiz_settings;

/**
 * Unit tests for the local_attendance\mod\quiz class in local_attendance plugin.
 * @package    local_attendance
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class modquiz_test extends \advanced_testcase {
    /**
     * The course object where are the attendance quizzes are created in.
     * @var \stdClass
     */
    protected \stdClass $course;

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
    }

    /**
     * Helper method to trigger import with a csv row and return the result
     * of the import log, quiz instance and course module.
     *
     * @param array $csvrow The CSV row to use for creating the quiz.
     * @return array An array containing the created mod_quiz object, the quiz record, and the course module.
     */
    protected function runCsvTest(array $csvrow): array {
        global $DB;
        $modquiz = new mod\quiz();
        $log = $modquiz->useCourse($this->course)->setRow($csvrow)->create($csvrow);
        $quiz = $DB->get_record('quiz', ['id' => $log->getId()], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id);
        return [$log, $quiz, $cm];
    }

    /**
     * Get the questions for a given quiz and course module.
     *
     * @param \stdClass $quiz The quiz object.
     * @param \stdClass $cm The course module object.
     * @return array The list of questions.
     */
    protected function getQuestions(
        \stdClass $quiz,
        \stdClass $cm,
    ): array {
        $quizobj = new quiz_settings($quiz, $cm, $this->course);
        $quizobj->preload_questions();
        $quizobj->load_questions();
        $questions = $quizobj->get_questions();
        return $questions;
    }

    /**
     * Test creating course with very basic fields.
     */
    public function test_create_quiz1(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Day 1 Attendance',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals('Day 1 Attendance', $log->getName());
        $this->assertEquals('Day 1 Attendance', $quiz->name);
        $this->assertEquals(60, $quiz->timelimit);
        $this->assertEquals(strtotime('2026-01-06 10:30:00'), $quiz->timeopen);
        $this->assertEquals(strtotime('2026-01-06 10:40:00'), $quiz->timeclose);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{6}$/', $quiz->password);
    }

    /**
     * Test creating course with custom password rule and HTML in question text fields.
     */
    public function test_create_quiz2(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Some Attendance 2',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'timelimit' => 120,
            'local_attendance_quiz_passwordrule' => 'nato',
            'local_attendance_quiz_questionname' => 'Attendance',
            'local_attendance_quiz_questiontext' => '<p class="alert alert-info">I was present</p>',
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow);
        $this->assertEquals('Some Attendance 2', $quiz->name);
        $this->assertEquals(120, $quiz->timelimit);
        // Load nato word list and check password is from that list.
        $nato = explode("\n", file_get_contents(__DIR__ . '/../wordlist/nato.csv'));
        $this->assertContains($quiz->password, $nato);
        $questions = $this->getQuestions($quiz, $cm);
        $this->assertCount(1, $questions);
        $question = reset($questions);
        $this->assertEquals('Attendance', $question->name);
        $this->assertEquals('<p class="alert alert-info">I was present</p>', $question->questiontext);
        $this->assertEquals(1, $question->questiontextformat); // FORMAT_HTML
        $this->assertEquals(1.0, $question->defaultmark);
    }

    /**
     * Test creating quiz with custom password via quizpassword field.
     */
    public function test_create_quiz_custom_password(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Custom Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'quizpassword' => 'mypassword',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals('mypassword', $quiz->password);
    }

    /**
     * Test creating quiz with empty quizpassword means no password.
     */
    public function test_create_quiz_no_password(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'No Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'quizpassword' => '',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals('', $quiz->password);
    }

    /**
     * Test password generation with 'lower' rule (only lowercase letters).
     */
    public function test_create_quiz_password_rule_lower(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Lower Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'lower',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals(6, strlen($quiz->password));
        $this->assertMatchesRegularExpression('/^[a-z]{6}$/', $quiz->password);
    }

    /**
     * Test password generation with 'alpha' rule (lower and upper case letters).
     */
    public function test_create_quiz_password_rule_alpha(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Alpha Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'alpha',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals(6, strlen($quiz->password));
        $this->assertMatchesRegularExpression('/^[a-zA-Z]{6}$/', $quiz->password);
    }

    /**
     * Test password generation with 'alnum' rule (letters and digits).
     */
    public function test_create_quiz_password_rule_alnum(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Alnum Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'alnum',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals(6, strlen($quiz->password));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{6}$/', $quiz->password);
    }

    /**
     * Test password generation with 'all' rule (letters, digits and special chars).
     */
    public function test_create_quiz_password_rule_all(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'All Chars Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'all',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals(6, strlen($quiz->password));
        // Check that password contains at least one special character or digit
        $this->assertMatchesRegularExpression('/[!@#$%&*()_+\-={}[\]|:;<>,.?\/]/', $quiz->password);
    }

    /**
     * Test password generation with 'en' wordlist (English nouns).
     */
    public function test_create_quiz_password_rule_en(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'English Word Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'en',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $en = explode("\n", file_get_contents(__DIR__ . '/../wordlist/en.csv'));
        $this->assertContains($quiz->password, $en);
    }

    /**
     * Test password generation with 'de' wordlist (German nouns).
     */
    public function test_create_quiz_password_rule_de(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'German Word Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'de',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $de = explode("\n", file_get_contents(__DIR__ . '/../wordlist/de.csv'));
        $this->assertContains($quiz->password, $de);
    }

    /**
     * Test password generation with 'color' wordlist.
     */
    public function test_create_quiz_password_rule_color(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Color Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'color',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $color = explode("\n", file_get_contents(__DIR__ . '/../wordlist/color.csv'));
        $this->assertContains($quiz->password, $color);
    }

    /**
     * Test password generation with 'capital' wordlist.
     */
    public function test_create_quiz_password_rule_capital(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Capital Password Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_passwordrule' => 'capital',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $capital = explode("\n", file_get_contents(__DIR__ . '/../wordlist/capital.csv'));
        $this->assertContains($quiz->password, $capital);
    }

    /**
     * Test custom answer options for yes and no.
     */
    public function test_create_quiz_custom_answers(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Custom Answers Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_questiontext' => 'Did you attend the class?',
            'local_attendance_quiz_answer_yes' => 'Yes, I was there',
            'local_attendance_quiz_answer_no' => 'No, I was absent',
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow);
        $questions = $this->getQuestions($quiz, $cm);
        $this->assertCount(1, $questions);
        $question = reset($questions);
        // Get the question options to check the answers
        $this->assertEquals('multichoice', $question->qtype);
        $this->assertEquals(1, $question->options->single); // single=1 for single choice.
        $this->assertEquals('Did you attend the class?', $question->questiontext);
        $this->assertEquals(2, $question->questiontextformat); // FORMAT_MOODLE
        // Check the answers
        $answers = \array_values($question->options->answers);
        $this->assertCount(2, $answers);
        $this->assertEquals('Yes, I was there', $answers[0]->answer);
        $this->assertEquals('No, I was absent', $answers[1]->answer);
        $this->assertEquals(1, $answers[0]->fraction); // Yes is correct
        $this->assertEquals(0, $answers[1]->fraction); // No is incorrect
        $this->assertEquals(2, $answers[0]->answerformat); // FORMAT_MOODLE
    }

    /**
     * Test custom feedback for yes and no answers.
     */
    public function test_create_quiz_custom_feedback(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Custom Feedback Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'local_attendance_quiz_feedback_yes' => 'Thank you for confirming!',
            'local_attendance_quiz_feedback_no' => 'Sorry you could <em>not</em> attend.',
            'local_attendance_quiz_generalfeedback' => 'This quiz is for attendance tracking.',
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow);
        $questions = $this->getQuestions($quiz, $cm);
        $this->assertCount(1, $questions);
        $question = reset($questions);
        // Check general feedback
        $this->assertEquals('This quiz is for attendance tracking.', $question->generalfeedback);
        $this->assertEquals(2, $question->generalfeedbackformat); // FORMAT_MOODLE
        // Check answer-specific feedback
        $answers = array_values($question->options->answers);
        $this->assertCount(2, $answers);
        $this->assertEquals('Thank you for confirming!', $answers[0]->feedback);
        $this->assertEquals('Sorry you could <em>not</em> attend.', $answers[1]->feedback);
        $this->assertEquals(2, $answers[0]->feedbackformat); // FORMAT_MOODLE
        $this->assertEquals(1, $answers[1]->feedbackformat); // FORMAT_HTML
    }

    /**
     * Test placing quiz in a specific section by section number.
     */
    public function test_create_quiz_in_section(): void {
        // Create additional sections in the course
        $this->getDataGenerator()->create_course_section(['course' => $this->course->id, 'section' => 2]);
        $this->getDataGenerator()->create_course_section(['course' => $this->course->id, 'section' => 3]);

        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Section 2 Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'section' => '2',
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow);
        $modinfo = get_fast_modinfo($this->course);
        $section = $modinfo->get_section_info_by_id($cm->section);
        $this->assertEquals(2, $section->sectionnum);
    }

    /**
     * Test placing quiz in a specific section by section id.
     */
    public function test_create_quiz_in_section_by_id(): void {
        global $DB;
        // Create an additional section and get its id
        $section2 = $this->getDataGenerator()->create_course_section([
            'course' => $this->course->id,
            'section' => 2
        ]);

        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Section by ID Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
            'sectionid' => (string)$section2->id,
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow);
        $sections = \course_modinfo::get_array_of_activities($this->course);
        foreach ($sections as $section) {
            if ($section->sectionid == $cm->section) {
                $this->assertEquals(2, $section->section);
                return;
            }
        }
        $this->assertFalse(true, 'Section not found for the quiz');
    }

    /**
     * Test placing quiz at a selected section and a specific position within a section.
     */
    public function test_create_quiz_at_section_and_position(): void {
        global $DB;
        // Create a quiz first in section 0 (default)
        $this->runCsvTest([
            'module' => 'local_attendance_quiz',
            'name' => 'First Quiz',
            'timeopen' => '2026-01-06 10:20:00',
            'timeclose' => '2026-01-06 10:30:00',
        ]);

        // Create a quiz in section 1 (default position)
        $this->runCsvTest([
            'module' => 'local_attendance_quiz',
            'name' => 'Second Quiz',
            'section' => '1',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
        ]);

        // Create another quiz at position 1 (should be before the previous quiz)
        [$log, $quiz, $cm] = $this->runCsvTest([
            'module' => 'local_attendance_quiz',
            'name' => 'Third Quiz',
            'timeopen' => '2026-01-06 11:30:00',
            'timeclose' => '2026-01-06 11:40:00',
            'section' => '1',
            'section_pos' => '1',
        ]);

        // Get all modules in section 0
        $modinfo = get_fast_modinfo($this->course);
        $section0modules = $modinfo->get_sections()[0] ?? [];
        $this->assertCount(1, $section0modules);
        // Get all modules in section 1
        $modinfo = get_fast_modinfo($this->course);
        $section1modules = $modinfo->get_sections()[1] ?? [];
        $this->assertCount(2, $section1modules);
        // The second quiz should be at position 1 (first position)
        $this->assertEquals($cm->id, reset($section1modules));

        // Create a fourth quiz in a new section
        $csvrow3 = [
            'module' => 'local_attendance_quiz',
            'name' => 'Fourth Quiz',
            'timeopen' => '2026-02-06 10:30:00',
            'timeclose' => '2026-02-06 10:40:00',
            'section' => '2',
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow3);
        // Fetch module info again to get updated section info
        $modinfo = get_fast_modinfo($this->course);
        $section2modules = $modinfo->get_sections()[2] ?? [];
        $this->assertCount(1, $section2modules);
        // The fourth quiz should be at first position
        $this->assertEquals($cm->id, reset($section2modules));
    }

    /**
     * Test that course format is automatically set to 'topics' when creating quiz.
     */
    public function test_course_format_set_to_topics(): void {
        // Create a course with a different format
        $this->course = $this->getDataGenerator()->create_course([
            'shortname' => 'at-course2',
            'fullname' => 'Test At Course 2',
            'format' => 'weeks',
            'enablecompletion' => COMPLETION_DISABLED,
        ]);

        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Format Test Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
        ];
        $modquiz = new mod\quiz();
        $modquiz->useCourse($this->course)->setRow($csvrow)->create($csvrow);

        // Reload course and check format
        $this->course = get_course($this->course->id);
        $this->assertEquals('topics', $this->course->format);
    }

    /**
     * Test that completion tracking is automatically enabled when creating quiz.
     */
    public function test_completion_tracking_enabled(): void {
        // Create a course with completion disabled
        $this->course = $this->getDataGenerator()->create_course([
            'shortname' => 'at-course3',
            'fullname' => 'Test At Course 3',
            'enablecompletion' => COMPLETION_DISABLED,
        ]);

        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Completion Test Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
        ];
        $modquiz = new mod\quiz();
        $modquiz->useCourse($this->course)->setRow($csvrow)->create($csvrow);

        // Reload course and check completion
        $this->course = get_course($this->course->id);
        $this->assertEquals(COMPLETION_ENABLED, $this->course->enablecompletion);
    }

    /**
     * Test that quiz has correct grade settings.
     */
    public function test_quiz_grade_settings(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Grade Settings Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
        ];
        [$log, $quiz] = $this->runCsvTest($csvrow);
        $this->assertEquals(1.0, $quiz->grade);
        $this->assertEquals(1.0, $quiz->sumgrades);
    }

    /**
     * Test that question has correct defaultmark of 1.0.
     */
    public function test_question_defaultmark(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Defaultmark Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow);
        $questions = $this->getQuestions($quiz, $cm);
        $this->assertCount(1, $questions);
        $question = reset($questions);
        $this->assertEquals(1.0, $question->defaultmark);
    }

    /**
     * Test that question uses default language strings when not specified.
     */
    public function test_question_default_strings(): void {
        $csvrow = [
            'module' => 'local_attendance_quiz',
            'name' => 'Default Strings Quiz',
            'timeopen' => '2026-01-06 10:30:00',
            'timeclose' => '2026-01-06 10:40:00',
        ];
        [$log, $quiz, $cm] = $this->runCsvTest($csvrow);
        $questions = $this->getQuestions($quiz, $cm);
        $this->assertCount(1, $questions);
        $question = reset($questions);
        // Check that question name and text are not empty (they should have default values)
        $this->assertNotEmpty($question->name);
        $this->assertNotEmpty($question->questiontext);
        // Check that answers are 'Yes' and 'No' (default strings)
        $answers = array_values($question->options->answers);
        $this->assertCount(2, $answers);
        $this->assertEquals(get_string('yes'), $answers[0]->answer);
        $this->assertEquals(get_string('no'), $answers[1]->answer);
    }

    /**
     * Test that password does not contain confusing character pairs.
     */
    public function test_password_no_confusing_chars(): void {
        // Run multiple times to ensure the validation works
        for ($i = 0; $i < 10; $i++) {
            $csvrow = [
                'module' => 'local_attendance_quiz',
                'name' => "Confusing Chars Quiz $i",
                'timeopen' => '2026-01-06 10:30:00',
                'timeclose' => '2026-01-06 10:40:00',
                'local_attendance_quiz_passwordrule' => 'alnum',
            ];
            [$log, $quiz] = $this->runCsvTest($csvrow);
            // Check that password doesn't contain confusing pairs
            $confusingPairs = ['0O', 'O0', 'Il', 'lI', '1I', 'I1', '1l', 'l1', '5S', 'S5', '2Z', 'Z2'];
            foreach ($confusingPairs as $pair) {
                $this->assertStringNotContainsString($pair, $quiz->password);
            }
        }
    }
}
