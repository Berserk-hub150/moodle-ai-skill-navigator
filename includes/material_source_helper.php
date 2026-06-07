<?php

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/production_guard.php');

require_once(__DIR__ . '/material_ai_policy.php');
require_once(__DIR__ . '/material_exclusion_helper.php');

if (!function_exists('local_aiskillnavigator_current_ai_is_local')) {
    function local_aiskillnavigator_current_ai_is_local(): bool {
        $provider = strtolower(trim((string)get_config('local_aiskillnavigator', 'provider')));
        $endpoint = strtolower(trim((string)get_config('local_aiskillnavigator', 'endpoint')));

        if ($provider === '' || $provider === 'prototype') {
            return true;
        }

        if (in_array($provider, ['ollama', 'local', 'local_ollama'], true)) {
            return true;
        }

        return strpos($endpoint, 'localhost') !== false
            || strpos($endpoint, '127.0.0.1') !== false
            || strpos($endpoint, 'host.docker.internal') !== false
            || strpos($endpoint, '::1') !== false;
    }
}

if (!function_exists('local_aiskillnavigator_material_external_allowed')) {
    function local_aiskillnavigator_material_external_allowed(stdClass $material): bool {
        if (isset($material->externalaiallowed)) {
            return ((int)$material->externalaiallowed) === 1;
        }

        if (isset($material->aipolicy)) {
            return ((string)$material->aipolicy) === 'external_allowed';
        }

        return false;
    }
}

if (!function_exists('local_aiskillnavigator_material_can_be_sent_to_current_ai')) {
    function local_aiskillnavigator_material_can_be_sent_to_current_ai(stdClass $material): bool {
        return local_aiskillnavigator_current_ai_is_local()
            || local_aiskillnavigator_material_external_allowed($material);
    }
}

if (!function_exists('local_aiskillnavigator_ai_policy_label')) {
    function local_aiskillnavigator_ai_policy_label(stdClass $material): string {
        return local_aiskillnavigator_material_external_allowed($material)
            ? 'Allowed for external AI'
            : 'Local AI only';
    }
}

if (!function_exists('local_aiskillnavigator_ai_policy_badge_class')) {
    function local_aiskillnavigator_ai_policy_badge_class(stdClass $material): string {
        return local_aiskillnavigator_material_external_allowed($material)
            ? 'badge badge-success'
            : 'badge badge-secondary';
    }
}

function local_aiskillnavigator_material_source_mode_from_request(int $defaultmaterialid = -1): string {
    // The plugin is now material-grounded by default.
    // Free "question/topic only" mode is intentionally disabled.
    return 'selected';
}



if (!function_exists('local_aiskillnavigator_material_source_is_prompt_generated')) {
    
}

if (!function_exists('local_aiskillnavigator_material_source_normalize_filename_key')) {
    
}

if (!function_exists('local_aiskillnavigator_material_source_duplicate_key')) {
    
}







function local_aiskillnavigator_material_source_is_prompt_generated(stdClass $material): bool {
    $title = strtolower((string)($material->title ?? ''));

    return strpos($title, 'prompt-to-moodle') !== false
        || strpos($title, 'prompt to moodle') !== false;
}

function local_aiskillnavigator_material_source_clean_course_title(string $title): string {
    $title = trim($title);
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/u', '', $title);
    $title = preg_replace('/^\[Prompt-to-Moodle\]\s*/iu', '', $title);
    $title = preg_replace('/^\[Section\s+[0-9]+\]\s*/iu', '', $title);
    $title = preg_replace('/^Materiale\s*-\s*/iu', '', $title);
    $title = preg_replace('/^File\s*[:\-]?\s*/iu', '', $title);
    $title = preg_replace('/\s+/u', ' ', $title);

    return trim((string)$title);
}

