<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\privacy;

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

class provider implements metadata_provider, request_provider, core_userlist_provider {
    private const USER_TABLES = [
        'local_aiskillnav_material',
        'local_aiskillnav_attempt',
        'local_aiskillnav_assessment',
        'local_aiskillnav_ass_att',
        'local_aiskillnav_sim',
        'local_aiskillnav_tutor_sig',
    ];

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

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            self::delete_userids_in_context($context, [$userid]);
        }
    }

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

    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_userids_in_context($userlist->get_context(), $userlist->get_userids());
    }

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

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'aisnuser');

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
                list($asssql, $assparams) = $DB->get_in_or_equal($assessmentids, SQL_PARAMS_NAMED, 'aisnass');
                $DB->delete_records_select('local_aiskillnav_ass_att', 'assessmentid ' . $asssql, $assparams);
            }

            $DB->delete_records_select('local_aiskillnav_assessment', 'courseid = :courseid AND userid ' . $usersql, $params);
        }

        foreach (['local_aiskillnav_attempt', 'local_aiskillnav_ass_att', 'local_aiskillnav_sim', 'local_aiskillnav_tutor_sig'] as $table) {
            if (!self::table_exists($table)) {
                continue;
            }

            $params = array_merge(['courseid' => $courseid], $userparams);
            $DB->delete_records_select($table, 'courseid = :courseid AND userid ' . $usersql, $params);
        }
    }

    private static function delete_material_related(array $materialids): void {
        global $DB;

        $materialids = array_values(array_unique(array_filter(array_map('intval', $materialids))));

        if (empty($materialids)) {
            return;
        }

        list($sql, $params) = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'aisnmat');

        foreach (['local_aiskillnav_chunk', 'local_aisn_kg_source', 'local_aisn_kg_relation'] as $table) {
            if (self::table_exists($table)) {
                $DB->delete_records_select($table, 'materialid ' . $sql, $params);
            }
        }
    }

    private static function table_exists(string $table): bool {
        global $DB;

        try {
            return $DB->get_manager()->table_exists(new \xmldb_table($table));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
