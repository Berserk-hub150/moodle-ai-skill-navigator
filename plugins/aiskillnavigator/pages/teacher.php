<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/tutor_signal_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');

global $PAGE, $OUTPUT, $DB;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);
if (isset($courseid) && (int)$courseid > 1 && function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}


$context = context_course::instance($courseid);

require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher.php', ['courseid' => $courseid]));
$PAGE->set_title('Teacher dashboard');
$PAGE->set_heading('Teacher dashboard');

$attempts = $DB->get_records_sql(
    "SELECT a.*, u.firstname, u.lastname, u.email
       FROM {local_aiskillnav_attempt} a
       JOIN {user} u ON u.id = a.userid
      WHERE a.courseid = :courseid
   ORDER BY a.timecreated DESC",
    ['courseid' => $courseid]
);

$materialcount = $DB->count_records('local_aiskillnav_material', ['courseid' => $courseid]);

$totalattempts = count($attempts);
$classaverage = 0;
$students = [];
$topicstats = [];
$sum = 0;

foreach ($attempts as $attempt) {
    $percentage = (int) $attempt->percentage;
    $sum += $percentage;

    $userid = (int) $attempt->userid;

    if (!isset($students[$userid])) {
        $studentuser = new stdClass();
        $studentuser->firstname = $attempt->firstname;
        $studentuser->lastname = $attempt->lastname;
        $studentuser->email = $attempt->email;

        $students[$userid] = [
            'userid' => $userid,
            'fullname' => fullname($studentuser),
            'email' => $attempt->email,
            'attempts' => 0,
            'sum' => 0,
            'average' => 0,
            'best' => 0,
            'lastscore' => null,
            'lasttime' => 0,
            'weakesttopic' => '',
            'topics' => [],
        ];
    }

    $students[$userid]['attempts']++;
    $students[$userid]['sum'] += $percentage;

    if ($percentage > $students[$userid]['best']) {
        $students[$userid]['best'] = $percentage;
    }

    if ((int) $attempt->timecreated > $students[$userid]['lasttime']) {
        $students[$userid]['lasttime'] = (int) $attempt->timecreated;
        $students[$userid]['lastscore'] = $percentage;
    }

    $topic = trim((string) $attempt->topic);
    if ($topic === '') {
        $topic = 'Unknown topic';
    }

    if (!isset($students[$userid]['topics'][$topic])) {
        $students[$userid]['topics'][$topic] = [
            'topic' => $topic,
            'attempts' => 0,
            'sum' => 0,
            'average' => 0,
        ];
    }

    $students[$userid]['topics'][$topic]['attempts']++;
    $students[$userid]['topics'][$topic]['sum'] += $percentage;

    if (!isset($topicstats[$topic])) {
        $topicstats[$topic] = [
            'topic' => $topic,
            'attempts' => 0,
            'sum' => 0,
            'average' => 0,
            'students' => [],
        ];
    }

    $topicstats[$topic]['attempts']++;
    $topicstats[$topic]['sum'] += $percentage;
    $topicstats[$topic]['students'][$userid] = true;
}

if ($totalattempts > 0) {
    $classaverage = (int) round($sum / $totalattempts);
}

foreach ($students as $userid => $student) {
    $students[$userid]['average'] = $student['attempts'] > 0
        ? (int) round($student['sum'] / $student['attempts'])
        : 0;

    $weakesttopic = null;

    foreach ($student['topics'] as $topic => $topicdata) {
        $topicaverage = $topicdata['attempts'] > 0
            ? (int) round($topicdata['sum'] / $topicdata['attempts'])
            : 0;

        $students[$userid]['topics'][$topic]['average'] = $topicaverage;

        if ($weakesttopic === null || $topicaverage < $weakesttopic['average']) {
            $weakesttopic = [
                'topic' => $topic,
                'average' => $topicaverage,
            ];
        }
    }

    if ($weakesttopic !== null) {
        $students[$userid]['weakesttopic'] = $weakesttopic['topic'] . ' (' . $weakesttopic['average'] . '%)';
    }
}

foreach ($topicstats as $topic => $data) {
    $topicstats[$topic]['average'] = $data['attempts'] > 0
        ? (int) round($data['sum'] / $data['attempts'])
        : 0;

    $topicstats[$topic]['studentcount'] = count($data['students']);
}

uasort($students, function ($a, $b) {
    if ($a['average'] === $b['average']) {
        return $a['fullname'] <=> $b['fullname'];
    }

    return $a['average'] <=> $b['average'];
});

usort($topicstats, function ($a, $b) {
    return $a['average'] <=> $b['average'];
});

