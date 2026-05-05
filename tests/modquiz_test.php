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
     * Test creating course with custom password rule and question text fields.
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
        $this->assertEquals(1.0, $question->defaultmark);
    }
}
