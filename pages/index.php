<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', 0, PARAM_INT);

if (!$courseid || $courseid <= SITEID) {
    print_error('missingparam', 'error', '', 'courseid');
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/index.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Skill Navigator');
$PAGE->set_heading('AI Skill Navigator');

function local_aisn_index_can_manage(context_course $context): bool {
    return has_capability('moodle/course:update', $context)
        || has_capability('moodle/course:manageactivities', $context)
        || has_capability('local/aiskillnavigator:managematerials', $context)
        || has_capability('local/aiskillnavigator:manageassessments', $context)
        || has_capability('local/aiskillnavigator:viewteacher', $context);
}

function local_aisn_index_card(
    string $title,
    string $description,
    string $path,
    int $courseid,
    string $buttonlabel,
    string $buttonclass,
    string $badge = ''
): string {
    $html = html_writer::start_div('col-md-6 col-xl-4 mb-4');
    $html .= html_writer::start_div('card h-100');
    $html .= html_writer::start_div('card-body d-flex flex-column');

    if ($badge !== '') {
        $html .= html_writer::div(s($badge), 'badge badge-light mb-3 align-self-start');
    }

    $html .= html_writer::tag('h3', s($title), ['class' => 'h4 mb-2']);
    $html .= html_writer::tag('p', s($description), ['class' => 'text-muted flex-grow-1']);

    $html .= html_writer::link(
        new moodle_url($path, ['courseid' => $courseid]),
        s($buttonlabel),
        ['class' => $buttonclass . ' btn-block mt-3']
    );

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}

function local_aisn_index_stat_card(string $value, string $label, string $class = ''): string {
    $html = html_writer::start_div('col-md-4 mb-3');
    $html .= html_writer::start_div('card card-body text-center ' . $class);
    $html .= html_writer::tag('h3', s($value));
    $html .= html_writer::tag('p', s($label), ['class' => 'mb-0 text-muted']);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}

$isadmin = is_siteadmin();
// AISN_ADMIN_ONLY_SEES_BOTH_SIDES_V2
// Admin vede sia strumenti docente sia strumenti studente.
// Docente vede solo strumenti docente.
// Studente vede solo strumenti studente.
$isteacher = local_aisn_index_can_manage($context) || $isadmin;
$isstudent = $isadmin || (is_enrolled($context, $USER, '', true) && !$isteacher);

echo $OUTPUT->header();

if (function_exists('local_aiskillnavigator_print_inline_styles')) {
    local_aiskillnavigator_print_inline_styles();
}

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Skill Navigator');

if ($isteacher) {
    echo html_writer::tag(
        'p',
        'Teacher workspace for managing course materials, AI activities and learning analytics.',
        ['class' => 'lead']
    );

    echo html_writer::start_div('row');
    echo local_aisn_index_stat_card('Teacher', 'Current role');
    echo local_aisn_index_stat_card('RAG enabled', 'Course-aware materials');
    echo local_aisn_index_stat_card('Safe mode', 'External AI guarded');
    echo html_writer::end_div();

    echo html_writer::tag('h3', 'Teacher tools');

    echo html_writer::start_div('row');
    echo local_aisn_index_card(
        'Teacher dashboard',
        'Monitor class progress, student attempts and teaching signals.',
        '/local/aiskillnavigator/pages/teacher.php',
        $courseid,
        'Open teacher dashboard',
        'btn btn-outline-secondary',
        'Analytics'
    );
    echo local_aisn_index_card(
        'Tutor analyst',
        'Analyse questions asked by students to the AI tutor.',
        '/local/aiskillnavigator/pages/tutor_analytics.php',
        $courseid,
        'Open Tutor analyst',
        'btn btn-outline-info',
        'Tutor-as-Sensor'
    );
    echo local_aisn_index_card(
        'Initial/final tests',
        'Create, edit and manage initial and final assessment tests.',
        '/local/aiskillnavigator/pages/teacher_assessments.php',
        $courseid,
        'Open tests',
        'btn btn-outline-success',
        'Assessment'
    );
    echo local_aisn_index_card(
        'Learning-gap analysis',
        'Review learning gaps and weak areas from student results.',
        '/local/aiskillnavigator/pages/gap_analysis.php',
        $courseid,
        'Open gap analysis',
        'btn btn-outline-warning',
        'Insights'
    );
    echo local_aisn_index_card(
        'AI Course Builder',
        'Build Moodle course sections and resources with AI assistance.',
        '/local/aiskillnavigator/pages/course_builder.php',
        $courseid,
        'Open AI Course Builder',
        'btn btn-outline-info',
        'Course design'
    );
    echo local_aisn_index_card(
        'AI Simulator Finder',
        'Generate simulator ideas and find online tools for practical activities.',
        '/local/aiskillnavigator/pages/simulator_finder.php',
        $courseid,
        'Open Simulator Finder',
        'btn btn-outline-info',
        'Activities'
    );
    echo html_writer::end_div();

    echo html_writer::tag('h3', 'Knowledge base');

    echo html_writer::start_div('row');
    echo local_aisn_index_card(
        'Course materials / RAG',
        'Synchronize course files, inspect extracted text, manage AI/OCR policy and RAG chunks.',
        '/local/aiskillnavigator/pages/teacher_materials.php',
        $courseid,
        'Open course materials / RAG',
        'btn btn-outline-success',
        'Materials'
    );
    echo html_writer::end_div();

}

if ($isstudent) {
    echo html_writer::tag(
        'p',
        'Student workspace for course-aware AI tutoring, quizzes, assessments and adaptive review.',
        ['class' => 'lead']
    );

    echo html_writer::start_div('row');
    echo local_aisn_index_stat_card('Student', 'Current role');
    echo local_aisn_index_stat_card('Course AI', 'Tools filtered for students');
    echo local_aisn_index_stat_card('Personalized', 'Review and practice');
    echo html_writer::end_div();

    echo html_writer::tag('h3', 'Student tools');

    echo html_writer::start_div('row');
    echo local_aisn_index_card(
        'AI Assessments',
        'Complete initial and final assessments for this course.',
        '/local/aiskillnavigator/pages/assessment.php',
        $courseid,
        'Open AI Assessments',
        'btn btn-outline-success',
        'Assessment'
    );
    echo local_aisn_index_card(
        'AI Tutor',
        'Ask course-aware questions based on the available learning materials.',
        '/local/aiskillnavigator/pages/tutor.php',
        $courseid,
        'Open AI Tutor',
        'btn btn-outline-primary',
        'Tutor'
    );
    echo local_aisn_index_card(
        'Adaptive review',
        'Review weak areas with adaptive explanations and practice support.',
        '/local/aiskillnavigator/pages/adaptive_review.php',
        $courseid,
        'Open adaptive review',
        'btn btn-outline-warning',
        'Review'
    );
    echo local_aisn_index_card(
        'AI Quiz',
        'Generate a quiz on course topics and test your understanding.',
        '/local/aiskillnavigator/pages/quizgenerator.php',
        $courseid,
        'Open AI Quiz',
        'btn btn-outline-primary',
        'Practice'
    );
    echo local_aisn_index_card(
        'AI Mind Map',
        'Create a visual concept map from a topic or learning material.',
        '/local/aiskillnavigator/pages/mindmapgenerator.php',
        $courseid,
        'Open AI Mind Map',
        'btn btn-outline-primary',
        'Map'
    );
    echo html_writer::end_div();

} else {
    echo html_writer::div('You are not enrolled in this course.', 'alert alert-info');
}

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary mt-3']
);

echo html_writer::end_div();

echo $OUTPUT->footer();
