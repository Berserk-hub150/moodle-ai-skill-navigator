<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/mojibake_guard.php');
require_once(__DIR__ . '/../includes/error_remediation_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');
require_once(__DIR__ . '/../includes/knowledge_graph_helper.php');

use local_aiskillnavigator\service\embedding_service;
use local_aiskillnavigator\service\real_ai_service;

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);
if (isset($courseid) && (int)$courseid > 1 && function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}


$context = context_course::instance($courseid);


local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/quizgenerator.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('quizgenerator', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('quizgenerator', 'local_aiskillnavigator'));

$topic = optional_param('topic', '', PARAM_TEXT);
$difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);

// -1 = argomento libero senza materiali.
//  0 = tutti i materiali leggibili.
// >0 = singolo materiale selezionato.
$materialid = optional_param('materialid', -1, PARAM_INT);

$generate = optional_param('generate', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);
if ($generate && $action !== 'grade') {
    // AISN_HOTFIX_QUIZ_GENERATE_SESSKEY_V2
    require_sesskey();
}

$result = '';
$quiz = null;
$score = null;
$total = 0;
$studentanswers = [];
$savedmessage = '';
$selectedmaterials = [];
$parseerror = '';
$warning = '';
$ragdebug = '';
$ragsources = [];

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials($courseid);

$embeddingservice = new embedding_service();
$totalchunks = $embeddingservice->count_indexed_chunks($courseid);

$sourcemode = local_aiskillnavigator_material_source_mode_from_request(-1);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);
$selectedmaterials = local_aiskillnavigator_material_source_selected_materials($readablematerials, $sourcemode, $selectedmaterialids);
$materialid = local_aiskillnavigator_material_source_legacy_materialid($sourcemode, $selectedmaterialids);

if ($sourcemode === 'selected' && empty($selectedmaterialids)) {
    $warning = 'Select at least one teacher material or switch to all course materials.';
}

function local_aiskillnavigator_clean_ai_json_response(string $raw): string {
    $clean = trim($raw);

    $clean = preg_replace('/^```json\s*/i', '', $clean);
    $clean = preg_replace('/^```\s*/i', '', $clean);
    $clean = preg_replace('/\s*```$/', '', $clean);
    $clean = trim($clean);

    $start = strpos($clean, '{');

    if ($start === false) {
        return '';
    }

    $clean = substr($clean, $start);
    $clean = trim($clean);

    $lastbrace = strrpos($clean, '}');

    if ($lastbrace !== false) {
        $possiblejson = substr($clean, 0, $lastbrace + 1);
        $decoded = json_decode($possiblejson, true);

        if (is_array($decoded)) {
            return $possiblejson;
        }
    }

    return local_aiskillnavigator_repair_json($clean);
}

function local_aiskillnavigator_repair_json(string $json): string {
    $json = trim($json);

    $json = preg_replace('/,\s*([}\]])/', '$1', $json);

    $stack = [];
    $instring = false;
    $escaped = false;
    $length = strlen($json);

    for ($i = 0; $i < $length; $i++) {
        $char = $json[$i];

        if ($escaped) {
            $escaped = false;
            continue;
        }

        if ($char === '\\' && $instring) {
            $escaped = true;
            continue;
        }

        if ($char === '"') {
            $instring = !$instring;
            continue;
        }

        if ($instring) {
            continue;
        }

        if ($char === '{') {
            $stack[] = '}';
        } else if ($char === '[') {
            $stack[] = ']';
        } else if ($char === '}' || $char === ']') {
            if (!empty($stack) && end($stack) === $char) {
                array_pop($stack);
            }
        }
    }

    while (!empty($stack)) {
        $json .= array_pop($stack);
    }

    return $json;
}


function local_aisn_quiz_clean_rag_source_title(string $title): string {
    $title = preg_replace('/(?:Ãƒ|Ã‚|Ã‚|Ã¢â‚¬|â€™|â€šÃ‚|Æ’Ã†)[\s\S]*/u', '', $title);
    $title = preg_replace('/\s+/u', ' ', trim((string)$title));

    return $title !== '' ? $title : 'Course material';
}

function local_aiskillnavigator_extract_quiz_json(string $raw): ?array {
    $cleanresult = local_aiskillnavigator_clean_ai_json_response($raw);

    if ($cleanresult === '') {
        return null;
    }

    $decoded = json_decode($cleanresult, true);

    if (!is_array($decoded)) {
        return null;
    }

    if (empty($decoded['questions']) || !is_array($decoded['questions'])) {
        return null;
    }

    $decoded['questions'] = array_slice(array_values($decoded['questions']), 0, 3);

    foreach ($decoded['questions'] as $index => $question) {
        if (!is_array($question)) {
            return null;
        }

        if (empty($question['question'])) {
            return null;
        }

        if (empty($question['options']) || !is_array($question['options'])) {
            return null;
        }

        $decoded['questions'][$index]['options'] = array_slice(array_values($question['options']), 0, 4);

        if (count($decoded['questions'][$index]['options']) !== 4) {
            return null;
        }

        if (!isset($question['correct_index'])) {
            $correcttext = '';

            if (!empty($question['correct']) && is_string($question['correct'])) {
                $correcttext = $question['correct'];
            } else if (!empty($question['answer']) && is_string($question['answer'])) {
                $correcttext = $question['answer'];
            } else if (!empty($question['correct_answer']) && is_string($question['correct_answer'])) {
                $correcttext = $question['correct_answer'];
            }

            if ($correcttext !== '') {
                $foundindex = array_search($correcttext, $decoded['questions'][$index]['options'], true);
                $decoded['questions'][$index]['correct_index'] = $foundindex !== false ? (int) $foundindex : 0;
            } else {
                $decoded['questions'][$index]['correct_index'] = 0;
            }
        }

        $correctindex = (int) $decoded['questions'][$index]['correct_index'];

        if ($correctindex < 0 || $correctindex > 3) {
            $decoded['questions'][$index]['correct_index'] = 0;
        }

        if (empty($decoded['questions'][$index]['explanation'])) {
            $decoded['questions'][$index]['explanation'] = 'Risposta corretta secondo il materiale o argomento selezionato.';
        }

        if (empty($decoded['questions'][$index]['skill'])) {
            $decoded['questions'][$index]['skill'] = 'Concetto valutato';
        }
    }

    if (empty($decoded['title'])) {
        $decoded['title'] = 'Generated AI test';
    }

    if (empty($decoded['topic'])) {
        $decoded['topic'] = 'Argomento generico';
    }

    if (empty($decoded['difficulty'])) {
        $decoded['difficulty'] = 'medium';
    }

    return $decoded;
}

