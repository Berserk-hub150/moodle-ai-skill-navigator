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

use local_aiskillnavigator\privacy\provider;
use core_privacy\local\request\approved_contextlist;

/**
 * Course lifecycle and privacy isolation on real Moodle storage.
 *
 * @package local_aiskillnavigator
 * @copyright 2026 Luca Magrini
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aiskillnavigator\privacy\provider
 * @covers \local_aiskillnavigator\observer
 */
final class lifecycle_test extends \advanced_testcase {
    /**
     * A privacy deletion removes derived evidence but preserves another user's course data.
     */
    public function test_privacy_removes_stale_shared_evidence(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $first = $generator->create_user();
        $second = $generator->create_user();
        $context = \context_course::instance($course->id);
        $conceptid = $DB->insert_record('local_aiskillnavigator_kg_concept', (object)[
            'courseid' => $course->id, 'name' => 'Shared', 'normalizedname' => 'shared', 'description' => 'PERSONAL evidence',
        ]);
        foreach ([$first, $second] as $user) {
            $materialid = $DB->insert_record('local_aiskillnavigator_material', (object)[
                'courseid' => $course->id, 'userid' => $user->id, 'title' => 'Source',
                'content' => 'Evidence', 'contenthash' => sha1('Evidence'),
            ]);
            $DB->insert_record('local_aiskillnavigator_kg_source', (object)[
                'conceptid' => $conceptid, 'materialid' => $materialid, 'evidence' => 'Evidence',
            ]);
            $DB->insert_record('local_aiskillnavigator_chunk', (object)[
                'courseid' => $course->id, 'materialid' => $materialid, 'title' => 'Source',
                'chunktext' => 'Evidence', 'embeddingmodel' => '',
            ]);
        }
        provider::delete_data_for_user(new approved_contextlist($first, 'local_aiskillnavigator', [$context->id]));
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_material'));
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_chunk'));
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_kg_source'));
        $this->assertSame('', $DB->get_field('local_aiskillnavigator_kg_concept', 'description', ['id' => $conceptid]));
        $this->assertEquals($second->id, $DB->get_field('local_aiskillnavigator_material', 'userid', ['courseid' => $course->id]));
    }

    /**
     * Deleting a course purges orphaned chunks and graph sources only in that course.
     */
    public function test_course_deletion_cleans_up_derived_data(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        foreach ([$course, $other] as $item) {
            $conceptid = $DB->insert_record('local_aiskillnavigator_kg_concept', (object)[
                'courseid' => $item->id, 'name' => 'Orphan', 'normalizedname' => 'orphan',
            ]);
            $DB->insert_record('local_aiskillnavigator_kg_source', (object)[
                'conceptid' => $conceptid, 'materialid' => 99999, 'evidence' => 'Orphaned source',
            ]);
            $DB->insert_record('local_aiskillnavigator_chunk', (object)[
                'courseid' => $item->id, 'materialid' => 99999, 'title' => 'Source',
                'chunktext' => 'Orphaned content', 'embeddingmodel' => '',
            ]);
        }
        set_config('document_ocr_enabled_course_' . $course->id, '1', 'local_aiskillnavigator');
        delete_course($course, false);
        $this->assertEquals(0, $DB->count_records('local_aiskillnavigator_chunk', ['courseid' => $course->id]));
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_chunk', ['courseid' => $other->id]));
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_kg_source'));
        $this->assertFalse(get_config('local_aiskillnavigator', 'document_ocr_enabled_course_' . $course->id));
    }

    /**
     * A course-level OCR switch preserves administrator settings and other courses.
     */
    public function test_ocr_setting_is_course_scoped(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        set_config('mistral_ocr_enabled', '0', 'local_aiskillnavigator');
        set_config('mistral_ocr_timeout', '75', 'local_aiskillnavigator');
        require_once(__DIR__ . '/../includes/document_ocr_toggle_helper.php');
        local_aisn_document_ocr_set_course_enabled($course->id, true);
        $this->assertTrue(local_aisn_document_ocr_course_enabled($course->id));
        $this->assertFalse(local_aisn_document_ocr_course_enabled($other->id));
        $this->assertSame('0', get_config('local_aiskillnavigator', 'mistral_ocr_enabled'));
        $this->assertSame('75', get_config('local_aiskillnavigator', 'mistral_ocr_timeout'));
        $this->expectException(\required_capability_exception::class);
        local_aisn_document_ocr_set_course_enabled($other->id, true);
    }
}
