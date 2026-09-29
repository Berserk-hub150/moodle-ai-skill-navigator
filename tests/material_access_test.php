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

use local_aiskillnavigator\service\material_access;
use local_aiskillnavigator\service\knowledge_graph_reader;
use local_aiskillnavigator\service\embedding_service;

/**
 * Restricted sources must never enter student prompts, graph evidence or RAG.
 *
 * @package local_aiskillnavigator
 * @copyright 2026 Luca Magrini
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aiskillnavigator\service\material_access
 * @covers \local_aiskillnavigator\service\knowledge_graph_reader
 * @covers \local_aiskillnavigator\service\embedding\embedding_searcher
 */
final class material_access_test extends \advanced_testcase {
    /**
     * Create a synchronised material without running OCR or a provider.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $page Page activity.
     * @return \stdClass Stored material.
     */
    private function material(\stdClass $course, \stdClass $page): \stdClass {
        global $DB;
        $record = (object)[
            'courseid' => $course->id, 'userid' => 0, 'title' => $page->name,
            'materialtype' => 'course_resource', 'content' => 'database database database',
            'sourcecmid' => $page->cmid, 'externalaiallowed' => 1, 'contenthash' => sha1('database'),
        ];
        $record->id = $DB->insert_record('local_aiskillnavigator_material', $record);
        return $record;
    }

    /**
     * Hidden activities, hidden sections, future dates and other groups are inaccessible.
     */
    public function test_activity_restrictions_and_cross_course_sources(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $visible = $generator->create_module('page', ['course' => $course->id]);
        $hidden = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $future = $generator->create_module('page', ['course' => $course->id, 'availability' => json_encode([
            'op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]], 'showc' => [true],
        ])]);
        $grouped = $generator->create_module('page', ['course' => $course->id, 'availability' => json_encode([
            'op' => '&', 'c' => [['type' => 'group', 'id' => (int)$group->id]], 'showc' => [true],
        ])]);
        $other = $generator->create_module('page', ['course' => $othercourse->id]);
        $records = array_map(fn($page) => $this->material($course, $page), [$visible, $hidden, $future, $grouped, $other]);
        $this->setUser($student);
        $this->assertTrue(material_access::can_read($records[0]));
        foreach (array_slice($records, 1) as $record) {
            $this->assertFalse(material_access::can_read($record));
        }
        require_once(__DIR__ . '/../includes/material_source_helper.php');
        $readable = local_aiskillnavigator_material_source_get_readable_materials($course->id);
        $this->assertSame([(int)$records[0]->id], array_keys($readable));
        $this->setAdminUser();
        $this->assertTrue(material_access::can_read($records[1]));
    }

    /**
     * Top-k ranking excludes denied chunks first and respects revoked external approval.
     */
    public function test_rag_filters_before_ranking(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('provider', 'prototype', 'local_aiskillnavigator');
        set_config('embeddingprovider', 'keyword', 'local_aiskillnavigator');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $hidden = $this->material($course, $generator->create_module('page', ['course' => $course->id, 'visible' => 0]));
        $visible = $this->material($course, $generator->create_module('page', ['course' => $course->id]));
        foreach ([$hidden, $visible] as $material) {
            $DB->insert_record('local_aiskillnavigator_chunk', (object)[
                'materialid' => $material->id, 'courseid' => $course->id, 'title' => $material->title,
                'chunktext' => 'database', 'embedding' => '[]', 'embeddingmodel' => 'keyword',
            ]);
        }
        $this->setUser($student);
        $results = (new embedding_service())->search('database', $course->id, 1);
        $this->assertCount(1, $results);
        $this->assertEquals($visible->id, $results[0]->materialid);
        $this->assertSame([], (new embedding_service())->search('database', $course->id, 1, $hidden->id));
        set_config('provider', 'openai', 'local_aiskillnavigator');
        set_config('externalaiapproved', 0, 'local_aiskillnavigator');
        $this->assertSame([], (new embedding_service())->search('database', $course->id, 1));
    }

    /**
     * A shared concept must not reuse evidence derived from a restricted source.
     */
    public function test_graph_uses_only_permitted_evidence(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('provider', 'prototype', 'local_aiskillnavigator');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $hidden = $this->material($course, $generator->create_module('page', ['course' => $course->id, 'visible' => 0]));
        $visible = $this->material($course, $generator->create_module('page', ['course' => $course->id]));
        $conceptid = $DB->insert_record('local_aiskillnavigator_kg_concept', (object)[
            'courseid' => $course->id, 'name' => 'Databases', 'normalizedname' => 'databases',
            'description' => 'SECRET global description',
        ]);
        foreach ([$hidden->id => 'SECRET restricted evidence', $visible->id => 'Permitted evidence'] as $id => $evidence) {
            $DB->insert_record('local_aiskillnavigator_kg_source', (object)[
                'conceptid' => $conceptid, 'materialid' => $id, 'evidence' => $evidence,
            ]);
        }
        $this->setUser($student);
        $graph = knowledge_graph_reader::read($course->id);
        $this->assertCount(1, $graph['nodes']);
        $this->assertSame('Permitted evidence', $graph['nodes'][0]['description']);
        $this->assertSame(1, $graph['nodes'][0]['sources']);
        require_once(__DIR__ . '/../includes/knowledge_graph_helper.php');
        $prompt = local_aisn_kg_prompt_context($course->id);
        $this->assertStringContainsString('Permitted evidence', $prompt);
        $this->assertStringNotContainsString('SECRET', $prompt);
        set_config('provider', 'openai', 'local_aiskillnavigator');
        set_config('externalaiapproved', 0, 'local_aiskillnavigator');
        $this->assertSame('', local_aisn_kg_prompt_context($course->id));
    }
}
