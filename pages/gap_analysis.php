<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/gap_analysis.php', ['courseid' => $courseid]));
$PAGE->set_title('AI learning-gap analysis');
$PAGE->set_heading('AI learning-gap analysis');

function local_aiskillnavigator_gap_table_exists(string $tablename): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($tablename));
}

function local_aiskillnavigator_gap_call_ai(string $prompt, string $systemprompt): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate($prompt, 2800, $systemprompt);
        }

        if (
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_config') &&
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_selector')
        ) {
            $config = new \local_aiskillnavigator\service\provider\ai_provider_config();
            $selector = new \local_aiskillnavigator\service\provider\ai_provider_selector();
            $provider = $selector->create($config);
            return $provider->generate($prompt, 2800, $systemprompt);
        }
    } catch (Throwable $e) {
        return 'AI error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}

function local_aiskillnavigator_gap_collect(int $courseid): array {
    global $DB;

    if (
        !local_aiskillnavigator_gap_table_exists('local_aiskillnav_assessment') ||
        !local_aiskillnavigator_gap_table_exists('local_aiskillnav_ass_att')
    ) {
        return [
            'assessments' => [],
            'skills' => [],
            'totalattempts' => 0,
            'average' => 0,
            'studentsatrisk' => 0,
        ];
    }

    $assessments = $DB->get_records(
        'local_aiskillnav_assessment',
        ['courseid' => $courseid],
        'timecreated ASC'
    );

    $assessmentsummary = [];
    $skills = [];
    $totalattempts = 0;
    $percentages = [];
    $studentsatrisk = [];

    foreach ($assessments as $assessment) {
        $attempts = $DB->get_records(
            'local_aiskillnav_ass_att',
            ['assessmentid' => (int)$assessment->id],
            'timecreated ASC'
        );

        $quiz = json_decode((string)$assessment->quizjson, true);
        $questions = is_array($quiz) && !empty($quiz['questions']) && is_array($quiz['questions'])
            ? array_values($quiz['questions'])
            : [];

        $attemptcount = count($attempts);
        $attemptsum = 0;

        foreach ($attempts as $attempt) {
            $totalattempts++;
            $attemptsum += (int)$attempt->percentage;
            $percentages[] = (int)$attempt->percentage;

            if ((int)$attempt->percentage < 60) {
                $studentsatrisk[(int)$attempt->userid] = true;
            }

            $answers = json_decode((string)$attempt->answersjson, true);

            if (!is_array($answers)) {
                $answers = [];
            }

            foreach ($questions as $index => $question) {
                $skill = trim((string)($question['ability'] ?? $question['skill'] ?? $question['Ability'] ?? 'General understanding'));

                if ($skill === '') {
                    $skill = 'General understanding';
                }

                $correctindex = isset($question['correct_index']) ? (int)$question['correct_index'] : -999;
                $answer = isset($answers[$index]) ? (int)$answers[$index] : -998;

                if (!isset($skills[$skill])) {
                    $skills[$skill] = [
                        'total' => 0,
                        'correct' => 0,
                        'wrong' => 0,
                    ];
                }

                $skills[$skill]['total']++;

                if ($answer === $correctindex) {
                    $skills[$skill]['correct']++;
                } else {
                    $skills[$skill]['wrong']++;
                }
            }
        }

        $assessmentsummary[] = [
            'title' => (string)$assessment->title,
            'type' => (string)$assessment->assessmenttype,
            'attempts' => $attemptcount,
            'average' => $attemptcount > 0 ? round($attemptsum / $attemptcount, 1) : 0,
        ];
    }

    uasort($skills, function($a, $b) {
        $arate = $a['total'] > 0 ? $a['correct'] / $a['total'] : 0;
        $brate = $b['total'] > 0 ? $b['correct'] / $b['total'] : 0;
        return $arate <=> $brate;
    });

    return [
        'assessments' => $assessmentsummary,
        'skills' => $skills,
        'totalattempts' => $totalattempts,
        'average' => !empty($percentages) ? round(array_sum($percentages) / count($percentages), 1) : 0,
        'studentsatrisk' => count($studentsatrisk),
    ];
}

$data = local_aiskillnavigator_gap_collect($courseid);
$airesult = '';

if ($action === 'generate') {
    require_sesskey();

    $lines = [];
    $lines[] = 'Course: ' . $course->fullname;
    $lines[] = '';
    $lines[] = 'Assessment summary:';

    foreach ($data['assessments'] as $assessment) {
        $lines[] = '- ' . $assessment['title'] . ' [' . $assessment['type'] . '], attempts: ' . $assessment['attempts'] . ', average: ' . $assessment['average'] . '%';
    }

    $lines[] = '';
    $lines[] = 'Ability-level results:';

    foreach ($data['skills'] as $skill => $stats) {
        $rate = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;
        $lines[] = '- ' . $skill . ': correct ' . $rate . '%, wrong ' . $stats['wrong'] . ', total ' . $stats['total'];
    }

    $prompt = "Analizza questi risultati Moodle di quiz iniziali e test finali.\n\n";
    $prompt .= implode("\n", $lines);
    $prompt .= "\n\nRispondi in italiano con queste sezioni:\n";
    $prompt .= "1. Sintesi della classe\n";
    $prompt .= "2. Lacune principali\n";
    $prompt .= "3. Concetti da ripassare\n";
    $prompt .= "4. Azioni didattiche consigliate al docente\n";
    $prompt .= "5. Mini piano di recupero\n";
    $prompt .= "6. Mini-quiz di recupero con 5 domande\n";
    $prompt .= "Non inventare dati personali non presenti nei risultati.\n";

    $airesult = local_aiskillnavigator_gap_call_ai(
        $prompt,
        'You are an educational analytics assistant for a Moodle teacher. Do not invent unavailable personal data.'
    );
}

echo $OUTPUT->header();
echo html_writer::tag('style', <<<'CSS'
/* AISN_GAP_ANALYSIS_VISUAL_FIX_V1 */
body.path-local-aiskillnavigator#page-local-aiskillnavigator-pages-gap_analysis [role="main"],
body#page-local-aiskillnavigator-pages-gap_analysis [role="main"] {
    max-width: 1180px !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis .aisn-stat-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 16px !important;
    margin: 24px 0 28px 0 !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis .aisn-stat {
    background: #ffffff !important;
    border: 1px solid #dbeafe !important;
    border-radius: 20px !important;
    padding: 22px 20px !important;
    min-height: 118px !important;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08) !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis .aisn-stat-value {
    font-size: 34px !important;
    line-height: 1 !important;
    font-weight: 950 !important;
    color: #0f172a !important;
    margin-bottom: 10px !important;
    letter-spacing: -0.04em !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis .aisn-stat-label {
    font-size: 14px !important;
    line-height: 1.3 !important;
    font-weight: 800 !important;
    color: #64748b !important;
    margin: 0 !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis .card {
    border: 1px solid #e2e8f0 !important;
    border-radius: 22px !important;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08) !important;
    overflow: hidden !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis .card-body {
    padding: 28px 30px !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis .card h3,
body#page-local-aiskillnavigator-pages-gap_analysis .card h4 {
    margin-top: 0 !important;
    margin-bottom: 18px !important;
    font-weight: 950 !important;
    color: #0f172a !important;
    letter-spacing: -0.04em !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis table.table {
    width: 100% !important;
    margin-bottom: 0 !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    overflow: hidden !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 16px !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis table.table th {
    background: #eef4fb !important;
    color: #0f172a !important;
    font-weight: 950 !important;
    border-bottom: 1px solid #dbe3ec !important;
    padding: 14px 16px !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis table.table td {
    padding: 14px 16px !important;
    vertical-align: middle !important;
    border-top: 1px solid #edf2f7 !important;
}

body#page-local-aiskillnavigator-pages-gap_analysis table.table tr:nth-child(even) td {
    background: #f8fafc !important;
}

@media (max-width: 1000px) {
    body#page-local-aiskillnavigator-pages-gap_analysis .aisn-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 650px) {
    body#page-local-aiskillnavigator-pages-gap_analysis .aisn-stat-grid {
        grid-template-columns: 1fr !important;
    }

    body#page-local-aiskillnavigator-pages-gap_analysis .card-body {
        padding: 22px 18px !important;
    }
}
CSS);

local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI learning-gap analysis');

echo html_writer::tag(
    'p',
    'Analyze initial diagnostic quizzes and final tests to identify weak abilities, class-level ability gaps and teacher-side remediation actions.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

echo html_writer::start_div('aisn-stat-grid');

echo html_writer::start_div('aisn-stat');
echo html_writer::div((string)(int)$data['totalattempts'], 'aisn-stat-value');
echo html_writer::div('Total attempts', 'aisn-stat-label');
echo html_writer::end_div();

echo html_writer::start_div('aisn-stat');
echo html_writer::div(s($data['average'] . '%'), 'aisn-stat-value');
echo html_writer::div('Average score', 'aisn-stat-label');
echo html_writer::end_div();

echo html_writer::start_div('aisn-stat');
echo html_writer::div((string)(int)$data['studentsatrisk'], 'aisn-stat-value');
echo html_writer::div('Students under 60%', 'aisn-stat-label');
echo html_writer::end_div();

echo html_writer::start_div('aisn-stat');
echo html_writer::div((string)count($data['skills']), 'aisn-stat-value');
echo html_writer::div('Detected abilities', 'aisn-stat-label');
echo html_writer::end_div();

echo html_writer::end_div();

if ((int)$data['totalattempts'] === 0) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'No student attempts yet');

    echo html_writer::tag(
        'p',
        'Generate and publish an initial diagnostic quiz or final test, then let students submit their answers. After that, this page will show ability gaps and AI recommendations.',
        ['class' => 'text-muted']
    );

    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]),
        'Create initial/final test',
        ['class' => 'btn btn-primary']
    );

    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Assessment summary');

    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::tag(
        'tr',
        html_writer::tag('th', 'Assessment') .
        html_writer::tag('th', 'Type') .
        html_writer::tag('th', 'Attempts') .
        html_writer::tag('th', 'Average')
    );

    foreach ($data['assessments'] as $assessment) {
        echo html_writer::tag(
            'tr',
            html_writer::tag('td', s($assessment['title'])) .
            html_writer::tag('td', s($assessment['type'])) .
            html_writer::tag('td', (int)$assessment['attempts']) .
            html_writer::tag('td', s($assessment['average'] . '%'))
        );
    }

    echo html_writer::end_tag('table');

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'weak abilities');

    if (empty($data['skills'])) {
        echo html_writer::div('No ability-level data available yet.', 'alert alert-info');
    } else {
        echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
        echo html_writer::tag(
            'tr',
            html_writer::tag('th', 'Ability') .
            html_writer::tag('th', 'Correct') .
            html_writer::tag('th', 'Wrong') .
            html_writer::tag('th', 'Correct %')
        );

        foreach ($data['skills'] as $skill => $stats) {
            $rate = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;

            echo html_writer::tag(
                'tr',
                html_writer::tag('td', s($skill)) .
                html_writer::tag('td', (int)$stats['correct']) .
                html_writer::tag('td', (int)$stats['wrong']) .
                html_writer::tag('td', s($rate . '%'))
            );
        }

        echo html_writer::end_tag('table');
    }

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/aiskillnavigator/pages/gap_analysis.php', ['courseid' => $courseid]),
    ]);

    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'generate']);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => 'Ask AI to analyze learning gaps',
    ]);

    echo html_writer::end_tag('form');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($airesult !== '') {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', 'AI recommendation');
    echo html_writer::tag('pre', s($airesult), [
        'style' => 'white-space: pre-wrap; background:#0f172a; color:#e5e7eb; padding:22px; border-radius:18px; line-height:1.55;',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::link(
    new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]),
    'Back to initial/final tests',
    ['class' => 'btn btn-secondary mr-2']
);

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();

echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();


