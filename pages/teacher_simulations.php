<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/simulator_materials_helper.php');
require_once(__DIR__ . '/../includes/saved_simulation_helper.php');

global $DB, $PAGE, $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$simulationid = optional_param('id', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$params = ['courseid' => $courseid];
if ($action !== '') {
    $params['action'] = $action;
}
if ($simulationid > 0) {
    $params['id'] = $simulationid;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', $params));
$PAGE->set_title('Saved simulations');
$PAGE->set_heading('Saved simulations');

local_aisn_sim_ensure_table();

$listurl = new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', ['courseid' => $courseid]);

if ($action === 'delete' && $simulationid > 0) {
    require_sesskey();

    $DB->delete_records('local_aiskillnav_sim', [
        'id' => $simulationid,
        'courseid' => $courseid,
    ]);

    redirect($listurl, 'Simulation deleted.', 1);
}

function local_aisn_saved_material_titles(stdClass $record): array {
    $titles = json_decode((string)($record->materialtitles ?? ''), true);
    return is_array($titles) ? array_values(array_filter(array_map('strval', $titles))) : [];
}

function local_aisn_saved_render_materials(array $titles): string {
    if (empty($titles)) {
        return '';
    }

    $html = html_writer::start_div('aisn-web-materials');
    $html .= html_writer::span('Materials', 'aisn-web-materials-label');

    foreach ($titles as $title) {
        $html .= html_writer::span(s($title), 'aisn-web-material-pill');
    }

    $html .= html_writer::end_div();
    return $html;
}

function local_aisn_saved_record_text(stdClass $record): string {
    $text = trim((string)($record->resulttext ?? ''));

    if ($text !== '') {
        return $text;
    }

    return trim((string)($record->description ?? ''));
}

function local_aisn_saved_record_title(stdClass $record): string {
    $topic = trim((string)($record->topic ?? ''));

    if ($topic !== '') {
        return $topic;
    }

    $title = trim((string)($record->title ?? ''));

    return $title !== '' ? $title : 'Generated simulator exercise';
}

function local_aisn_saved_record_level(stdClass $record): string {
    $level = trim((string)($record->level ?? ''));

    return $level !== '' ? $level : '-';
}

function local_aisn_saved_css(): string {
    return '
/* AISN_SAVED_SIMULATIONS_LINK_DETAIL_V1 */

.aisn-saved-wrap {
    max-width: 1180px;
    margin: 0 auto;
}

.aisn-saved-hero {
    margin: 28px 0 20px;
    padding: 34px 38px;
    border-radius: 24px;
    background: linear-gradient(135deg, #1476d4 0%, #3b82f6 52%, #60a5fa 100%);
    color: #fff;
    box-shadow: 0 18px 42px rgba(37, 99, 235, .18);
}

.aisn-saved-hero h2 {
    margin: 0;
    color: #fff;
    font-size: 34px;
    font-weight: 950;
    letter-spacing: -.045em;
}

.aisn-saved-hero p {
    margin: 10px 0 0;
    color: rgba(255,255,255,.92);
    font-size: 16px;
}

.aisn-saved-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin: 0 0 22px;
}

.aisn-saved-search {
    max-width: 520px;
    border-radius: 13px !important;
    padding: 12px 14px !important;
    border: 1px solid #cbd5e1 !important;
}

.aisn-saved-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 20px;
}

.aisn-saved-card {
    border: 1px solid #dbe7f3;
    border-radius: 24px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    box-shadow: 0 18px 44px rgba(15, 23, 42, .08);
    padding: 24px;
    display: flex;
    flex-direction: column;
    min-height: 265px;
}

.aisn-saved-card h3 {
    margin: 0 0 12px;
    color: #071226;
    font-size: 24px;
    font-weight: 950;
    letter-spacing: -.035em;
}

.aisn-saved-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 14px;
}

.aisn-saved-pill {
    display: inline-flex;
    align-items: center;
    padding: 7px 11px;
    border-radius: 999px;
    background: #eef6ff;
    border: 1px solid #cfe6ff;
    color: #27496d;
    font-size: 12px;
    font-weight: 850;
}

.aisn-saved-material-one {
    color: #475569;
    font-size: 14px;
    line-height: 1.45;
    margin: 0 0 12px;
}

.aisn-saved-preview {
    color: #334155;
    line-height: 1.62;
    font-size: 15px;
    margin: 0 0 20px;
    flex: 1;
}

.aisn-saved-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: auto;
}