function local_aiskillnavigator_material_short_title(stdClass $material): string {
    $title = trim((string) ($material->title ?? 'Materiale senza titolo'));

    if ($title === '') {
        $title = 'Materiale senza titolo';
    }

    $contentlength = strlen((string) ($material->content ?? ''));

    return $title . ' (' . $contentlength . ' chars)';
}

if ($action === 'grade') {
    require_sesskey();

    $encodedquiz = required_param('quizdata', PARAM_RAW);
    $decodedjson = base64_decode($encodedquiz, true);

    if ($decodedjson !== false) {
        $quiz = json_decode($decodedjson, true);
    }

    if (is_array($quiz) && !empty($quiz['questions']) && is_array($quiz['questions'])) {
        $score = 0;
        $total = count($quiz['questions']);

        foreach ($quiz['questions'] as $index => $question) {
            $answer = optional_param('answer_' . $index, -1, PARAM_INT);
            $studentanswers[$index] = $answer;

            $correctindex = isset($question['correct_index']) ? (int) $question['correct_index'] : -1;

            if ($answer === $correctindex) {
                $score++;
            }
        }

        $percentage = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        $record = new stdClass();
        $record->courseid = $courseid;
        $record->userid = $USER->id;
        $record->topic = (string) ($quiz['topic'] ?? ($topic !== '' ? $topic : 'Argomento generico'));
        $record->difficulty = (string) ($quiz['difficulty'] ?? $difficulty);
        $record->score = $score;
        $record->maxscore = $total;
        $record->percentage = $percentage;
        $record->quizjson = json_encode($quiz, JSON_UNESCAPED_UNICODE);
        $record->answersjson = json_encode($studentanswers, JSON_UNESCAPED_UNICODE);
        $record->timecreated = time();

        $DB->insert_record('local_aiskillnav_attempt', $record);

        $savedmessage = 'Quiz attempt saved in the student profile.';
    }
} else if ($generate) {
    // AISN_PS1_QUIZ_GENERATE_SESSKEY
    require_sesskey();
    $selectedmaterials = local_aiskillnavigator_material_source_selected_materials($readablematerials, $sourcemode, $selectedmaterialids);

    $kgcontext = function_exists('local_aisn_kg_prompt_context')
        ? local_aisn_kg_prompt_context((int)$courseid, (string)$topic, 28)
        : '';

    if ($kgcontext !== '' && $sourcemode !== 'manual') {
        $selectedmaterials[] = (object)[
            'title' => 'Course Knowledge Graph',
            'content' => $kgcontext,
            'materialtype' => 'knowledge_graph',
        ];
    }

    $service = new real_ai_service();

    if ($sourcemode === 'manual') {
        $fallbacktopic = $topic !== '' ? $topic : 'Digital Twin';
        $result = $service->generate_quiz($fallbacktopic, $difficulty);
    } else if ($totalchunks > 0) {
        $searchquery = $topic !== '' ? $topic : 'quiz based on course materials';
        $results = local_aiskillnavigator_material_source_search($embeddingservice, $searchquery, $courseid, 6, $sourcemode, $selectedmaterialids);

        if (!empty($results)) {
            $ragcontext = $embeddingservice->build_context($results, 6500);
            if (!empty($kgcontext)) {
                $ragcontext .= "\n\nRAG_CONTEXT_PLUS_KNOWLEDGE_GRAPH\n" . $kgcontext;
            }
            $result = $service->generate_quiz_with_rag_context($topic, $difficulty, $ragcontext);
            $ragdebug = count($results) . ' RAG chunks retrieved, top similarity: ' . $results[0]->similarity;

            foreach ($results as $ragresult) {
                $ragsources[local_aisn_quiz_clean_rag_source_title((string)$ragresult->title) . ' chunk ' . (((int)$ragresult->chunkindex) + 1)] = $ragresult->similarity;
            }
        } else if (!empty($selectedmaterials)) {
            $warning = 'No RAG chunks found for this focus. Falling back to full material context.';
            $result = $service->generate_quiz_from_course_materials($topic, $difficulty, $selectedmaterials);
        } else {
            $warning = 'No RAG chunks found. Falling back to manual topic generation.';
            $result = $service->generate_quiz($topic !== '' ? $topic : 'Digital Twin', $difficulty);
        }
    } else if (!empty($selectedmaterials)) {
        $warning = 'Teacher materials exist but are not indexed for RAG yet. Falling back to full material context.';
        $result = $service->generate_quiz_from_course_materials($topic, $difficulty, $selectedmaterials);
    } else {
        $fallbacktopic = $topic !== '' ? $topic : 'Digital Twin';
        $result = $service->generate_quiz($fallbacktopic, $difficulty);
    }

    $quiz = local_aiskillnavigator_extract_quiz_json($result);

    if ($quiz === null) {
        if ($sourcemode !== 'manual' && $totalchunks > 0) {
            $searchquery = $topic !== '' ? $topic : 'quiz based on course materials';
        $results = local_aiskillnavigator_material_source_search($embeddingservice, $searchquery, $courseid, 6, $sourcemode, $selectedmaterialids);
            $ragcontext = $embeddingservice->build_context($results, 6500);
            $result = $service->generate_quiz_with_rag_context($topic, $difficulty, $ragcontext);
        } else {
            $result = !empty($selectedmaterials)
                ? $service->generate_quiz_from_course_materials($topic, $difficulty, $selectedmaterials)
                : $service->generate_quiz($topic !== '' ? $topic : 'Digital Twin', $difficulty);
        }

        $quiz = local_aiskillnavigator_extract_quiz_json($result);
    }

    if ($quiz === null) {
        $parseerror = 'The AI response could not be parsed as a structured test.';
    }
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo local_aiskillnavigator_error_remediation_css();
echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('quizgenerator', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Generate an AI micro-quiz from a generic topic or from teacher materials. In RAG mode, the quiz is grounded on the most relevant indexed chunks.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

if ($savedmessage !== '') {
    echo html_writer::div(s($savedmessage), 'alert alert-success');
}

if ($warning !== '') {
    echo html_writer::div(s($warning), 'alert alert-warning');
}

if ($totalchunks > 0) {
    echo html_writer::div(
        'RAG index active: ' . $totalchunks . ' chunks indexed. Quiz generation can use semantic retrieval.',
        'alert alert-success'
    );
} else if (empty($readablematerials)) {
    echo html_writer::div(
        'No readable teacher materials found yet. You can still generate a quiz from a manual topic.',
        'alert alert-warning'
    );
} else {
    echo html_writer::div(
        'Readable teacher materials available: ' . count($readablematerials) . ', but no RAG chunks are indexed yet. Re-index materials from Teacher Materials.',
        'alert alert-warning'
    );
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Generate a new test');

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/quizgenerator.php'),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'generate',
    'value' => '1',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);


echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => $courseid,
]);

