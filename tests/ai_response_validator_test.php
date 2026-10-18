<?php
/** Test JSON response validator. */
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../plugins/aiskillnavigator/classes/service/provider/ai_response_validator.php');

use local_aiskillnavigator\service\provider\ai_response_validator;

function check($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$validator = new ai_response_validator();

// Valid quiz JSON
$validquiz = json_encode([
    'title' => 'Test Quiz',
    'questions' => [
        [
            'question' => 'Q1',
            'options' => ['A', 'B'],
            'correct_index' => 0
        ]
    ]
]);

check(true, $validator->validate($validquiz, 'quiz'), 'Valid quiz JSON should pass validation');
check(true, $validator->validate(json_decode($validquiz, true), 'quiz'), 'Valid quiz array should pass validation');

// Invalid quiz JSON (missing questions or invalid question structure)
$invalidquiz1 = json_encode(['title' => 'Bad Quiz']);
$invalidquiz2 = json_encode([
    'title' => 'Bad Quiz 2',
    'questions' => [
        ['question' => '', 'options' => ['A', 'B'], 'correct_index' => 0]
    ]
]);
check(false, $validator->validate($invalidquiz1, 'quiz'), 'Invalid quiz JSON should fail validation');
check(false, $validator->validate($invalidquiz2, 'quiz'), 'Quiz with empty question text should fail validation');

// Malformed JSON string
check(false, $validator->validate('not json', 'quiz'), 'Malformed JSON string should fail validation');

// Valid mindmap JSON
$validmindmap = json_encode(['title' => 'Mindmap', 'nodes' => []]);
check(true, $validator->validate($validmindmap, 'mindmap'), 'Valid mindmap JSON should pass validation');
check(false, $validator->validate(json_encode(['title' => 'Bad']), 'mindmap'), 'Invalid mindmap should fail validation');

// Valid summary JSON
$validsummary = json_encode(['title' => 'Summary', 'summary' => 'Text summary']);
check(true, $validator->validate($validsummary, 'summary'), 'Valid summary JSON should pass validation');
check(false, $validator->validate(json_encode(['title' => 'Bad']), 'summary'), 'Invalid summary should fail validation');

// Valid blueprint JSON
$validblueprint = json_encode(['title' => 'XR Blueprint', 'scenes' => []]);
$validblueprint2 = json_encode(['title' => 'XR Blueprint 2', 'steps' => []]);
check(true, $validator->validate($validblueprint, 'blueprint'), 'Valid blueprint JSON with scenes should pass validation');
check(true, $validator->validate($validblueprint2, 'xr_blueprint'), 'Valid blueprint JSON with steps should pass validation');
check(false, $validator->validate(json_encode(['title' => 'Bad Blueprint']), 'blueprint'), 'Invalid blueprint should fail validation');

echo "ai_response_validator_test: OK\n";