.aisn-saved-open {
    border-radius: 13px !important;
    font-weight: 900 !important;
    padding: 10px 15px !important;
}

.aisn-saved-delete {
    border-radius: 13px !important;
    font-weight: 900 !important;
    padding: 10px 15px !important;
}

.aisn-web-page {
    max-width: 1040px;
    margin: 0 auto;
}

.aisn-web-simulation {
    border: 1px solid #dbe7f3;
    border-radius: 30px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    box-shadow: 0 24px 62px rgba(15, 23, 42, .11);
    overflow: hidden;
    margin: 28px 0 34px;
}

.aisn-web-header {
    padding: 38px 44px;
    background:
        radial-gradient(circle at top left, rgba(96, 165, 250, .24), transparent 36%),
        linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #60a5fa 100%);
    color: #fff;
}

.aisn-web-header h2 {
    color: #fff;
    margin: 0;
    font-size: 38px;
    line-height: 1.08;
    font-weight: 950;
    letter-spacing: -.05em;
}

.aisn-web-header p {
    margin: 12px 0 0;
    color: rgba(255,255,255,.9);
}

.aisn-web-body {
    padding: 38px 44px 44px;
}

.aisn-web-materials {
    margin: 0 0 28px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 9px;
}

.aisn-web-materials-label {
    font-weight: 950;
    color: #0f172a;
    margin-right: 4px;
}

.aisn-web-material-pill {
    display: inline-flex;
    padding: 7px 11px;
    border-radius: 999px;
    background: #eef6ff;
    border: 1px solid #cfe6ff;
    color: #27496d;
    font-size: 12px;
    font-weight: 850;
}

.aisn-web-content {
    color: #0f172a;
    font-size: 18px;
    line-height: 1.82;
}

.aisn-web-content p {
    margin: 0 0 20px;
}

.aisn-web-section-title {
    margin: 32px 0 14px;
    padding-left: 15px;
    border-left: 5px solid #0f6fd9;
    color: #071226;
    font-size: 25px;
    font-weight: 950;
    letter-spacing: -.035em;
}

.aisn-web-list {
    margin: 10px 0 22px 28px;
    padding: 0;
}

.aisn-web-list li {
    margin-bottom: 9px;
}

.aisn-web-linkline a {
    font-weight: 900;
}

.aisn-web-empty {
    padding: 18px;
    border-radius: 16px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-weight: 850;
}

.aisn-web-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 22px 0 42px;
}

.aisn-web-actions .btn {
    border-radius: 13px !important;
    font-weight: 900 !important;
    padding: 10px 15px !important;
}

.aisn-bad-old-record {
    margin: 0 0 24px;
    padding: 14px 16px;
    border-radius: 15px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-weight: 850;
}

@media (max-width: 900px) {
    .aisn-saved-grid {
        grid-template-columns: 1fr;
    }

    .aisn-web-header,
    .aisn-web-body {
        padding: 28px 22px;
    }

    .aisn-web-header h2 {
        font-size: 30px;
    }

    .aisn-web-content {
        font-size: 16px;
        line-height: 1.72;
    }
}
';
}

echo $OUTPUT->header();

if (function_exists('local_aiskillnavigator_print_inline_styles')) {
    local_aiskillnavigator_print_inline_styles();
}

echo html_writer::tag('style', local_aisn_saved_css());

if ($action === 'view' && $simulationid > 0) {
    $record = $DB->get_record('local_aiskillnav_sim', [
        'id' => $simulationid,
        'courseid' => $courseid,
    ], '*', MUST_EXIST);

    $titles = local_aisn_saved_material_titles($record);
    $title = local_aisn_saved_record_title($record);
    $recordtext = local_aisn_saved_record_text($record);
    $clean = local_aisn_sim_clean_generated_result($recordtext);

    echo html_writer::start_div('container-fluid aisn-web-page');

    echo html_writer::start_div('aisn-web-simulation');

    echo html_writer::start_div('aisn-web-header');
    echo html_writer::tag('h2', s($title));
    echo html_writer::tag(
        'p',
        'Level: ' . s(local_aisn_saved_record_level($record)) . ' · Date: ' . s(userdate((int)$record->timecreated))
    );
    echo html_writer::end_div();

    echo html_writer::start_div('aisn-web-body');

    echo local_aisn_saved_render_materials($titles);

    if (local_aisn_saved_sim_is_bad_raw($recordtext)) {
        echo html_writer::div(
            'Nota: questo record era stato salvato con testo sporco della pagina Moodle. La vista dettaglio mostra solo la parte utile recuperata.',
            'aisn-bad-old-record'
        );
    }

    echo html_writer::div(local_aisn_saved_sim_render_content($clean), 'aisn-web-content');

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('aisn-web-actions');

    echo html_writer::link(
        $listurl,
        'Back to saved simulations',
        ['class' => 'btn btn-secondary']
    );

    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
        'Back to Simulator Finder',
        ['class' => 'btn btn-outline-secondary']
    );

    echo html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        'Back to course',
        ['class' => 'btn btn-outline-secondary']
    );

    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', [
            'courseid' => $courseid,
            'id' => (int)$record->id,
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]),
        'Delete',
        [
            'class' => 'btn btn-danger',
            'onclick' => "return confirm('Eliminare questa simulazione salvata?');",
        ]
    );

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

