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

use local_aiskillnavigator\task\sync_course;
use local_aiskillnavigator\service\embedding_service;

/**
 * Background synchronisation and safe index replacement.
 *
 * @package local_aiskillnavigator
 * @copyright 2026 Luca Magrini
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aiskillnavigator\task\sync_course
 * @covers \local_aiskillnavigator\service\embedding\embedding_indexer
 */
final class sync_course_test extends \advanced_testcase {
    /**
     * Queuing is idempotent and a worker imports and indexes a real Moodle page.
     */
    public function test_background_sync_and_duplicate_queue(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('provider', 'prototype', 'local_aiskillnavigator');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $page = $generator->create_module('page', [
            'course' => $course->id, 'name' => 'Database fundamentals', 'content' => '<p>Tables contain rows and columns.</p>',
        ]);
        $this->setUser($teacher);
        sync_course::queue($course->id, $teacher->id);
        sync_course::queue($course->id, $teacher->id);
        $this->assertEquals(1, $DB->count_records('task_adhoc', ['component' => 'local_aiskillnavigator']));
        $task = new sync_course();
        $task->set_custom_data(['courseid' => $course->id]);
        $task->set_userid($teacher->id);
        $this->expectOutputRegex('/Course material synchronisation completed:/');
        $task->execute();
        $material = $DB->get_record('local_aiskillnavigator_material', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals($page->cmid, $material->sourcecmid);
        $this->assertStringContainsString('Tables contain rows', $material->content);
        $this->assertGreaterThan(0, $DB->count_records('local_aiskillnavigator_chunk', ['materialid' => $material->id]));
        $task->execute();
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_material', ['courseid' => $course->id]));
    }

    /**
     * A queued task does not process materials after the teacher loses access.
     */
    public function test_revoked_permission_stops_worker(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $task = new sync_course();
        $task->set_custom_data(['courseid' => $course->id]);
        $task->set_userid($student->id);
        $this->expectOutputRegex('/permission has been revoked/');
        $task->execute();
        $this->assertEquals(0, $DB->count_records('local_aiskillnavigator_material'));
    }

    /**
     * A failed replacement insert rolls back to the previous searchable index.
     */
    public function test_failed_index_replacement_preserves_previous_chunks(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        set_config('provider', 'prototype', 'local_aiskillnavigator');
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('local_aiskillnavigator_material', (object)[
            'courseid' => $course->id, 'title' => 'Original title', 'content' => 'Original searchable content.',
            'contenthash' => sha1('Original searchable content.'),
        ]);
        $service = new embedding_service();
        $this->assertTrue($service->index_material($id)['success']);
        $original = $DB->get_records('local_aiskillnavigator_chunk', ['materialid' => $id]);
        try {
            $service->index_material($id, $course->id, str_repeat('x', 1000), 'Replacement content.');
            $this->fail('An oversized title must not be silently stored.');
        } catch (\dml_write_exception $e) {
            $this->assertEquals($original, $DB->get_records('local_aiskillnavigator_chunk', ['materialid' => $id]));
        }
    }
}
