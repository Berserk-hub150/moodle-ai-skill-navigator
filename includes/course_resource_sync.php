<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/material_exclusion_helper.php');
require_once(__DIR__ . '/pdf_text_extractor.php');
require_once(__DIR__ . '/ocr_helper.php');
require_once(__DIR__ . '/mistral_ocr_helper.php');
require_once(__DIR__ . '/knowledge_graph_helper.php');

if (!function_exists('local_aisn_crs_table_exists')) {
    function local_aisn_crs_table_exists(string $tablename): bool {
        global $DB;

        try {
            return $DB->get_manager()->table_exists(new xmldb_table($tablename));
        } catch (Throwable $e) {
            debugging('AI Skill Navigator table check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}

if (!function_exists('local_aisn_crs_field_exists')) {
    function local_aisn_crs_field_exists(string $tablename, string $fieldname): bool {
        global $DB;

        static $cache = [];
        $key = $tablename . '.' . $fieldname;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $cache[$key] = $DB->get_manager()->field_exists(
                new xmldb_table($tablename),
                new xmldb_field($fieldname)
            );
        } catch (Throwable $e) {
            debugging('AI Skill Navigator field check failed for ' . $key . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('local_aiskillnavigator_course_module_external_ai_allowed')) {
    function local_aiskillnavigator_course_module_external_ai_allowed(int $cmid): int {
        if ($cmid <= 0) {
            return 0;
        }

        $stored = get_config('local_aiskillnavigator', 'cm_external_ai_' . $cmid);
        return ((string)$stored === '1') ? 1 : 0;
    }
}

if (!function_exists('local_aisn_crs_content_hash')) {
    function local_aisn_crs_content_hash(string $content): string {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/\s+/u', ' ', trim((string)$content));

        if ($content === '') {
            return '';
        }

        return sha1(strtolower(core_text::substr($content, 0, 120000)));
    }
}

if (!function_exists('local_aisn_crs_large_file_threshold')) {
    function local_aisn_crs_large_file_threshold(): int {
        $configured = (int)get_config('local_aiskillnavigator', 'largefilethresholdbytes');
        if ($configured > 0) {
            return max(5 * 1024 * 1024, $configured);
        }
        return 25 * 1024 * 1024;
    }
}

if (!function_exists('local_aisn_crs_file_size')) {
    function local_aisn_crs_file_size(stored_file $file): int {
        try {
            return (int)$file->get_filesize();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('local_aisn_crs_is_large_file')) {
    function local_aisn_crs_is_large_file(stored_file $file): bool {
        $size = local_aisn_crs_file_size($file);
        return $size > 0 && $size >= local_aisn_crs_large_file_threshold();
    }
}

if (!function_exists('local_aisn_crs_extraction_note')) {
    function local_aisn_crs_extraction_note(stored_file $file, string $reason): string {
        $name = trim((string)$file->get_filename());
        $size = local_aisn_crs_file_size($file);
        $sizeinfo = $size > 0 && function_exists('display_size') ? display_size($size) : ($size . ' bytes');
        return "File: " . $name . "\n[Fast extraction note: " . $reason . ". File size: " . $sizeinfo . "]";
    }
}

if (!function_exists('local_aisn_crs_title_for_document')) {
    function local_aisn_crs_title_for_document(int $courseid, int $cmid, string $title): string {
        $title = trim((string)$title) !== '' ? trim((string)$title) : ('Course module ' . $cmid);
        $title = core_text::substr($title, 0, 230);

        return core_text::substr('[Course #' . $courseid . ' / cm #' . $cmid . '] ' . $title, 0, 255);
    }
}

if (!function_exists('local_aisn_crs_cmid_from_title')) {
    function local_aisn_crs_cmid_from_title(string $title): int {
        if (function_exists('local_aisn_course_cm_id_from_material_title')) {
            return local_aisn_course_cm_id_from_material_title($title);
        }

        if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', $title, $m)) {
            return (int)$m[1];
        }

        return 0;
    }
}

if (!function_exists('local_aisn_crs_material_cmid')) {
    function local_aisn_crs_material_cmid(stdClass $material): int {
        if (isset($material->sourcecmid) && (int)$material->sourcecmid > 0) {
            return (int)$material->sourcecmid;
        }

        return local_aisn_crs_cmid_from_title((string)($material->title ?? ''));
    }
}

if (!function_exists('local_aisn_crs_get_records')) {
    function local_aisn_crs_get_records(array $conditions, string $sort = 'timemodified DESC, id DESC'): array {
        global $DB;

        try {
            return $DB->get_records('local_aiskillnav_material', $conditions, $sort);
        } catch (Throwable $e) {
            debugging('AI Skill Navigator material lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
}

if (!function_exists('local_aisn_crs_first_record')) {
    function local_aisn_crs_first_record(array $records): ?stdClass {
        foreach ($records as $record) {
            return $record;
        }

        return null;
    }
}

if (!function_exists('local_aisn_crs_find_existing_material')) {
    function local_aisn_crs_find_existing_material(int $courseid, int $cmid, string $title, string $contenthash): ?stdClass {
        global $DB;

        if ($cmid > 0 && local_aisn_crs_field_exists('local_aiskillnav_material', 'sourcecmid')) {
            $found = local_aisn_crs_first_record(local_aisn_crs_get_records([
                'courseid' => $courseid,
                'materialtype' => 'course_resource',
                'sourcecmid' => $cmid,
            ]));

            if ($found) {
                return $found;
            }
        }

        $found = local_aisn_crs_first_record(local_aisn_crs_get_records([
            'courseid' => $courseid,
            'materialtype' => 'course_resource',
            'title' => $title,
        ]));

        if ($found) {
            return $found;
        }

        if ($cmid > 0) {
            $select = 'courseid = :courseid AND materialtype = :materialtype AND ' . $DB->sql_like('title', ':title', false, false);
            $params = [
                'courseid' => $courseid,
                'materialtype' => 'course_resource',
                'title' => '[Course #' . $courseid . ' / cm #' . $cmid . ']%',
            ];

            try {
                $found = local_aisn_crs_first_record($DB->get_records_select('local_aiskillnav_material', $select, $params, 'timemodified DESC, id DESC'));
                if ($found) {
                    return $found;
                }
            } catch (Throwable $e) {
                debugging('AI Skill Navigator title-prefix material lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        if ($contenthash !== '' && local_aisn_crs_field_exists('local_aiskillnav_material', 'contenthash')) {
            $found = local_aisn_crs_first_record(local_aisn_crs_get_records([
                'courseid' => $courseid,
                'materialtype' => 'course_resource',
                'contenthash' => $contenthash,
            ]));

            if ($found) {
                return $found;
            }
        }

        return null;
    }
}

if (!function_exists('local_aisn_crs_set_optional_material_fields')) {
    function local_aisn_crs_set_optional_material_fields(stdClass $record, int $cmid, string $contenthash): stdClass {
        if (local_aisn_crs_field_exists('local_aiskillnav_material', 'sourcecmid')) {
            $record->sourcecmid = $cmid;
        }

        if (local_aisn_crs_field_exists('local_aiskillnav_material', 'contenthash')) {
            $record->contenthash = $contenthash;
        }

        return $record;
    }
}

if (!function_exists('local_aisn_crs_policy_for_cmid')) {
    function local_aisn_crs_policy_for_cmid(int $cmid): array {
        $allowed = local_aiskillnavigator_course_module_external_ai_allowed($cmid);
        return [$allowed, $allowed ? 'external_allowed' : 'local_only'];
    }
}

if (!function_exists('local_aisn_crs_delete_materials')) {
    function local_aisn_crs_delete_materials(array $materialids): int {
        global $DB;

        $materialids = array_values(array_unique(array_filter(array_map('intval', $materialids))));

        if (empty($materialids)) {
            return 0;
        }

        if (local_aisn_crs_table_exists('local_aiskillnav_chunk')) {
            list($insql, $params) = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'mid');
            $DB->delete_records_select('local_aiskillnav_chunk', 'materialid ' . $insql, $params);
        }

        if (local_aisn_crs_table_exists('local_aisn_kg_source')) {
            list($insql, $params) = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'kgs');
            $DB->delete_records_select('local_aisn_kg_source', 'materialid ' . $insql, $params);
        }

        if (local_aisn_crs_table_exists('local_aisn_kg_relation')) {
            list($insql, $params) = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'kgr');
            $DB->delete_records_select('local_aisn_kg_relation', 'materialid ' . $insql, $params);
        }

        list($insql, $params) = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'mat');
        $DB->delete_records_select('local_aiskillnav_material', 'id ' . $insql, $params);

        return count($materialids);
    }
}

if (!function_exists('local_aisn_crs_is_prompt_material')) {
    function local_aisn_crs_is_prompt_material(stdClass $material): bool {
        $title = strtolower((string)($material->title ?? ''));
        return strpos($title, 'prompt-to-moodle') !== false || strpos($title, 'prompt to moodle') !== false;
    }
}

if (!function_exists('local_aisn_crs_better_material')) {
    function local_aisn_crs_better_material(stdClass $current, stdClass $candidate): stdClass {
        $currentprompt = local_aisn_crs_is_prompt_material($current);
        $candidateprompt = local_aisn_crs_is_prompt_material($candidate);

        if ($currentprompt !== $candidateprompt) {
            return $candidateprompt ? $current : $candidate;
        }

        $currentlen = core_text::strlen((string)($current->content ?? ''));
        $candidatelen = core_text::strlen((string)($candidate->content ?? ''));

        if ($candidatelen !== $currentlen) {
            return $candidatelen > $currentlen ? $candidate : $current;
        }

        return ((int)($candidate->timemodified ?? 0) > (int)($current->timemodified ?? 0)) ? $candidate : $current;
    }
}

if (!function_exists('local_aisn_crs_material_duplicate_key')) {
    function local_aisn_crs_material_duplicate_key(stdClass $material): string {
        // AISN_DB_DUPLICATE_KEY_CONTENT_FIRST_V1
        // Used by DB cleanup after sync: merge duplicates across different cmids
        // when they represent the same file/body.
        $doc = [
            'title' => (string)($material->title ?? ''),
            'content' => (string)($material->content ?? ''),
            'cmid' => 0,
        ];

        $filename = function_exists('local_aiskillnavigator_course_resource_filename_key')
            ? local_aiskillnavigator_course_resource_filename_key($doc)
            : '';

        $body = function_exists('local_aiskillnavigator_course_resource_body_key')
            ? local_aiskillnavigator_course_resource_body_key($doc)
            : '';

        if ($filename !== '' && $body !== '') {
            return 'filebody:' . $filename . ':' . $body;
        }

        if ($body !== '') {
            return 'body:' . $body;
        }

        if ($filename !== '') {
            return 'file:' . $filename;
        }

        $hash = (string)($material->contenthash ?? '');

        if ($hash !== '') {
            return 'hash:' . $hash;
        }

        $cmid = local_aisn_crs_material_cmid($material);

        if ($cmid > 0) {
            return 'cm:' . $cmid;
        }

        return 'title:' . strtolower(trim((string)($material->title ?? '')));
    }
}
if (!function_exists('local_aisn_crs_cleanup_duplicate_course_resources')) {
    function local_aisn_crs_cleanup_duplicate_course_resources(int $courseid): int {
        global $DB;

        if (!local_aisn_crs_table_exists('local_aiskillnav_material')) {
            return 0;
        }

        $records = $DB->get_records('local_aiskillnav_material', [
            'courseid' => $courseid,
            'materialtype' => 'course_resource',
        ], 'timemodified DESC, id DESC');

        $groups = [];

        foreach ($records as $record) {
            $key = local_aisn_crs_material_duplicate_key($record);

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $record;
        }

        $deleteids = [];

        foreach ($groups as $records) {
            if (count($records) < 2) {
                continue;
            }

            $keep = array_shift($records);

            foreach ($records as $candidate) {
                $better = local_aisn_crs_better_material($keep, $candidate);

                if ((int)$better->id !== (int)$keep->id) {
                    $deleteids[] = (int)$keep->id;
                    $keep = $better;
                } else {
                    $deleteids[] = (int)$candidate->id;
                }
            }
        }

        return local_aisn_crs_delete_materials($deleteids);
    }
}

if (!function_exists('local_aiskillnavigator_sync_course_resources')) {
    function local_aiskillnavigator_sync_course_resources(int $courseid, int $userid = 0, bool $force = false): array {
        global $DB, $USER;

        // Demo-safe mode: avoid OCR/RAG scan on normal page loads.
        // CLI scripts and explicit forced actions still run the full sync.
        if (!defined('CLI_SCRIPT') && !$force) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'duplicatesdeleted' => 0];
        }

        if ($courseid <= SITEID || !local_aisn_crs_table_exists('local_aiskillnav_material')) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'duplicatesdeleted' => 0];
        }

        if ($userid <= 0 && !empty($USER) && !empty($USER->id)) {
            $userid = (int)$USER->id;
        }

        $documents = local_aiskillnavigator_collect_course_resource_documents($courseid);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $changedids = [];

        foreach ($documents as $doc) {
            $cmid = (int)($doc['cmid'] ?? 0);

            if ($cmid > 0 && local_aisn_course_material_is_excluded($courseid, $cmid)) {
                $skipped++;
                continue;
            }

            $content = local_aiskillnavigator_limit_material_text((string)($doc['content'] ?? ''));

            if ($content === '') {
                $skipped++;
                continue;
            }

            $hash = local_aisn_crs_content_hash($content);
            $sourcetitle = local_aisn_crs_title_for_document($courseid, $cmid, (string)($doc['title'] ?? 'Course material'));
            $existing = local_aisn_crs_find_existing_material($courseid, $cmid, $sourcetitle, $hash);
            [$cmexternalallowed, $aipolicy] = local_aisn_crs_policy_for_cmid($cmid);

            if ($existing) {
                $needsupdate = false;

                if ((string)($existing->title ?? '') !== $sourcetitle) {
                    $existing->title = $sourcetitle;
                    $needsupdate = true;
                }

                if ((string)($existing->content ?? '') !== $content || $force) {
                    $existing->content = $content;
                    $needsupdate = true;
                }

                if ((int)($existing->userid ?? 0) !== $userid) {
                    $existing->userid = $userid;
                    $needsupdate = true;
                }

                if ((int)($existing->externalaiallowed ?? 0) !== $cmexternalallowed) {
                    $existing->externalaiallowed = $cmexternalallowed;
                    $needsupdate = true;
                }

                if ((string)($existing->aipolicy ?? '') !== $aipolicy) {
                    $existing->aipolicy = $aipolicy;
                    $needsupdate = true;
                }

                $existing = local_aisn_crs_set_optional_material_fields($existing, $cmid, $hash);

                if ($needsupdate || $force) {
                    $existing->timemodified = time();
                    $DB->update_record('local_aiskillnav_material', $existing);
                    $updated++;
                    $changedids[] = (int)$existing->id;
                } else {
                    // Backfill sourcecmid/contenthash on old rows without reindexing if no content changed.
                    if (
                        (local_aisn_crs_field_exists('local_aiskillnav_material', 'sourcecmid') && (int)($existing->sourcecmid ?? 0) !== $cmid) ||
                        (local_aisn_crs_field_exists('local_aiskillnav_material', 'contenthash') && (string)($existing->contenthash ?? '') !== $hash)
                    ) {
                        $existing = local_aisn_crs_set_optional_material_fields($existing, $cmid, $hash);
                        $existing->timemodified = time();
                        $DB->update_record('local_aiskillnav_material', $existing);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }

                continue;
            }

            $record = new stdClass();
            $record->courseid = $courseid;
            $record->userid = $userid;
            $record->title = $sourcetitle;
            $record->materialtype = 'course_resource';
            $record->content = $content;
            $record->externalaiallowed = $cmexternalallowed;
            $record->aipolicy = $aipolicy;
            $record->timecreated = time();
            $record->timemodified = time();
            $record = local_aisn_crs_set_optional_material_fields($record, $cmid, $hash);

            $newid = $DB->insert_record('local_aiskillnav_material', $record);
            $created++;
            $changedids[] = (int)$newid;
        }

        $duplicatesdeleted = local_aisn_crs_cleanup_duplicate_course_resources($courseid);

        $changedids = array_values(array_unique(array_filter(array_map('intval', $changedids), static function($id): bool {
            return $id > 0;
        })));

        if (!empty($changedids)) {
            local_aiskillnavigator_try_index_synced_materials($courseid, $changedids);

            if (function_exists('local_aisn_kg_sync_course_after_material_sync')) {
                local_aisn_kg_sync_course_after_material_sync($courseid, $changedids);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'duplicatesdeleted' => $duplicatesdeleted,
        ];
    }
}

if (!function_exists('local_aiskillnavigator_collect_course_resource_documents')) {
    function local_aiskillnavigator_collect_course_resource_documents(int $courseid): array {
        global $DB;

        $documents = [];
        $modinfo = get_fast_modinfo($courseid);

        foreach ($modinfo->cms as $cm) {
            if (empty($cm->id) || empty($cm->modname) || !$cm->visible) {
                continue;
            }

            $cmcontext = context_module::instance($cm->id, IGNORE_MISSING);

            if (!$cmcontext) {
                continue;
            }

            $title = trim((string)$cm->name);
            $pieces = [];

            if ($cm->modname === 'resource') {
                $pieces[] = local_aiskillnavigator_extract_files_from_area($cmcontext->id, 'mod_resource', 'content', (int)$cm->id);
            } else if ($cm->modname === 'folder') {
                $pieces[] = local_aiskillnavigator_extract_files_from_area($cmcontext->id, 'mod_folder', 'content', (int)$cm->id);
            } else if ($cm->modname === 'page') {
                $page = $DB->get_record('page', ['id' => $cm->instance]);
                if ($page) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string)$page->intro);
                    $pieces[] = local_aiskillnavigator_clean_html_text((string)$page->content);
                }
            } else if ($cm->modname === 'label') {
                $label = $DB->get_record('label', ['id' => $cm->instance]);
                if ($label) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string)$label->intro);
                }
            } else if ($cm->modname === 'url') {
                $url = $DB->get_record('url', ['id' => $cm->instance]);
                if ($url) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string)$url->intro);
                    $pieces[] = 'External URL: ' . (string)$url->externalurl;
                }
            } else if ($cm->modname === 'book') {
                $book = $DB->get_record('book', ['id' => $cm->instance]);

                if ($book) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string)$book->intro);
                }

                $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance, 'hidden' => 0], 'pagenum ASC');

                foreach ($chapters as $chapter) {
                    $pieces[] = 'Chapter: ' . (string)$chapter->title . "\n" . local_aiskillnavigator_clean_html_text((string)$chapter->content);
                }
            }

            $content = trim(implode("\n\n", array_filter(array_map('trim', $pieces))));

            if ($content === '') {
                continue;
            }

            $documents[] = [
                'cmid' => (int)$cm->id,
                'modname' => (string)$cm->modname,
                'title' => $title !== '' ? $title : ('Course module ' . $cm->id),
                'content' => $content,
                'contenthash' => local_aisn_crs_content_hash($content),
            ];
        }

        return local_aiskillnavigator_dedupe_course_resource_documents($documents);
    }
}

