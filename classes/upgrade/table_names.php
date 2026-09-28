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

namespace local_aiskillnavigator\upgrade;

/**
 * Migrate legacy table names to the full Moodle component prefix.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class table_names {
    /** @var array Legacy names mapped to the component-prefixed names. */
    public const MAP = [
        'local_aiskillnav_material' => 'local_aiskillnavigator_material',
        'local_aiskillnav_attempt' => 'local_aiskillnavigator_attempt',
        'local_aiskillnav_chunk' => 'local_aiskillnavigator_chunk',
        'local_aiskillnav_assessment' => 'local_aiskillnavigator_assessment',
        'local_aiskillnav_ass_att' => 'local_aiskillnavigator_ass_att',
        'local_aiskillnav_sim' => 'local_aiskillnavigator_sim',
        'local_aiskillnav_tutor_sig' => 'local_aiskillnavigator_tutor_sig',
        'local_aisn_kg_concept' => 'local_aiskillnavigator_kg_concept',
        'local_aisn_kg_source' => 'local_aiskillnavigator_kg_source',
        'local_aisn_kg_relation' => 'local_aiskillnavigator_kg_relation',
    ];

    /**
     * Rename tables in place, preserving records, IDs, indexes and relationships.
     *
     * A partially completed migration can resume. Conflicting destination tables
     * stop the upgrade before any rename, instead of overwriting either dataset.
     */
    public static function migrate(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach (self::MAP as $oldname => $newname) {
            if ($dbman->table_exists($oldname) && $dbman->table_exists($newname)) {
                throw new \ddl_exception('ddltablealreadyexists', $newname);
            }
        }
        foreach (self::MAP as $oldname => $newname) {
            if ($dbman->table_exists($oldname)) {
                $dbman->rename_table(new \xmldb_table($oldname), $newname);
            }
        }
    }
}
