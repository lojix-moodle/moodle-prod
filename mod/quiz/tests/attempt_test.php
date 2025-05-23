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

namespace mod_quiz;

use core_question\local\bank\condition;
use core_question\local\bank\question_version_status;
use core_question_generator;
use mod_quiz_generator;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests for the quiz_attempt class.
 *
 * @package   mod_quiz
 * @category  test
 * @copyright 2014 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\quiz_attempt
 */
final class attempt_test extends \advanced_testcase {

    /**
     * Create quiz and attempt data with layout.
     *
     * @param string $layout layout to set. Like quiz attempt.layout. E.g. '1,2,0,3,4,0,'.
     * @param string $navmethod quiz navigation method (defaults to free)
     * @return quiz_attempt the new quiz_attempt object
     */
    protected function create_quiz_and_attempt_with_layout($layout, $navmethod = QUIZ_NAVMETHOD_FREE) {
        $this->resetAfterTest(true);

        // Make a user to do the quiz.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        // Make a quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id,
            'grade' => 100.0, 'sumgrades' => 2, 'navmethod' => $navmethod]);

        $quizobj = quiz_settings::create($quiz->id, $user->id);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $page = 1;
        foreach (explode(',', $layout) as $slot) {
            if ($slot == 0) {
                $page += 1;
                continue;
            }

            $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
            quiz_add_quiz_question($question->id, $quiz, $page);
        }

        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null, false, [], [], $user->id);
        return quiz_attempt::create($attempt->id);
    }

    public function test_attempt_url(): void {
        $attempt = $this->create_quiz_and_attempt_with_layout('1,2,0,3,4,0,5,6,0');

        $attemptid = $attempt->get_attempt()->id;
        $cmid = $attempt->get_cmid();
        $url = '/mod/quiz/attempt.php';
        $params = ['attempt' => $attemptid, 'cmid' => $cmid, 'page' => 2];

        $this->assertEquals(new \moodle_url($url, $params), $attempt->attempt_url(null, 2));

        $params['page'] = 1;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->attempt_url(3));

        $questionattempt = $attempt->get_question_attempt(4);
        $expecteanchor = $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url($url, $params, $expecteanchor), $attempt->attempt_url(4));

        $questionattempt = $attempt->get_question_attempt(3);
        $expecteanchor = '#' . $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url('#'), $attempt->attempt_url(null, 2, 2));
        $this->assertEquals(new \moodle_url($expecteanchor), $attempt->attempt_url(3, -1, 1));

        $questionattempt = $attempt->get_question_attempt(4);
        $expecteanchor = $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url(null, null, $expecteanchor, null), $attempt->attempt_url(4, -1, 1));

        // Summary page.
        $url = '/mod/quiz/summary.php';
        unset($params['page']);
        $this->assertEquals(new \moodle_url($url, $params), $attempt->summary_url());

        // Review page.
        $url = '/mod/quiz/review.php';
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url());

        $params['page'] = 1;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(3, -1, false));
        $this->assertEquals(new \moodle_url($url, $params, $expecteanchor), $attempt->review_url(4, -1, false));

        unset($params['page']);
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2, true));
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(1, -1, true));

        $params['page'] = 2;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2, false));
        unset($params['page']);

        $params['showall'] = 0;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 0, false));
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(1, -1, false));

        $params['page'] = 1;
        unset($params['showall']);
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(3, -1, false));

        $params['page'] = 2;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2));
        $this->assertEquals(new \moodle_url('#'), $attempt->review_url(null, -1, null, 0));

        $questionattempt = $attempt->get_question_attempt(3);
        $expecteanchor = '#' . $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url($expecteanchor), $attempt->review_url(3, -1, null, 0));

        $questionattempt = $attempt->get_question_attempt(4);
        $expecteanchor = '#' . $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url($expecteanchor), $attempt->review_url(4, -1, null, 0));
        $this->assertEquals(new \moodle_url('#'), $attempt->review_url(null, 2, true, 0));

        $questionattempt = $attempt->get_question_attempt(1);
        $expecteanchor = '#' . $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url($expecteanchor), $attempt->review_url(1, -1, true, 0));
        $this->assertEquals(new \moodle_url($expecteanchor), $attempt->review_url(1, -1, false, 0));
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2, false, 0));
        $this->assertEquals(new \moodle_url('#'), $attempt->review_url(null, 0, false, 0));

        $params['page'] = 1;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(3, -1, false, 0));

        // Setup another attempt.
        $attempt = $this->create_quiz_and_attempt_with_layout(
            '1,2,3,4,5,6,7,8,9,10,0,11,12,13,14,15,16,17,18,19,20,0,' .
            '21,22,23,24,25,26,27,28,29,30,0,31,32,33,34,35,36,37,38,39,40,0,' .
            '41,42,43,44,45,46,47,48,49,50,0,51,52,53,54,55,56,57,58,59,60,0');

        $attemptid = $attempt->get_attempt()->id;
        $cmid = $attempt->get_cmid();
        $params = ['attempt' => $attemptid, 'cmid' => $cmid];
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url());

        $params['page'] = 2;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2));

        $params['page'] = 1;
        unset($params['showall']);
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(11, -1, false));

        $questionattempt = $attempt->get_question_attempt(12);
        $expecteanchor = $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url($url, $params, $expecteanchor), $attempt->review_url(12, -1, false));

        $params['showall'] = 1;
        unset($params['page']);
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2, true));

        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(1, -1, true));
        $params['page'] = 2;
        unset($params['showall']);
        $this->assertEquals(new \moodle_url($url, $params),  $attempt->review_url(null, 2, false));
        unset($params['page']);
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 0, false));
        $params['page'] = 1;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(11, -1, false));
        $this->assertEquals(new \moodle_url($url, $params, $expecteanchor), $attempt->review_url(12, -1, false));
        $params['page'] = 2;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2));
        $this->assertEquals(new \moodle_url('#'), $attempt->review_url(null, -1, null, 0));

        $questionattempt = $attempt->get_question_attempt(3);
        $expecteanchor = $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url(null, null, $expecteanchor), $attempt->review_url(3, -1, null, 0));

        $questionattempt = $attempt->get_question_attempt(4);
        $expecteanchor = $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url(null, null, $expecteanchor), $attempt->review_url(4, -1, null, 0));

        $questionattempt = $attempt->get_question_attempt(1);
        $expecteanchor = '#' . $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url($expecteanchor), $attempt->review_url(1, -1, true, 0));
        $this->assertEquals(new \moodle_url('#'), $attempt->review_url(null, 2, true, 0));

        $params['page'] = 2;
        $questionattempt = $attempt->get_question_attempt(1);
        $expecteanchor = '#' . $questionattempt->get_outer_question_div_unique_id();
        $this->assertEquals(new \moodle_url($expecteanchor), $attempt->review_url(1, -1, false, 0));
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(null, 2, false, 0));
        $this->assertEquals(new \moodle_url('#'), $attempt->review_url(null, 0, false, 0));

        $params['page'] = 1;
        $this->assertEquals(new \moodle_url($url, $params), $attempt->review_url(11, -1, false, 0));
    }

    /**
     * Tests attempt page titles when all questions are on a single page.
     */
    public function test_attempt_titles_single(): void {
        $attempt = $this->create_quiz_and_attempt_with_layout('1,2,0');

        // Attempt page.
        $this->assertEquals('Quiz 1', $attempt->attempt_page_title(0));

        // Summary page.
        $this->assertEquals('Quiz 1: Attempt summary', $attempt->summary_page_title());

        // Review page.
        $this->assertEquals('Quiz 1: Attempt review', $attempt->review_page_title(0));
    }

    /**
     * Tests attempt page titles when questions are on multiple pages, but are reviewed on a single page.
     */
    public function test_attempt_titles_multiple_single(): void {
        $attempt = $this->create_quiz_and_attempt_with_layout('1,2,0,3,4,0,5,6,0');

        // Attempt page.
        $this->assertEquals('Quiz 1 (page 1 of 3)', $attempt->attempt_page_title(0));
        $this->assertEquals('Quiz 1 (page 2 of 3)', $attempt->attempt_page_title(1));
        $this->assertEquals('Quiz 1 (page 3 of 3)', $attempt->attempt_page_title(2));

        // Summary page.
        $this->assertEquals('Quiz 1: Attempt summary', $attempt->summary_page_title());

        // Review page.
        $this->assertEquals('Quiz 1: Attempt review', $attempt->review_page_title(0, true));
    }

    /**
     * Tests attempt page titles when questions are on multiple pages, and they are reviewed on multiple pages as well.
     */
    public function test_attempt_titles_multiple_multiple(): void {
        $attempt = $this->create_quiz_and_attempt_with_layout(
                '1,2,3,4,5,6,7,8,9,10,0,11,12,13,14,15,16,17,18,19,20,0,' .
                '21,22,23,24,25,26,27,28,29,30,0,31,32,33,34,35,36,37,38,39,40,0,' .
                '41,42,43,44,45,46,47,48,49,50,0,51,52,53,54,55,56,57,58,59,60,0');

        // Attempt page.
        $this->assertEquals('Quiz 1 (page 1 of 6)', $attempt->attempt_page_title(0));
        $this->assertEquals('Quiz 1 (page 2 of 6)', $attempt->attempt_page_title(1));
        $this->assertEquals('Quiz 1 (page 6 of 6)', $attempt->attempt_page_title(5));

        // Summary page.
        $this->assertEquals('Quiz 1: Attempt summary', $attempt->summary_page_title());

        // Review page.
        $this->assertEquals('Quiz 1: Attempt review (page 1 of 6)', $attempt->review_page_title(0));
        $this->assertEquals('Quiz 1: Attempt review (page 2 of 6)', $attempt->review_page_title(1));
        $this->assertEquals('Quiz 1: Attempt review (page 6 of 6)', $attempt->review_page_title(5));

        // When all questions are shown.
        $this->assertEquals('Quiz 1: Attempt review', $attempt->review_page_title(0, true));
        $this->assertEquals('Quiz 1: Attempt review', $attempt->review_page_title(1, true));
    }

    public function test_is_participant(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student', [], 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $quizobj = quiz_settings::create($quiz->id);

        // Login as student.
        $this->setUser($student);
        // Convert to a lesson object.
        $this->assertEquals(true, $quizobj->is_participant($student->id),
            'Student is enrolled, active and can participate');

        // Login as student2.
        $this->setUser($student2);
        $this->assertEquals(false, $quizobj->is_participant($student2->id),
            'Student is enrolled, suspended and can NOT participate');

        // Login as an admin.
        $this->setAdminUser();
        $this->assertEquals(false, $quizobj->is_participant($USER->id),
            'Admin is not enrolled and can NOT participate');

        $this->getDataGenerator()->enrol_user(2, $course->id);
        $this->assertEquals(true, $quizobj->is_participant($USER->id),
            'Admin is enrolled and can participate');

        $this->getDataGenerator()->enrol_user(2, $course->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->assertEquals(true, $quizobj->is_participant($USER->id),
            'Admin is enrolled, suspended and can participate');
    }

    /**
     * Test quiz_prepare_and_start_new_attempt function
     */
    public function test_quiz_prepare_and_start_new_attempt(): void {
        global $USER;
        $this->resetAfterTest();

        // Create course.
        $course = $this->getDataGenerator()->create_course();
        // Create students.
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        // Create question and add it to quiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 1);

        $quizobj = quiz_settings::create($quiz->id);

        // Login as student1.
        $this->setUser($student1);
        // Create attempt for student1.
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null, false, [], []);
        $this->assertEquals($student1->id, $attempt->userid);
        $this->assertEquals(0, $attempt->preview);

        // Login as student2.
        $this->setUser($student2);
        // Create attempt for student2.
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null, false, [], []);
        $this->assertEquals($student2->id, $attempt->userid);
        $this->assertEquals(0, $attempt->preview);

        // Login as admin.
        $this->setAdminUser();
        // Create attempt for student1.
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 2, null, false, [], [], $student1->id);
        $this->assertEquals($student1->id, $attempt->userid);
        $this->assertEquals(0, $attempt->preview);
        $student1attempt = $attempt; // Save for extra verification below.
        // Create attempt for student2.
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 2, null, false, [], [], $student2->id);
        $this->assertEquals($student2->id, $attempt->userid);
        $this->assertEquals(0, $attempt->preview);
        // Create attempt for user id that the same with current $USER->id.
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 2, null, false, [], [], $USER->id);
        $this->assertEquals($USER->id, $attempt->userid);
        $this->assertEquals(1, $attempt->preview);

        // Check that the userid stored in the first step is the user the attempt is for,
        // not the user who triggered the creation.
        $quba = question_engine::load_questions_usage_by_activity($student1attempt->uniqueid);
        $step = $quba->get_question_attempt(1)->get_step(0);
        $this->assertEquals($student1->id, $step->get_user_id());
    }

    /**
     * Test quiz_prepare_and_start_new_attempt function
     */
    public function test_quiz_prepare_and_start_new_attempt_random_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course.
        $course = $this->getDataGenerator()->create_course();
        // Create quiz.
        /** @var mod_quiz_generator $quizgenerator */
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);

        // Create question with 2 versions. V1 ready. V2 draft.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null,
                ['questiontext' => 'V1', 'category' => $category->id]);
        $questiongenerator->update_question($question, null,
                ['questiontext' => 'V2', 'status' => question_version_status::QUESTION_STATUS_DRAFT]);

        // Add a random question form that category.
        $filtercondition = [
            'filter' => [
                'category' => [
                    'jointype' => condition::JOINTYPE_DEFAULT,
                    'values' => [$category->id],
                    'filteroptions' => ['includesubcategories' => false],
                ],
            ],
        ];
        $quizobj = quiz_settings::create($quiz->id);
        $quizobj->get_structure()->add_random_questions(1, 1, $filtercondition);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        // Create an attempt.
        $quizobj = quiz_settings::create($quiz->id);
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null);
        $this->assertEquals(1, $attempt->preview);
    }

    /**
     * Test check_page_access function
     * @covers \quiz_attempt::check_page_access
     */
    public function test_check_page_access(): void {
        $timenow = time();

        // Free navigation.
        $attempt = $this->create_quiz_and_attempt_with_layout('1,0,2,0,3,0,4,0,5,0', QUIZ_NAVMETHOD_FREE);

        // Check access.
        $this->assertTrue($attempt->check_page_access(4));
        $this->assertTrue($attempt->check_page_access(3));
        $this->assertTrue($attempt->check_page_access(2));
        $this->assertTrue($attempt->check_page_access(1));
        $this->assertTrue($attempt->check_page_access(0));
        $this->assertTrue($attempt->check_page_access(2));

        // Access page 2.
        $attempt->set_currentpage(2);
        $attempt = quiz_attempt::create($attempt->get_attempt()->id);

        // Check access.
        $this->assertTrue($attempt->check_page_access(0));
        $this->assertTrue($attempt->check_page_access(1));
        $this->assertTrue($attempt->check_page_access(2));
        $this->assertTrue($attempt->check_page_access(3));
        $this->assertTrue($attempt->check_page_access(4));

        // Sequential navigation.
        $attempt = $this->create_quiz_and_attempt_with_layout('1,0,2,0,3,0,4,0,5,0', QUIZ_NAVMETHOD_SEQ);

        // Check access.
        $this->assertTrue($attempt->check_page_access(0));
        $this->assertTrue($attempt->check_page_access(1));
        $this->assertFalse($attempt->check_page_access(2));
        $this->assertFalse($attempt->check_page_access(3));
        $this->assertFalse($attempt->check_page_access(4));

        // Access page 1.
        $attempt->set_currentpage(1);
        $attempt = quiz_attempt::create($attempt->get_attempt()->id);
        $this->assertTrue($attempt->check_page_access(1));

        // Access page 2.
        $attempt->set_currentpage(2);
        $attempt = quiz_attempt::create($attempt->get_attempt()->id);
        $this->assertTrue($attempt->check_page_access(2));

        $this->assertTrue($attempt->check_page_access(3));
        $this->assertFalse($attempt->check_page_access(4));
        $this->assertFalse($attempt->check_page_access(1));
    }

    /**
     * Starting a new attempt with a question in draft status should throw an exception.
     *
     * @covers ::quiz_start_new_attempt()
     * @return void
     */
    public function test_start_new_attempt_with_draft(): void {
        $this->resetAfterTest();

        // Create course.
        $course = $this->getDataGenerator()->create_course();
        // Create students.
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        // Create question and add it to quiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null,
                ['category' => $cat->id, 'status' => question_version_status::QUESTION_STATUS_DRAFT]);
        quiz_add_quiz_question($question->id, $quiz, 1);

        $quizobj = quiz_settings::create($quiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $student1->id);

        $this->expectExceptionObject(new \moodle_exception('questiondraftonly', 'mod_quiz', '', $question->name));
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
    }

    /**
     * Starting a new attempt built on last with a question in draft status should throw an exception.
     *
     * @covers ::quiz_start_attempt_built_on_last()
     * @return void
     */
    public function test_quiz_start_attempt_built_on_last_with_draft(): void {
        global $DB;
        $this->resetAfterTest();

        // Create course.
        $course = $this->getDataGenerator()->create_course();
        // Create students.
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        // Create question and add it to quiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz, 1);

        $quizobj = quiz_settings::create($quiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $student1->id);
        $attempt = quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        $attempt = quiz_attempt_save_started($quizobj, $quba, $attempt);
        $DB->set_field('question_versions', 'status', question_version_status::QUESTION_STATUS_DRAFT,
                ['questionid' => $question->id]);
        // We need to reset the cache since the question has been edited by changing its status to draft.
        \question_bank::notify_question_edited($question->id);
        $quizobj = quiz_settings::create($quiz->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $newattempt = quiz_create_attempt($quizobj, 2, $attempt, time(), false, $student1->id);

        $this->expectExceptionObject(new \moodle_exception('questiondraftonly', 'mod_quiz', '', $question->name));
        quiz_start_attempt_built_on_last($quba, $newattempt, $attempt);
    }

    public function test_get_grade_item_totals(): void {
        $attemptobj = $this->create_quiz_and_attempt_with_layout('1,2,3,0');
        /** @var mod_quiz_generator $quizgenerator */
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        // Set up some section grades.
        $listeninggrade = $quizgenerator->create_grade_item(['quizid' => $attemptobj->get_quizid(), 'name' => 'Listening']);
        $readinggrade = $quizgenerator->create_grade_item(['quizid' => $attemptobj->get_quizid(), 'name' => 'Reading']);
        $structure = $attemptobj->get_quizobj()->get_structure();
        $structure->update_slot_grade_item($structure->get_slot_by_number(1), $listeninggrade->id);
        $structure->update_slot_grade_item($structure->get_slot_by_number(2), $listeninggrade->id);
        $structure->update_slot_grade_item($structure->get_slot_by_number(3), $readinggrade->id);

        // Reload the attempt and verify.
        $attemptobj = quiz_attempt::create($attemptobj->get_attemptid());
        $grades = $attemptobj->get_grade_item_totals();

        // All grades zero because student has not done the quiz yet, but this is a sufficent test.
        $this->assertEquals('Listening', $grades[$listeninggrade->id]->name);
        $this->assertEquals(0, $grades[$listeninggrade->id]->grade);
        $this->assertEquals(2, $grades[$listeninggrade->id]->maxgrade);
        $this->assertEquals('Reading', $grades[$readinggrade->id]->name);
        $this->assertEquals(0, $grades[$readinggrade->id]->grade);
        $this->assertEquals(1, $grades[$readinggrade->id]->maxgrade);
    }

    /**
     * When creating a new quiz attempt, question attempts should be created with the first step's timecreated set to null.
     *
     * When the question attempt is rendered, it should be set to the current time.
     *
     * @return void
     * @throws \coding_exception
     * @covers ::quiz_start_new_attempt
     */
    public function test_step_timecreated_unset_when_starting_quiz_attempt(): void {
        $attempt = $this->create_quiz_and_attempt_with_layout('1');
        $questionattempt = $attempt->get_question_attempt(1);
        $this->assertEquals(\question_attempt_step::TIMECREATED_ON_FIRST_RENDER, $questionattempt->get_step(0)->get_timecreated());
        $questionattempt->render(new \question_display_options(), 1);
        $this->assertEqualsWithDelta(time(), $questionattempt->get_step(0)->get_timecreated(), 1);
    }
}
