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

// ── Quiz: valid cases ──────────────────────────────────────────────

// Valid quiz with 4 options and correct_index.
$validquiz = [
    'title' => 'Test Quiz',
    'questions' => [
        [
            'question' => 'What is PHP?',
            'options' => ['A language', 'A database', 'An OS', 'A browser'],
            'correct_index' => 0
        ]
    ]
];
check(true, $validator->validate($validquiz, 'quiz'), 'Valid quiz array should pass');
check(true, $validator->validate(json_encode($validquiz), 'quiz'), 'Valid quiz JSON string should pass');

// Valid quiz with string correct_index '2'.
$quizstringindex = $validquiz;
$quizstringindex['questions'][0]['correct_index'] = '2';
check(true, $validator->validate($quizstringindex, 'quiz'), 'Quiz with string correct_index "2" should pass');

// Valid quiz using correct_answer string that matches an option.
$quizstranswer = [
    'title' => 'Answer Quiz',
    'questions' => [
        [
            'question' => 'Capital of Italy?',
            'options' => ['Milan', 'Rome', 'Naples', 'Turin'],
            'correct_answer' => 'Rome'
        ]
    ]
];
check(true, $validator->validate($quizstranswer, 'quiz'), 'Quiz with correct_answer matching an option should pass');

// Valid quiz using correct field.
$quizcorrect = $quizstranswer;
unset($quizcorrect['questions'][0]['correct_answer']);
$quizcorrect['questions'][0]['correct'] = 'Rome';
check(true, $validator->validate($quizcorrect, 'quiz'), 'Quiz with correct field matching an option should pass');

// Valid quiz using answer field.
$quizanswer = $quizstranswer;
unset($quizanswer['questions'][0]['correct_answer']);
$quizanswer['questions'][0]['answer'] = 'Rome';
check(true, $validator->validate($quizanswer, 'quiz'), 'Quiz with answer field matching an option should pass');

// ── Quiz: invalid cases ────────────────────────────────────────────

// Missing questions key.
check(false, $validator->validate(['title' => 'Bad'], 'quiz'), 'Quiz missing questions should fail');

// Empty questions array.
check(false, $validator->validate(['title' => 'Bad', 'questions' => []], 'quiz'), 'Quiz with empty questions should fail');

// Empty question text.
$emptyq = $validquiz;
$emptyq['questions'][0]['question'] = '';
check(false, $validator->validate($emptyq, 'quiz'), 'Quiz with empty question text should fail');

// Only 2 options (was previously passing, now must fail).
$twoopts = [
    'title' => 'Bad Quiz',
    'questions' => [
        ['question' => 'Q1', 'options' => ['A', 'B'], 'correct_index' => 0]
    ]
];
check(false, $validator->validate($twoopts, 'quiz'), 'Quiz with only 2 options should fail');

// 5 options.
$fiveopts = [
    'title' => 'Bad Quiz',
    'questions' => [
        ['question' => 'Q1', 'options' => ['A', 'B', 'C', 'D', 'E'], 'correct_index' => 0]
    ]
];
check(false, $validator->validate($fiveopts, 'quiz'), 'Quiz with 5 options should fail');

// Option containing empty string.
$emptyopt = $validquiz;
$emptyopt['questions'][0]['options'] = ['A', '', 'C', 'D'];
check(false, $validator->validate($emptyopt, 'quiz'), 'Quiz with empty option string should fail');

// Option containing non-string value.
$numopt = $validquiz;
$numopt['questions'][0]['options'] = ['A', 42, 'C', 'D'];
check(false, $validator->validate($numopt, 'quiz'), 'Quiz with non-string option should fail');

// correct_index out of range (4).
$badidx = $validquiz;
$badidx['questions'][0]['correct_index'] = 4;
check(false, $validator->validate($badidx, 'quiz'), 'Quiz with correct_index 4 should fail');

// correct_index out of range (-1).
$negidx = $validquiz;
$negidx['questions'][0]['correct_index'] = -1;
check(false, $validator->validate($negidx, 'quiz'), 'Quiz with correct_index -1 should fail');

// correct_index as non-numeric string.
$strnonnum = $validquiz;
$strnonnum['questions'][0]['correct_index'] = 'banana';
check(false, $validator->validate($strnonnum, 'quiz'), 'Quiz with correct_index "banana" should fail');

// correct_answer that matches no option.
$nomatch = [
    'title' => 'Bad Quiz',
    'questions' => [
        [
            'question' => 'Q1',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_answer' => 'Z'
        ]
    ]
];
check(false, $validator->validate($nomatch, 'quiz'), 'Quiz with correct_answer matching no option should fail');

// No correct answer field at all.
$nocorrect = [
    'title' => 'Bad Quiz',
    'questions' => [
        [
            'question' => 'Q1',
            'options' => ['A', 'B', 'C', 'D']
        ]
    ]
];
check(false, $validator->validate($nocorrect, 'quiz'), 'Quiz with no correct answer field should fail');

// Malformed JSON string.
check(false, $validator->validate('not json', 'quiz'), 'Malformed JSON string should fail');

// ── Mindmap: valid and invalid ─────────────────────────────────────

// Valid mindmap with branches.
$validmindmap = ['title' => 'Mindmap', 'branches' => [['title' => 'Branch 1', 'summary' => 'S1']]];
check(true, $validator->validate($validmindmap, 'mindmap'), 'Valid mindmap with branches should pass');
check(true, $validator->validate(json_encode($validmindmap), 'mindmap'), 'Valid mindmap JSON string should pass');

// Empty branches array is valid (structural check only).
check(true, $validator->validate(['title' => 'M', 'branches' => []], 'mindmap'), 'Mindmap with empty branches should pass');

// Old schema using nodes instead of branches — must fail now.
check(false, $validator->validate(['title' => 'M', 'nodes' => []], 'mindmap'), 'Mindmap with nodes instead of branches should fail');

// Missing title.
check(false, $validator->validate(['branches' => []], 'mindmap'), 'Mindmap missing title should fail');

// Branches as string.
check(false, $validator->validate(['title' => 'M', 'branches' => 'not array'], 'mindmap'), 'Mindmap with branches as string should fail');

// ── Summary: valid and invalid ─────────────────────────────────────

$validsummary = ['title' => 'Summary', 'summary' => 'Text summary'];
check(true, $validator->validate($validsummary, 'summary'), 'Valid summary should pass');
check(true, $validator->validate(json_encode($validsummary), 'summary'), 'Valid summary JSON string should pass');
check(false, $validator->validate(['title' => 'Bad'], 'summary'), 'Summary missing summary field should fail');
check(false, $validator->validate(['title' => 'Bad', 'summary' => 123], 'summary'), 'Summary with non-string summary should fail');

// ── Blueprint: valid and invalid ───────────────────────────────────

check(true, $validator->validate(['title' => 'XR', 'scenes' => []], 'blueprint'), 'Blueprint with scenes should pass');
check(true, $validator->validate(['title' => 'XR', 'steps' => []], 'xr_blueprint'), 'xr_blueprint with steps should pass');
check(false, $validator->validate(['title' => 'Bad Blueprint'], 'blueprint'), 'Blueprint without scenes or steps should fail');

// ── Unknown type: fail-closed ──────────────────────────────────────

check(false, $validator->validate(['title' => 'Homework'], 'homework'), 'Unknown type should fail (fail-closed)');
check(false, $validator->validate(['anything' => true], 'flashcard'), 'Another unknown type should fail');

echo "ai_response_validator_test: OK\n";