echo html_writer::start_div('form-group');
echo local_aiskillnavigator_material_source_selector_html(
    $readablematerials,
    $embeddingservice,
    $courseid,
    $sourcemode,
    $selectedmaterialids,
    'Generation source',
    'Choose Manual topic only for a generic topic, all materials for full RAG search, or selected materials to use only specific uploaded files.'
);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');

echo html_writer::tag('label', 'Topic or optional focus', ['for' => 'topic']);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
    'placeholder' => 'Example: CSS, One Piece, Digital Twin, cybersecurity...',
]);

echo html_writer::tag(
    'small',
    'With manual topic mode this is the quiz topic. With teacher materials mode this is only an optional focus inside the selected materials.',
    ['class' => 'form-text text-muted']
);

echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');

echo html_writer::tag('label', 'Difficulty', ['for' => 'difficulty']);

echo html_writer::select(
    [
        'easy' => 'Easy',
        'medium' => 'Medium',
        'hard' => 'Hard',
    ],
    'difficulty',
    $difficulty,
    false,
    [
        'class' => 'form-control',
        'id' => 'difficulty',
    ]
);

echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Generate test',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

if ($quiz !== null) {
    $quizjson = json_encode($quiz, JSON_UNESCAPED_UNICODE);
    $encodedquiz = base64_encode($quizjson);

    echo html_writer::start_div('card mt-4 mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', s($quiz['title'] ?? 'Generated AI test'));

    echo html_writer::tag(
        'p',
        'Topic: ' . s($quiz['topic'] ?? ($topic !== '' ? $topic : 'Argomento generico')) .
        ' | Difficulty: ' . s($quiz['difficulty'] ?? $difficulty),
        ['class' => 'text-muted']
    );

    if (!empty($ragsources)) {
        echo html_writer::tag('p', 'Generated with RAG semantic retrieval:', ['class' => 'text-muted mb-1']);
        echo html_writer::start_tag('ul', ['class' => 'text-muted small']);

        foreach ($ragsources as $title => $similarity) {
            echo html_writer::tag(
                'li',
                s($title) . ' ' . html_writer::tag('span', 'similarity: ' . $similarity, ['class' => 'badge badge-info'])
            );
        }

        echo html_writer::end_tag('ul');

        if ($ragdebug !== '') {
            echo html_writer::tag('p', s($ragdebug), ['class' => 'text-muted small']);
        }
    } else if (!empty($selectedmaterials)) {
        $sourcenames = [];

        foreach ($selectedmaterials as $material) {
            $sourcenames[] = $material->title;
        }

        echo html_writer::tag(
            'p',
            'Generated from teacher materials: ' . s(implode(', ', $sourcenames)),
            ['class' => 'text-muted']
        );
    } else {
        echo html_writer::tag(
            'p',
            'Generated from manual topic, without teacher materials.',
            ['class' => 'text-muted']
        );
    }

    if ($score !== null) {
        $percentage = $total > 0 ? round(($score / $total) * 100) : 0;

        $alertclass = 'alert-danger';

        if ($percentage >= 80) {
            $alertclass = 'alert-success';
        } else if ($percentage >= 50) {
            $alertclass = 'alert-warning';
        }

        echo html_writer::div(
            html_writer::tag('h4', 'Result') .
            html_writer::tag('p', 'Score: ' . $score . '/' . $total . ' (' . $percentage . '%)'),
            'alert ' . $alertclass
        );
    }

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/aiskillnavigator/pages/quizgenerator.php'),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'action',
        'value' => 'grade',
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'courseid',
        'value' => $courseid,
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'topic',
        'value' => s($topic),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'difficulty',
        'value' => s($difficulty),
    ]);

    echo local_aiskillnavigator_material_source_hidden_fields($sourcemode, $selectedmaterialids);
echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'quizdata',
        'value' => $encodedquiz,
    ]);

    foreach ($quiz['questions'] as $index => $question) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h4', 'Question ' . ($index + 1));
        echo html_writer::tag('p', s($question['question'] ?? ''), ['class' => 'font-weight-bold']);

        $options = $question['options'] ?? [];
        $correctindex = isset($question['correct_index']) ? (int) $question['correct_index'] : -1;
        $selectedanswer = $studentanswers[$index] ?? null;

        if (is_array($options)) {
            foreach ($options as $optionindex => $option) {
                $inputid = 'q' . $index . '_option' . $optionindex;

                $attributes = [
                    'type' => 'radio',
                    'name' => 'answer_' . $index,
                    'id' => $inputid,
                    'value' => $optionindex,
                    'class' => 'mr-2',
                    'required' => 'required',
                ];

                if ($selectedanswer !== null && (int) $selectedanswer === (int) $optionindex) {
                    $attributes['checked'] = 'checked';
                }

                if ($score !== null) {
                    $attributes['disabled'] = 'disabled';
                }

                $labeltext = s($option);

                if ($score !== null && $optionindex === $correctindex) {
                    $labeltext .= ' ' . html_writer::tag('span', 'Correct answer', ['class' => 'badge badge-success ml-2']);
                }

                if (
                    $score !== null &&
                    $selectedanswer !== null &&
                    (int) $selectedanswer === (int) $optionindex &&
                    (int) $selectedanswer !== $correctindex
                ) {
                    $labeltext .= ' ' . html_writer::tag('span', 'Your answer', ['class' => 'badge badge-danger ml-2']);
                }

                echo html_writer::start_div('form-check mb-2');
                echo html_writer::empty_tag('input', $attributes);

                echo html_writer::tag('label', $labeltext, [
                    'for' => $inputid,
                    'class' => 'form-check-label',
                ]);

                echo html_writer::end_div();
            }
        }

        if ($score !== null) {
            if (!empty($question['skill'])) {
                echo html_writer::tag(
                    'p',
                    html_writer::tag('strong', 'Ability: ') . s($question['skill']),
                    ['class' => 'mt-3']
                );
            }

            if (!empty($question['explanation'])) {
                echo html_writer::tag(
                    'p',
                    html_writer::tag('strong', 'Explanation: ') . s($question['explanation'])
                );
            }
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    if ($score === null) {
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-success mt-3',
            'value' => 'Submit test',
        ]);
    } else {
        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/quizgenerator.php', [
                'generate' => 1,
                'topic' => $topic,
                'difficulty' => $difficulty,
                'materialid' => $materialid,
                'courseid' => $courseid,
                // AISN_FINAL_GENERATE_ANOTHER_SESSKEY
                'sesskey' => sesskey(),
            ]),
            'Generate another test',
            ['class' => 'btn btn-primary mt-3']
        );
    }

    echo html_writer::end_tag('form');
} else if ($result !== '') {
    echo html_writer::start_div('alert alert-warning mt-4');
    echo html_writer::tag('h4', s($parseerror !== '' ? $parseerror : 'The AI response could not be parsed as a structured test.'));
    echo html_writer::tag('p', 'Raw response:');

    echo html_writer::tag('pre', s($result), [
        'style' => 'white-space: pre-wrap; font-family: inherit;',
    ]);

    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Back to course', ['class' => 'btn btn-secondary']),
    'mt-4'
);

echo html_writer::end_div();
echo local_aiskillnavigator_mojibake_guard();
echo local_aiskillnavigator_quiz_tavily_video_assets_final_single((int)$courseid);
echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();




