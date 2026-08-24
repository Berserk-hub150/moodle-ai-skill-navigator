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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Xmldb local aiskillnavigator upgrade helper.
 */
function xmldb_local_aiskillnavigator_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026051001) {
        $table = new xmldb_table('local_aiskillnav_material');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('materialtype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'text');
            $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_aiskillnav_attempt');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('topic', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('difficulty', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'medium');
            $table->add_field('score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('maxscore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('percentage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('quizjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('answersjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('topic_ix', XMLDB_INDEX_NOTUNIQUE, ['topic']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051001, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026051002) {
        upgrade_plugin_savepoint(true, 2026051002, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026051003) {
        upgrade_plugin_savepoint(true, 2026051003, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026051401) {
        $table = new xmldb_table('local_aiskillnav_chunk');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('chunkindex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('chunktext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('embedding', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('embeddingmodel', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('materialid_ix', XMLDB_INDEX_NOTUNIQUE, ['materialid']);
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051401, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026051921) {
        $table = new xmldb_table('local_aiskillnav_assessment');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('assessmenttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pre');
            $table->add_field('focus', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('difficulty', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'medium');
            $table->add_field('quizjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('sourcemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all');
            $table->add_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmenttype']);
            $table->add_index('visible_ix', XMLDB_INDEX_NOTUNIQUE, ['visible']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_aiskillnav_ass_att');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('assessmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('maxscore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('percentage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('answersjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('assessmentid_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmentid']);
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051921, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026053001) {
        $table = new xmldb_table('local_aiskillnav_material');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('externalaiallowed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('aipolicy', XMLDB_TYPE_TEXT, null, null, null, null, null, 'externalaiallowed');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $simtable = new xmldb_table('local_aiskillnav_sim');
        if (!$dbman->table_exists($simtable)) {
            $simtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $simtable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $simtable->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $simtable->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $simtable->add_field('url', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $simtable->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $simtable->add_field('source', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'manual');
            $simtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $simtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $simtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $simtable->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $simtable->add_index('course_source_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'source']);
            $simtable->add_index('material_idx', XMLDB_INDEX_NOTUNIQUE, ['materialid']);
            $dbman->create_table($simtable);
        }

        upgrade_plugin_savepoint(true, 2026053001, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026053002) {
        $table = new xmldb_table('local_aiskillnav_material');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('externalaiallowed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('aipolicy', XMLDB_TYPE_TEXT, null, null, null, null, null, 'externalaiallowed');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026053002, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026060101) {
        $table = new xmldb_table('local_aiskillnav_material');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('sourcecmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'materialtype');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '', 'sourcecmid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('externalaiallowed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            // phpcs:ignore moodle.Files.LineLength
            $field = new xmldb_field('aipolicy', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'local_only', 'externalaiallowed');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $index = new xmldb_index('materialtype_ix', XMLDB_INDEX_NOTUNIQUE, ['materialtype']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new xmldb_index('sourcecmid_ix', XMLDB_INDEX_NOTUNIQUE, ['sourcecmid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new xmldb_index('material_identity_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'materialtype', 'sourcecmid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $index = new xmldb_index('contenthash_ix', XMLDB_INDEX_NOTUNIQUE, ['contenthash']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $DB->execute("UPDATE {local_aiskillnav_material}
                             SET aipolicy = CASE WHEN externalaiallowed = 1 THEN 'external_allowed' ELSE 'local_only' END
                           WHERE aipolicy IS NULL OR aipolicy = ''");

            $DB->execute("UPDATE {local_aiskillnav_material}
                             SET contenthash = ''
                           WHERE contenthash IS NULL");
        }

        $table = new xmldb_table('local_aiskillnav_chunk');
        if ($dbman->table_exists($table)) {
            $index = new xmldb_index('chunk_identity_ix', XMLDB_INDEX_NOTUNIQUE, ['materialid', 'chunkindex']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_aiskillnav_assessment');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('sourcemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all', 'quizjson');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sourcemode');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026060101, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026060401) {
        $table = new xmldb_table('local_aiskillnav_sim');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('topic', XMLDB_TYPE_CHAR, '255', null, null, null, '');
            $table->add_field('level', XMLDB_TYPE_CHAR, '40', null, null, null, '');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('source', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'ai_generated');
            $table->add_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('materialtitles', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('resulttext', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $table->add_index('course_source_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'source']);
            $table->add_index('course_user_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
            $table->add_index('material_idx', XMLDB_INDEX_NOTUNIQUE, ['materialid']);

            $dbman->create_table($table);
        } else {
            $fields = [
                new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid'),
                new xmldb_field('materialid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid'),
                new xmldb_field('topic', XMLDB_TYPE_CHAR, '255', null, null, null, '', 'materialid'),
                new xmldb_field('level', XMLDB_TYPE_CHAR, '40', null, null, null, '', 'topic'),
                new xmldb_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'source'),
                new xmldb_field('materialtitles', XMLDB_TYPE_TEXT, null, null, null, null, null, 'materialids'),
                new xmldb_field('resulttext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'materialtitles'),
            ];

            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }

            $index = new xmldb_index('course_user_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            $DB->execute("UPDATE {local_aiskillnav_sim}
                             SET resulttext = description
                           WHERE (resulttext IS NULL OR resulttext = '')
                             AND description IS NOT NULL");
        }

        upgrade_plugin_savepoint(true, 2026060401, 'local', 'aiskillnavigator');
    }
    if ($oldversion < 2026060702) {
        $table = new xmldb_table('local_aiskillnav_tutor_sig');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('question', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('sourcemode', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'none');
            $table->add_field('materials', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('skill', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, 'General');
            $table->add_field('requesttype', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, 'question');
            $table->add_field('difficulty', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'medium');
            $table->add_field('answerpreview', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('skill_idx', XMLDB_INDEX_NOTUNIQUE, ['skill']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060702, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026061201) {
        $concept = new xmldb_table('local_aisn_kg_concept');

        if (!$dbman->table_exists($concept)) {
            $concept->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $concept->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $concept->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $concept->add_field('normalizedname', XMLDB_TYPE_CHAR, '191', null, XMLDB_NOTNULL, null, '');
            $concept->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $concept->add_field('confidence', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
            $concept->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $concept->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $concept->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $concept->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $concept->add_index('norm_ix', XMLDB_INDEX_NOTUNIQUE, ['normalizedname']);
            $dbman->create_table($concept);
        }

        $source = new xmldb_table('local_aisn_kg_source');

        if (!$dbman->table_exists($source)) {
            $source->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $source->add_field('conceptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $source->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $source->add_field('chunkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $source->add_field('evidence', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $source->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $source->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $source->add_index('concept_ix', XMLDB_INDEX_NOTUNIQUE, ['conceptid']);
            $source->add_index('material_ix', XMLDB_INDEX_NOTUNIQUE, ['materialid']);
            $source->add_index('chunk_ix', XMLDB_INDEX_NOTUNIQUE, ['chunkid']);
            $dbman->create_table($source);
        }

        $relation = new xmldb_table('local_aisn_kg_relation');

        if (!$dbman->table_exists($relation)) {
            $relation->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $relation->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $relation->add_field('sourceconceptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $relation->add_field('targetconceptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $relation->add_field('relationtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'related_to');
            $relation->add_field('confidence', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
            $relation->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $relation->add_field('chunkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $relation->add_field('evidence', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $relation->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $relation->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $relation->add_index('course_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $relation->add_index('source_ix', XMLDB_INDEX_NOTUNIQUE, ['sourceconceptid']);
            $relation->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targetconceptid']);
            $relation->add_index('material_ix', XMLDB_INDEX_NOTUNIQUE, ['materialid']);
            $dbman->create_table($relation);
        }

        upgrade_plugin_savepoint(true, 2026061201, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026080600) {
        $table = new xmldb_table('local_aiskillnav_material');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field(
                'aipolicy',
                XMLDB_TYPE_CHAR,
                '32',
                null,
                XMLDB_NOTNULL,
                null,
                'local_only',
                'externalaiallowed'
            );

            if ($dbman->field_exists($table, $field)) {
                $DB->execute("UPDATE {local_aiskillnav_material}
                                SET aipolicy = CASE
                                    WHEN externalaiallowed = 1 THEN 'external_allowed'
                                    ELSE 'local_only'
                                END
                              WHERE aipolicy IS NULL OR aipolicy = ''");
                $dbman->change_field_type($table, $field);
                $dbman->change_field_notnull($table, $field);
                $dbman->change_field_default($table, $field);
            }
        }

        $table = new xmldb_table('local_aiskillnav_chunk');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field(
                'embeddingmodel',
                XMLDB_TYPE_CHAR,
                '255',
                null,
                XMLDB_NOTNULL,
                null,
                '',
                'embedding'
            );

            if ($dbman->field_exists($table, $field)) {
                $DB->execute("UPDATE {local_aiskillnav_chunk}
                                SET embeddingmodel = ''
                              WHERE embeddingmodel IS NULL");
                $dbman->change_field_type($table, $field);
                $dbman->change_field_notnull($table, $field);
                $dbman->change_field_default($table, $field);
            }
        }

        $table = new xmldb_table('local_aiskillnav_attempt');

        if ($dbman->table_exists($table)) {
            $index = new xmldb_index('topic_ix', XMLDB_INDEX_NOTUNIQUE, ['topic']);

            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_aiskillnav_assessment');

        if ($dbman->table_exists($table)) {
            $index = new xmldb_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('local_aiskillnav_sim');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field(
                'source',
                XMLDB_TYPE_CHAR,
                '40',
                null,
                XMLDB_NOTNULL,
                null,
                'ai_generated',
                'description'
            );

            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }

            $field = new xmldb_field(
                'url',
                XMLDB_TYPE_TEXT,
                null,
                null,
                null,
                null,
                null,
                'title'
            );

            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026080600, 'local', 'aiskillnavigator');
    }

    return true;
}
