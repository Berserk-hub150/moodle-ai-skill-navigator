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

use local_aiskillnavigator\service\quiz_attempt_repository;

/**
 * Exercise native quiz reporting against Moodle's database and access rules.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aiskillnavigator\service\quiz_attempt_repository
 */
final class quiz_attempt_repository_test extends \advanced_testcase {
    /**
     * Only scored, completed, non-preview attempts from this course are reported.
     */
    public function test_native_attempt_scope_and_grades(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $otherquiz = $generator->create_module('quiz', ['course' => $othercourse->id]);
        $expectedid = $this->create_native_attempt($quiz, $student);
        $this->create_native_attempt($quiz, $student, ['attempt' => 2, 'preview' => 1]);
        $this->create_native_attempt($quiz, $student, ['attempt' => 3, 'state' => 'inprogress']);
        $this->create_native_attempt($quiz, $student, ['attempt' => 4, 'sumgrades' => null]);
        $this->create_native_attempt($otherquiz, $student);

        $attempts = (new quiz_attempt_repository())->for_course($course->id);

        $this->assertCount(1, $attempts);
        $this->assertEquals($expectedid, $attempts[0]->id);
        $this->assertSame(75, $attempts[0]->percentage);
        $this->assertSame('Moodle quiz', $attempts[0]->source);
        $this->assertEquals($quiz->name, $attempts[0]->topic);
        $this->assertSame(0, $DB->count_records('local_aiskillnav_attempt'));
    }

    /**
     * Enrolment alone does not grant access to native quiz reports.
     */
    public function test_native_reports_require_reporting_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $this->create_native_attempt($quiz, $student);

        $this->setUser($student);
        $this->assertFalse(has_capability('mod/quiz:viewreports', \context_module::instance($quiz->cmid)));
        $this->assertSame([], (new quiz_attempt_repository())->for_course($course->id));
    }

    /**
     * A restricted teacher can report only on students in their own groups.
     */
    public function test_separate_groups_restrict_native_reports(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $teacher = $generator->create_user();
        $firststudent = $generator->create_user();
        $secondstudent = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($firststudent->id, $course->id, 'student');
        $generator->enrol_user($secondstudent->id, $course->id, 'student');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $coursecontext->id);
        $firstgroup = $generator->create_group(['courseid' => $course->id]);
        $secondgroup = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $firstgroup->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $firstgroup->id, 'userid' => $firststudent->id]);
        $generator->create_group_member(['groupid' => $secondgroup->id, 'userid' => $secondstudent->id]);
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $this->create_native_attempt($quiz, $firststudent);
        $this->create_native_attempt($quiz, $secondstudent);

        $this->setUser($teacher);
        $context = \context_module::instance($quiz->cmid);
        $this->assertTrue(has_capability('mod/quiz:viewreports', $context));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context));
        $attempts = (new quiz_attempt_repository())->for_course($course->id);
        $this->assertCount(1, $attempts);
        $this->assertEquals($firststudent->id, $attempts[0]->userid);

        groups_remove_member($firstgroup, $teacher);
        $this->assertSame([], (new quiz_attempt_repository())->for_course($course->id));
        $this->setAdminUser();
        $this->assertCount(2, (new quiz_attempt_repository())->for_course($course->id));
    }

    /**
     * Insert a native attempt with a valid question usage for the report fixture.
     *
     * @param \stdClass $quiz Generated quiz.
     * @param \stdClass $student Attempt owner.
     * @param array $overrides Attempt fields to override.
     * @return int Attempt ID.
     */
    private function create_native_attempt(\stdClass $quiz, \stdClass $student, array $overrides = []): int {
        global $DB;

        $DB->set_field('quiz', 'sumgrades', 10, ['id' => $quiz->id]);
        $usageid = $DB->insert_record('question_usages', (object)[
            'contextid' => \context_module::instance($quiz->cmid)->id,
            'component' => 'mod_quiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);
        return $DB->insert_record('quiz_attempts', (object)array_replace([
            'quiz' => $quiz->id,
            'userid' => $student->id,
            'attempt' => 1,
            'uniqueid' => $usageid,
            'layout' => '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'finished',
            'timestart' => time() - 60,
            'timefinish' => time(),
            'timemodified' => time(),
            'sumgrades' => 7.5,
        ], $overrides));
    }
}
