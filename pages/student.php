<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);


local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/student.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('studentdashboard', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('studentdashboard', 'local_aiskillnavigator'));

$attempts = $DB->get_records(
    'local_aiskillnav_attempt',
    [
        'courseid' => $courseid,
        'userid' => $USER->id,
    ],
    'timecreated DESC',
    '*',
    0,
    50
);

$totalattempts = count($attempts);
$average = 0;
$bestscore = 0;
$lastscore = null;
$topicstats = [];

if ($totalattempts > 0) {
    $sum = 0;
    $first = true;

    foreach ($attempts as $attempt) {
        $percentage = (int) $attempt->percentage;
        $sum += $percentage;

        if ($percentage > $bestscore) {
            $bestscore = $percentage;
        }

        if ($first) {
            $lastscore = $percentage;
            $first = false;
        }

        $topic = trim((string) $attempt->topic);
        if ($topic === '') {
            $topic = 'Unknown topic';
        }

        if (!isset($topicstats[$topic])) {
            $topicstats[$topic] = [
                'topic' => $topic,
                'attempts' => 0,
                'sum' => 0,
                'average' => 0,
                'best' => 0,
            ];
        }

        $topicstats[$topic]['attempts']++;
        $topicstats[$topic]['sum'] += $percentage;

        if ($percentage > $topicstats[$topic]['best']) {
            $topicstats[$topic]['best'] = $percentage;
        }
    }

    $average = (int) round($sum / $totalattempts);

    foreach ($topicstats as $topic => $data) {
        $topicstats[$topic]['average'] = $data['attempts'] > 0
            ? (int) round($data['sum'] / $data['attempts'])
            : 0;
    }
}

$strongesttopic = null;
$weakesttopic = null;

foreach ($topicstats as $data) {
    if ($strongesttopic === null || $data['average'] > $strongesttopic['average']) {
        $strongesttopic = $data;
    }

    if ($weakesttopic === null || $data['average'] < $weakesttopic['average']) {
        $weakesttopic = $data;
    }
}

usort($topicstats, function ($a, $b) {
    return $a['average'] <=> $b['average'];
});

$recommendation = 'Complete at least one AI quiz to receive a recommendation.';

if ($totalattempts > 0) {
    if ($average >= 80) {
        $recommendation = 'Great work. Your average is strong. Try a harder quiz or ask the AI tutor to deepen the topic.';
    } else if ($average >= 50) {
        $recommendation = 'Good start. Review the mind map and repeat the quiz after studying the weakest topic.';
    } else {
        $recommendation = 'Focus on basic concepts. Use the Course AI Tutor, generate a mind map, then retry an easier quiz.';
    }

    if ($weakesttopic !== null) {
        $recommendation .= ' Priority topic: ' . $weakesttopic['topic'] . '.';
    }
}

function local_aiskillnavigator_student_badge_class(int $percentage): string {
    if ($percentage >= 80) {
        return 'badge badge-success';
    }

    if ($percentage >= 50) {
        return 'badge badge-warning';
    }

    return 'badge badge-danger';
}

function local_aiskillnavigator_student_status_text(int $percentage): string {
    if ($percentage >= 80) {
        return 'Strong';
    }

    if ($percentage >= 50) {
        return 'Needs practice';
    }

    return 'At risk';
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('studentdashboard', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'This dashboard shows your AI quiz attempts, learning progress and personalised recommendations.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', (string) $totalattempts) .
    html_writer::tag('p', 'Completed AI quizzes'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', $average . '%') .
    html_writer::tag('p', 'Average score'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', $bestscore . '%') .
    html_writer::tag('p', 'Best score'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', $lastscore === null ? '-' : $lastscore . '%') .
    html_writer::tag('p', 'Last attempt'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Personal recommendation');
echo html_writer::tag('p', s($recommendation));

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]),
        'Ask Course AI Tutor',
        ['class' => 'btn btn-primary mr-2']
    ) .
    ' ' .
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/mindmapgenerator.php', ['courseid' => $courseid]),
        'Generate Mind Map',
        ['class' => 'btn btn-secondary mr-2']
    ) .
    ' ' .
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/quizgenerator.php', ['courseid' => $courseid]),
        'Try New Quiz',
        ['class' => 'btn btn-success']
    ),
    'mt-3'
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('h3', 'Performance by topic', ['class' => 'mt-4']);

if (empty($topicstats)) {
    echo html_writer::div('No topic statistics yet. Complete at least one generated quiz.', 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Topic');
    echo html_writer::tag('th', 'Attempts');
    echo html_writer::tag('th', 'Average');
    echo html_writer::tag('th', 'Best');
    echo html_writer::tag('th', 'Status');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($topicstats as $data) {
        $topicaverage = (int) $data['average'];

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($data['topic']));
        echo html_writer::tag('td', s((string) $data['attempts']));
        echo html_writer::tag('td', s($topicaverage . '%'));
        echo html_writer::tag('td', s($data['best'] . '%'));
        echo html_writer::tag(
            'td',
            html_writer::tag(
                'span',
                local_aiskillnavigator_student_status_text($topicaverage),
                ['class' => local_aiskillnavigator_student_badge_class($topicaverage)]
            )
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::tag('h3', 'Recent quiz attempts', ['class' => 'mt-4']);

if (empty($attempts)) {
    echo html_writer::div('No quiz attempts saved yet.', 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Date');
    echo html_writer::tag('th', 'Topic');
    echo html_writer::tag('th', 'Difficulty');
    echo html_writer::tag('th', 'Score');
    echo html_writer::tag('th', 'Percentage');
    echo html_writer::tag('th', 'Status');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($attempts as $attempt) {
        $percentage = (int) $attempt->percentage;

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', userdate($attempt->timecreated));
        echo html_writer::tag('td', s($attempt->topic));
        echo html_writer::tag('td', s($attempt->difficulty));
        echo html_writer::tag('td', s($attempt->score . '/' . $attempt->maxscore));
        echo html_writer::tag('td', s($percentage . '%'));
        echo html_writer::tag(
            'td',
            html_writer::tag(
                'span',
                local_aiskillnavigator_student_status_text($percentage),
                ['class' => local_aiskillnavigator_student_badge_class($percentage)]
            )
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::div(
    html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Back to course', ['class' => 'btn btn-secondary'])
);

echo html_writer::end_div();

echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();


