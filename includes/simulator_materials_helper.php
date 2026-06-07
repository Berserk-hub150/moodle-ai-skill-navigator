<?php

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/saved_simulation_helper.php');

require_once(__DIR__ . '/course_resource_sync.php');
require_once(__DIR__ . '/material_ai_policy.php');

function local_aisn_sim_table_exists(string $name): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($name));
}


function local_aisn_sim_field_exists(string $fieldname): bool {
    global $DB;

    static $cache = [];

    if (array_key_exists($fieldname, $cache)) {
        return $cache[$fieldname];
    }

    try {
        $cache[$fieldname] = $DB->get_manager()->field_exists(
            new xmldb_table('local_aiskillnav_sim'),
            new xmldb_field($fieldname)
        );
    } catch (Throwable $e) {
        debugging('AI Skill Navigator sim field check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $cache[$fieldname] = false;
    }

    return $cache[$fieldname];
}

function local_aisn_sim_add_field_if_missing(xmldb_table $table, xmldb_field $field): void {
    global $DB;

    $dbman = $DB->get_manager();

    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

function local_aisn_sim_material_cmid(stdClass $material): int {
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

function local_aisn_sim_ensure_table(): void {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_aiskillnav_sim');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('materialid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('topic', XMLDB_TYPE_CHAR, '255', null, null, null, '');
        $table->add_field('level', XMLDB_TYPE_CHAR, '40', null, null, null, '');
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('source', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'ai_generated');
        $table->add_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null);
        $table->add_field('materialtitles', XMLDB_TYPE_TEXT, null, null, null, null);
        $table->add_field('resulttext', XMLDB_TYPE_TEXT, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('material_idx', XMLDB_INDEX_NOTUNIQUE, ['materialid']);
        $dbman->create_table($table);
        return;
    }

    // Upgrade old ad-hoc simulator tables to the superset expected by this helper.
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('materialid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'userid'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('topic', XMLDB_TYPE_CHAR, '255', null, null, null, '', 'materialid'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('level', XMLDB_TYPE_CHAR, '40', null, null, null, '', 'topic'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'level'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null, 'title'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'url'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('source', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'ai_generated', 'description'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'source'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('materialtitles', XMLDB_TYPE_TEXT, null, null, null, null, null, 'materialids'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('resulttext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'materialtitles'));
    local_aisn_sim_add_field_if_missing($table, new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'));
}

function local_aisn_sim_selected_ids(): array {
    $ids = optional_param_array('materialids', [], PARAM_INT);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    return $ids;
}


function local_aisn_sim_material_selectable(stdClass $material): bool {
    if (function_exists('local_aiskillnavigator_material_can_be_sent_to_current_ai')) {
        return local_aiskillnavigator_material_can_be_sent_to_current_ai($material);
    }

    return true;
}

function local_aisn_sim_material_policy_label_safe(stdClass $material): string {
    if (function_exists('local_aiskillnavigator_ai_policy_label')) {
        return local_aiskillnavigator_ai_policy_label($material);
    }

    return !empty($material->externalaiallowed)
        ? 'Allowed for external AI'
        : 'Local AI only';
}

function local_aisn_sim_material_policy_class_safe(stdClass $material): string {
    if (function_exists('local_aiskillnavigator_ai_policy_badge_class')) {
        return local_aiskillnavigator_ai_policy_badge_class($material);
    }

    return !empty($material->externalaiallowed)
        ? 'badge badge-success'
        : 'badge badge-warning';
}


function local_aisn_sim_clean_title(string $title): string {
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/', '', $title);
    return trim((string)$title);
}

function local_aisn_sim_get_course_materials(int $courseid): array {
    global $DB;

    if (!local_aisn_sim_table_exists('local_aiskillnav_material')) {
        return [];
    }

    if (function_exists('local_aiskillnavigator_sync_course_resources')) {
        local_aiskillnavigator_sync_course_resources($courseid, 0, false);
    }

    $records = $DB->get_records('local_aiskillnav_material', [
        'courseid' => $courseid,
        'materialtype' => 'course_resource',
    ], 'title ASC');

    $modinfo = get_fast_modinfo($courseid);
    $visible = [];

    foreach ($records as $record) {
        $cmid = local_aisn_sim_material_cmid($record);

        if ($cmid <= 0) {
            continue;
        }

        if (empty($modinfo->cms[$cmid]) || empty($modinfo->cms[$cmid]->visible)) {
            continue;
        }

        if (trim((string)($record->content ?? '')) === '') {
            continue;
        }

        if (!isset($visible[$cmid])) {
            $visible[$cmid] = $record;
            continue;
        }

        $currentlen = core_text::strlen((string)($visible[$cmid]->content ?? ''));
        $newlen = core_text::strlen((string)($record->content ?? ''));

        if ($newlen > $currentlen) {
            $visible[$cmid] = $record;
        }
    }

    $out = [];
    foreach ($visible as $record) {
        $out[(int)$record->id] = $record;
    }

    return $out;
}

function local_aisn_sim_selected_materials(int $courseid, array $ids): array {
    $materials = local_aisn_sim_get_course_materials($courseid);

    if (empty($ids)) {
        return [];
    }

    return array_filter($materials, function($material) use ($ids) {
        return in_array((int)$material->id, $ids, true) &&
            local_aisn_sim_material_selectable($material);
    });
}

function local_aisn_sim_material_context(int $courseid, array $ids): string {
    $selected = local_aisn_sim_selected_materials($courseid, $ids);
    $parts = [];
    $seen = [];

    foreach ($selected as $material) {
        $cmid = local_aisn_sim_material_cmid($material);
        $key = $cmid > 0 ? 'cm:' . $cmid : 'id:' . (int)$material->id;

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $title = local_aisn_sim_clean_title((string)$material->title);
        $content = trim((string)$material->content);

        if ($content === '') {
            continue;
        }

        if (core_text::strlen($content) > 5000) {
            $content = core_text::substr($content, 0, 5000) . "\n[Material truncated]";
        }

        $parts[] = "MATERIALE: " . $title . "\n" . $content;
    }

    return trim(implode("\n\n---\n\n", $parts));
}

function local_aisn_sim_require_materials_for_post(int $courseid): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $ids = local_aisn_sim_selected_ids();

    if (empty($ids)) {
        redirect(
            new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
            'Select at least one course material before generating a simulator exercise.',
            3,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $context = local_aisn_sim_material_context($courseid, $ids);

    if ($context === '') {
        redirect(
            new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
            'The selected material has no readable text.',
            3,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if (!empty($_POST['aisn_selected_material_context_added'])) {
        return;
    }

    $addition = "\n\nSelected Moodle course materials:\n" . $context;
    $targetkey = 'materials';

    foreach (['materials', 'material', 'teacher_notes', 'notes', 'context', 'constraints'] as $candidate) {
        if (isset($_POST[$candidate]) && trim((string)$_POST[$candidate]) !== '') {
            $targetkey = $candidate;
            break;
        }
    }

    $current = isset($_POST[$targetkey]) ? (string)$_POST[$targetkey] : '';

    if (strpos($current, 'Selected Moodle course materials:') === false) {
        $_POST[$targetkey] = trim($current . $addition);
        $_REQUEST[$targetkey] = $_POST[$targetkey];
    }

    $_POST['aisn_selected_material_context_added'] = '1';
    $_REQUEST['aisn_selected_material_context_added'] = '1';
}

function local_aisn_sim_material_selector_html(int $courseid): string {
    $materials = local_aisn_sim_get_course_materials($courseid);
    $selectedids = local_aisn_sim_selected_ids();

    $html = html_writer::start_div('aisn-material-selector', [
        'id' => 'aisn-sim-material-selector',
    ]);

    $html .= html_writer::tag('style', '
.aisn-material-selector {
    border: 1px solid #d9e2ec;
    border-radius: 18px;
    padding: 22px;
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    margin: 0 0 22px;
    position: static !important;
    z-index: auto !important;
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
.aisn-material.is-disabled {
    opacity: .55;
    background: #f3f4f6;
    border-color: #d1d5db;
    cursor: not-allowed;
}
.aisn-material-disabled-note {
    display: block;
    margin-top: 8px;
    color: #92400e;
    font-size: 12px;
    font-weight: 750;
}
.aisn-material:hover {
    border-color: #60a5fa;
    background: #f8fbff;
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

    $html .= html_writer::tag('div', '1. Course material', [
        'class' => 'aisn-material-selector-title',
    ]);

    $html .= html_writer::tag(
        'div',
        'Select at least one course material. The AI answer will be grounded only on the selected material.',
        ['class' => 'aisn-material-selector-help']
    );

    if (empty($materials)) {
        $html .= html_writer::div(
            'No selectable course materials found. Add material in Moodle Edit mode or with AI Course Builder.',
            'aisn-empty'
        );

        $html .= html_writer::end_div();

        return $html;
    }

    $summarytext = empty($selectedids)
        ? 'Open course material menu'
        : count($selectedids) . ' material(s) selected';

    $html .= html_writer::start_tag('details', [
        'class' => 'aisn-material-dropdown',
        'open' => 'open',
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

    foreach ($materials as $material) {
        $id = (int)$material->id;
        $title = local_aisn_sim_clean_title((string)$material->title);
        $content = trim((string)($material->content ?? ''));

        $excerpt = core_text::strlen($content) > 210
            ? core_text::substr($content, 0, 210) . '...'
            : $content;

        $selectable = local_aisn_sim_material_selectable($material);
        $rowclass = $selectable ? 'aisn-material' : 'aisn-material is-disabled';

        $html .= html_writer::start_tag('label', [
            'class' => $rowclass,
            'data-aisn-material-row' => '1',
            'data-search' => s(core_text::strtolower($title . ' ' . $content)),
        ]);

        $inputattrs = [
            'type' => 'checkbox',
            'name' => 'materialids[]',
            'value' => $id,
            'checked' => ($selectable && in_array($id, $selectedids, true)) ? 'checked' : null,
            'data-aisn-material-checkbox' => '1',
        ];

        if (!$selectable) {
            $inputattrs['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $inputattrs);

        $html .= html_writer::span(s($title), 'aisn-material-title');

        $html .= html_writer::empty_tag('br');

        $html .= html_writer::span(core_text::strlen($content) . ' chars', 'aisn-badge');

        $html .= ' ';
        $html .= html_writer::span(
            s(local_aisn_sim_material_policy_label_safe($material)),
            local_aisn_sim_material_policy_class_safe($material)
        );

        if (!$selectable) {
            $html .= html_writer::span(
                'Not selectable with the current AI provider. Enable local AI or allow this material for external AI.',
                'aisn-material-disabled-note'
            );
        }

        $html .= html_writer::div(s($excerpt), 'aisn-excerpt');

        $html .= html_writer::end_tag('label');
    }

    $html .= html_writer::end_div();
    $html .= html_writer::end_tag('details');

    $html .= html_writer::tag('script', '
(function() {
    function initMaterialSelector() {
        const root = document.getElementById("aisn-sim-material-selector");
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

        if (search && search.dataset.ready !== "1") {
            search.dataset.ready = "1";

            search.addEventListener("input", function() {
                const q = String(search.value || "").toLowerCase().trim();

                rows.forEach(function(row) {
                    const haystack = String(row.dataset.search || row.textContent || "").toLowerCase();
                    row.style.display = haystack.indexOf(q) !== -1 ? "" : "none";
                });
            });
        }

        boxes.forEach(function(box) {
            if (box.dataset.ready === "1") { return; }
            box.dataset.ready = "1";
            box.addEventListener("change", refreshSummary);
        });

        if (form && form.dataset.aisnSimMaterialGuard !== "1") {
            form.dataset.aisnSimMaterialGuard = "1";

            form.addEventListener("submit", function(event) {
                const selected = boxes.some(function(box) { return box.checked; });

                if (!selected) {
                    event.preventDefault();
                    event.stopPropagation();
                    alert("Select at least one selectable course material. Local-only materials require local AI or must be allowed for external AI.");
                }
            }, true);
        }

        refreshSummary();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initMaterialSelector);
    } else {
        initMaterialSelector();
    }

    setTimeout(initMaterialSelector, 400);
})();
');

    $html .= html_writer::end_div();

    return $html;
}


function local_aisn_sim_saved_link_html(int $courseid): string {
    return html_writer::div(
        html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', ['courseid' => $courseid]),
            'Saved simulations',
            ['class' => 'btn btn-secondary mb-3']
        ),
        'aisn-sim-saved-link'
    );
}

function local_aisn_sim_prepare_capture(int $courseid): void {
    // The previous implementation captured the whole rendered Moodle page at shutdown.
    // That stored navigation, CSS and JavaScript inside saved simulations.
    // Saving is now explicit in simulator_finder.php after the AI result is generated.
    return;
}

function local_aisn_sim_save_generated(
    int $courseid,
    int $userid,
    string $topic,
    string $level,
    array $materialids,
    array $materialtitles,
    string $resulttext
): void {
    global $DB;

    if (function_exists('local_aisn_sim_clean_generated_result')) {
        $resulttext = local_aisn_sim_clean_generated_result($resulttext);
    }

    $resulttext = trim($resulttext);
    if ($resulttext === '') {
        return;
    }

    local_aisn_sim_ensure_table();

    $now = time();
    $record = new stdClass();
    $record->courseid = $courseid;

    if (local_aisn_sim_field_exists('userid')) {
        $record->userid = $userid;
    }

    if (local_aisn_sim_field_exists('materialid')) {
        $record->materialid = !empty($materialids) ? (int)reset($materialids) : null;
    }

    if (local_aisn_sim_field_exists('topic')) {
        $record->topic = core_text::substr($topic, 0, 255);
    }

    if (local_aisn_sim_field_exists('level')) {
        $record->level = core_text::substr($level, 0, 40);
    }

    if (local_aisn_sim_field_exists('title')) {
        $record->title = core_text::substr($topic !== '' ? $topic : 'Generated simulator exercise', 0, 255);
    }

    if (local_aisn_sim_field_exists('url')) {
        $record->url = '';
    }

    if (local_aisn_sim_field_exists('description')) {
        $record->description = core_text::substr($resulttext, 0, 65000);
    }

    if (local_aisn_sim_field_exists('source')) {
        $record->source = 'ai_generated';
    }

    if (local_aisn_sim_field_exists('materialids')) {
        $record->materialids = json_encode(array_values($materialids));
    }

    if (local_aisn_sim_field_exists('materialtitles')) {
        $record->materialtitles = json_encode(array_values($materialtitles), JSON_UNESCAPED_UNICODE);
    }

    if (local_aisn_sim_field_exists('resulttext')) {
        $record->resulttext = core_text::substr($resulttext, 0, 65000);
    }

    $record->timecreated = $now;

    if (local_aisn_sim_field_exists('timemodified')) {
        $record->timemodified = $now;
    }

        // AISN_SIM_DEDUPE_SAVE_SAFE_V3
    if (function_exists('local_aisn_sim_upsert_record')) {
        local_aisn_sim_upsert_record($record);
    } else {
        $DB->insert_record('local_aiskillnav_sim', $record);
    }
}

/**
 * AISN_SIM_DEDUPE_CORE_SAFE_V3
 * Dedupe sicuro per simulazioni salvate.
 */

if (!function_exists('local_aisn_sim_dupe_normalize_value')) {
    function local_aisn_sim_dupe_normalize_value($value, int $max = 12000): string {
        $text = (string)($value ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r", "\t", "\xC2\xA0"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        $text = trim((string)$text);

        if (class_exists('core_text')) {
            $text = core_text::strtolower($text);
            if (core_text::strlen($text) > $max) {
                $text = core_text::substr($text, 0, $max);
            }
        } else {
            $text = strtolower($text);
            if (strlen($text) > $max) {
                $text = substr($text, 0, $max);
            }
        }

        return trim($text);
    }
}

if (!function_exists('local_aisn_sim_dupe_json_key')) {
    function local_aisn_sim_dupe_json_key($value): string {
        if (is_array($value)) {
            $arr = $value;
        } else {
            $raw = (string)($value ?? '');
            $decoded = json_decode($raw, true);
            $arr = is_array($decoded) ? $decoded : [$raw];
        }

        $arr = array_map(static function($item): string {
            return trim((string)$item);
        }, $arr);

        $arr = array_values(array_filter($arr, static function($item): bool {
            return $item !== '';
        }));

        sort($arr, SORT_NATURAL | SORT_FLAG_CASE);

        return implode('|', $arr);
    }
}

if (!function_exists('local_aisn_sim_record_signature')) {
    function local_aisn_sim_record_signature(stdClass $record): string {
        $courseid = (int)($record->courseid ?? 0);
        $userid = (int)($record->userid ?? 0);

        $topic = local_aisn_sim_dupe_normalize_value((string)($record->topic ?? $record->title ?? ''), 600);
        $level = local_aisn_sim_dupe_normalize_value((string)($record->level ?? ''), 80);

        $materialkey = '';
        if (isset($record->materialids) && trim((string)$record->materialids) !== '') {
            $materialkey = local_aisn_sim_dupe_json_key($record->materialids);
        } else if (isset($record->materialtitles) && trim((string)$record->materialtitles) !== '') {
            $materialkey = local_aisn_sim_dupe_json_key($record->materialtitles);
        }

        $result = '';
        if (isset($record->resulttext) && trim((string)$record->resulttext) !== '') {
            $result = (string)$record->resulttext;
        } else if (isset($record->description) && trim((string)$record->description) !== '') {
            $result = (string)$record->description;
        }

        $resultkey = local_aisn_sim_dupe_normalize_value($result, 16000);

        return sha1(json_encode([
            'courseid' => $courseid,
            'userid' => $userid,
            'topic' => $topic,
            'level' => $level,
            'materials' => $materialkey,
            'result' => $resultkey,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('local_aisn_sim_unique_records')) {
    function local_aisn_sim_unique_records(array $records): array {
        $seen = [];
        $out = [];

        foreach ($records as $key => $record) {
            if (!is_object($record)) {
                continue;
            }

            $sig = local_aisn_sim_record_signature($record);

            if (isset($seen[$sig])) {
                continue;
            }

            $seen[$sig] = true;
            $out[$key] = $record;
        }

        return $out;
    }
}

if (!function_exists('local_aisn_sim_upsert_record')) {
    function local_aisn_sim_upsert_record(stdClass $record): int {
        global $DB;

        $courseid = (int)($record->courseid ?? 0);

        if ($courseid <= 0) {
            return (int)$DB->insert_record('local_aiskillnav_sim', $record);
        }

        $sig = local_aisn_sim_record_signature($record);
        $existing = $DB->get_records('local_aiskillnav_sim', ['courseid' => $courseid], 'timecreated DESC, id DESC');
        $matches = [];

        foreach ($existing as $old) {
            if (local_aisn_sim_record_signature($old) === $sig) {
                $matches[] = (int)$old->id;
            }
        }

        if (!empty($matches)) {
            $keepid = (int)$matches[0];
            $record->id = $keepid;

            if (isset($record->timecreated)) {
                unset($record->timecreated);
            }

            if (function_exists('local_aisn_sim_field_exists') && local_aisn_sim_field_exists('timemodified')) {
                $record->timemodified = time();
            }

            $DB->update_record('local_aiskillnav_sim', $record);

            foreach (array_slice($matches, 1) as $deleteid) {
                $DB->delete_records('local_aiskillnav_sim', ['id' => (int)$deleteid]);
            }

            return $keepid;
        }

        return (int)$DB->insert_record('local_aiskillnav_sim', $record);
    }
}
