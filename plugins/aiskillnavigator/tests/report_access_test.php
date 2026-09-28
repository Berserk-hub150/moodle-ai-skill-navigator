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

use local_aiskillnavigator\service\report_access;
use local_aiskillnavigator\service\quiz_attempt_repository;
use local_aiskillnavigator\service\request_access;

/**
 * Course roles, separate groups and mutation permissions.
 *
 * @package local_aiskillnavigator
 * @copyright 2026 Luca Magrini
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aiskillnavigator\service\report_access
 * @covers \local_aiskillnavigator\service\request_access
 * @covers \local_aiskillnavigator\service\quiz_attempt_repository
 */
final class report_access_test extends \advanced_testcase {
    /**
     * Practice, diagnostic and tutor reports share the same group scope.
     */
    public function test_all_student_reports_obey_separate_groups(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $teacher = $generator->create_user();
        $first = $generator->create_user();
        $second = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($first->id, $course->id, 'student');
        $generator->enrol_user($second->id, $course->id, 'student');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $first->id]);
        foreach ([$first, $second] as $student) {
            $DB->insert_record('local_aiskillnavigator_attempt', (object)[
                'courseid' => $course->id, 'userid' => $student->id, 'topic' => 'Topic',
            ]);
            $DB->insert_record('local_aiskillnavigator_ass_att', (object)[
                'assessmentid' => 99, 'courseid' => $course->id, 'userid' => $student->id,
            ]);
            $DB->insert_record('local_aiskillnavigator_tutor_sig', (object)[
                'courseid' => $course->id, 'userid' => $student->id,
                'question' => $student->id === $first->id ? 'Visible question' : 'PRIVATE QUESTION',
            ]);
        }
        $this->setUser($teacher);
        $attempts = (new quiz_attempt_repository())->for_course($course->id);
        $this->assertCount(1, $attempts);
        $this->assertEquals($first->id, $attempts[0]->userid);
        $diagnostics = report_access::assessment_attempts($course->id, 99);
        $this->assertCount(1, $diagnostics);
        $this->assertEquals($first->id, reset($diagnostics)->userid);
        require_once(__DIR__ . '/../includes/tutor_signal_helper.php');
        $html = local_aiskillnavigator_tutor_signal_teacher_panel($course->id);
        $this->assertStringContainsString('Visible question', $html);
        $this->assertStringNotContainsString('PRIVATE QUESTION', $html);
        groups_remove_member($group, $teacher);
        $this->assertSame([], report_access::assessment_attempts($course->id, 99));
        $this->assertSame([], (new quiz_attempt_repository())->for_course($course->id));
        $this->setAdminUser();
        $this->assertCount(2, report_access::assessment_attempts($course->id, 99));
        $this->setUser($first);
        $this->assertSame([], report_access::assessment_attempts($course->id, 99));
        $this->assertSame([], (new quiz_attempt_repository())->for_course($course->id));
    }

    /**
     * A reporting-only teacher cannot mutate assessments.
     */
    public function test_read_only_teacher_cannot_write(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('local/aiskillnavigator:manageassessments', CAP_PROHIBIT, $roleid, $context->id);
        $this->setUser($teacher);
        $this->assertTrue(has_capability('local/aiskillnavigator:viewteacher', $context));
        $this->expectException(\required_capability_exception::class);
        request_access::require_write($context, 'local/aiskillnavigator:manageassessments');
    }

    /**
     * Even administrators must use a form to mutate state.
     */
    public function test_get_request_cannot_write(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $oldmethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        try {
            $this->expectException(\moodle_exception::class);
            request_access::require_write(\context_course::instance($course->id), 'local/aiskillnavigator:manageassessments');
        } finally {
            if ($oldmethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $oldmethod;
            }
        }
    }

    /**
     * Explicit student access remains valid when a person also holds a teacher role.
     */
    public function test_dual_role_can_use_granted_student_tools(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $generator->enrol_user($user->id, $course->id, 'teacher');
        $this->setUser($user);
        $context = \context_course::instance($course->id);
        $this->assertTrue(has_capability('local/aiskillnavigator:viewstudent', $context));
        $this->assertTrue(has_capability('local/aiskillnavigator:viewteacher', $context));
        require_once(__DIR__ . '/../includes/role_guard.php');
        local_aisn_require_student_area($context);
    }
}
