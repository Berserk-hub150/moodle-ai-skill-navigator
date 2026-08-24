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
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/document_ocr_toggle_helper.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT);

require_login();

/**
 * Local aisn index card helper.
 */
function local_aisn_index_card(
    string $title,
    string $description,
    string $path,
    int $courseid,
    string $buttontext,
    string $buttonclass,
    string $badge = ''
): string {
    $url = new moodle_url($path, ['courseid' => $courseid]);

    $html = html_writer::start_div('col-md-4 mb-3');
    $html .= html_writer::start_div('card h-100 shadow-sm');
    $html .= html_writer::start_div('card-body');

    if ($badge !== '') {
        $html .= html_writer::span(s($badge), 'badge badge-light border mb-2');
    }

    $html .= html_writer::tag('h4', s($title), ['class' => 'card-title']);
    $html .= html_writer::tag('p', s($description), ['class' => 'card-text text-muted']);
    $html .= html_writer::link($url, s($buttontext), ['class' => $buttonclass]);

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}


/**
 * Local aisn index ocr card helper.
 */
function local_aisn_index_ocr_card(int $courseid): string {
    if (
        !function_exists('local_aisn_document_ocr_user_can_toggle') ||
        !function_exists('local_aisn_document_ocr_course_enabled') ||
        !function_exists('local_aisn_document_ocr_toggle_url')
    ) {
        return '';
    }

    if (!local_aisn_document_ocr_user_can_toggle($courseid)) {
        return '';
    }

    $enabled = local_aisn_document_ocr_course_enabled($courseid);
    $url = local_aisn_document_ocr_toggle_url($courseid, $enabled);

    $description = $enabled
        ? 'OCR attivo per questo corso. Premi il pulsante per disattivarlo.'
        : 'OCR disattivato per questo corso. Premi il pulsante per attivarlo sui documenti.';

    $html = '';
    $html .= html_writer::start_div('col-md-4 mb-3');
    $html .= html_writer::start_div('card h-100 shadow-sm border-success');
    $html .= html_writer::start_div('card-body');
    $html .= html_writer::span('OCR', 'badge badge-success mb-2');
    $html .= html_writer::tag('h4', 'OCR documenti', ['class' => 'card-title']);
    $html .= html_writer::tag('p', $description, ['class' => 'card-text text-muted']);
    $html .= html_writer::link(
        $url,
        $enabled ? 'Disattiva OCR' : 'Attiva OCR',
        ['class' => $enabled ? 'btn btn-outline-danger' : 'btn btn-outline-success']
    );
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}
/**
 * Local aisn index user courses helper.
 */
function local_aisn_index_user_courses(): array {
    global $DB;

    if (function_exists('enrol_get_my_courses')) {
        $courses = enrol_get_my_courses(['id', 'fullname', 'shortname'], 'fullname ASC');
        if (!empty($courses)) {
            return $courses;
        }
    }

    return [];
}

if ($courseid <= SITEID) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/index.php'));
    $PAGE->set_title(get_string('page_index_title_1', 'local_aiskillnavigator'));
    $PAGE->set_heading(get_string('page_index_heading_1', 'local_aiskillnavigator'));

    echo $OUTPUT->header();

    if (function_exists('local_aiskillnavigator_print_inline_styles')) {
        local_aiskillnavigator_print_inline_styles();
    }

    echo html_writer::start_div('container mt-4');
    echo html_writer::tag('h2', 'AI Skill Navigator');
    echo html_writer::tag(
        'p',
        'Select a Moodle course to open the AI Skill Navigator tools.',
        ['class' => 'lead text-muted']
    );

    $courses = local_aisn_index_user_courses();

    if (empty($courses)) {
        echo html_writer::div(
            // phpcs:ignore moodle.Files.LineLength
            'No course was selected. Open AI Skill Navigator from a Moodle course or use a URL like /local/aiskillnavigator/pages/index.php?courseid=2.',
            'alert alert-info'
        );

        echo html_writer::link(
            new moodle_url('/my/courses.php'),
            'Go to my courses',
            ['class' => 'btn btn-primary']
        );
    } else {
        echo html_writer::start_div('row');

        foreach ($courses as $course) {
            $cid = (int)$course->id;
            $url = new moodle_url('/local/aiskillnavigator/pages/index.php', ['courseid' => $cid]);

            echo html_writer::start_div('col-md-4 mb-3');
            echo html_writer::start_div('card h-100 shadow-sm');
            echo html_writer::start_div('card-body');
            echo html_writer::tag('h4', format_string($course->fullname), ['class' => 'card-title']);
            echo html_writer::tag('p', s($course->shortname), ['class' => 'text-muted']);
            echo html_writer::link($url, 'Open AI Skill Navigator', ['class' => 'btn btn-primary']);
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
        }

        echo html_writer::end_div();
    }

    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/index.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('page_index_title_2', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('page_index_heading_2', 'local_aiskillnavigator'));
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));