function local_aiskillnavigator_material_source_filename_key(stdClass $material): string {
    $title = local_aiskillnavigator_material_source_clean_course_title((string)($material->title ?? ''));
    $content = (string)($material->content ?? '');

    if (preg_match('/([^\[\]\r\n\/\\\\]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx))\b/iu', $title, $matches)) {
        return strtolower(basename(str_replace('\\', '/', trim($matches[1]))));
    }

    if (preg_match('/^\s*File\s*[:\-]?\s*([^\r\n]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx))\b/iu', $content, $matches)) {
        return strtolower(basename(str_replace('\\', '/', trim($matches[1]))));
    }

    return '';
}

function local_aiskillnavigator_material_source_body_key(stdClass $material): string {
    $content = (string)($material->content ?? '');
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = preg_replace('/^\s*File\s*[:\-]?\s*[^\r\n]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx)\b[^\r\n]*[\r\n]*/iu', '', $content);
    $content = preg_replace('/\s+/u', ' ', trim((string)$content));

    if (strlen($content) < 120) {
        return '';
    }

    return sha1(strtolower(substr($content, 0, 12000)));
}

function local_aiskillnavigator_material_source_duplicate_key(stdClass $material): string {
    $filename = local_aiskillnavigator_material_source_filename_key($material);
    $body = local_aiskillnavigator_material_source_body_key($material);

    if ($filename !== '') {
        return 'file:' . $filename;
    }

    if ($body !== '') {
        return 'body:' . $body;
    }

    return '';
}

if (!function_exists('local_aiskillnavigator_material_source_clean_title')) {
    function local_aiskillnavigator_material_source_clean_title(stdClass $material): string {
        if (function_exists('local_aiskillnavigator_material_source_clean_course_title')) {
            $title = local_aiskillnavigator_material_source_clean_course_title((string)($material->title ?? 'Course material'));
        } else {
            $title = (string)($material->title ?? 'Course material');
            $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/u', '', $title);
            $title = preg_replace('/^\[Prompt-to-Moodle\]\s*/iu', '', $title);
            $title = preg_replace('/^\[Section\s+[0-9]+\]\s*/iu', '', $title);
            $title = preg_replace('/^Materiale\s*-\s*/iu', '', $title);
            $title = preg_replace('/^File\s*[:\-]?\s*/iu', '', $title);
            $title = preg_replace('/\s+/u', ' ', trim((string)$title));
        }

        return trim((string)$title) !== '' ? trim((string)$title) : 'Course material';
    }
}

if (!function_exists('local_aiskillnavigator_material_source_normalize_title_for_dedupe')) {
    function local_aiskillnavigator_material_source_normalize_title_for_dedupe(string $title): string {
        if (function_exists('local_aiskillnavigator_material_source_clean_course_title')) {
            return local_aiskillnavigator_material_source_clean_course_title($title);
        }

        $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/u', '', trim($title));
        $title = preg_replace('/^\[Prompt-to-Moodle\]\s*/iu', '', $title);
        $title = preg_replace('/^\[Section\s+[0-9]+\]\s*/iu', '', $title);
        $title = preg_replace('/^Materiale\s*-\s*/iu', '', $title);
        $title = preg_replace('/^File\s*[:\-]?\s*/iu', '', $title);
        $title = preg_replace('/\s+/u', ' ', $title);

        return trim((string)$title);
    }
}

function local_aisn_matlist_is_prompt(stdClass $material): bool {
    $title = strtolower((string)($material->title ?? ''));

    return strpos($title, 'prompt-to-moodle') !== false
        || strpos($title, 'prompt to moodle') !== false;
}

function local_aisn_matlist_clean_title(string $title): string {
    $title = trim($title);
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/u', '', $title);
    $title = preg_replace('/^\[Prompt-to-Moodle\]\s*/iu', '', $title);
    $title = preg_replace('/^\[Section\s+[0-9]+\]\s*/iu', '', $title);
    $title = preg_replace('/^Materiale\s*-\s*/iu', '', $title);
    $title = preg_replace('/^File\s*[:\-]?\s*/iu', '', $title);
    $title = preg_replace('/\s+/u', ' ', $title);

    return trim((string)$title);
}