function local_aiskillnavigator_quiz_video_remediation_assets(int $courseid): string {
    $endpoint = new moodle_url('/local/aiskillnavigator/pages/quiz_error_video.php', ['courseid' => $courseid, 'sesskey' => sesskey()]);
    $endpointjson = json_encode($endpoint->out(false), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return <<<HTML
<style id="aisn-quiz-video-remediation-v1">
.aisn-video-remediation-card {
    margin-top: 18px;
    border: 1px solid #bfdbfe;
    border-left: 7px solid #0f6cbf;
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    border-radius: 18px;
    padding: 16px 18px;
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.aisn-video-remediation-card h4 {
    margin: 0 0 10px 0;
    color: #0f172a;
    font-weight: 900;
}
.aisn-video-remediation-card a {
    color: #0f6cbf;
    font-weight: 850;
    word-break: break-word;
}
.aisn-video-remediation-card p {
    margin: 9px 0 0 0;
    color: #475569;
    line-height: 1.5;
}
.aisn-video-remediation-chip {
    display: inline-block;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e3a8a;
    padding: 5px 10px;
    font-size: .82rem;
    font-weight: 900;
    margin-bottom: 8px;
}
.aisn-video-remediation-loading {
    margin-top: 18px;
    padding: 14px;
    border-radius: 16px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-weight: 800;
}
.aisn-video-remediation-muted {
    margin-top: 18px;
    padding: 14px;
    border-radius: 16px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-weight: 750;
}
</style>

<script id="aisn-quiz-video-remediation-v1-js">
(function () {
    const endpoint = {$endpointjson};

    function escapeHtml(value) {
        return String(value || "").replace(/[&<>"']/g, function(m) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","'":"&#039;"}[m];
        });
    }

    function cleanLine(value) {
        return String(value || "").replace(/Correct answer/g, "").replace(/\\s+/g, " ").trim();
    }

    function getTopic() {
        const url = new URL(window.location.href);
        const fromUrl = url.searchParams.get("topic");
        if (fromUrl) return fromUrl;

        const input = document.querySelector('input[name="topic"], textarea[name="topic"]');
        if (input && input.value) return input.value.trim();

        const lead = document.querySelector(".lead");
        if (lead) return lead.textContent.trim();

        return document.title || "";
    }

    function findQuestionCards() {
        return Array.from(document.querySelectorAll(".card, .aisn-card, section, div"))
            .filter(function (el) {
                const h = el.querySelector("h2,h3,h4");
                return h && /^Question\\s+\\d+/i.test((h.textContent || "").trim());
            });
    }

    function extractSkill(card) {
        const text = card.innerText || "";
        const match = (text.match(/Ability:\\s*([^\\n]+)/i) || text.match(/Skill:\\s*([^\\n]+)/i));
        return match ? cleanLine(match[1]) : "";
    }

    function extractExplanation(card) {
        const text = card.innerText || "";
        const match = text.match(/Explanation:\\s*([^\\n]+)/i);
        return match ? cleanLine(match[1]) : "";
    }

    function extractQuestion(card) {
        const h = card.querySelector("h2,h3,h4");
        if (!h) return "";
        let n = h.nextElementSibling;
        while (n) {
            const t = cleanLine(n.textContent || "");
            if (t && !/^padding$|^margin$|^gap$/i.test(t) && !t.includes("Correct answer")) {
                return t;
            }
            n = n.nextElementSibling;
        }
        return cleanLine(card.innerText || "").slice(0, 180);
    }

    function isWrong(card) {
        const checked = card.querySelector('input[type="radio"]:checked');
        if (!checked) return false;

        const checkedText = cleanLine((checked.closest("label") || checked.parentElement || checked).textContent || "");
        if (checkedText.includes("Correct answer")) return false;

        return true;
    }

    async function attachVideo(card) {
        if (card.dataset.aisnVideoRemediation === "1") return;
        card.dataset.aisnVideoRemediation = "1";

        if (!isWrong(card)) return;

        const skill = extractSkill(card) || "quiz ability";
        const question = extractQuestion(card);
        const explanation = extractExplanation(card);
        const topic = getTopic();

        const box = document.createElement("div");
        box.className = "aisn-video-remediation-loading";
        box.textContent = "Searching for a video/explanation to improve: " + skill + "...";
        card.appendChild(box);

        const url = endpoint +
            "&skill=" + encodeURIComponent(skill) +
            "&question=" + encodeURIComponent(question + " " + explanation) +
            "&topic=" + encodeURIComponent(topic);

        try {
            const response = await fetch(url, {credentials: "same-origin"});
            const data = await response.json();

            if (!data.ok) {
                box.className = "aisn-video-remediation-muted";
                box.textContent = data.message || "Nessuna risorsa trovata.";
                return;
            }

            const label = data.isvideo ? "Video consigliato dopo lâ€™errore" : "Risorsa consigliata dopo lâ€™errore";

            box.className = "aisn-video-remediation-card";
            box.innerHTML =
                '<span class="aisn-video-remediation-chip">' + escapeHtml(label) + '</span>' +
                '<h4>Recupero adattivo guidato dallâ€™errore</h4>' +
                '<p><strong>Ability to improve:</strong> ' + escapeHtml(skill) + '</p>' +
                '<p><a href="' + escapeHtml(data.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.title || data.url) + '</a></p>' +
                (data.snippet ? '<p>' + escapeHtml(data.snippet) + '</p>' : '') +
                '<p><strong>Mini-attivita:</strong> ' + escapeHtml(data.activity || '') + '</p>' +
                '<p><small>Fonte trovata tramite Search API: ' + escapeHtml(data.provider || '') + '</small></p>';
        } catch (e) {
            box.className = "aisn-video-remediation-muted";
            box.textContent = "Errore durante la ricerca online. Controlla configurazione Search API.";
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(function () {
            findQuestionCards().forEach(attachVideo);
        }, 300);
    });
})();
</script>
HTML;
}


function local_aiskillnavigator_quiz_video_dedupe_guard(): string {
    return <<<'HTML'
<script id="aisn-video-dedupe-v1">
(function () {
    function dedupe() {
        document.querySelectorAll(".aisn-video-remediation-card, .aisn-video-remediation-loading, .aisn-video-remediation-muted").forEach(function (card) {
            var parent = card.parentElement;
            if (!parent) return;

            var cards = parent.querySelectorAll(".aisn-video-remediation-card, .aisn-video-remediation-loading, .aisn-video-remediation-muted");
            if (cards.length <= 1) return;

            cards.forEach(function (c, index) {
                if (index > 0) c.remove();
            });
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        dedupe();
        setTimeout(dedupe, 500);
        setTimeout(dedupe, 1500);
        setTimeout(dedupe, 3000);
    });

    new MutationObserver(dedupe).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();
</script>
HTML;
}


function local_aiskillnavigator_quiz_clean_video_labels(): string {
    return <<<'HTML'
<script id="aisn-clean-video-labels-final-v1">
(function () {
    function cleanCard(card) {
        if (!card) return;

        var chip = card.querySelector(".aisn-video-remediation-chip");
        if (chip) {
            chip.textContent = "Video consigliato dopo l'errore";
        }

        var h4 = card.querySelector("h4");
        if (h4) {
            h4.textContent = "Recupero adattivo guidato dall'errore";
        }

        card.querySelectorAll("p").forEach(function (p) {
            var txt = p.textContent || "";

            if (txt.indexOf("Mini-") !== -1 || txt.indexOf("Mini") !== -1) {
                var idx = txt.indexOf("Guarda");
                if (idx >= 0) {
                    p.innerHTML = "<strong>Mini-attivita:</strong> " + txt.substring(idx);
                } else {
                    p.innerHTML = "<strong>Mini-attivita:</strong> Guarda la risorsa consigliata, poi riprova spiegando il concetto in 3 righe.";
                }
            }
        });
    }

    function run() {
        document.querySelectorAll(".aisn-video-remediation-card").forEach(cleanCard);
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 100);
        setTimeout(run, 400);
        setTimeout(run, 1000);
        setTimeout(run, 2500);
    });

    new MutationObserver(function () {
        run();
    }).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();
</script>
HTML;
}


function local_aiskillnavigator_quiz_video_rescue_final(): string {
    return <<<'HTML'
<script id="aisn-quiz-video-rescue-final-v1">
(function () {
    function escapeHtml(value) {
        return String(value || "").replace(/[&<>"']/g, function(m) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m];
        });
    }

    function findQuestionCard(box) {
        let el = box.parentElement;

        while (el && el !== document.body) {
            const text = el.innerText || "";
            if (/Question\s+\d+/i.test(text) && el.querySelector('input[type="radio"]')) {
                return el;
            }
            el = el.parentElement;
        }

        return box.parentElement;
    }

    function isWrongQuestion(card) {
        if (!card) return false;
        return /Your answer/i.test(card.innerText || "");
    }

    function extractSkill(card) {
        const text = card ? (card.innerText || "") : "";
        const match = (text.match(/Ability:\\s*([^\\n]+)/i) || text.match(/Skill:\\s*([^\\n]+)/i));
        return match ? match[1].replace(/\s+/g, " ").trim() : "quiz ability";
    }

    function extractQuestion(card) {
        if (!card) return "";
        const h = card.querySelector("h2,h3,h4");
        let n = h ? h.nextElementSibling : null;

        while (n) {
            const txt = (n.textContent || "").replace(/\s+/g, " ").trim();
            if (txt && !txt.includes("Correct answer") && !txt.includes("Your answer")) {
                return txt.slice(0, 180);
            }
            n = n.nextElementSibling;
        }

        return "";
    }

    function youtubeFallbackUrl(skill, question) {
        const query = (skill + " " + question + " tutorial spiegazione video").trim();
        return "https://www.youtube.com/results?search_query=" + encodeURIComponent(query);
    }

    function renderFallback(box, skill, question) {
        box.className = "aisn-video-remediation-card";
        box.innerHTML =
            '<span class="aisn-video-remediation-chip">Video consigliato dopo l\\'errore</span>' +
            '<h4>Recupero adattivo guidato dall\\'errore</h4>' +
            '<p><strong>Ability to improve:</strong> ' + escapeHtml(skill) + '</p>' +
            '<p><a href="' + escapeHtml(youtubeFallbackUrl(skill, question)) + '" target="_blank" rel="noopener noreferrer">Apri ricerca video YouTube mirata</a></p>' +
            '<p>La ricerca automatica sta impiegando troppo tempo, quindi viene proposta una ricerca video mirata sulla competenza da recuperare.</p>' +
            '<p><strong>Mini-attivita:</strong> guarda una spiegazione pertinente e poi riscrivi in 3 righe il concetto collegato.</p>' +
            '<p><small>Fonte: fallback YouTube mirato</small></p>';
    }

    function cleanCardLabels(card) {
        if (!card) return;

        const chip = card.querySelector(".aisn-video-remediation-chip");
        if (chip) chip.textContent = "Video consigliato dopo l'errore";

        const h4 = card.querySelector("h4");
        if (h4) h4.textContent = "Recupero adattivo guidato dall'errore";

        card.querySelectorAll("p").forEach(function (p) {
            const txt = p.textContent || "";
            if (txt.indexOf("Mini-") !== -1) {
                const idx = txt.indexOf("Guarda");
                const rest = idx >= 0 ? txt.substring(idx) : "guarda la risorsa consigliata e rispiega il concetto in 3 righe.";
                p.innerHTML = "<strong>Mini-attivita:</strong> " + escapeHtml(rest);
            }
        });
    }

    function rescue() {
        document.querySelectorAll(".aisn-video-remediation-loading, .aisn-video-remediation-card, .aisn-video-remediation-muted").forEach(function (box) {
            const card = findQuestionCard(box);

            if (!isWrongQuestion(card)) {
                box.remove();
                return;
            }

            if (box.classList.contains("aisn-video-remediation-card")) {
                cleanCardLabels(box);
                return;
            }

            if (!box.classList.contains("aisn-video-remediation-loading")) {
                return;
            }

            if (box.dataset.rescueAttached === "1") {
                return;
            }

            box.dataset.rescueAttached = "1";

            const skill = extractSkill(card);
            const question = extractQuestion(card);

            setTimeout(function () {
                if (box.isConnected && box.classList.contains("aisn-video-remediation-loading")) {
                    renderFallback(box, skill, question);
                }
            }, 6000);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        rescue();
        setTimeout(rescue, 300);
        setTimeout(rescue, 1200);
        setTimeout(rescue, 3000);
        setTimeout(rescue, 7000);
    });

    new MutationObserver(rescue).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();
</script>
HTML;
}


function local_aiskillnavigator_quiz_tavily_video_assets(int $courseid): string {
    $endpoint = new moodle_url('/local/aiskillnavigator/pages/quiz_error_video.php', ['courseid' => $courseid, 'sesskey' => sesskey()]);
    $endpointjson = json_encode($endpoint->out(false), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return <<<HTML
<style id="aisn-tavily-video-v1">
.aisn-tavily-video-card {
    margin-top: 18px;
    border: 1px solid #bfdbfe;
    border-left: 7px solid #0f6cbf;
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    border-radius: 18px;
    padding: 16px 18px;
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.aisn-tavily-video-chip {
    display: inline-block;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e3a8a;
    padding: 5px 10px;
    font-size: .82rem;
    font-weight: 900;
    margin-bottom: 8px;
}
.aisn-tavily-video-card h4 {
    margin: 0 0 10px 0;
    color: #0f172a;
    font-weight: 900;
}
.aisn-tavily-video-card a {
    color: #0f6cbf;
    font-weight: 850;
    word-break: break-word;
}
.aisn-tavily-video-card p {
    margin: 9px 0 0 0;
    color: #475569;
    line-height: 1.5;
}
.aisn-tavily-video-loading {
    margin-top: 18px;
    padding: 14px;
    border-radius: 16px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-weight: 800;
}
.aisn-tavily-video-muted {
    margin-top: 18px;
    padding: 14px;
    border-radius: 16px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-weight: 750;
}
</style>

<script id="aisn-tavily-video-v1-js">
(function () {
    const endpoint = {$endpointjson};

    function clean(value) {
        return String(value || "")
            .replace(/Correct answer/g, "")
            .replace(/Your answer/g, "")
            .replace(/\\s+/g, " ")
            .trim();
    }

    function escapeHtml(value) {
        return String(value || "").replace(/[&<>"']/g, function(m) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","'":"&#039;"}[m];
        });
    }

    function fixLabels(text) {
        return String(text || "")
            .replace(/â‚¬â€žÂ¢/g, "'")
            .replace(/â€™/g, "'")
            .replace(/â€™/g, "'")
            .replace(/â€™/g, "'")
            .replace(/Ã /g, "a'")
            .replace(/Ã¨/g, "e'")
            .replace(/Ã©/g, "e'")
            .replace(/Ã¬/g, "i'")
            .replace(/Ã²/g, "o'")
            .replace(/Ã¹/g, "u'")
            .replace(/Ãƒ /g, "a'")
            .replace(/Ã¨/g, "e'")
            .replace(/Ã©/g, "e'")
            .replace(/Ã¬/g, "i'")
            .replace(/Ã²/g, "o'")
            .replace(/Ã¹/g, "u'");
    }

    function findQuestionCards() {
        return Array.from(document.querySelectorAll(".card, .aisn-card, section, div"))
            .filter(function (el) {
                const h = el.querySelector("h2,h3,h4");
                if (!h) return false;
                if (!/^Question\\s+\\d+/i.test(clean(h.textContent))) return false;
                return !!el.querySelector('input[type="radio"]');
            });
    }

    function isWrong(card) {
        const checked = card.querySelector('input[type="radio"]:checked');
        if (!checked) return false;

        const label = checked.closest("label") || checked.parentElement;
        const labelText = label ? label.textContent : "";

        if (/Correct answer/i.test(labelText)) return false;
        return /Your answer/i.test(labelText) || /Your answer/i.test(card.innerText || "");
    }

    function extractSkill(card) {
        const text = card.innerText || "";
        const match = (text.match(/Ability:\\s*([^\\n]+)/i) || text.match(/Skill:\\s*([^\\n]+)/i));
        return match ? clean(match[1]) : "quiz ability";
    }

    function extractQuestion(card) {
        const h = card.querySelector("h2,h3,h4");
        let node = h ? h.nextElementSibling : null;

        while (node) {
            const t = clean(node.textContent || "");
            if (
                t &&
                !t.includes("Correct answer") &&
                !t.includes("Your answer") &&
                !/^required$|^mandatory$|^necessary$|^compulsory$|^padding$|^margin$|^gap$|^src$|^alt$|^title$|^width$/i.test(t)
            ) {
                return t.slice(0, 180);
            }
            node = node.nextElementSibling;
        }

        return clean(card.innerText || "").slice(0, 180);
    }

    function renderCard(box, data, skill) {
        const label = data.isvideo ? "Video consigliato dopo l'errore" : "Risorsa consigliata dopo l'errore";

        box.className = "aisn-tavily-video-card";
        box.innerHTML =
            '<span class="aisn-tavily-video-chip">' + escapeHtml(label) + '</span>' +
            '<h4>Recupero adattivo guidato dall\\'errore</h4>' +
            '<p><strong>Ability to improve:</strong> ' + escapeHtml(skill) + '</p>' +
            '<p><a href="' + escapeHtml(data.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(fixLabels(data.title || data.url)) + '</a></p>' +
            (data.snippet ? '<p>' + escapeHtml(fixLabels(data.snippet)) + '</p>' : '') +
            '<p><strong>Mini-attivita:</strong> ' + escapeHtml(fixLabels(data.activity || "Guarda la risorsa e rispiega il concetto in 3 righe.")) + '</p>' +
            '<p><small>Fonte trovata tramite Search API: ' + escapeHtml(data.provider || "tavily") + '</small></p>';
    }

    async function addCard(card) {
        if (card.dataset.tavilyVideoDone === "1") return;
        card.dataset.tavilyVideoDone = "1";

        if (!isWrong(card)) return;

        card.querySelectorAll(".aisn-direct-youtube-card, .aisn-video-remediation-card, .aisn-video-remediation-loading, .aisn-video-remediation-muted, .aisn-tavily-video-card, .aisn-tavily-video-loading, .aisn-tavily-video-muted").forEach(function (el) {
            el.remove();
        });

        const skill = extractSkill(card);
        const question = extractQuestion(card);

        const box = document.createElement("div");
        box.className = "aisn-tavily-video-loading";
        box.textContent = "Searching with Tavily for a video/tutorial to improve: " + skill + "...";
        card.appendChild(box);

        const controller = new AbortController();
        const timer = setTimeout(function () {
            controller.abort();
        }, 12000);

        const url = endpoint +
            "&skill=" + encodeURIComponent(skill) +
            "&question=" + encodeURIComponent(question) +
            "&topic=" + encodeURIComponent(document.title || "");

        try {
            const response = await fetch(url, {
                credentials: "same-origin",
                signal: controller.signal
            });

            clearTimeout(timer);

            const data = await response.json();

            if (!data.ok) {
                box.className = "aisn-tavily-video-muted";
                box.textContent = fixLabels(data.message || "Tavily non ha trovato una risorsa utile.");
                return;
            }

            renderCard(box, data, skill);
        } catch (e) {
            clearTimeout(timer);
            box.className = "aisn-tavily-video-muted";
            box.textContent = "Timeout Tavily o errore di rete. Riprova o controlla la configurazione Search API.";
        }
    }

    function run() {
        document.querySelectorAll(".aisn-direct-youtube-card, .aisn-video-remediation-card, .aisn-video-remediation-loading, .aisn-video-remediation-muted").forEach(function (el) {
            el.remove();
        });

        findQuestionCards().forEach(addCard);
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 500);
        setTimeout(run, 1500);
    });
})();
</script>
HTML;
}


function local_aiskillnavigator_quiz_tavily_video_assets_final_single(int $courseid): string {
    $endpoint = new moodle_url('/local/aiskillnavigator/pages/quiz_error_video.php', ['courseid' => $courseid, 'sesskey' => sesskey()]);
    $endpointjson = json_encode($endpoint->out(false), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return <<<HTML
<style id="aisn-tavily-final-v1">
.aisn-tavily-final-card {
    margin-top: 18px;
    border: 1px solid #bfdbfe;
    border-left: 7px solid #0f6cbf;
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    border-radius: 18px;
    padding: 16px 18px;
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.aisn-tavily-final-chip {
    display: inline-block;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e3a8a;
    padding: 5px 10px;
    font-size: .82rem;
    font-weight: 900;
    margin-bottom: 8px;
}
.aisn-tavily-final-card h4 {
    margin: 0 0 10px 0;
    color: #0f172a;
    font-weight: 900;
}
.aisn-tavily-final-card a {
    color: #0f6cbf;
    font-weight: 850;
    word-break: break-word;
}
.aisn-tavily-final-card p {
    margin: 9px 0 0 0;
    color: #475569;
    line-height: 1.5;
}
.aisn-tavily-final-loading,
.aisn-tavily-final-muted {
    margin-top: 18px;
    padding: 14px;
    border-radius: 16px;
    font-weight: 800;
}
.aisn-tavily-final-loading {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
}
.aisn-tavily-final-muted {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
}
</style>

<script id="aisn-tavily-final-v1-js">
(function () {
    const endpoint = {$endpointjson};

    function clean(value) {
        return String(value || "")
            .replace(/Correct answer/g, "")
            .replace(/Your answer/g, "")
            .replace(/\\s+/g, " ")
            .trim();
    }

    function escapeHtml(value) {
        return String(value || "").replace(/[&<>"']/g, function(m) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","'":"&#039;"}[m];
        });
    }

    function findQuestionCards() {
        return Array.from(document.querySelectorAll(".card, .aisn-card, section, div"))
            .filter(function (el) {
                const h = el.querySelector("h2,h3,h4");
                if (!h) return false;
                if (!/^Question\\s+\\d+/i.test(clean(h.textContent))) return false;
                return !!el.querySelector('input[type="radio"]');
            });
    }

    function isWrong(card) {
        const checked = card.querySelector('input[type="radio"]:checked');
        if (!checked) return false;

        const label = checked.closest("label") || checked.parentElement;
        const labelText = label ? label.textContent : "";

        if (/Correct answer/i.test(labelText)) return false;
        return /Your answer/i.test(labelText) || /Your answer/i.test(card.innerText || "");
    }

    function extractSkill(card) {
        const text = card.innerText || "";
        const match = (text.match(/Ability:\\s*([^\\n]+)/i) || text.match(/Skill:\\s*([^\\n]+)/i));
        return match ? clean(match[1]) : "quiz ability";
    }

    function extractQuestion(card) {
        const h = card.querySelector("h2,h3,h4");
        let node = h ? h.nextElementSibling : null;

        while (node) {
            const t = clean(node.textContent || "");
            if (
                t &&
                !t.includes("Correct answer") &&
                !t.includes("Your answer") &&
                !/^required$|^mandatory$|^necessary$|^compulsory$|^padding$|^margin$|^gap$|^src$|^alt$|^title$|^width$/i.test(t)
            ) {
                return t.slice(0, 180);
            }
            node = node.nextElementSibling;
        }

        return clean(card.innerText || "").slice(0, 180);
    }

    function removeAllOldCards(card) {
        card.querySelectorAll(
            ".aisn-direct-youtube-card, " +
            ".aisn-video-remediation-card, .aisn-video-remediation-loading, .aisn-video-remediation-muted, " +
            ".aisn-tavily-video-card, .aisn-tavily-video-loading, .aisn-tavily-video-muted, " +
            ".aisn-tavily-final-card, .aisn-tavily-final-loading, .aisn-tavily-final-muted"
        ).forEach(function (el) {
            el.remove();
        });
    }

    function renderCard(box, data, skill) {
        const label = data.isvideo ? "Video consigliato dopo l'errore" : "Risorsa consigliata dopo l'errore";

        box.className = "aisn-tavily-final-card";
        box.innerHTML =
            '<span class="aisn-tavily-final-chip">' + escapeHtml(label) + '</span>' +
            '<h4>Recupero adattivo guidato dall\\'errore</h4>' +
            '<p><strong>Ability to improve:</strong> ' + escapeHtml(skill) + '</p>' +
            '<p><a href="' + escapeHtml(data.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.title || data.url) + '</a></p>' +
            (data.snippet ? '<p>' + escapeHtml(data.snippet) + '</p>' : '') +
            '<p><strong>Mini-attivita:</strong> ' + escapeHtml(data.activity || "Guarda la risorsa e rispiega il concetto in 3 righe.") + '</p>' +
            '<p><small>Fonte trovata tramite Search API: ' + escapeHtml(data.provider || "tavily") + '</small></p>';
    }

    async function addSingleCard(card) {
        if (!isWrong(card)) {
            removeAllOldCards(card);
            return;
        }

        if (card.dataset.aisnTavilyFinalDone === "1") {
            const cards = card.querySelectorAll(".aisn-tavily-final-card, .aisn-tavily-final-loading, .aisn-tavily-final-muted");
            cards.forEach(function (el, i) {
                if (i > 0) el.remove();
            });
            return;
        }

        card.dataset.aisnTavilyFinalDone = "1";
        removeAllOldCards(card);

        const skill = extractSkill(card);
        const question = extractQuestion(card);

        const box = document.createElement("div");
        box.className = "aisn-tavily-final-loading";
        box.textContent = "Searching with Tavily for a video/tutorial to improve: " + skill + "...";
        card.appendChild(box);

        const url = endpoint +
            "&skill=" + encodeURIComponent(skill) +
            "&question=" + encodeURIComponent(question) +
            "&topic=" + encodeURIComponent(document.title || "");

        try {
            const response = await fetch(url, {credentials: "same-origin"});
            const data = await response.json();

            if (!data.ok) {
                box.className = "aisn-tavily-final-muted";
                box.textContent = data.message || "Tavily non ha trovato una risorsa utile.";
                return;
            }

            renderCard(box, data, skill);
        } catch (e) {
            box.className = "aisn-tavily-final-muted";
            box.textContent = "Errore Tavily/Search API. Controlla configurazione o rete.";
        }
    }

    function run() {
        findQuestionCards().forEach(addSingleCard);
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 500);
        setTimeout(run, 1500);
    });
})();
</script>
HTML;
}