$isteacher = is_siteadmin()
    || has_capability('local/aiskillnavigator:viewteacher', $context)
    || has_capability('moodle/course:update', $context)
    || has_capability('moodle/course:manageactivities', $context);

$isstudent = is_siteadmin()
    || has_capability('local/aiskillnavigator:viewstudent', $context)
    || has_capability('moodle/course:view', $context);

echo $OUTPUT->header();

if (function_exists('local_aiskillnavigator_print_inline_styles')) {
    local_aiskillnavigator_print_inline_styles();
}

echo html_writer::start_div('container mt-4');
echo html_writer::tag('h2', 'AI Skill Navigator');
echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'lead text-muted']);

if ($isteacher) {
    echo html_writer::tag('h3', 'Teacher tools');

    echo html_writer::start_div('row');
    echo local_aisn_index_card(
        'Teacher dashboard',
        'Monitor course materials, quiz attempts, weak topics and students at risk.',
        '/local/aiskillnavigator/pages/teacher.php',
        $courseid,
        'Open dashboard',
        'btn btn-outline-primary',
        'Teacher'
    );
    echo local_aisn_index_card(
        'Tutor analyst',
        'Analyze questions asked to the AI Tutor and identify recurring student needs.',
        '/local/aiskillnavigator/pages/tutor_analytics.php',
        $courseid,
        'Open tutor analyst',
        'btn btn-outline-info',
        'Analytics'
    );
    echo local_aisn_index_card(
        'Initial/final tests',
        'Create and manage initial and final assessments for the course.',
        '/local/aiskillnavigator/pages/teacher_assessments.php',
        $courseid,
        'Open tests',
        'btn btn-outline-success',
        'Assessment'
    );
    echo local_aisn_index_card(
        'Learning-gap analysis',
        'Analyze test results and identify learning gaps in the class.',
        '/local/aiskillnavigator/pages/gap_analysis.php',
        $courseid,
        'Open gap analysis',
        'btn btn-outline-warning',
        'Gap'
    );
    echo local_aisn_index_card(
        'AI Course Builder',
        'Modify Moodle course sections and materials using a teacher prompt.',
        '/local/aiskillnavigator/pages/course_builder.php',
        $courseid,
        'Open Course Builder',
        'btn btn-outline-primary',
        'Builder'
    );
    echo local_aisn_index_card(
        'AI Simulator Finder',
        'Find online simulators and exercises connected to course materials.',
        '/local/aiskillnavigator/pages/simulator_finder.php',
        $courseid,
        'Open simulator finder',
        'btn btn-outline-info',
        'Simulation'
    );
    echo local_aisn_index_card(
        'Course materials / RAG',
        'Manage course materials used as context by the AI tools.',
        '/local/aiskillnavigator/pages/teacher_materials.php',
        $courseid,
        'Open materials',
        'btn btn-outline-success',
        'RAG'
    );
    echo html_writer::end_div();
}

if ($isstudent) {
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
}

if (!$isteacher && !$isstudent) {
    echo html_writer::div('You are not enrolled in this course.', 'alert alert-info');
}

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary mt-3']
);

echo html_writer::end_div();

echo $OUTPUT->footer();
