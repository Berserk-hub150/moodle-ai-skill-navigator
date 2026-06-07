<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$assessmentid = optional_param('assessmentid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/assessment.php', ['courseid' => $courseid]));
$PAGE->set_title('AI assessments');
$PAGE->set_heading('AI assessments');

function local_aiskillnavigator_assessment_table_exists(string $tablename): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($tablename));
}

function local_aiskillnavigator_assessment_type_label(string $type): string {
    if ($type === 'pretest' || $type === 'initial' || $type === 'diagnostic') {
        return 'Initial diagnostic quiz';
    }

    if ($type === 'final' || $type === 'posttest') {
        return 'Final test';
    }

    return $type !== '' ? ucfirst($type) : 'Assessment';
}

function local_aiskillnavigator_assessment_decode_quiz(string $json): ?array {
    $quiz = json_decode($json, true);

    if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
        return null;
    }

    return $quiz;
}

function local_aiskillnavigator_assessment_get_published(int $courseid): array {
    global $DB;

    if (!local_aiskillnavigator_assessment_table_exists('local_aiskillnav_assessment')) {
        return [];
    }

    return array_values($DB->get_records_select(
        'local_aiskillnav_assessment',
        'courseid = :courseid AND visible = :visible',
        [
            'courseid' => $courseid,
            'visible' => 1,
        ],
        'timecreated DESC'
    ));
}

function local_aiskillnavigator_assessment_get_attempt(int $assessmentid, int $userid): ?stdClass {
    global $DB;

    if (!local_aiskillnavigator_assessment_table_exists('local_aiskillnav_ass_att')) {
        return null;
    }

    $records = $DB->get_records(
        'local_aiskillnav_ass_att',
        [
            'assessmentid' => $assessmentid,
            'userid' => $userid,
        ],
        'timecreated DESC',
        '*',
        0,
        1
    );

    if (empty($records)) {
        return null;
    }

    return reset($records);
}

function local_aiskillnavigator_assessment_card(stdClass $assessment, ?stdClass $attempt, int $courseid): string {
    $type = local_aiskillnavigator_assessment_type_label((string)($assessment->assessmenttype ?? ''));
    $title = trim((string)($assessment->title ?? 'AI assessment'));
    $focus = trim((string)($assessment->focus ?? ''));
    $difficulty = trim((string)($assessment->difficulty ?? ''));

    $html = html_writer::start_div('card mb-3');
    $html .= html_writer::start_div('card-body');

    $html .= html_writer::tag('h3', s($title));

    $meta = [];
    $meta[] = $type;

    if ($difficulty !== '') {
        $meta[] = 'Difficulty: ' . $difficulty;
    }

    if ($focus !== '') {
        $meta[] = 'Focus: ' . $focus;
    }

    $html .= html_writer::tag('p', s(implode(' Â· ', $meta)), ['class' => 'text-muted']);

    if ($attempt) {
        $html .= html_writer::div(
            'Last result: ' . (int)$attempt->score . '/' . (int)$attempt->maxscore . ' (' . (int)$attempt->percentage . '%)',
            'alert alert-success'
        );
    }

    $html .= html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/assessment.php', [
            'courseid' => $courseid,
            'assessmentid' => (int)$assessment->id,
        ]),
        $attempt ? 'Retake assessment' : 'Start assessment',
        ['class' => 'btn btn-primary']
    );

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}

$savedmessage = '';
$score = null;
$maxscore = 0;
$percentage = 0;
$selectedassessment = null;
$quiz = null;

if ($assessmentid > 0 && local_aiskillnavigator_assessment_table_exists('local_aiskillnav_assessment')) {
    $selectedassessment = $DB->get_record('local_aiskillnav_assessment', [
        'id' => $assessmentid,
        'courseid' => $courseid,
        'visible' => 1,
    ]);

    if ($selectedassessment) {
        $quiz = local_aiskillnavigator_assessment_decode_quiz((string)$selectedassessment->quizjson);
    }
}

