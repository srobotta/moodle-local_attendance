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

namespace local_attendance\mod;

use mod_quiz\quiz_settings;
use local_attendance\modcreate;
use local_attendance\modcreate_interface;
use local_attendance\utils\utils;

/**
 * Custom implementation to create all necessary modules for an attendance course.
 * In the course itself, completion tracking must be enabled and several quizzes
 * will be created for attendance taking.
 *
 * @package     local_attendance
 * @copyright   2025 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz extends modcreate {
    /**
     * The course module instance after creation.
     * @var object|null
     */
    protected ?object $cm = null;

    /**
     * The password rule for the attendance quiz.
     * @var string|null
     */
    protected ?string $passwordRule = null;

    /**
     * Use an existing course where the activity will be created.
     * Also, make sure that the course is in topics format and completion tracking is enabled.
     * @param \stdClass $course
     * @return modcreate_interface
     */
    public function useCourse(\stdClass $course): modcreate_interface{
        parent::useCourse($course);
        $save = false;
        // When creating attendance quizzes, we require the course to be in topics format.
        if ($this->course->format !== 'topics') {
            $save = true;
            $this->course->format = 'topics';
        }
        // Also ensure that the completion tracking is enabled.
        if ($this->course->enablecompletion != COMPLETION_ENABLED) {
            $save = true;
            $this->course->enablecompletion = COMPLETION_ENABLED;
        }
        if ($save) {
            update_course($this->course);
        }
        return $this;
    }
    
    /**
     * Create a quiz for attendance in the course.
     * @param array $data
     * @return modcreate_interface
     */
    public function create(array $data): modcreate_interface {
        // If set, check the password rule that is set in the key 'local_attendance_attendancequiz_passwordrule'.
        $this->passwordRule = \array_key_exists('local_attendance_quiz_passwordrule', $data)
            ? $data['local_attendance_quiz_passwordrule'] : 'lower';
        // Unset module-specific data that is not for quiz.
        foreach (\array_keys($data) as $key) {
            if (str_starts_with($key, 'local_attendance_quiz_')) {
                unset($data[$key]);
            }
        }
        // Base module is quiz.
        $data['module'] = 'quiz';
        // Ensure that timeopen and timeclose are timestamps.
        foreach (['timeopen', 'timeclose'] as $key) {
            if (\array_key_exists($key, $data) === false) {
                throw new \moodle_exception('ex_missingfield', 'local_attendance', '', ['field' => $key]);
            }
            $data[$key] = utils::parseDateTime($key, $data);
        }
        // If the quiz password is not set, set a default one from a list.
        if (empty($data['quizpassword'])) {
            $data['quizpassword'] = $this->getPassword();
        }
        // There should be a short limit for the quiz to avoid long attempts.
        if (!\array_key_exists('timelimit', $data)) {
            $data['timelimit'] = 60; // 1 minute time limit.
        }

        // One question with 1 point is sufficient for attendance.
        $data['gradepass'] = 1.0;
        $data['grade'] = 1.0;
        $data['sumgrades'] = 1.0;
        // Completion tracking based on passing the quiz.
        $data['completion'] = COMPLETION_TRACKING_AUTOMATIC;
        $data['completionminattempts'] = 0;
        $data['completionusegrade'] = 1;
        $data['completionpassgrade'] = 0;
        $data['completionexpected'] = 0; // No expected date.

        // Create the module now.
        parent::create($data);
        // Get the course module instance.
        $this->cm = get_coursemodule_from_instance('quiz', $this->getId(), $this->course->id, false, MUST_EXIST);
        $this->moduleinfo->questionid = $this->addQuestion();
        return $this;
    }

    /**
     * Add a question to the created quiz.
     * @return int
     */
    protected function addQuestion(): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php'); 
        $quiz = $DB->get_record('quiz', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $quizobj = new quiz_settings($quiz, $this->cm, $this->course);
        $quizobj->get_structure()->check_can_be_edited();
        $questionid = $this->createQuestion();
        quiz_add_quiz_question($questionid, $quiz, 0);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();
        return $questionid;
    }

    /**
     * Create a multiple choice question for attendance.
     * @return int the created question id
     * @throws \moodle_exception
     */
    protected function createQuestion(): int {
        \core_question\local\bank\helper::require_plugin_enabled('qbank_editquestion');

        $context = \context_module::instance($this->cm->id);

        // Use question bank to create the question in the default category for the qbank of the quiz.
        $category = question_get_default_category($context->id, true);
        // This is the main question object.
        $question = new \stdClass();
        $question->category = $category->id;
        $question->qtype = 'multichoice';
        $question->contextid = $category->contextid;
        // Check permissions to add question in the category.
        $categorycontext = \context::instance_by_id($category->contextid);
        if (!has_capability('moodle/question:add', $categorycontext)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'create question');
        }
        // This is the data that would come from the form when writing the question. Here the
        // data is set up as we need it to create a multichoice question for attendance with
        // a yes and no option with yes being the correct answer. Some of the data can be set
        // from the CSV import.
        $questiondata = new \stdClass();
        $questiondata->name = $this->row['local_attendance_quiz_questionname'] 
            ?? get_string('col_questionname', 'local_attendance');
        $questiondata->questiontext = $this->getTextAndFormat(
            'local_attendance_quiz_questiontext',
            get_string('col_questiontext', 'local_attendance')
        );
        $questiondata->generalfeedback = $this->getTextAndFormat(
            'local_attendance_quiz_generalfeedback',
            get_string('col_generalfeedback', 'local_attendance')
        );
        $questiondata->correctfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $questiondata->partiallycorrectfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $questiondata->incorrectfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $questiondata->defaultmark = 1.0;
        $questiondata->answernumbering = 'none';
        $questiondata->answer = [
            $this->getTextAndFormat('local_attendance_quiz_answer_yes', get_string('yes')),
            $this->getTextAndFormat('local_attendance_quiz_answer_no', get_string('no')),
        ];
        $questiondata->fraction = [
            1.0,
            0,
        ];
        $questiondata->feedback = [
            $this->getTextAndFormat('local_attendance_quiz_feedback_yes', ''),
            $this->getTextAndFormat('local_attendance_quiz_feedback_no', ''),
        ];
        $questiondata->single = 1;
        $questiondata->shuffleanswers = 0;
        $questiondata->hidden = 0;
        $questiondata->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $questiondata->category = $category->id . ',' . $category->contextid;
        // Get the question type object and save the question.
        $qtypeobj = \question_bank::get_qtype($question->qtype);
        $storedQuestion = $qtypeobj->save_question($question, $questiondata);
        return $storedQuestion->id;
    }

    /**
     * Create a password based on the given password rule. That is:
     * generating a random password with letters only, alphanumeric, all characters or
     * picking a word from a wordlist.
     * @return string the generated password
     * @throws \Exception
     */
    protected function getPassword(): string {
        // Simple generated passwords.
        if (\in_array($this->passwordRule, ['lower', 'alpha' , 'alnum', 'all'])) {
            $length = 6;
            $charset = 'abcdefghijklmnopqrstuvwxyz';
            $password = '';
            if ($this->passwordRule !== 'lower') {
                $charset .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            }
            if ($this->passwordRule === 'alnum' || $this->passwordRule === 'all') {
                $charset .= '0123456789';
                if ($this->passwordRule === 'all') {
                    $charset .= '!@#$%&*()_+-={}[]|:;<>,.?/';
                }
            }
            $max = strlen($charset) - 1;
            while (!$this->isvalidGeneratedPassword($password)) {
                $password = '';
                for ($i = 0; $i < $length; $i++) {
                    $password .= $charset[random_int(0, $max)];
                }
            }
            return $password;
        }
        // Sanitize the rule to avoid path traversal.
        $rule = preg_replace('/[^a-z_]/', '', $this->passwordRule);
        $ruleFile = __DIR__ . '/../../wordlist/' . $rule . '.csv';
        // Pick a random line from the wordlist file and use that as password.
        if (file_exists($ruleFile)) {
            $passwords = explode("\n", file_get_contents($ruleFile));
            if (empty($passwords) || count($passwords) === 1) {
                throw new \moodle_exception(
                    'ex_invalidpasswdrulecontent',
                    'local_attendance',
                    '',
                    ['file' => basename($ruleFile)]
                );
            }
            return trim($passwords[array_rand($passwords)]);
        }
        throw new \moodle_exception(
            'ex_invalidpasswdrule',
            'local_attendance',
            '',
            ['rule' => $this->passwordRule]
        );
    }

    /**
     * Check that the generated password does not contain confusing characters.
     * @param string $password
     * @return bool
     */
    protected function isvalidGeneratedPassword(string $password): bool {
        $notThese = ['0O', 'O0', 'Il', 'lI', '1I', 'I1', '1l', 'l1', '5S', 'S5', '2Z', 'Z2'];
        foreach ($notThese as $pair) {
            if (str_contains($password, $pair)) {
                return false;
            }
        }
        return strlen($password) > 0;
    }

    /**
     * Return the used password for the quiz.
     * @return string
     */
    public function getAdditionalData(): string
    {
        return get_string('password') . '=' . $this->moduleinfo->password;
    }
}