function local_aisn_matlist_filename(stdClass $material): string {
    $title = local_aisn_matlist_clean_title((string)($material->title ?? ''));
    $content = (string)($material->content ?? '');

    if (preg_match('/([^\[\]\r\n\/\\\\]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx))\b/iu', $title, $matches)) {
        return strtolower(basename(str_replace('\\', '/', trim($matches[1]))));
    }

    if (preg_match('/^\s*File\s*[:\-]?\s*([^\r\n]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx))\b/iu', $content, $matches)) {
        return strtolower(basename(str_replace('\\', '/', trim($matches[1]))));
    }

    return '';
}

function local_aisn_matlist_body_hash(stdClass $material): string {
    $content = (string)($material->content ?? '');
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = preg_replace('/^\s*File\s*[:\-]?\s*[^\r\n]+\.(?:txt|md|csv|json|xml|html|htm|pdf|docx|pptx)\b[^\r\n]*[\r\n]*/iu', '', $content);
    $content = preg_replace('/^\s*\[Prompt-to-Moodle\]\s*/iu', '', $content);
    $content = preg_replace('/^\s*\[Section\s+[0-9]+\]\s*/iu', '', $content);
    $content = preg_replace('/\s+/u', ' ', trim((string)$content));

    if (core_text::strlen($content) < 80) {
        return '';
    }

    return sha1(strtolower(core_text::substr($content, 0, 12000)));
}