if (!function_exists('local_aiskillnavigator_extract_files_from_area')) {
    function local_aiskillnavigator_extract_files_from_area(int $contextid, string $component, string $filearea, int $cmid = 0): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, $component, $filearea, false, 'filename', false);

        $pieces = [];

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filename = $file->get_filename();
            $text = local_aiskillnavigator_extract_stored_file_text($file, $cmid);

            if (trim($text) === '') {
                continue;
            }

            $pieces[] = "File: " . $filename . "\n" . $text;
        }

        return trim(implode("\n\n", $pieces));
    }
}

if (!function_exists('local_aiskillnavigator_extract_stored_file_text')) {
    function local_aiskillnavigator_extract_stored_file_text(stored_file $file, int $cmid = 0): string {
        $filename = strtolower($file->get_filename());
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // AISN_MISTRAL_OCR_FIRST_V1
        // Try Mistral OCR first. If it fails or privacy policy blocks it,
        // fallback to the existing local extraction pipeline.
        if (function_exists('local_aisn_mistral_ocr_supported_extension') &&
            function_exists('local_aisn_mistral_ocr_extract_stored_file') &&
            local_aisn_mistral_ocr_supported_extension($extension)) {
            $mistraltext = local_aisn_mistral_ocr_extract_stored_file($file, $cmid);
            $mistraltext = trim((string)$mistraltext);

            if ($mistraltext !== '') {
                return local_aiskillnavigator_limit_material_text($mistraltext);
            }
        }

        $rawtextExtensions = [
            'txt', 'md', 'csv', 'json', 'xml', 'html', 'htm',
            'php', 'js', 'css', 'sql', 'cs', 'java', 'py', 'cpp', 'c',
        ];

        if (in_array($extension, $rawtextExtensions, true)) {
            return local_aiskillnavigator_limit_material_text((string)$file->get_content());
        }

        if ($extension === 'docx') {
            return local_aiskillnavigator_limit_material_text(local_aiskillnavigator_extract_docx_text($file));
        }

        if ($extension === 'pptx') {
            return local_aiskillnavigator_limit_material_text(local_aiskillnavigator_extract_pptx_text($file));
        }

        if ($extension === 'pdf') {
            return local_aiskillnavigator_limit_material_text(local_aiskillnavigator_extract_pdf_text_if_possible($file));
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'bmp', 'tif', 'tiff', 'webp'], true)) {
            return local_aiskillnavigator_limit_material_text(local_aiskillnavigator_extract_image_text($file));
        }

        return '';
    }
}

