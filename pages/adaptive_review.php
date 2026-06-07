<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/adaptive_learning_helper.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/adaptive_review.php', ['courseid' => $courseid]));
$PAGE->set_title('Adaptive review');
$PAGE->set_heading('Adaptive review');

function local_aiskillnavigator_adaptive_call_ai(string $prompt): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate(
                $prompt,
                2600,
                'You are an adaptive Moodle tutor. Generate remedial explanations and practice questions based on weak abilities. Do not invent private data.'
            );
        }
    } catch (Throwable $e) {
        return 'AI error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}

$profile = local_aiskillnavigator_adaptive_collect_student($courseid, (int)$USER->id);
$airesult = '';

if ($action === 'generate') {
    require_sesskey();

    $prompt = local_aiskillnavigator_adaptive_prompt_context($profile);
    $prompt .= "\n\nCreate in Italian:\n";
    $prompt .= "1. short diagnosis of the student's weak points\n";
    $prompt .= "2. simple explanation for each weak skill\n";
    $prompt .= "3. 5 new practice questions using different wording from previous tests\n";
    $prompt .= "4. a final checklist for improvement\n";

    $airesult = local_aiskillnavigator_adaptive_call_ai($prompt);
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'Adaptive review');

echo html_writer::tag(
    'p',
    'This page estimates the student weak abilities from previous quiz/test answers and generates targeted recovery practice.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if (empty($profile['skills'])) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', 'No learning data yet');
    echo html_writer::tag('p', 'Complete at least one AI quiz or initial/final test. Then this page will build a personalized weak-ability profile.', ['class' => 'text-muted']);
    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Detected weak abilities');

    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::tag(
        'tr',
        html_writer::tag('th', 'Ability') .
        html_writer::tag('th', 'Correct') .
        html_writer::tag('th', 'Wrong') .
        html_writer::tag('th', 'Estimated mastery')
    );

    foreach ($profile['skills'] as $skill) {
        echo html_writer::tag(
            'tr',
            html_writer::tag('td', s($skill['Ability'])) .
            html_writer::tag('td', (int)$skill['correct']) .
            html_writer::tag('td', (int)$skill['wrong']) .
            html_writer::tag('td', s($skill['mastery'] . '%'))
        );
    }

    echo html_writer::end_tag('table');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/aiskillnavigator/pages/adaptive_review.php', ['courseid' => $courseid]),
    ]);

    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'generate']);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => 'Generate adaptive review',
    ]);

    echo html_writer::end_tag('form');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($airesult !== '') {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', 'Personalized recovery');
    echo html_writer::tag('pre', s($airesult), [
        'style' => 'white-space: pre-wrap; background:#0f172a; color:#e5e7eb; padding:22px; border-radius:18px; line-height:1.55;',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();

echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();