$records = $DB->get_records('local_aiskillnav_sim', ['courseid' => $courseid], 'timecreated DESC, id DESC');

// AISN_SIM_DEDUPE_LIST_V1
// Difesa UI: anche se il DB contiene duplicati vecchi, la lista mostra una sola simulazione.
if (function_exists('local_aisn_sim_unique_records')) {
    $records = local_aisn_sim_unique_records($records);
}

echo html_writer::start_div('container-fluid aisn-saved-wrap');

echo html_writer::start_div('aisn-saved-hero');
echo html_writer::tag('h2', 'Saved simulations');
echo html_writer::tag('p', 'Archivio delle simulazioni generate. Apri una simulazione per visualizzarla come pagina dedicata.');
echo html_writer::end_div();

echo html_writer::start_div('aisn-saved-toolbar');
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'id' => 'aisn-saved-sim-search',
    'class' => 'form-control aisn-saved-search',
    'placeholder' => 'Search saved simulation...',
]);
echo html_writer::link(
    new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
    'New simulation',
    ['class' => 'btn btn-primary aisn-saved-open']
);
echo html_writer::end_div();

if (empty($records)) {
    echo html_writer::div('No saved simulations yet.', 'alert alert-info');
} else {
    echo html_writer::start_div('aisn-saved-grid');

    foreach ($records as $record) {
        $titles = local_aisn_saved_material_titles($record);
        $title = local_aisn_saved_record_title($record);
        $recordtext = local_aisn_saved_record_text($record);
        $preview = local_aisn_saved_sim_preview($recordtext);

        echo html_writer::start_div('aisn-saved-card', [
            'data-aisn-saved-card' => '1',
        ]);

        echo html_writer::tag('h3', s($title));

        echo html_writer::start_div('aisn-saved-meta');
        echo html_writer::span('Level: ' . s(local_aisn_saved_record_level($record)), 'aisn-saved-pill');
        echo html_writer::span(s(userdate((int)$record->timecreated)), 'aisn-saved-pill');
        echo html_writer::end_div();

        if (!empty($titles)) {
            echo html_writer::tag(
                'p',
                'Materials: ' . s(implode(', ', array_slice($titles, 0, 3))) . (count($titles) > 3 ? '...' : ''),
                ['class' => 'aisn-saved-material-one']
            );
        }

        echo html_writer::tag('p', s($preview), ['class' => 'aisn-saved-preview']);

        echo html_writer::start_div('aisn-saved-actions');

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', [
                'courseid' => $courseid,
                'action' => 'view',
                'id' => (int)$record->id,
            ]),
            'Apri simulazione',
            ['class' => 'btn btn-primary aisn-saved-open']
        );

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', [
                'courseid' => $courseid,
                'id' => (int)$record->id,
                'action' => 'delete',
                'sesskey' => sesskey(),
            ]),
            'Delete',
            [
                'class' => 'btn btn-danger aisn-saved-delete',
                'onclick' => "return confirm('Eliminare questa simulazione salvata?');",
            ]
        );

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
        'Back to Simulator Finder',
        ['class' => 'btn btn-secondary']
    ) . ' ' .
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        'Back to course',
        ['class' => 'btn btn-secondary']
    ),
    'mt-4 mb-5'
);

echo html_writer::script("
(function () {
    const search = document.getElementById('aisn-saved-sim-search');
    const cards = Array.from(document.querySelectorAll('[data-aisn-saved-card]'));

    if (!search) { return; }

    search.addEventListener('input', function () {
        const q = String(search.value || '').toLowerCase().trim();

        cards.forEach(function (card) {
            const text = String(card.textContent || '').toLowerCase();
            card.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
");

echo html_writer::end_div();

echo $OUTPUT->footer();

