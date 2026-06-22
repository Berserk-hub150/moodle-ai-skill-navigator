<?php

namespace local_aiskillnavigator;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function course_created(\core\event\course_created $event): void {
        $courseid = (int)($event->courseid ?: $event->objectid);

        if (self::auto_block_enabled()) {
            self::ensure_course_block($courseid);
        }

        if (self::auto_sync_enabled()) {
            self::sync_course_resources($courseid, (int)$event->userid);
        }
    }

    public static function course_updated(\core\event\course_updated $event): void {
        $courseid = (int)($event->courseid ?: $event->objectid);

        if (self::auto_block_enabled()) {
            self::ensure_course_block($courseid);
        }

        if (self::auto_sync_enabled()) {
            self::sync_course_resources($courseid, (int)$event->userid);
        }
    }

    public static function course_module_created(\core\event\course_module_created $event): void {
        if (self::auto_sync_enabled()) {
            self::sync_course_resources((int)$event->courseid, (int)$event->userid);
        }
    }

    public static function course_module_updated(\core\event\course_module_updated $event): void {
        if (self::auto_sync_enabled()) {
            self::sync_course_resources((int)$event->courseid, (int)$event->userid);
        }
    }

    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        if (!self::auto_sync_enabled()) {
            return;
        }

        $courseid = (int)$event->courseid;
        $cmid = (int)$event->objectid;

        if ($courseid <= SITEID || $cmid <= 0) {
            return;
        }

        try {
            if (!self::table_exists('local_aiskillnav_material')) {
                return;
            }

            $select = 'courseid = :courseid AND materialtype = :materialtype AND ' . $DB->sql_like('title', ':title', false, false);
            $params = [
                'courseid' => $courseid,
                'materialtype' => 'course_resource',
                'title' => '%cm #' . $cmid . ']%',
            ];

            $materials = $DB->get_records_select('local_aiskillnav_material', $select, $params);
            $materialids = array_map('intval', array_keys($materials));

            self::delete_material_chunks($materialids);
            $DB->delete_records_select('local_aiskillnav_material', $select, $params);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator course module cleanup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    public static function ensure_course_block(int $courseid): void {
        global $DB;

        if ($courseid <= SITEID) {
            return;
        }

        try {
            if (!$DB->record_exists('block', ['name' => 'aiskillnavigator'])) {
                return;
            }

            $context = \context_course::instance($courseid, IGNORE_MISSING);

            if (!$context) {
                return;
            }

            if ($DB->record_exists('block_instances', [
                'blockname' => 'aiskillnavigator',
                'parentcontextid' => $context->id,
            ])) {
                return;
            }

            $record = new \stdClass();
            $record->blockname = 'aiskillnavigator';
            $record->parentcontextid = $context->id;
            $record->showinsubcontexts = 0;
            $record->pagetypepattern = 'course-view-*';
            $record->subpagepattern = '';
            $record->defaultregion = 'side-pre';
            $record->defaultweight = -10;
            $record->configdata = '';
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('block_instances', $record);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator block auto-install failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    private static function auto_block_enabled(): bool {
        return (string)get_config('local_aiskillnavigator', 'autoblockcourses') === '1';
    }

    private static function auto_sync_enabled(): bool {
        return (string)get_config('local_aiskillnavigator', 'autosynccourseresources') === '1';
    }

    private static function sync_course_resources(int $courseid, int $userid): void {
        global $CFG;

        if ($courseid <= SITEID) {
            return;
        }

        $syncfile = $CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php';

        if (!file_exists($syncfile)) {
            debugging('AI Skill Navigator sync file missing: ' . $syncfile, DEBUG_DEVELOPER);
            return;
        }

        require_once($syncfile);

        if (!function_exists('local_aiskillnavigator_sync_course_resources')) {
            debugging('AI Skill Navigator sync function missing.', DEBUG_DEVELOPER);
            return;
        }

        try {
            local_aiskillnavigator_sync_course_resources($courseid, $userid, true);
            self::dedupe_course_resource_materials($courseid);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator automatic course resource sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Safety net for automatic sync.
     *
     * The sync helper can be triggered by several Moodle events. If it inserts
     * instead of updating, the same course module appears multiple times in
     * materials, prompts and RAG chunks. Keep one record per course module id.
     */
    private static function dedupe_course_resource_materials(int $courseid): void {
        global $DB;

        if (!self::table_exists('local_aiskillnav_material')) {
            return;
        }

        $records = $DB->get_records(
            'local_aiskillnav_material',
            ['courseid' => $courseid, 'materialtype' => 'course_resource'],
            'id ASC',
            'id, courseid, userid, title, materialtype, content, timecreated, timemodified'
        );

        if (empty($records)) {
            return;
        }

        $kept = [];
        $deleteids = [];

        foreach ($records as $record) {
            $key = self::material_identity_key($record);

            if ($key === '') {
                continue;
            }

            if (!isset($kept[$key])) {
                $kept[$key] = $record;
                continue;
            }

            $current = $kept[$key];

            $currenttime = (int)($current->timemodified ?? $current->timecreated ?? 0);
            $recordtime = (int)($record->timemodified ?? $record->timecreated ?? 0);
            $currentid = (int)($current->id ?? 0);
            $recordid = (int)($record->id ?? 0);

            $recordisnewer = $recordtime > $currenttime || ($recordtime === $currenttime && $recordid > $currentid);

            if ($recordisnewer) {
                $deleteids[] = $currentid;
                $kept[$key] = $record;
            } else {
                $deleteids[] = $recordid;
            }
        }

        $deleteids = array_values(array_unique(array_filter(array_map('intval', $deleteids))));

        if (!empty($deleteids)) {
            self::delete_material_chunks($deleteids);
            list($insql, $inparams) = $DB->get_in_or_equal($deleteids, SQL_PARAMS_NAMED, 'dup');
            $DB->delete_records_select('local_aiskillnav_material', 'id ' . $insql, $inparams);
            debugging('AI Skill Navigator removed duplicate course materials: ' . count($deleteids), DEBUG_DEVELOPER);
        }
    }

    private static function material_identity_key(\stdClass $record): string {
        $title = trim((string)($record->title ?? ''));
        $type = trim((string)($record->materialtype ?? ''));
        $content = trim((string)($record->content ?? ''));

        if ($title === '' && $content === '') {
            return '';
        }

        if (preg_match('/cm\s*#\s*([0-9]+)\s*\]/i', $title, $matches)) {
            return 'course-module:' . (int)$matches[1];
        }

        return strtolower($type) . ':' . md5(self::normalise_key_text($title) . "\n" . self::normalise_key_text($content));
    }

    private static function normalise_key_text(string $text): string {
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text);

        if (class_exists('\core_text')) {
            return \core_text::strtolower((string)$text);
        }

        return strtolower((string)$text);
    }

    private static function delete_material_chunks(array $materialids): void {
        global $DB;

        $materialids = array_values(array_unique(array_filter(array_map('intval', $materialids))));

        if (empty($materialids) || !self::table_exists('local_aiskillnav_chunk')) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'mid');
        $DB->delete_records_select('local_aiskillnav_chunk', 'materialid ' . $insql, $inparams);
    }

    private static function table_exists(string $table): bool {
        global $DB;

        try {
            return in_array($table, $DB->get_tables(false), true);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator table check failed for ' . $table . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}

