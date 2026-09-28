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

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/material_ai_policy.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/knowledge_graph_helper.php');
require_once(__DIR__ . '/../includes/material_exclusion_helper.php');
require_once(__DIR__ . '/../includes/mojibake_guard.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$materialid = optional_param('materialid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

require_capability('local/aiskillnavigator:managematerials', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('page_teacher_materials_title', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('page_teacher_materials_heading', 'local_aiskillnavigator'));
/**
 * Local aisn tm table exists helper.
 *
 * @param string $name Name.
 */
function local_aisn_tm_table_exists(string $name): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($name));
}

/**
 * Local aisn tm get material helper.
 *
 * @param int $materialid Stored material ID.
 * @param int $courseid Moodle course ID.
 */
function local_aisn_tm_get_material(int $materialid, int $courseid): stdClass {
    global $DB;

    $material = $DB->get_record('local_aiskillnavigator_material', [
        'id' => $materialid,
        'courseid' => $courseid,
    ]);

    if (!$material) {
        throw new moodle_exception('invalidrecord', 'error');
    }

    return $material;
}

/**
 * Local aisn tm visible course materials helper.
 *
 * @param int $courseid Moodle course ID.
 */
function local_aisn_tm_visible_course_materials(int $courseid): array {
    global $DB;

    if (!local_aisn_tm_table_exists('local_aiskillnavigator_material')) {
        return [];
    }

    local_aiskillnavigator_sync_course_resources($courseid, 0, true);

    $records = $DB->get_records('local_aiskillnavigator_material', [
        'courseid' => $courseid,
        'materialtype' => 'course_resource',
    ], 'timemodified DESC, id DESC');

    $modinfo = get_fast_modinfo($courseid);
    $bycmid = [];

    foreach ($records as $record) {
        $cmid = local_aisn_course_cm_id_from_material_title((string)($record->title ?? ''));

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

    $visible = [];

    foreach ($bycmid as $record) {
        $visible[(int)$record->id] = $record;
    }

    return $visible;
}


/**
 * Local aisn tm cm id from title helper.
 *
 * @param string $title Title.
 */
function local_aisn_tm_cm_id_from_title(string $title): int {
    if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', $title, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

/**
 * Local aisn tm clean course title helper.
 *
 * @param string $title Title.
 */
function local_aisn_tm_clean_course_title(string $title): string {
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/', '', $title);
    return trim((string)$title);
}

/**
 * Local aisn tm excerpt helper.
 *
 * @param string $content Content to process.
 * @param int $max Max.
 */
function local_aisn_tm_excerpt(string $content, int $max = 600): string {
    $content = trim(preg_replace('/\s+/u', ' ', strip_tags($content)));

    if (core_text::strlen($content) > $max) {
        return core_text::substr($content, 0, $max) . '...';
    }

    return $content;
}

/**
 * Local aisn tm chunk counts helper.
 *
 * @param array $materials Course materials used for this operation.
 */
function local_aisn_tm_chunk_counts(array $materials): array {
    global $DB;

    if (empty($materials) || !local_aisn_tm_table_exists('local_aiskillnavigator_chunk')) {
        return [];
    }

    $ids = array_keys($materials);
    [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mid');

    $sql = "SELECT materialid, COUNT(1) AS chunks
              FROM {local_aiskillnavigator_chunk}
             WHERE materialid $insql
          GROUP BY materialid";

    $rows = $DB->get_records_sql($sql, $params);
    $counts = [];

    foreach ($rows as $row) {
        $counts[(int)$row->materialid] = (int)$row->chunks;
    }

    return $counts;
}


/**
 * Local aisn tm material policy external allowed helper.
 *
 * @param stdClass $material Material.
 */
function local_aisn_tm_material_policy_external_allowed(stdClass $material): bool {
    if (function_exists('local_aiskillnavigator_material_external_allowed')) {
        return local_aiskillnavigator_material_external_allowed($material);
    }

    if (isset($material->externalaiallowed)) {
        return ((int)$material->externalaiallowed) === 1;
    }

    if (isset($material->aipolicy)) {
        return ((string)$material->aipolicy) === 'external_allowed';
    }

    return false;
}

/**
 * Local aisn tm set policy helper.
 *
 * @param stdClass $material Material.
 * @param bool $externalallowed Externalallowed.
 */
function local_aisn_tm_set_policy(stdClass $material, bool $externalallowed): void {
    global $DB;

    local_aiskillnavigator_set_material_ai_policy((int)$material->id, (int)$material->courseid, $externalallowed);

    if (function_exists('local_aisn_kg_rebuild_material') && function_exists('local_aisn_kg_delete_material')) {
        if ($externalallowed) {
            local_aisn_kg_rebuild_material((int)$material->id);
        } else {
            local_aisn_kg_delete_material((int)$material->id);
        }
    }
}

/**
 * Local aisn tm material cmid helper.
 *
 * @param stdClass $material Material.
 */
function local_aisn_tm_material_cmid(stdClass $material): int {
    if (isset($material->sourcecmid) && (int)$material->sourcecmid > 0) {
        return (int)$material->sourcecmid;
    }

    if (function_exists('local_aisn_course_cm_id_from_material_title')) {
        $cmid = local_aisn_course_cm_id_from_material_title((string)($material->title ?? ''));

        if ($cmid > 0) {
            return $cmid;
        }
    }

    if (function_exists('local_aisn_tm_cm_id_from_title')) {
        return local_aisn_tm_cm_id_from_title((string)($material->title ?? ''));
    }

    if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', (string)($material->title ?? ''), $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

/**
 * Local aisn tm delete material helper.
 *
 * @param stdClass $material Material.
 */
function local_aisn_tm_delete_material(stdClass $material): void {
    global $DB;

    $courseid = (int)($material->courseid ?? 0);
    $materialid = (int)($material->id ?? 0);
    $cmid = local_aisn_tm_material_cmid($material);
    if ($cmid > 0) {
        get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
        require_capability('moodle/course:manageactivities', context_module::instance($cmid));
    }

    $materialids = [];

    if ($materialid > 0) {
        $materialids[] = $materialid;
    }

    if ($courseid > SITEID && $cmid > 0) {
        // Blocca subito la ricomparsa nel RAG anche se la delete Moodle fallisse.
        if (function_exists('local_aisn_course_material_set_excluded')) {
            local_aisn_course_material_set_excluded($courseid, $cmid, true);
        }

        // Raccogli eventuali duplicati dello stesso course module.
        try {
            if (local_aisn_tm_table_exists('local_aiskillnavigator_material')) {
                $dbman = $DB->get_manager();
                $table = new xmldb_table('local_aiskillnavigator_material');
                $sourcefield = new xmldb_field('sourcecmid');

                if ($dbman->field_exists($table, $sourcefield)) {
                    $samecm = $DB->get_records('local_aiskillnavigator_material', [
                        'courseid' => $courseid,
                        'materialtype' => 'course_resource',
                        'sourcecmid' => $cmid,
                    ]);

                    foreach ($samecm as $row) {
                        $materialids[] = (int)$row->id;
                    }
                }

                // phpcs:ignore moodle.Files.LineLength
                $select = 'courseid = :courseid AND materialtype = :materialtype AND ' . $DB->sql_like('title', ':title', false, false);
                $params = [
                    'courseid' => $courseid,
                    'materialtype' => 'course_resource',
                    'title' => '[Course #' . $courseid . ' / cm #' . $cmid . ']%',
                ];

                $sametitle = $DB->get_records_select('local_aiskillnavigator_material', $select, $params);

                foreach ($sametitle as $row) {
                    $materialids[] = (int)$row->id;
                }
            }
        } catch (Throwable $e) {
            debugging('AI Skill Navigator material duplicate lookup failed before delete: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Elimina anche la risorsa o attività corrispondente dal corso Moodle.
        try {
            $cm = get_coursemodule_from_id('', $cmid, $courseid, false, IGNORE_MISSING);

            if ($cm && (int)$cm->course === $courseid) {
                course_delete_module($cmid);
            }
        } catch (Throwable $e) {
            debugging('AI Skill Navigator Moodle course module delete failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        unset_config('cm_ai_excluded_' . $cmid, 'local_aiskillnavigator');
        unset_config('cm_external_ai_' . $cmid, 'local_aiskillnavigator');
    }

    $materialids = array_values(array_unique(array_filter(array_map('intval', $materialids))));

    foreach ($materialids as $id) {
        if (function_exists('local_aisn_kg_delete_material')) {
            local_aisn_kg_delete_material($id);
        }
    }

    if (!empty($materialids) && local_aisn_tm_table_exists('local_aiskillnavigator_chunk')) {
        [$insql, $params] = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'mid');
        $DB->delete_records_select('local_aiskillnavigator_chunk', 'materialid ' . $insql, $params);
    }

    if (!empty($materialids) && local_aisn_tm_table_exists('local_aiskillnavigator_material')) {
        [$insql, $params] = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'mat');
        $DB->delete_records_select('local_aiskillnavigator_material', 'id ' . $insql, $params);
    }

    if ($courseid > SITEID) {
        rebuild_course_cache($courseid, true);
    }
}

if ($action !== '' && $materialid > 0) {
    \local_aiskillnavigator\service\request_access::require_write($context, 'local/aiskillnavigator:managematerials');

    $material = local_aisn_tm_get_material($materialid, $courseid);

    if ($action === 'allow') {
        local_aisn_tm_set_policy($material, true);
        redirect($PAGE->url, 'Material allowed for external AI providers.', 1);
    }

    if ($action === 'restrict') {
        local_aisn_tm_set_policy($material, false);
        redirect($PAGE->url, 'Material restricted to local AI only.', 1);
    }

    if ($action === 'delete') {
        local_aisn_tm_delete_material($material);
        redirect($PAGE->url, 'Material deleted from course and RAG.', 1);
    }
}

$materials = local_aisn_tm_visible_course_materials($courseid);
$chunkscounts = local_aisn_tm_chunk_counts($materials);

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::tag('style', '
body.path-local-aiskillnavigator #page-header,
body.path-local-aiskillnavigator #page-navbar,
body.path-local-aiskillnavigator .secondary-navigation,
body.path-local-aiskillnavigator nav.moremenu,
body.path-local-aiskillnavigator .moremenu,
body.path-local-aiskillnavigator .nav-tabs {
    display: none !important;
}

.aisn-material-policy-page {
    max-width: 1180px;
    margin: 0 auto;
}

.aisn-material-policy-page .alert {
    border-radius: 14px;
}

.aisn-material-policy-page .card {
    border-radius: 18px;
}
');


echo html_writer::start_div('container-fluid aisn-material-policy-page');

if (empty($materials)) {
    echo html_writer::div(
        // phpcs:ignore moodle.Files.LineLength
        'No visible course resources found. Add a file/page/resource in Moodle Edit mode or create material through AI Course Builder; this page will synchronize automatically.',
        'alert alert-warning'
    );

    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}


if (
    function_exists('local_aisn_prod_current_ai_is_local')
    && function_exists('local_aisn_prod_external_ai_globally_enabled')
    && !local_aisn_prod_current_ai_is_local()
    && !local_aisn_prod_external_ai_globally_enabled()
) {
    echo html_writer::div(
        // phpcs:ignore moodle.Files.LineLength
        'AISN_EXTERNAL_GLOBAL_GATE_NOTICE: External AI provider detected, but the global admin approval is disabled. Materials can still be marked as Allowed here, but they will not be sent to external AI until the admin enables Approve external AI for teacher materials.',
        'alert alert-warning'
    );
}


echo html_writer::tag('h3', 'Synchronized course materials');

echo html_writer::tag('style', '
.aisn-material-search-wrap {
    margin: 16px 0 22px;
}
.aisn-material-search {
    max-width: 520px;
    border-radius: 12px;
    padding: 12px 14px;
}
.aisn-bottom-back {
    margin-top: 28px;
}
');

echo html_writer::start_div('aisn-material-search-wrap');
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'id' => 'aisn-material-search',
    'class' => 'form-control aisn-material-search',
    'placeholder' => 'Search by title, filename, text or AI policy...',
]);
echo html_writer::end_div();

echo html_writer::tag('script', '
(function() {
    const search = document.getElementById("aisn-material-search");
    if (!search) { return; }

    const cards = Array.from(document.querySelectorAll("[data-aisn-material-card]"));

    search.addEventListener("input", function() {
        const q = String(search.value || "").toLowerCase().trim();

        cards.forEach(function(card) {
            const haystack = String(card.dataset.search || card.textContent || "").toLowerCase();
            card.style.display = haystack.indexOf(q) !== -1 ? "" : "none";
        });
    });
})();
');


foreach ($materials as $material) {
    $materialid = (int)$material->id;
    $title = local_aisn_tm_clean_course_title((string)$material->title);
    $content = (string)($material->content ?? '');
    $chunks = (int)($chunkscounts[$materialid] ?? 0);
    $policylabel = local_aiskillnavigator_ai_policy_label($material);
    $externalallowed = local_aisn_tm_material_policy_external_allowed($material);

    echo html_writer::start_div('card mb-3 shadow-sm', [
        'data-aisn-material-card' => '1',
        'data-search' => core_text::strtolower($title . ' ' . $content . ' ' . $policylabel),
    ]);
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h4', s($title));

    echo html_writer::tag(
        'p',
        'Type: course resource | RAG chunks: ' . $chunks . ' | AI policy: ' . s($policylabel),
        ['class' => 'text-muted']
    );

    echo html_writer::span(
        s($policylabel),
        local_aiskillnavigator_ai_policy_badge_class($material)
    );

    echo html_writer::tag('pre', s(local_aisn_tm_excerpt($content)), [
        'class' => 'mt-3 p-3 bg-light rounded',
        'style' => 'white-space: pre-wrap;',
    ]);

    echo html_writer::start_div('mt-3');
    if ($externalallowed) {
        echo $OUTPUT->single_button(
            new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
                'courseid' => $courseid,
                'materialid' => $materialid,
                'action' => 'restrict',
                'sesskey' => sesskey(),
            ]),
            'Restrict to local AI only',
            'post'
        );
    } else {
        echo $OUTPUT->single_button(
            new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
                'courseid' => $courseid,
                'materialid' => $materialid,
                'action' => 'allow',
                'sesskey' => sesskey(),
            ]),
            'Allow external AI',
            'post'
        );
    }

    echo $OUTPUT->single_button(
        new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
            'courseid' => $courseid,
            'materialid' => $materialid,
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]),
        'Delete from course and RAG',
        'post'
    );

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}


echo html_writer::div(
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        'Back to course',
        ['class' => 'btn btn-secondary']
    ),
    'aisn-bottom-back'
);

echo html_writer::end_div();

if (function_exists('local_aiskillnavigator_mojibake_guard')) {
    echo local_aiskillnavigator_mojibake_guard();
}
echo $OUTPUT->footer();
