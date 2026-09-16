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
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/markdown_table_formatter.php');
local_aisn_start_mdtable_formatter();
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');
require_once(__DIR__ . '/../includes/tutor_signal_helper.php');
require_once(__DIR__ . '/../includes/ai_output_helper.php');
require_once(__DIR__ . '/../includes/ai_response_guard.php');
require_once(__DIR__ . '/../includes/tutor_prompt_helper.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

if (function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('page_tutor_title', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('page_tutor_heading', 'local_aiskillnavigator'));
/**
 * Local aiskillnavigator tutor limit context helper.
 */
function local_aiskillnavigator_tutor_limit_context(string $text, int $limit = 9000): string {
    $text = local_aiskillnavigator_fix_mojibake(trim($text));

    if (\core_text::strlen($text) > $limit) {
        return \core_text::substr($text, 0, $limit) . "\n[Content truncated]";
    }

    return $text;
}


/**
 * Local aisn tutor cleanup answer helper.
 */
function local_aisn_tutor_cleanup_answer(string $answer): string {
    $answer = trim($answer);

    $patterns = [
        '/\n?\s*Se vuoi,?\s+posso\s+.*$/ius',
        '/\n?\s*Fammi sapere\s+.*$/ius',
        '/\n?\s*Posso fornirti\s+.*$/ius',
        '/\n?\s*Dimmi se vuoi\s+.*$/ius',
        '/\n?\s*Se ti serve,?\s+posso\s+.*$/ius',
    ];

    foreach ($patterns as $pattern) {
        $answer = preg_replace($pattern, '', $answer);
    }

    // Correzioni leggere di formule ricorrenti brutte.
    $answer = str_replace('Quando si usa Nei database', 'Quando si usa nei database', $answer);
    $answer = str_replace('SQL Copy', 'CQL', $answer);
    $answer = str_replace('JavaScript Copy', 'JavaScript', $answer);

    return trim($answer);
}
/**
 * Local aiskillnavigator tutor call ai helper.
 */
function local_aiskillnavigator_tutor_call_ai(string $prompt, string $systemprompt): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate($prompt, 2600, $systemprompt);
        }

        if (
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_config') &&
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_selector')
        ) {
            $config = new \local_aiskillnavigator\service\provider\ai_provider_config();
            $selector = new \local_aiskillnavigator\service\provider\ai_provider_selector();
            $provider = $selector->create($config);
            return $provider->generate($prompt, 2600, $systemprompt);
        }
    } catch (Throwable $e) {
        return 'AI error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials((int)$courseid);

$sourcemode = local_aiskillnavigator_material_source_mode_from_request(-1);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);
$question = optional_param('question', '', PARAM_RAW_TRIMMED);

if ($sourcemode === 'all') {
    $sourcemode = 'selected';
}

$answer = '';
$error = '';
$usedmaterialnames = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($question === '') {
        $error = 'Write a question first.';
    } else {
        $selectedmaterials = local_aiskillnavigator_material_source_selected_materials(
            $readablematerials,
            $sourcemode,
            $selectedmaterialids
        );

        if ($sourcemode === 'selected' && empty($selectedmaterials)) {
            $error = 'Select at least one course material allowed for the current AI provider.';
        }

        if ($error === '') {
            if ($sourcemode === 'manual') {
                // phpcs:ignore moodle.Files.LineLength
                $systemprompt = local_aiskillnavigator_tutor_system_prompt(false);
                $prompt = "Student question:\n" . $question;
            } else {
                $contextparts = [];

                foreach ($selectedmaterials as $material) {
                    $name = local_aiskillnavigator_material_source_clean_title($material);
                    $usedmaterialnames[] = $name;

                    $contextparts[] =
                        "SOURCE: " . $name . "\n" .
                        local_aiskillnavigator_tutor_limit_context((string)$material->content);
                }

                // phpcs:ignore moodle.Files.LineLength
                $systemprompt = local_aiskillnavigator_tutor_system_prompt(true);

                $prompt =
                    "Selected course materials:\n\n" .
                    implode("\n\n---\n\n", $contextparts) .
                    "\n\nStudent question:\n" .
                    $question;
            }

            // phpcs:ignore moodle.Files.LineLength
            $answer = local_aisn_tutor_cleanup_answer(local_aiskillnavigator_fix_mojibake(local_aiskillnavigator_tutor_call_ai($prompt, $systemprompt)));
            if (local_aiskillnavigator_ai_response_is_error($answer)) {
                $error = $answer !== '' ? $answer : get_string('ai_empty_response', 'local_aiskillnavigator');
                $answer = '';
            } else {
                local_aiskillnavigator_tutor_signal_store(
                    (int)$courseid, (int)$USER->id, $question, $sourcemode, $usedmaterialnames, $answer
                );
            }
        }
    }
}

