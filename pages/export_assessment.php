<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');

global $DB, $USER;

$courseid = required_param('courseid', PARAM_INT);
$assessmentid = required_param('assessmentid', PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$assessment = $DB->get_record('local_aiskillnav_assessment', [
    'id' => $assessmentid,
    'courseid' => $courseid,
], '*', MUST_EXIST);

$quiz = json_decode((string)$assessment->quizjson, true);

if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
    throw new moodle_exception('Invalid quiz JSON');
}

$filenamebase = clean_filename('ai_assessment_' . $assessmentid . '_' . date('Ymd_His'));

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenamebase . '.json"');
    echo json_encode($quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($format === 'gift') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenamebase . '.gift"');

    foreach (array_values($quiz['questions']) as $index => $question) {
        $qtext = trim((string)($question['question'] ?? ('Question ' . ($index + 1))));
        $options = isset($question['options']) && is_array($question['options']) ? array_values($question['options']) : [];
        $correct = isset($question['correct_index']) ? (int)$question['correct_index'] : 0;

        echo "::Q" . ($index + 1) . "::" . $qtext . " {\n";

        foreach ($options as $i => $option) {
            echo ($i === $correct ? "=" : "~") . trim((string)$option) . "\n";
        }

        echo "}\n\n";
    }

    exit;
}

$delimiter = ',';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenamebase . ($format === 'google' ? '_google_forms.csv' : '.csv') . '"');

// AISN_CSV_UTF8_BOM
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

if ($format === 'google') {
    fputcsv($out, ['Question', 'Option 1', 'Option 2', 'Option 3', 'Option 4', 'Correct answer', 'Explanation', 'Skill'], $delimiter);
} else {
    fputcsv($out, ['title', 'type', 'question', 'option_1', 'option_2', 'option_3', 'option_4', 'correct_index', 'correct_answer', 'explanation', 'skill'], $delimiter);
}

foreach (array_values($quiz['questions']) as $question) {
    $qtext = trim((string)($question['question'] ?? ''));
    $options = isset($question['options']) && is_array($question['options']) ? array_values($question['options']) : [];
    $correct = isset($question['correct_index']) ? (int)$question['correct_index'] : 0;
    $correctanswer = $options[$correct] ?? '';
    $explanation = (string)($question['explanation'] ?? '');
    $skill = (string)($question['skill'] ?? $question['ability'] ?? $question['Ability'] ?? '');

    while (count($options) < 4) {
        $options[] = '';
    }

    if ($format === 'google') {
        fputcsv($out, [$qtext, $options[0], $options[1], $options[2], $options[3], $correctanswer, $explanation, $skill], $delimiter);
    } else {
        fputcsv($out, [
            (string)$assessment->title,
            (string)$assessment->assessmenttype,
            $qtext,
            $options[0],
            $options[1],
            $options[2],
            $options[3],
            $correct,
            $correctanswer,
            $explanation,
            $skill,
        ], $delimiter);
    }
}

fclose($out);
exit;