if ($action === 'submit' && $selectedassessment && $quiz) {
    require_sesskey();

    $score = 0;
    $answers = [];
    $questions = array_values($quiz['questions']);
    $maxscore = count($questions);

    foreach ($questions as $index => $question) {
        $answer = optional_param('answer_' . $index, -1, PARAM_INT);
        $answers[$index] = $answer;

        $correctindex = isset($question['correct_index']) ? (int)$question['correct_index'] : -1;

        if ($answer === $correctindex) {
            $score++;
        }
    }

    $percentage = $maxscore > 0 ? (int)round(($score / $maxscore) * 100) : 0;

    if (local_aiskillnavigator_assessment_table_exists('local_aiskillnav_ass_att')) {
        $record = new stdClass();
        $record->assessmentid = (int)$selectedassessment->id;
        $record->courseid = $courseid;
        $record->userid = (int)$USER->id;
        $record->score = $score;
        $record->maxscore = $maxscore;
        $record->percentage = $percentage;
        $record->answersjson = json_encode($answers, JSON_UNESCAPED_UNICODE);
        $record->timecreated = time();

        $DB->insert_record('local_aiskillnav_ass_att', $record);
    }

    $savedmessage = 'Assessment submitted successfully.';
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

if ($selectedassessment && $quiz) {
    echo html_writer::tag('h2', s($selectedassessment->title));
    echo html_writer::tag(
        'p',
        'Answer the questions below. Your result will help the teacher understand strengths and learning gaps.',
        ['class' => 'lead']
    );

    echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

    if ($savedmessage !== '') {
        echo html_writer::div(
            s($savedmessage) . ' Result: ' . (int)$score . '/' . (int)$maxscore . ' (' . (int)$percentage . '%)',
            'alert alert-success'
        );
    }

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/aiskillnavigator/pages/assessment.php', [
            'courseid' => $courseid,
            'assessmentid' => (int)$selectedassessment->id,
        ]),
    ]);

    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'submit']);

    $questions = array_values($quiz['questions']);

    foreach ($questions as $index => $question) {
        $questiontext = (string)($question['question'] ?? ('Question ' . ($index + 1)));
        $options = isset($question['options']) && is_array($question['options']) ? array_values($question['options']) : [];

        echo html_writer::start_div('mb-4');
        echo html_writer::tag('h4', ($index + 1) . '. ' . s($questiontext));

        foreach ($options as $optionindex => $optiontext) {
            $name = 'answer_' . $index;
            $id = 'answer_' . $index . '_' . $optionindex;

            echo html_writer::start_div('form-check mb-2');

            echo html_writer::empty_tag('input', [
                'type' => 'radio',
                'name' => $name,
                'id' => $id,
                'value' => $optionindex,
                'class' => 'form-check-input',
                'required' => 'required',
            ]);

            echo html_writer::tag('label', s($optiontext), [
                'for' => $id,
                'class' => 'form-check-label',
            ]);

            echo html_writer::end_div();
        }

        echo html_writer::end_div();
    }

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => 'Submit assessment',
    ]);

    echo html_writer::end_tag('form');

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/assessment.php', ['courseid' => $courseid]),
        'Back to assessments',
        ['class' => 'btn btn-secondary']
    );

    echo html_writer::end_div();

    echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();
    exit;
}

$assessments = local_aiskillnavigator_assessment_get_published($courseid);

echo html_writer::tag('h2', 'AI assessments for students');

echo html_writer::tag(
    'p',
    'This page shows the initial diagnostic quiz and the final test created by the teacher. Students complete them here; the results are used to identify learning gaps and measure progress.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if (empty($assessments)) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'No assessment published yet');

    echo html_writer::tag(
        'p',
        'The teacher has not published an initial diagnostic quiz or final test for this course yet.',
        ['class' => 'text-muted']
    );

    echo html_writer::tag(
        'p',
        'When the teacher creates and publishes a test from "Initial/final tests", it will appear here for students.',
        ['class' => 'text-muted']
    );

    if (has_capability('local/aiskillnavigator:viewteacher', $context)) {
        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]),
            'Create initial/final test',
            ['class' => 'btn btn-primary']
        );
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    foreach ($assessments as $assessment) {
        $attempt = local_aiskillnavigator_assessment_get_attempt((int)$assessment->id, (int)$USER->id);
        echo local_aiskillnavigator_assessment_card($assessment, $attempt, $courseid);
    }
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