$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/aisn_tutor_final_formatter.css', ['v' => time()]));
$PAGE->requires->js(new moodle_url('/local/aiskillnavigator/assets/aisn_tutor_final_formatter.js', ['v' => time()]));
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/aisn_tutor_clean_answer.css', ['v' => time()]));
echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Tutor');

echo html_writer::tag(
    'p',
    'Ask a question grounded only on the selected Moodle course materials.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo local_aiskillnavigator_material_source_selector_html(
    $readablematerials,
    null,
    $courseid,
    $sourcemode,
    $selectedmaterialids,
    '1. Source',
    ''
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', '2. Question');

echo html_writer::tag('textarea', s($question), [
    'name' => 'question',
    'id' => 'question',
    'class' => 'form-control',
    'rows' => 5,
    'placeholder' => 'Esempio: spiegami la differenza tra funzione lineare e funzione quadratica.',
    'required' => 'required',
]);

echo html_writer::start_div('mt-3');

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => 'Ask AI',
]);

echo ' ';

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');

if ($answer !== '') {
    // AISN_TUTOR_CLEAN_CARD_V2.
    echo html_writer::start_div('card mb-4 aisn-tutor-answer-card');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Answer', ['class' => 'aisn-tutor-answer-title']);

    if (!empty($usedmaterialnames)) {
        echo html_writer::div(
            'Used materials: ' . s(implode(', ', $usedmaterialnames)),
            'text-muted mb-3 aisn-used-materials'
        );
    } else {
        echo html_writer::div('Used materials: none', 'text-muted mb-3 aisn-used-materials');
    }

    echo html_writer::start_div('aisn-answer aisn-tutor-answer-body');
    echo local_aiskillnavigator_render_ai_answer($answer);
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

// phpcs:ignore moodle.Files.LineLength
echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_mdtable_assets')) {
    echo local_aisn_mdtable_assets();
}
// phpcs:ignore moodle.Files.LineLength
echo '<link rel="stylesheet" href="' . (new moodle_url('/local/aiskillnavigator/assets/aisn_answer_renderer_v3.css', ['v' => time()]))->out(false) . '">';
echo '<script>
window.MathJax = {
    tex: {
        inlineMath: [["\\\\(", "\\\\)"], ["$", "$"]],
        displayMath: [["\\\\[", "\\\\]"], ["$$", "$$"]]
    },
    svg: { fontCache: "global" }
};
</script>';
if ((string)get_config('local_aiskillnavigator', 'enablemathjaxcdn') === '1') {
    echo '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>';
}
// phpcs:ignore moodle.Files.LineLength
echo '<script src="' . (new moodle_url('/local/aiskillnavigator/assets/aisn_answer_renderer_v3.js', ['v' => time()]))->out(false) . '"></script>';
echo html_writer::tag('style', file_get_contents(__DIR__ . '/../assets/aisn_tutor_visual_override.css'));
// AISN_TUTOR_VISUAL_OVERRIDE_LOAD_V4.
echo html_writer::tag('style', file_get_contents(__DIR__ . '/../assets/aisn_tutor_dom_bridge.css'));
echo html_writer::script(file_get_contents(__DIR__ . '/../assets/aisn_tutor_dom_bridge.js'));
// AISN_TUTOR_DOM_BRIDGE_LOAD_V1.
echo html_writer::script(file_get_contents(__DIR__ . '/../assets/aisn_tutor_dom_direct_style.js'));
// AISN_TUTOR_DIRECT_STYLE_LOAD_V1.
echo $OUTPUT->footer();
