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

namespace local_aiskillnavigator\privacy;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Provider implementation.
 */
class provider implements core_userlist_provider, metadata_provider, request_provider {
    private const USER_TABLES = [
        'local_aiskillnav_material',
        'local_aiskillnav_attempt',
        'local_aiskillnav_assessment',
        'local_aiskillnav_ass_att',
        'local_aiskillnav_sim',
        'local_aiskillnav_tutor_sig',
    ];

    /**
     * Get metadata helper.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_aiskillnav_material',
            [
                'courseid' => 'privacy:metadata:courseid',
                'userid' => 'privacy:metadata:userid',
                'title' => 'privacy:metadata:content',
                'materialtype' => 'privacy:metadata:content',
                'content' => 'privacy:metadata:content',
                'externalaiallowed' => 'privacy:metadata:content',
                'aipolicy' => 'privacy:metadata:content',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:local_aiskillnav_material'
        );

        $collection->add_database_table(
            'local_aiskillnav_attempt',
            [
                'courseid' => 'privacy:metadata:courseid',
                'userid' => 'privacy:metadata:userid',
                'topic' => 'privacy:metadata:content',
                'difficulty' => 'privacy:metadata:content',
                'score' => 'privacy:metadata:content',
                'maxscore' => 'privacy:metadata:content',
                'percentage' => 'privacy:metadata:content',
                'quizjson' => 'privacy:metadata:content',
                'answersjson' => 'privacy:metadata:content',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:local_aiskillnav_attempt'
        );

        $collection->add_database_table(
            'local_aiskillnav_chunk',
            [
                'materialid' => 'privacy:metadata:content',
                'courseid' => 'privacy:metadata:courseid',
                'title' => 'privacy:metadata:content',
                'chunktext' => 'privacy:metadata:content',
                'embeddingmodel' => 'privacy:metadata:content',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:local_aiskillnav_chunk'
        );

        $collection->add_database_table(
            'local_aiskillnav_assessment',
            [
                'courseid' => 'privacy:metadata:courseid',
                'userid' => 'privacy:metadata:userid',
                'title' => 'privacy:metadata:content',
                'assessmenttype' => 'privacy:metadata:content',
                'focus' => 'privacy:metadata:content',
                'difficulty' => 'privacy:metadata:content',
                'quizjson' => 'privacy:metadata:content',
                'materialids' => 'privacy:metadata:content',
                'visible' => 'privacy:metadata:content',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:local_aiskillnav_assessment'
        );

        $collection->add_database_table(
            'local_aiskillnav_ass_att',
            [
                'assessmentid' => 'privacy:metadata:content',
                'courseid' => 'privacy:metadata:courseid',
                'userid' => 'privacy:metadata:userid',
                'score' => 'privacy:metadata:content',
                'maxscore' => 'privacy:metadata:content',
                'percentage' => 'privacy:metadata:content',
                'answersjson' => 'privacy:metadata:content',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:local_aiskillnav_ass_att'
        );

        $collection->add_database_table(
            'local_aiskillnav_sim',
            [
                'courseid' => 'privacy:metadata:courseid',
                'userid' => 'privacy:metadata:userid',
                'topic' => 'privacy:metadata:content',
                'level' => 'privacy:metadata:content',
                'title' => 'privacy:metadata:content',
                'url' => 'privacy:metadata:content',
                'description' => 'privacy:metadata:content',
                'resulttext' => 'privacy:metadata:content',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:local_aiskillnav_sim'
        );

        $collection->add_database_table(
            'local_aiskillnav_tutor_sig',
            [
                'courseid' => 'privacy:metadata:courseid',
                'userid' => 'privacy:metadata:userid',
                'question' => 'privacy:metadata:content',
                'sourcemode' => 'privacy:metadata:content',
                'materials' => 'privacy:metadata:content',
                'skill' => 'privacy:metadata:content',
                'requesttype' => 'privacy:metadata:content',
                'difficulty' => 'privacy:metadata:content',
                'answerpreview' => 'privacy:metadata:content',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:local_aiskillnav_tutor_sig'
        );

        $collection->add_external_location_link(
            'configured_ai_provider',
            [
                'prompt' => 'privacy:metadata:content',
                'api_key' => 'privacy:metadata:configured_ai_provider',
            ],
            'privacy:metadata:configured_ai_provider'
        );

        return $collection;
    }

    /**
     * Get contexts for userid helper.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        foreach (self::USER_TABLES as $table) {
            $contextlist->add_from_sql(
                "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {{$table}} t ON t.courseid = ctx.instanceid
                  WHERE ctx.contextlevel = :contextcourse
                    AND t.userid = :userid",
                [
                    'contextcourse' => CONTEXT_COURSE,
                    'userid' => $userid,
                ]
            );
        }

        return $contextlist;
    }

    /**
     * Export user data helper.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ((int)$context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $courseid = (int)$context->instanceid;
            $data = [];

            foreach (self::USER_TABLES as $table) {
                if (!self::table_exists($table)) {
                    continue;
                }

                $records = $DB->get_records($table, ['courseid' => $courseid, 'userid' => (int)$user->id], 'id ASC');
                $data[$table] = array_values($records);
            }

            if (!empty($data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_aiskillnavigator')],
                    (object)$data
                );
            }
        }
    }

    /**
     * Delete data for all users in context helper.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ((int)$context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = (int)$context->instanceid;

        if (self::table_exists('local_aiskillnav_material')) {
            $materialids = $DB->get_fieldset_select('local_aiskillnav_material', 'id', 'courseid = ?', [$courseid]);
            self::delete_material_related($materialids);
        }

        foreach (array_reverse(self::USER_TABLES) as $table) {
            if (self::table_exists($table)) {
                $DB->delete_records($table, ['courseid' => $courseid]);
            }
        }

        foreach (['local_aisn_kg_source', 'local_aisn_kg_relation', 'local_aisn_kg_concept'] as $table) {
            if (self::table_exists($table)) {
                if ($table === 'local_aisn_kg_concept' || $table === 'local_aisn_kg_relation') {
                    $DB->delete_records($table, ['courseid' => $courseid]);
                }
            }
        }
    }

    /**
     * Delete data for user helper.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            self::delete_userids_in_context($context, [$userid]);
        }
    }

    /**
     * Get users in context helper.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ((int)$context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = (int)$context->instanceid;

        foreach (self::USER_TABLES as $table) {
            if (!self::table_exists($table)) {
                continue;
            }

            $userlist->add_from_sql(
                'userid',
                "SELECT DISTINCT userid
                   FROM {{$table}}
                  WHERE courseid = :courseid
                    AND userid > 0",
                ['courseid' => $courseid]
            );
        }
    }

    /**
     * Delete data for users helper.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_userids_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Delete userids in context helper.
     */
    private static function delete_userids_in_context(\context $context, array $userids): void {
        global $DB;

        if ((int)$context->contextlevel !== CONTEXT_COURSE || empty($userids)) {
            return;
        }

        $courseid = (int)$context->instanceid;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));

        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'aisnuser');

        if (self::table_exists('local_aiskillnav_material')) {
            $params = array_merge(['courseid' => $courseid], $userparams);
            $materialids = $DB->get_fieldset_select(
                'local_aiskillnav_material',
                'id',
                'courseid = :courseid AND userid ' . $usersql,
                $params
            );
            self::delete_material_related($materialids);
            $DB->delete_records_select('local_aiskillnav_material', 'courseid = :courseid AND userid ' . $usersql, $params);
        }

        if (self::table_exists('local_aiskillnav_assessment')) {
            $params = array_merge(['courseid' => $courseid], $userparams);
            $assessmentids = $DB->get_fieldset_select(
                'local_aiskillnav_assessment',
                'id',
                'courseid = :courseid AND userid ' . $usersql,
                $params
            );

            if (!empty($assessmentids) && self::table_exists('local_aiskillnav_ass_att')) {
                [$asssql, $assparams] = $DB->get_in_or_equal($assessmentids, SQL_PARAMS_NAMED, 'aisnass');
                $DB->delete_records_select('local_aiskillnav_ass_att', 'assessmentid ' . $asssql, $assparams);
            }

            $DB->delete_records_select('local_aiskillnav_assessment', 'courseid = :courseid AND userid ' . $usersql, $params);
        }

        // phpcs:ignore moodle.Files.LineLength
        foreach (['local_aiskillnav_attempt', 'local_aiskillnav_ass_att', 'local_aiskillnav_sim', 'local_aiskillnav_tutor_sig'] as $table) {
            if (!self::table_exists($table)) {
                continue;
            }

            $params = array_merge(['courseid' => $courseid], $userparams);
            $DB->delete_records_select($table, 'courseid = :courseid AND userid ' . $usersql, $params);
        }
    }

    /**
     * Delete material related helper.
     */
    private static function delete_material_related(array $materialids): void {
        global $DB;

        $materialids = array_values(array_unique(array_filter(array_map('intval', $materialids))));

        if (empty($materialids)) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'aisnmat');
        $conceptids = [];

        if (self::table_exists('local_aisn_kg_source')) {
            $conceptids = $DB->get_fieldset_select(
                'local_aisn_kg_source',
                'conceptid',
                'materialid ' . $sql,
                $params
            );
        }

        foreach (['local_aiskillnav_chunk', 'local_aisn_kg_source', 'local_aisn_kg_relation'] as $table) {
            if (self::table_exists($table)) {
                $DB->delete_records_select($table, 'materialid ' . $sql, $params);
            }
        }

        self::delete_orphan_concepts($conceptids);
    }

    /**
     * Delete orphan concepts helper.
     */
    private static function delete_orphan_concepts(array $conceptids): void {
        global $DB;

        $conceptids = array_values(array_unique(array_filter(array_map('intval', $conceptids))));

        if (
            empty($conceptids) ||
            !self::table_exists('local_aisn_kg_concept') ||
            !self::table_exists('local_aisn_kg_source')
        ) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal($conceptids, SQL_PARAMS_NAMED, 'aisnconcept');
        $orphans = $DB->get_fieldset_sql(
            "SELECT c.id
               FROM {local_aisn_kg_concept} c
          LEFT JOIN {local_aisn_kg_source} s ON s.conceptid = c.id
              WHERE c.id {$sql}
                AND s.id IS NULL",
            $params
        );

        if (empty($orphans)) {
            return;
        }

        [$orphansql, $orphanparams] = $DB->get_in_or_equal(
            $orphans,
            SQL_PARAMS_NAMED,
            'aisnorph'
        );

        if (self::table_exists('local_aisn_kg_relation')) {
            [$sourcesql, $sourceparams] = $DB->get_in_or_equal(
                $orphans,
                SQL_PARAMS_NAMED,
                'aisnsrc'
            );
            [$targetsql, $targetparams] = $DB->get_in_or_equal(
                $orphans,
                SQL_PARAMS_NAMED,
                'aisntgt'
            );

            $DB->delete_records_select(
                'local_aisn_kg_relation',
                'sourceconceptid ' . $sourcesql . ' OR targetconceptid ' . $targetsql,
                array_merge($sourceparams, $targetparams)
            );
        }

        $DB->delete_records_select('local_aisn_kg_concept', 'id ' . $orphansql, $orphanparams);
    }

    /**
     * Table exists helper.
     */
    private static function table_exists(string $table): bool {
        global $DB;

        try {
            return $DB->get_manager()->table_exists(new \xmldb_table($table));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