$studentsatrisk = 0;

foreach ($students as $student) {
    if ($student['average'] < 50) {
        $studentsatrisk++;
    }
}

function local_aiskillnavigator_teacher_badge_class(int $percentage): string {
    if ($percentage >= 80) {
        return 'badge badge-success';
    }

    if ($percentage >= 50) {
        return 'badge badge-warning';
    }

    return 'badge badge-danger';
}

function local_aiskillnavigator_teacher_status_text(int $percentage): string {
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

echo html_writer::tag('h2', 'Teacher dashboard');

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

echo html_writer::tag(
    'p',
    'This dashboard shows saved course materials, aggregated quiz results, weak topics and students at risk.',
    ['class' => 'lead']
);

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', (string) $materialcount) .
    html_writer::tag('p', 'Teacher materials'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', (string) $totalattempts) .
    html_writer::tag('p', 'Quiz attempts'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', $classaverage . '%') .
    html_writer::tag('p', 'Class average'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3 mb-3');
echo html_writer::div(
    html_writer::tag('h3', (string) $studentsatrisk) .
    html_writer::tag('p', 'Students at risk'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', ['courseid' => $courseid]),
        'Manage teacher materials',
        ['class' => 'btn btn-primary']
    ),
    'mt-3 mb-4'
);

echo html_writer::tag('h3', 'Student performance');

if (empty($students)) {
    echo html_writer::div('No student quiz attempts yet.', 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Student');
    echo html_writer::tag('th', 'Email');
    echo html_writer::tag('th', 'Attempts');
    echo html_writer::tag('th', 'Average');
    echo html_writer::tag('th', 'Best');
    echo html_writer::tag('th', 'Last score');
    echo html_writer::tag('th', 'Weakest topic');
    echo html_writer::tag('th', 'Status');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($students as $student) {
        $average = (int) $student['average'];

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($student['fullname']));
        echo html_writer::tag('td', s($student['email']));
        echo html_writer::tag('td', s((string) $student['attempts']));
        echo html_writer::tag('td', s($average . '%'));
        echo html_writer::tag('td', s($student['best'] . '%'));
        echo html_writer::tag('td', s($student['lastscore'] === null ? '-' : $student['lastscore'] . '%'));
        echo html_writer::tag('td', s($student['weakesttopic'] !== '' ? $student['weakesttopic'] : '-'));
        echo html_writer::tag(
            'td',
            html_writer::tag(
                'span',
                local_aiskillnavigator_teacher_status_text($average),
                ['class' => local_aiskillnavigator_teacher_badge_class($average)]
            )
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::tag('h3', 'Topic performance', ['class' => 'mt-4']);

if (empty($topicstats)) {
    echo html_writer::div('No topic data available yet.', 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Topic');
    echo html_writer::tag('th', 'Attempts');
    echo html_writer::tag('th', 'Students');
    echo html_writer::tag('th', 'Average');
    echo html_writer::tag('th', 'Class status');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($topicstats as $topic) {
        $average = (int) $topic['average'];

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($topic['topic']));
        echo html_writer::tag('td', s((string) $topic['attempts']));
        echo html_writer::tag('td', s((string) $topic['studentcount']));
        echo html_writer::tag('td', s($average . '%'));
        echo html_writer::tag(
            'td',
            html_writer::tag(
                'span',
                local_aiskillnavigator_teacher_status_text($average),
                ['class' => local_aiskillnavigator_teacher_badge_class($average)]
            )
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::tag('h3', 'Recent quiz attempts', ['class' => 'mt-4']);

if (empty($attempts)) {
    echo html_writer::div('No recent quiz attempts.', 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Date');
    echo html_writer::tag('th', 'Student');
    echo html_writer::tag('th', 'Topic');
    echo html_writer::tag('th', 'Difficulty');
    echo html_writer::tag('th', 'Score');
    echo html_writer::tag('th', 'Percentage');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $shown = 0;

    foreach ($attempts as $attempt) {
        if ($shown >= 20) {
            break;
        }

        $studentuser = new stdClass();
        $studentuser->firstname = $attempt->firstname;
        $studentuser->lastname = $attempt->lastname;

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', userdate($attempt->timecreated));
        echo html_writer::tag('td', s(fullname($studentuser)));
        echo html_writer::tag('td', s($attempt->topic));
        echo html_writer::tag('td', s($attempt->difficulty));
        echo html_writer::tag('td', s($attempt->score . '/' . $attempt->maxscore));
        echo html_writer::tag('td', s($attempt->percentage . '%'));
        echo html_writer::end_tag('tr');

        $shown++;
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