function local_aisn_matlist_cmid(stdClass $material): int {
    if (isset($material->sourcecmid) && (int)$material->sourcecmid > 0) {
        return (int)$material->sourcecmid;
    }

    if (function_exists('local_aisn_course_cm_id_from_material_title')) {
        return local_aisn_course_cm_id_from_material_title((string)($material->title ?? ''));
    }

    if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', (string)($material->title ?? ''), $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

function local_aisn_matlist_key(stdClass $material): string {
    $cmid = local_aisn_matlist_cmid($material);
    if ($cmid > 0) {
        return 'cm:' . $cmid;
    }

    $filename = local_aisn_matlist_filename($material);
    if ($filename !== '') {
        return 'file:' . $filename;
    }

    $bodyhash = local_aisn_matlist_body_hash($material);
    if ($bodyhash !== '') {
        return 'body:' . $bodyhash;
    }

    $cleantitle = strtolower(local_aisn_matlist_clean_title((string)($material->title ?? '')));
    if ($cleantitle !== '') {
        return 'title:' . $cleantitle;
    }

    return 'id:' . (int)($material->id ?? 0);
}

function local_aisn_matlist_better(stdClass $current, stdClass $candidate): stdClass {
    $currentprompt = local_aisn_matlist_is_prompt($current);
    $candidateprompt = local_aisn_matlist_is_prompt($candidate);

    if ($currentprompt !== $candidateprompt) {
        return $candidateprompt ? $current : $candidate;
    }

    $currentcontent = core_text::strlen((string)($current->content ?? ''));
    $candidatecontent = core_text::strlen((string)($candidate->content ?? ''));

    if ($candidatecontent > $currentcontent) {
        return $candidate;
    }

    return ((int)($candidate->id ?? 0) < (int)($current->id ?? 0)) ? $candidate : $current;
}

function local_aisn_matlist_dedupe($materials): array {
    if (!is_array($materials)) {
        return [];
    }

    $deduped = [];
    $keytoid = [];

    foreach ($materials as $id => $material) {
        if (!($material instanceof stdClass)) {
            continue;
        }

        $key = local_aisn_matlist_key($material);

        if (!isset($keytoid[$key])) {
            $keytoid[$key] = (int)$id;
            $deduped[(int)$id] = $material;
            continue;
        }

        $existingid = $keytoid[$key];
        $existing = $deduped[$existingid];

        $better = local_aisn_matlist_better($existing, $material);

        if ($better !== $existing) {
            unset($deduped[$existingid]);
            $keytoid[$key] = (int)$id;
            $deduped[(int)$id] = $material;
        }
    }

    return $deduped;
}

function local_aiskillnavigator_material_source_get_readable_materials(int $courseid, bool $includeall = true): array {
    global $DB, $CFG;

    if (!$DB->get_manager()->table_exists(new xmldb_table('local_aiskillnav_material'))) {
        return [];
    }

    $syncfile = $CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php';

    if (file_exists($syncfile)) {
        require_once($syncfile);

        if (function_exists('local_aiskillnavigator_sync_course_resources')) {
            local_aiskillnavigator_sync_course_resources($courseid, 0, false);
        }
    }

    $records = $DB->get_records(
        'local_aiskillnav_material',
        [
            'courseid' => $courseid,
            'materialtype' => 'course_resource',
        ],
        'timemodified DESC, id DESC'
    );

    if (empty($records)) {
        return [];
    }

    $modinfo = get_fast_modinfo($courseid);
    $bycmid = [];

    foreach ($records as $record) {
        $cmid = local_aisn_matlist_cmid($record);

        if ($cmid <= 0) {
            continue;
        }

        if (local_aisn_course_material_is_excluded($courseid, $cmid)) {
            continue;
        }

        if (empty($modinfo->cms[$cmid]) || empty($modinfo->cms[$cmid]->visible)) {
            continue;
        }

        if (trim((string)($record->content ?? '')) === '') {
            continue;
        }

        if (!isset($bycmid[$cmid])) {
            $bycmid[$cmid] = $record;
        }
    }

    ksort($bycmid);

    $readable = [];

    foreach ($bycmid as $record) {
        $readable[(int)$record->id] = $record;
    }

    if (function_exists('local_aisn_matlist_dedupe')) {
        return local_aisn_matlist_dedupe($readable);
    }

    return $readable;
}


function local_aiskillnavigator_material_source_selected_ids_from_request(array $readablematerials): array {
    $ids = optional_param_array('materialids', [], PARAM_INT);
    $legacy = optional_param('materialid', -1, PARAM_INT);

    if ($legacy > 0) {
        $ids[] = $legacy;
    }

    $ids = array_values(array_unique(array_map('intval', $ids)));

    return array_values(array_filter($ids, function($id) use ($readablematerials) {
        return isset($readablematerials[$id]);
    }));
}

function local_aiskillnavigator_material_source_selected_materials(array $readablematerials, string $sourcemode, array $selectedmaterialids): array {
    if ($sourcemode === 'manual') {
        return [];
    }

    $selected = [];

    if ($sourcemode === 'all') {
        $selected = array_values($readablematerials);
    } else {
        foreach ($selectedmaterialids as $id) {
            $id = (int)$id;

            if (isset($readablematerials[$id])) {
                $selected[] = $readablematerials[$id];
            }
        }
    }

    if (function_exists('local_aisn_matlist_dedupe')) {
        $selected = local_aisn_matlist_dedupe($selected);
    }

    return array_values(array_filter($selected, function($material) {
        return local_aiskillnavigator_material_can_be_sent_to_current_ai($material);
    }));
}

function local_aiskillnavigator_material_source_legacy_materialid(string $sourcemode, array $selectedmaterialids): int {
    if ($sourcemode === 'all') {
        return 0;
    }

    if ($sourcemode === 'selected' && !empty($selectedmaterialids)) {
        return (int)reset($selectedmaterialids);
    }

    return -1;
}



function local_aiskillnavigator_material_source_short_title(stdClass $material): string {
    return local_aiskillnavigator_material_source_clean_title($material) . ' (' . strlen((string)($material->content ?? '')) . ' chars)';
}

function local_aiskillnavigator_material_source_excerpt(string $text, int $limit = 170): string {
    if (function_exists('local_aiskillnavigator_fix_mojibake')) {
        $text = local_aiskillnavigator_fix_mojibake($text);
    }

    $text = trim((string)preg_replace('/\s+/u', ' ', $text));

    if (\core_text::strlen($text) > $limit) {
        return \core_text::substr($text, 0, $limit) . '...';
    }

    return $text;
}


function local_aisn_prod_filter_rag_results_by_ai_policy(array $results, int $courseid): array {
    if (empty($results)) {
        return [];
    }

    $readable = local_aiskillnavigator_material_source_get_readable_materials($courseid);
    $allowed = [];

    foreach ($readable as $material) {
        if (local_aiskillnavigator_material_can_be_sent_to_current_ai($material)) {
            $allowed[(int)$material->id] = true;
        }
    }

    if (empty($allowed)) {
        return [];
    }

    return array_values(array_filter($results, static function($result) use ($allowed): bool {
        $materialid = isset($result->materialid) ? (int)$result->materialid : 0;
        return $materialid > 0 && isset($allowed[$materialid]);
    }));
}

function local_aiskillnavigator_material_source_search($embeddingservice, string $query, int $courseid, int $limit, string $sourcemode, array $selectedmaterialids): array {
    if ($sourcemode === 'manual') {
        return [];
    }

    $results = [];

    try {
        if (method_exists($embeddingservice, 'search')) {
            if ($sourcemode === 'all') {
                $results = $embeddingservice->search($query, $courseid, $limit);
            } else {
                $merged = [];

                foreach ($selectedmaterialids as $materialid) {
                    $partial = $embeddingservice->search($query, $courseid, $limit, (int)$materialid);

                    foreach ($partial as $result) {
                        $key = (int)($result->id ?? 0);

                        if ($key <= 0) {
                            $key = crc32(($result->title ?? '') . ($result->chunkindex ?? '') . ($result->chunktext ?? ''));
                        }

                        $merged[$key] = $result;
                    }
                }

                $results = array_values($merged);
            }
        } else if (method_exists($embeddingservice, 'semantic_search')) {
            $results = $embeddingservice->semantic_search($courseid, $query, $limit);
        } else if (method_exists($embeddingservice, 'retrieve')) {
            $results = $embeddingservice->retrieve($courseid, $query, $limit);
        }
    } catch (Throwable $e) {
        debugging('AI Skill Navigator material source search skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }

    if (empty($results)) {
        return [];
    }

    $results = local_aisn_prod_filter_rag_results_by_ai_policy($results, $courseid);

    if (empty($results)) {
        return [];
    }

    if ($sourcemode === 'selected' && !empty($selectedmaterialids)) {
        $selected = array_map('intval', $selectedmaterialids);

        $results = array_values(array_filter($results, function($result) use ($selected) {
            $materialid = isset($result->materialid) ? (int)$result->materialid : 0;
            return in_array($materialid, $selected, true);
        }));
    }

    usort($results, function($a, $b) {
        return ((float)($b->similarity ?? 0)) <=> ((float)($a->similarity ?? 0));
    });

    return array_slice($results, 0, $limit);
}

function local_aiskillnavigator_material_source_search_rag(
    $embeddingservice,
    string $query,
    int $courseid,
    string $sourcemode,
    array $materialids,
    int $topk
): array {
    return local_aiskillnavigator_material_source_search(
        $embeddingservice,
        $query,
        $courseid,
        $topk,
        $sourcemode,
        $materialids
    );
}

function local_aiskillnavigator_material_source_hidden_fields(string $sourcemode, array $materialids): string {
    $html = '';

    $html .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sourcemode',
        'value' => $sourcemode,
    ]);

    foreach ($materialids as $materialid) {
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'materialids[]',
            'value' => (int)$materialid,
        ]);
    }

    return $html;
}

