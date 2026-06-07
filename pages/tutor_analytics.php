<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/tutor_signal_helper.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/tutor_analytics.php', ['courseid' => $courseid]));
$PAGE->set_title('Tutor analyst');
$PAGE->set_heading('Tutor analyst');

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid aisn-tutor-analytics-page');

echo html_writer::tag('h2', 'Tutor analyst');
echo html_writer::tag(
    'p',
    'Le domande fatte dagli studenti al tutor vengono raccolte come segnali didattici: ability richieste, dubbi ricorrenti e argomenti da rinforzare.',
    ['class' => 'lead']
);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher.php', ['courseid' => $courseid]),
        'Back to teacher dashboard',
        ['class' => 'btn btn-secondary']
    ) . ' ' .
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        'Back to course',
        ['class' => 'btn btn-outline-secondary']
    ),
    'mb-4'
);

echo local_aiskillnavigator_tutor_signal_teacher_panel((int)$courseid);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher.php', ['courseid' => $courseid]),
        'Back to teacher dashboard',
        ['class' => 'btn btn-secondary']
    ),
    'mt-4'
);

echo html_writer::end_div();

if (function_exists('local_aisn_ai_output_formatter_assets')) {
    echo local_aisn_ai_output_formatter_assets();
}

echo $OUTPUT->footer();
