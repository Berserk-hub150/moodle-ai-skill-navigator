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

use local_aiskillnavigator\upgrade\table_names;

/**
 * Verify that the component-prefix migration preserves existing course data.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aiskillnavigator\upgrade\table_names
 */
final class table_names_test extends \advanced_testcase {
    /**
     * Moodle's real upgrade entry point preserves a populated legacy installation.
     */
    public function test_real_upgrade_from_legacy_release(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $materialid = $DB->insert_record('local_aiskillnavigator_material', (object)[
            'courseid' => 42, 'title' => 'Legacy', 'content' => 'Existing content', 'contenthash' => sha1('Existing content'),
        ]);
        $dbman = $DB->get_manager();
        foreach (table_names::MAP as $oldname => $newname) {
            $dbman->rename_table(new \xmldb_table($newname), $oldname);
        }
        set_config('version', 2026080600, 'local_aiskillnavigator');
        try {
            $this->assertTrue(xmldb_local_aiskillnavigator_upgrade(2026080600));
            $this->assertSame('Existing content', $DB->get_field('local_aiskillnavigator_material', 'content', ['id' => $materialid]));
            $this->assertEquals(2026092500, get_config('local_aiskillnavigator', 'version'));
        } finally {
            table_names::migrate();
        }
    }

    /**
     * Resume a partial migration without changing records, IDs or references.
     */
    public function test_migration_preserves_data_and_can_resume(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $materialid = $DB->insert_record('local_aiskillnavigator_material', (object)[
            'courseid' => $course->id,
            'userid' => get_admin()->id,
            'title' => 'Existing course material',
            'content' => 'Preserve the course content and its approval.',
            'contenthash' => sha1('Preserve the course content and its approval.'),
            'externalaiallowed' => 1,
            'aipolicy' => 'external_allowed',
        ]);
        $chunkid = $DB->insert_record('local_aiskillnavigator_chunk', (object)[
            'courseid' => $course->id,
            'materialid' => $materialid,
            'title' => 'Existing course material',
            'chunktext' => 'Preserve the course content and its approval.',
            'embeddingmodel' => '',
        ]);
        $material = $DB->get_record('local_aiskillnavigator_material', ['id' => $materialid], '*', MUST_EXIST);
        $chunk = $DB->get_record('local_aiskillnavigator_chunk', ['id' => $chunkid], '*', MUST_EXIST);
        $dbman = $DB->get_manager();
        foreach (table_names::MAP as $oldname => $newname) {
            $dbman->rename_table(new \xmldb_table($newname), $oldname);
        }
        // Simulate interruption after the first successful rename.
        $dbman->rename_table(new \xmldb_table('local_aiskillnav_material'), 'local_aiskillnavigator_material');

        table_names::migrate();

        foreach (table_names::MAP as $oldname => $newname) {
            $this->assertFalse($dbman->table_exists($oldname));
            $this->assertTrue($dbman->table_exists($newname));
        }
        $this->assertEquals($material, $DB->get_record('local_aiskillnavigator_material', ['id' => $materialid]));
        $this->assertEquals($chunk, $DB->get_record('local_aiskillnavigator_chunk', ['id' => $chunkid]));
        table_names::migrate();
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_material'));
        $this->assertEquals(1, $DB->count_records('local_aiskillnavigator_chunk'));
    }

    /**
     * Refuse conflicting old and new tables before renaming any data.
     */
    public function test_conflicting_destination_is_preserved(): void {
        global $DB;

        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        $legacy = new \xmldb_table('local_aiskillnav_material');
        $legacy->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $legacy->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($legacy);
        try {
            $this->expectException(\ddl_exception::class);
            table_names::migrate();
        } finally {
            $this->assertTrue($dbman->table_exists('local_aiskillnavigator_material'));
            $this->assertTrue($dbman->table_exists($legacy));
            $dbman->drop_table($legacy);
        }
    }
}