function local_aiskillnavigator_material_source_hidden_inputs(string $sourcemode, array $materialids): string {
    return local_aiskillnavigator_material_source_hidden_fields($sourcemode, $materialids);
}

function local_aiskillnavigator_material_source_selector_html(
    array $readablematerials,
    $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $selectedmaterialids,
    string $label = 'Course material',
    string $help = ''
): string {
    $readablematerials = local_aisn_matlist_dedupe($readablematerials);
    $selectedmaterialids = array_values(array_filter($selectedmaterialids, function($id) use ($readablematerials) {
        return isset($readablematerials[(int)$id]);
    }));

    $sourcemode = 'selected';

    $allowedcount = 0;

    foreach ($readablematerials as $material) {
        if (local_aiskillnavigator_material_can_be_sent_to_current_ai($material)) {
            $allowedcount++;
        }
    }

    $html = '';

    $html .= html_writer::tag('style', '
.aisn-material-selector {
    border: 1px solid #d9e2ec;
    border-radius: 18px;
    padding: 22px;
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
}
.aisn-material-selector-title {
    font-size: 1.12rem;
    font-weight: 900;
    margin-bottom: 8px;
}
.aisn-material-selector-help {
    color: #52616b;
    margin-bottom: 14px;
}
.aisn-material-dropdown {
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    overflow: hidden;
    background: #f8fafc;
}
.aisn-material-dropdown > summary {
    cursor: pointer;
    padding: 13px 15px;
    font-weight: 850;
    background: #eff6ff;
    list-style: none;
}
.aisn-material-dropdown > summary::-webkit-details-marker {
    display: none;
}
.aisn-material-search {
    width: calc(100% - 24px);
    margin: 12px;
    padding: 11px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 11px;
}
.aisn-material-list {
    max-height: 300px;
    overflow-y: auto;
    padding: 0 12px 12px;
}
.aisn-material {
    display: block;
    margin: 8px 0;
    padding: 12px;
    border: 1px solid #dbeafe;
    border-radius: 12px;
    background: #ffffff;
}
.aisn-material:hover {
    border-color: #60a5fa;
    background: #f8fbff;
}
.aisn-material.is-disabled {
    opacity: .60;
    cursor: not-allowed;
    background: #f3f4f6;
}
.aisn-material-title {
    font-weight: 850;
    margin-left: 7px;
}
.aisn-badge {
    display: inline-block;
    margin-top: 7px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #334155;
    font-size: 12px;
    font-weight: 750;
}
.aisn-material-note {
    display: block;
    margin-top: 8px;
    color: #92400e;
    font-size: 12px;
    font-weight: 750;
}
.aisn-excerpt {
    color: #64748b;
    font-size: 13px;
    margin-top: 8px;
}
.aisn-empty {
    padding: 14px;
    border-radius: 12px;
    background: #fff3cd;
    border: 1px solid #ffec99;
    color: #664d03;
    font-weight: 750;
}
');

    $html .= html_writer::start_div('aisn-material-selector');

    $html .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sourcemode',
        'value' => 'selected',
    ]);

    $html .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'source_mode',
        'value' => 'selected',
    ]);

    $html .= html_writer::tag('div', '1. Course material', ['class' => 'aisn-material-selector-title']);

    $html .= html_writer::tag(
        'div',
        'Select at least one course material. The AI answer will be grounded only on the selected material.',
        ['class' => 'aisn-material-selector-help']
    );

    if (empty($readablematerials) || $allowedcount === 0) {
        $html .= html_writer::div(
            'No selectable course materials found. Add material in Course materials / RAG before using this tool.',
            'aisn-empty'
        );

        $html .= html_writer::end_div();

        return $html;
    }

    $summarytext = empty($selectedmaterialids)
        ? 'Open course material menu'
        : count($selectedmaterialids) . ' material(s) selected';

    $html .= html_writer::start_tag('details', [
        'class' => 'aisn-material-dropdown',
        'open' => 'open',
        'data-aisn-material-dropdown' => '1',
    ]);

    $html .= html_writer::tag('summary', s($summarytext), [
        'data-aisn-material-summary' => '1',
    ]);

    $html .= html_writer::empty_tag('input', [
        'type' => 'search',
        'class' => 'aisn-material-search',
        'placeholder' => 'Search course material...',
        'data-aisn-material-search' => '1',
    ]);

    $html .= html_writer::start_div('aisn-material-list', [
        'data-aisn-material-list' => '1',
    ]);

    foreach ($readablematerials as $material) {
        $id = (int)$material->id;
        $allowed = local_aiskillnavigator_material_can_be_sent_to_current_ai($material);

        $checked = $allowed && in_array($id, $selectedmaterialids, true);
        $class = $allowed ? 'aisn-material' : 'aisn-material is-disabled';

        $title = local_aiskillnavigator_material_source_clean_title($material);
        $searchtext = strtolower($title . ' ' . (string)($material->content ?? ''));

        $html .= html_writer::start_tag('label', [
            'class' => $class,
            'data-aisn-material-row' => '1',
            'data-search' => s($searchtext),
        ]);

        $inputattrs = [
            'type' => 'checkbox',
            'name' => 'materialids[]',
            'value' => $id,
            'checked' => $checked ? 'checked' : null,
            'data-aisn-material-checkbox' => '1',
        ];

        if (!$allowed) {
            $inputattrs['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $inputattrs);
        $html .= html_writer::span(s($title), 'aisn-material-title');

        $html .= html_writer::empty_tag('br');
        $html .= html_writer::span(strlen((string)$material->content) . ' chars', 'aisn-badge');
        $html .= ' ';
        $html .= html_writer::span(
            s(local_aiskillnavigator_ai_policy_label($material)),
            local_aiskillnavigator_ai_policy_badge_class($material)
        );

        if (!$allowed) {
            $html .= html_writer::span(
                'Not selectable with the current external provider.',
                'aisn-material-note'
            );
        }

        $html .= html_writer::div(
            s(local_aiskillnavigator_material_source_excerpt((string)$material->content)),
            'aisn-excerpt'
        );

        $html .= html_writer::end_tag('label');
    }

    $html .= html_writer::end_div();
    $html .= html_writer::end_tag('details');

    $html .= html_writer::tag('script', '
(function() {
    const root = document.currentScript.closest(".aisn-material-selector");
    if (!root) { return; }

    const form = root.closest("form");
    const search = root.querySelector("[data-aisn-material-search]");
    const summary = root.querySelector("[data-aisn-material-summary]");
    const rows = Array.from(root.querySelectorAll("[data-aisn-material-row]"));
    const boxes = Array.from(root.querySelectorAll("[data-aisn-material-checkbox]:not(:disabled)"));

    function refreshSummary() {
        const selected = boxes.filter(function(box) { return box.checked; }).length;
        summary.textContent = selected > 0
            ? selected + " material(s) selected"
            : "Open course material menu";
    }

    if (search) {
        search.addEventListener("input", function() {
            const q = String(search.value || "").toLowerCase().trim();

            rows.forEach(function(row) {
                const haystack = String(row.dataset.search || row.textContent || "").toLowerCase();
                row.style.display = haystack.indexOf(q) !== -1 ? "" : "none";
            });
        });
    }

    boxes.forEach(function(box) {
        box.addEventListener("change", refreshSummary);
    });

    if (form) {
        form.addEventListener("submit", function(event) {
            const assessmentType = form.querySelector("[name=\"assessmenttype\"]");
            const aisnSkipMaterialValidationForPretest = assessmentType && String(assessmentType.value || "") === "pre";

            if (aisnSkipMaterialValidationForPretest || root.offsetParent === null) {
                return;
            }

            const selected = boxes.some(function(box) { return box.checked; });

            if (!selected) {
                event.preventDefault();
                event.stopPropagation();
                alert("Select at least one course material. The AI cannot answer without selected course material.");
            }
        }, true);
    }

    refreshSummary();
})();
');

    $html .= html_writer::end_div();

    return $html;
}


function local_aiskillnavigator_material_source_render_controls(
    array $readablematerials,
    $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $materialids,
    string $label,
    string $manualtext,
    string $alltext,
    string $selectedtext,
    string $helptext
): string {
    return local_aiskillnavigator_material_source_selector_html(
        $readablematerials,
        $embeddingservice,
        $courseid,
        $sourcemode,
        $materialids,
        $label,
        $helptext
    );
}

function local_aiskillnavigator_material_source_footer_script(): string {
    return '';
}

function local_aiskillnavigator_material_source_count_chunks(
    $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $materialids
): int {
    if ($sourcemode === 'manual') {
        return 0;
    }

    if ($sourcemode === 'all' && method_exists($embeddingservice, 'count_indexed_chunks')) {
        return $embeddingservice->count_indexed_chunks($courseid);
    }

    $total = 0;

    foreach ($materialids as $materialid) {
        if (method_exists($embeddingservice, 'count_indexed_chunks')) {
            $total += (int)$embeddingservice->count_indexed_chunks($courseid, (int)$materialid);
        }
    }

    return $total;
}