if (!function_exists('local_aiskillnavigator_extract_docx_text')) {
    function local_aiskillnavigator_extract_docx_text(stored_file $file): string {
        $tmpdir = make_temp_directory('local_aiskillnavigator/course_import');
        $tmppath = $tmpdir . '/' . uniqid('docx_', true) . '.docx';
        $file->copy_content_to($tmppath);

        $parts = [];
        $large = local_aisn_crs_is_large_file($file);

        try {
            if (function_exists('local_aisn_extract_docx_xml_text_from_path')) {
                $parts[] = local_aisn_extract_docx_xml_text_from_path($tmppath);
            }

            $xmltext = trim(implode("

", array_filter(array_map('trim', $parts))));

            // For large DOCX files, keep Course Builder fast: XML text only, no embedded-image OCR.
            if (!$large && core_text::strlen($xmltext) < 2000 && function_exists('local_aisn_ocr_docx_images_from_path')) {
                $parts[] = local_aisn_ocr_docx_images_from_path($tmppath);
            }
        } catch (Throwable $e) {
            debugging('DOCX extraction/OCR failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        @unlink($tmppath);
        $text = trim(implode("

", array_filter(array_map('trim', $parts))));

        if ($text === '' && $large) {
            return local_aisn_crs_extraction_note($file, 'large DOCX linked as Moodle resource; no readable XML text found and OCR was skipped');
        }

        return $text;
    }
}

if (!function_exists('local_aiskillnavigator_extract_pptx_text')) {
    function local_aiskillnavigator_extract_pptx_text(stored_file $file): string {
        $tmpdir = make_temp_directory('local_aiskillnavigator/course_import');
        $tmppath = $tmpdir . '/' . uniqid('pptx_', true) . '.pptx';
        $file->copy_content_to($tmppath);

        $parts = [];

        try {
            // Fast PPTX extraction: convert slide XML/chart XML to text.
            // Do NOT OCR embedded images here. OCR on 100MB+ slide decks is too slow for Course Builder.
            if (function_exists('local_aisn_extract_pptx_xml_text_from_path')) {
                $parts[] = local_aisn_extract_pptx_xml_text_from_path($tmppath);
            }

            if (function_exists('local_aisn_extract_pptx_chart_text_from_path')) {
                $parts[] = local_aisn_extract_pptx_chart_text_from_path($tmppath);
            }
        } catch (Throwable $e) {
            debugging('PPTX fast text extraction failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        @unlink($tmppath);
        $text = trim(implode("

", array_filter(array_map('trim', $parts))));

        if ($text === '') {
            return local_aisn_crs_extraction_note($file, 'PPTX converted with fast XML-to-text mode; no selectable slide text found, embedded-image OCR skipped');
        }

        return $text;
    }
}

if (!function_exists('local_aiskillnavigator_extract_image_text')) {
    function local_aiskillnavigator_extract_image_text(stored_file $file): string {
        $tmpdir = make_temp_directory('local_aiskillnavigator/course_import');
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $tmppath = $tmpdir . '/' . uniqid('img_', true) . '.' . ($ext !== '' ? $ext : 'img');
        $file->copy_content_to($tmppath);

        try {
            $text = function_exists('local_aisn_ocr_image_path')
                ? local_aisn_ocr_image_path($tmppath, $file->get_filename())
                : '';
        } catch (Throwable $e) {
            debugging('Image OCR failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $text = '';
        }

        @unlink($tmppath);
        return trim((string)$text);
    }
}

if (!function_exists('local_aiskillnavigator_extract_pdf_text_if_possible')) {
    function local_aiskillnavigator_extract_pdf_text_if_possible(stored_file $file): string {
        $tmpdir = make_temp_directory('local_aiskillnavigator/course_import');
        $tmppath = $tmpdir . '/' . uniqid('pdf_', true) . '.pdf';
        $file->copy_content_to($tmppath);

        try {
            $text = local_aiskillnavigator_extract_pdf_text_from_path($tmppath, $file->get_filename());
        } catch (Throwable $e) {
            debugging('PDF extraction failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $text = '';
        }

        @unlink($tmppath);
        $text = trim((string)$text);

        if ($text === '' && local_aisn_crs_is_large_file($file)) {
            return local_aisn_crs_extraction_note($file, 'large PDF linked as Moodle resource; pdftotext found no text layer and OCR was skipped');
        }

        return $text;
    }
}

if (!function_exists('local_aiskillnavigator_clean_html_text')) {
    function local_aiskillnavigator_clean_html_text(string $html): string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    }
}

if (!function_exists('local_aiskillnavigator_limit_material_text')) {
    function local_aiskillnavigator_limit_material_text(string $text): string {
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));

        if (core_text::strlen($text) > 120000) {
            $text = core_text::substr($text, 0, 120000) . "\n[Content truncated for indexing]";
        }

        return trim($text);
    }
}

if (!function_exists('local_aiskillnavigator_course_resource_document_is_prompt_generated')) {
    function local_aiskillnavigator_course_resource_document_is_prompt_generated(array $doc): bool {
        $title = strtolower((string)($doc['title'] ?? ''));

        return strpos($title, 'prompt-to-moodle') !== false
            || strpos($title, 'prompt to moodle') !== false;
    }
}

if (!function_exists('local_aiskillnavigator_course_resource_clean_title')) {
    function local_aiskillnavigator_course_resource_clean_title(string $title): string {
        $title = trim($title);
        $title = preg_replace('/^\[Prompt-to-Moodle\]\s*/iu', '', $title);
        $title = preg_replace('/^\[Section\s+[0-9]+\]\s*/iu', '', $title);
        $title = preg_replace('/^Materiale\s*-\s*/iu', '', $title);
        $title = preg_replace('/^File\s*[:\-]?\s*/iu', '', $title);
        $title = preg_replace('/\s+/u', ' ', $title);

        return trim((string)$title);
    }
}

if (!function_exists('local_aiskillnavigator_course_resource_filename_key')) {
    function local_aiskillnavigator_course_resource_filename_key(array $doc): string {
        $title = local_aiskillnavigator_course_resource_clean_title((string)($doc['title'] ?? ''));
        $content = (string)($doc['content'] ?? '');

        if (preg_match('/([^\[\]\r\n\/\\\\]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx))\b/iu', $title, $matches)) {
            return strtolower(basename(str_replace('\\', '/', trim($matches[1]))));
        }

        if (preg_match('/^\s*File\s*[:\-]?\s*([^\r\n]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx))\b/iu', $content, $matches)) {
            return strtolower(basename(str_replace('\\', '/', trim($matches[1]))));
        }

        return '';
    }
}

if (!function_exists('local_aiskillnavigator_course_resource_body_key')) {
    function local_aiskillnavigator_course_resource_body_key(array $doc): string {
        $content = (string)($doc['content'] ?? '');
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/^\s*File\s*[:\-]?\s*[^\r\n]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx)\b[^\r\n]*[\r\n]*/iu', '', $content);
        $content = preg_replace('/\s+/u', ' ', trim((string)$content));

        if (core_text::strlen($content) < 120) {
            return '';
        }

        return sha1(strtolower(core_text::substr($content, 0, 12000)));
    }
}

if (!function_exists('local_aiskillnavigator_course_resource_document_duplicate_key')) {
    function local_aiskillnavigator_course_resource_document_duplicate_key(array $doc): string {
        // AISN_DUPLICATE_KEY_CONTENT_FIRST_V1
        // Prefer content identity over cmid so the same material does not appear twice
        // when added once by AI Course Builder and once manually in Moodle.
        $filename = local_aiskillnavigator_course_resource_filename_key($doc);
        $body = local_aiskillnavigator_course_resource_body_key($doc);

        if ($filename !== '' && $body !== '') {
            return 'filebody:' . $filename . ':' . $body;
        }

        if ($body !== '') {
            return 'body:' . $body;
        }

        if ($filename !== '') {
            return 'file:' . $filename;
        }

        $cmid = (int)($doc['cmid'] ?? 0);

        if ($cmid > 0) {
            return 'cm:' . $cmid;
        }

        return '';
    }
}

if (!function_exists('local_aiskillnavigator_dedupe_course_resource_documents')) {
    function local_aiskillnavigator_dedupe_course_resource_documents(array $documents): array {
        $normalfilenames = [];

        foreach ($documents as $doc) {
            $filename = local_aiskillnavigator_course_resource_filename_key($doc);

            if ($filename !== '' && !local_aiskillnavigator_course_resource_document_is_prompt_generated($doc)) {
                $normalfilenames[$filename] = true;
            }
        }

        $deduped = [];
        $seen = [];

        foreach ($documents as $doc) {
            $filename = local_aiskillnavigator_course_resource_filename_key($doc);

            if (
                $filename !== '' &&
                local_aiskillnavigator_course_resource_document_is_prompt_generated($doc) &&
                isset($normalfilenames[$filename])
            ) {
                continue;
            }

            $key = local_aiskillnavigator_course_resource_document_duplicate_key($doc);

            if ($key !== '') {
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
            }

            $deduped[] = $doc;
        }

        return array_values($deduped);
    }
}

if (!function_exists('local_aiskillnavigator_try_index_synced_materials')) {
    function local_aiskillnavigator_try_index_synced_materials(int $courseid, array $materialids): void {
        if (empty($materialids) || !class_exists('\local_aiskillnavigator\service\embedding_service')) {
            return;
        }

        try {
            $service = new \local_aiskillnavigator\service\embedding_service();

            foreach ($materialids as $materialid) {
                if (!local_aisn_crs_get_records(['id' => (int)$materialid])) {
                    continue;
                }

                if (method_exists($service, 'index_material')) {
                    $service->index_material((int)$materialid);
                } else if (method_exists($service, 'index_material_by_id')) {
                    $service->index_material_by_id((int)$materialid);
                } else if (method_exists($service, 'index_teacher_material')) {
                    $service->index_teacher_material((int)$materialid);
                }
            }
        } catch (Throwable $e) {
            debugging('AI Skill Navigator course material auto-index skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

