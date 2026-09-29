<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_aiskillnavigator;

use local_aiskillnavigator\service\practice_quiz;

/**
 * Server-held quiz integrity and submission isolation.
 *
 * @package local_aiskillnavigator
 * @copyright 2026 Luca Magrini
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aiskillnavigator\service\practice_quiz
 */
final class practice_quiz_test extends \advanced_testcase {
    /**
     * A model response suitable for practice.
     *
     * @return array Quiz fixture.
     */
    private function quiz(): array {
        return ['title' => 'Databases', 'topic' => 'Keys', 'difficulty' => 'medium', 'questions' => [
            ['question' => 'Choose the key', 'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 2],
        ]];
    }

    /**
     * Client-side answer keys cannot influence grading or duplicate a stored result.
     */
    public function test_server_grading_and_duplicate_submission(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();
        $token = practice_quiz::create($course->id, $this->quiz());
        $result = practice_quiz::submit($course->id, $token, [0, 'correct_index' => 0, 'score' => 100]);
        $this->assertSame(0, $result['score']);
        $repeat = practice_quiz::submit($course->id, $token, [2]);
        $this->assertSame($result, $repeat);
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_attempt'));
        $attempt = $DB->get_record('local_aiskillnavigator_attempt', ['id' => $result['attemptid']], '*', MUST_EXIST);
        $this->assertEquals(0, $attempt->percentage);
        $this->assertEquals($user->id, $attempt->userid);
        $this->assertEquals($course->id, $attempt->courseid);
        $token = practice_quiz::create($course->id, $this->quiz());
        $this->assertSame(1, practice_quiz::submit($course->id, $token, [2])['score']);
    }

    /**
     * Tokens are unavailable outside their issuing user.
     */
    public function test_other_user_cannot_submit(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $token = practice_quiz::create(42, $this->quiz());
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        practice_quiz::submit(42, $token, [2]);
    }

    /**
     * A valid token from another course is rejected.
     */
    public function test_other_course_cannot_submit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $token = practice_quiz::create(42, $this->quiz());
        $this->expectException(\moodle_exception::class);
        practice_quiz::submit(43, $token, [2]);
    }

    /**
     * Expired quizzes fail before any result is inserted.
     */
    public function test_expired_token(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $token = practice_quiz::create(42, $this->quiz());
        $SESSION->aiskillnavigatorquizzes[$token]['expires'] = time() - 1;
        $this->expectException(\moodle_exception::class);
        practice_quiz::get(42, $token);
    }

    /**
     * Sessions do not grow without bounds as new quizzes are generated.
     */
    public function test_quiz_eviction(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $first = practice_quiz::create(42, $this->quiz());
        for ($i = 0; $i < practice_quiz::MAX_QUIZZES; $i++) {
            practice_quiz::create(42, $this->quiz());
        }
        $this->assertCount(practice_quiz::MAX_QUIZZES, $SESSION->aiskillnavigatorquizzes);
        $this->assertArrayNotHasKey($first, $SESSION->aiskillnavigatorquizzes);
    }

    /**
     * Invalid or ambiguous model output must not silently become a graded quiz.
     */
    public function test_invalid_model_answers(): void {
        $quiz = $this->quiz();
        unset($quiz['questions'][0]['correct_index']);
        $this->assertNull(practice_quiz::normalise($quiz));
        foreach ([-1, 4, false, [], '1abc', 1.5] as $invalid) {
            $quiz['questions'][0]['correct_index'] = $invalid;
            $this->assertNull(practice_quiz::normalise($quiz));
        }
        $quiz = $this->quiz();
        $quiz['questions'][0]['options'][0] = ['unexpected' => 'array'];
        $this->assertNull(practice_quiz::normalise($quiz));
        $quiz = $this->quiz();
        unset($quiz['questions'][0]['correct_index']);
        $quiz['questions'][0]['answer'] = 'C';
        $this->assertSame(2, practice_quiz::normalise($quiz)['questions'][0]['correct_index']);
    }
}
