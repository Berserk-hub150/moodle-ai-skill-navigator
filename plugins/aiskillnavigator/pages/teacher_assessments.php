<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');
require_once(__DIR__ . '/../includes/knowledge_graph_helper.php');

use local_aiskillnavigator\service\ai_provider_factory;
use local_aiskillnavigator\service\embedding_service;

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);
require_login($course);

if ((int)$courseid > 1 && function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]));
$PAGE->set_title('Initial/final tests');
$PAGE->set_heading('Initial/final tests');

$action = optional_param('action', '', PARAM_ALPHA);
$message = '';
$error = '';
$rawresponse = '';

function local_aisn_ass_table_exists(string $tablename): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($tablename));
}

function local_aisn_ass_clean_json(string $raw): string {
    $clean = trim($raw);
    $clean = preg_replace('/^```json\s*/i', '', $clean);
    $clean = preg_replace('/^```\s*/i', '', $clean);
    $clean = preg_replace('/\s*```$/', '', $clean);
    $start = strpos($clean, '{');
    if ($start === false) {
        return '';
    }
    $clean = substr($clean, $start);
    $lastbrace = strrpos($clean, '}');
    if ($lastbrace !== false) {
        $clean = substr($clean, 0, $lastbrace + 1);
    }
    return trim($clean);
}

function local_aisn_ass_normalize_question(array $question): ?array {
    $text = trim((string)($question['question'] ?? ''));
    if ($text === '') {
        return null;
    }

    $options = isset($question['options']) && is_array($question['options'])
        ? array_values($question['options'])
        : [];

    $options = array_map(static function($option): string {
        return trim((string)$option);
    }, $options);

    $options = array_slice($options, 0, 4);
    while (count($options) < 4) {
        $options[] = '';
    }

    $nonempty = array_values(array_filter($options, static function($option): bool {
        return trim((string)$option) !== '';
    }));

    if (count($nonempty) < 2) {
        return null;
    }

    $correct = (int)($question['correct_index'] ?? 0);
    $correct = max(0, min(3, $correct));

    if (trim((string)$options[$correct]) === '') {
        $correct = 0;
    }

    $ability = trim((string)($question['ability'] ?? $question['skill'] ?? ''));
    if ($ability === '') {
        $ability = 'Ability evaluated';
    }

    $explanation = trim((string)($question['explanation'] ?? ''));
    if ($explanation === '') {
        $explanation = 'Correct answer based on the assessment context.';
    }

    return [
        'question' => $text,
        'options' => $options,
        'correct_index' => $correct,
        'ability' => $ability,
        'skill' => $ability,
        'explanation' => $explanation,
    ];
}

function local_aisn_ass_parse_quiz(string $raw): ?array {
    $clean = local_aisn_ass_clean_json($raw);
    if ($clean === '') {
        return null;
    }

    $decoded = json_decode($clean, true);
    if (!is_array($decoded) || empty($decoded['questions']) || !is_array($decoded['questions'])) {
        return null;
    }

    $questions = [];
    foreach (array_slice(array_values($decoded['questions']), 0, 20) as $question) {
        if (!is_array($question)) {
            continue;
        }
        $normalized = local_aisn_ass_normalize_question($question);
        if ($normalized !== null) {
            $questions[] = $normalized;
        }
    }

    if (empty($questions)) {
        return null;
    }

    return [
        'title' => trim((string)($decoded['title'] ?? 'AI assessment')),
        'topic' => trim((string)($decoded['topic'] ?? 'Course materials')),
        'type' => trim((string)($decoded['type'] ?? '')),
        'questions' => $questions,
    ];
}

function local_aisn_ass_context_from_materials(array $materials, int $limit = 7500): string {
    $context = '';
    $source = 1;
    foreach ($materials as $material) {
        $title = trim((string)($material->title ?? 'Untitled material'));
        $type = trim((string)($material->materialtype ?? 'text'));
        $content = trim((string)($material->content ?? ''));
        if ($content === '') {
            continue;
        }
        $content = trim((string)preg_replace('/\s+/u', ' ', $content));
        $block = "SOURCE {$source}\nTitle: {$title}\nType: {$type}\nContent: {$content}\n\n";
        if (core_text::strlen($context . $block) > $limit) {
            $remaining = $limit - core_text::strlen($context);
            if ($remaining > 250) {
                $context .= core_text::substr($block, 0, $remaining) . "...\n\n";
            }
            break;
        }
        $context .= $block;
        $source++;
    }
    return trim($context);
}

function local_aisn_ass_build_prompt(string $type, string $focus, string $difficulty, string $materialcontext): string {
    $isfinal = $type === 'final';
    $typename = $isfinal ? 'final comprehension test / post-test' : 'initial diagnostic quiz / pre-test';
    $goal = $isfinal
        ? 'measure what students learned after the lesson or module using teacher materials as the source of truth'
        : 'measure prior knowledge and prerequisites before students study the teacher materials';

    $focus = trim($focus) !== '' ? trim($focus) : ($isfinal ? 'main concepts in the selected materials' : 'general prerequisites for the module');

    $prompt = "Generate a Moodle {$typename}.\n\n";
    $prompt .= "Goal: {$goal}.\n";
    $prompt .= "Focus: {$focus}.\n";
    $prompt .= "Difficulty: {$difficulty}.\n\n";

    if ($isfinal) {
        $prompt .= "Use ONLY the selected course materials below. Do not invent unsupported facts.\n\n";
        $prompt .= "COURSE MATERIALS:\n{$materialcontext}\n\n";
    } else {
        $prompt .= "Do NOT use teacher materials. Generate prerequisite questions only.\n\n";
    }

    $prompt .= "Return ONLY valid JSON, no Markdown and no extra text.\n";
    $prompt .= "Required structure:\n";
    $prompt .= "{\n";
    $prompt .= "  \"title\": \"Assessment title\",\n";
    $prompt .= "  \"topic\": \"Topic\",\n";
    $prompt .= "  \"type\": \"{$type}\",\n";
    $prompt .= "  \"questions\": [\n";
    $prompt .= "    {\n";
    $prompt .= "      \"question\": \"Question text\",\n";
    $prompt .= "      \"options\": [\"A\", \"B\", \"C\", \"D\"],\n";
    $prompt .= "      \"correct_index\": 0,\n";
    $prompt .= "      \"ability\": \"Ability evaluated\",\n";
    $prompt .= "      \"skill\": \"Ability evaluated\",\n";
    $prompt .= "      \"explanation\": \"Why the answer is correct\"\n";
    $prompt .= "    }\n";
    $prompt .= "  ]\n";
    $prompt .= "}\n\n";
    $prompt .= "Generate exactly 5 multiple-choice questions. Each question must have exactly 4 options. correct_index must be between 0 and 3.\n";
    return $prompt;
}

function local_aisn_ass_generate(string $type, string $focus, string $difficulty, string $context): array {
    $prompt = local_aisn_ass_build_prompt($type, $focus, $difficulty, $context);
    $provider = ai_provider_factory::create_from_config();
    $raw = $provider->generate($prompt, 3200, 'You are a strict JSON generator for Moodle assessments. Return only valid JSON.');
    return [local_aisn_ass_parse_quiz($raw), $raw];
}

function local_aisn_ass_badge(string $type): string {
    return $type === 'final'
        ? html_writer::span('Final test', 'badge bg-success')
        : html_writer::span('Pre-test', 'badge bg-info');
}

function local_aisn_ass_type_label(string $type): string {
    return $type === 'final'
        ? 'Final comprehension test / post-test'
        : 'Initial diagnostic quiz / pre-test';
}

function local_aisn_ass_get_assessment(int $id, int $courseid): stdClass {
    global $DB;
    return $DB->get_record('local_aiskillnav_assessment', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
}

function local_aisn_ass_get_attempt_count(int $assessmentid): int {
    global $DB;
    return local_aisn_ass_table_exists('local_aiskillnav_ass_att')
        ? $DB->count_records('local_aiskillnav_ass_att', ['assessmentid' => $assessmentid])
        : 0;
}

function local_aisn_ass_questions_from_form(): array {
    // AISN_ASS_NESTED_OPTION_FIX_V1
    // Moodle optional_param_array() non gestisce bene array annidati tipo option[key][].
    // Qui leggiamo $_POST in modo controllato e puliamo solo valori scalari con clean_param().
    $post = $_POST;

    $keysraw = isset($post['qkey']) && is_array($post['qkey']) ? $post['qkey'] : [];
    $questionraw = isset($post['question']) && is_array($post['question']) ? $post['question'] : [];
    $optionraw = isset($post['option']) && is_array($post['option']) ? $post['option'] : [];
    $correctraw = isset($post['correct_index']) && is_array($post['correct_index']) ? $post['correct_index'] : [];
    $abilityraw = isset($post['ability']) && is_array($post['ability']) ? $post['ability'] : [];
    $explanationraw = isset($post['explanation']) && is_array($post['explanation']) ? $post['explanation'] : [];

    $cleanstring = static function($value, string $type = PARAM_RAW_TRIMMED): string {
        if (is_array($value)) {
            return '';
        }

        return clean_param((string)$value, $type);
    };

    $cleanint = static function($value): int {
        if (is_array($value)) {
            return 0;
        }

        return (int)clean_param((string)$value, PARAM_INT);
    };

    $questions = [];

    foreach ($keysraw as $keyraw) {
        if (is_array($keyraw)) {
            continue;
        }

        $key = clean_param((string)$keyraw, PARAM_ALPHANUMEXT);
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$key);

        if ($key === '') {
            continue;
        }

        $options = [];
        $rawoptions = isset($optionraw[$key]) && is_array($optionraw[$key]) ? $optionraw[$key] : [];

        foreach (array_slice(array_values($rawoptions), 0, 4) as $rawoption) {
            $options[] = $cleanstring($rawoption, PARAM_RAW_TRIMMED);
        }

        while (count($options) < 4) {
            $options[] = '';
        }

        $normalized = local_aisn_ass_normalize_question([
            'question' => $cleanstring($questionraw[$key] ?? '', PARAM_RAW_TRIMMED),
            'options' => $options,
            'correct_index' => $cleanint($correctraw[$key] ?? 0),
            'ability' => $cleanstring($abilityraw[$key] ?? '', PARAM_TEXT),
            'explanation' => $cleanstring($explanationraw[$key] ?? '', PARAM_RAW_TRIMMED),
        ]);

        if ($normalized !== null) {
            $questions[] = $normalized;
        }
    }

    if (empty($questions)) {
        throw new moodle_exception('At least one valid question with at least two non-empty options is required.');
    }

    return $questions;
}
function local_aisn_ass_render_question_editor(string $key, int $number, array $question): string {
    $question = local_aisn_ass_normalize_question($question) ?? [
        'question' => '',
        'options' => ['', '', '', ''],
        'correct_index' => 0,
        'ability' => '',
        'explanation' => '',
    ];
    $options = array_values($question['options']);
    while (count($options) < 4) {
        $options[] = '';
    }
    $correct = max(0, min(3, (int)($question['correct_index'] ?? 0)));

    $html = html_writer::start_div('card mb-3 aisn-question-card', ['data-question-card' => '1']);
    $html .= html_writer::start_div('card-body');
    $html .= html_writer::tag('h4', 'Question ' . $number, ['data-question-title' => '1']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'qkey[]', 'value' => $key]);

    $html .= html_writer::start_div('form-group');
    $html .= html_writer::tag('label', 'Question text');
    $html .= html_writer::tag('textarea', s((string)$question['question']), [
        'name' => 'question[' . $key . ']',
        'class' => 'form-control',
        'rows' => 3,
        'required' => 'required',
    ]);
    $html .= html_writer::end_div();

    for ($i = 0; $i < 4; $i++) {
        $html .= html_writer::start_div('form-group mt-2');
        $html .= html_writer::tag('label', 'Option ' . chr(65 + $i));
        $html .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'option[' . $key . '][]',
            'class' => 'form-control',
            'value' => s((string)$options[$i]),
        ]);
        $html .= html_writer::end_div();
    }

    $html .= html_writer::start_div('form-group mt-2');
    $html .= html_writer::tag('label', 'Correct answer');
    $html .= html_writer::select([0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'], 'correct_index[' . $key . ']', $correct, false, ['class' => 'form-control custom-select aisn-wide-select']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('form-group mt-2');
    $html .= html_writer::tag('label', 'Ability');
    $html .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'ability[' . $key . ']',
        'class' => 'form-control',
        'value' => s((string)($question['ability'] ?? $question['skill'] ?? '')),
    ]);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('form-group mt-2');
    $html .= html_writer::tag('label', 'Explanation');
    $html .= html_writer::tag('textarea', s((string)($question['explanation'] ?? '')), [
        'name' => 'explanation[' . $key . ']',
        'class' => 'form-control',
        'rows' => 2,
    ]);
    $html .= html_writer::end_div();

    $html .= html_writer::tag('button', 'Remove question', ['type' => 'button', 'class' => 'btn btn-outline-danger btn-sm mt-3', 'data-remove-question' => '1']);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

function local_aisn_ass_render_edit_form(stdClass $assessment, array $quiz, int $courseid, int $attemptcount): void {
    $questions = isset($quiz['questions']) && is_array($quiz['questions']) ? array_values($quiz['questions']) : [];
    if (empty($questions)) {
        $questions = [[
            'question' => '',
            'options' => ['', '', '', ''],
            'correct_index' => 0,
            'ability' => '',
            'skill' => '',
            'explanation' => '',
        ]];
    }

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', 'Edit test');
    echo html_writer::tag('p', 'The teacher can modify title, focus, difficulty, publication status, questions, options, correct answers, abilities and explanations.', ['class' => 'text-muted']);

    if ($attemptcount > 0) {
        echo html_writer::div('This test already has ' . $attemptcount . ' student attempt(s). Saving changes resets previous attempts and statistics.', 'alert alert-warning');
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php')]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'update']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$assessment->id]);

    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', 'Assessment title');
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'title', 'class' => 'form-control', 'required' => 'required', 'value' => s((string)$assessment->title)]);
    echo html_writer::end_div();

    echo html_writer::start_div('form-group mt-3');
    echo html_writer::tag('label', 'Assessment type');
    echo html_writer::tag('div', local_aisn_ass_type_label((string)$assessment->assessmenttype), ['class' => 'form-control-plaintext font-weight-bold']);
    echo html_writer::end_div();

    echo html_writer::start_div('form-group mt-3');
    echo html_writer::tag('label', 'Focus / topic');
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'focus', 'class' => 'form-control', 'value' => s((string)$assessment->focus)]);
    echo html_writer::end_div();

    echo html_writer::start_div('form-group mt-3');
    echo html_writer::tag('label', 'Difficulty');
    echo html_writer::select(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'], 'difficulty', (string)$assessment->difficulty, false, ['class' => 'form-control custom-select aisn-wide-select']);
    echo html_writer::end_div();

    echo html_writer::start_div('form-check mt-3');
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'visible', 'value' => 0]);
    $visibleattrs = ['type' => 'checkbox', 'name' => 'visible', 'id' => 'edit_visible', 'class' => 'form-check-input', 'value' => 1];
    if (!empty($assessment->visible)) {
        $visibleattrs['checked'] = 'checked';
    }
    echo html_writer::empty_tag('input', $visibleattrs);
    echo html_writer::tag('label', 'Published to students', ['for' => 'edit_visible', 'class' => 'form-check-label']);
    echo html_writer::end_div();

    echo html_writer::tag('hr', '');
    echo html_writer::start_div('', ['id' => 'aisn-question-editor-list']);
    foreach ($questions as $index => $question) {
        echo local_aisn_ass_render_question_editor('q' . $index, $index + 1, is_array($question) ? $question : []);
    }
    echo html_writer::end_div();

    echo html_writer::tag('button', 'Add question', ['type' => 'button', 'class' => 'btn btn-outline-primary mb-3', 'id' => 'aisn-add-question']);

    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => $attemptcount > 0 ? 'Save changes and reset attempts' : 'Save test changes']);
    echo ' ';
    echo html_writer::link(new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]), 'Cancel', ['class' => 'btn btn-outline-secondary']);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');

    echo html_writer::script(<<<'JS'
(function() {
    const list = document.getElementById('aisn-question-editor-list');
    const add = document.getElementById('aisn-add-question');
    if (!list || !add) { return; }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"]/g, function(ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch];
        });
    }

    function renumber() {
        Array.from(list.querySelectorAll('[data-question-card]')).forEach(function(card, index) {
            const title = card.querySelector('[data-question-title]');
            if (title) { title.textContent = 'Question ' + (index + 1); }
        });
    }

    function cardHtml(key) {
        return `
<div class="card mb-3 aisn-question-card" data-question-card="1">
  <div class="card-body">
    <h4 data-question-title="1">Question</h4>
    <input type="hidden" name="qkey[]" value="${escapeHtml(key)}">
    <div class="form-group"><label>Question text</label><textarea name="question[${escapeHtml(key)}]" class="form-control" rows="3" required></textarea></div>
    <div class="form-group mt-2"><label>Option A</label><input type="text" name="option[${escapeHtml(key)}][]" class="form-control"></div>
    <div class="form-group mt-2"><label>Option B</label><input type="text" name="option[${escapeHtml(key)}][]" class="form-control"></div>
    <div class="form-group mt-2"><label>Option C</label><input type="text" name="option[${escapeHtml(key)}][]" class="form-control"></div>
    <div class="form-group mt-2"><label>Option D</label><input type="text" name="option[${escapeHtml(key)}][]" class="form-control"></div>
    <div class="form-group mt-2"><label>Correct answer</label><select name="correct_index[${escapeHtml(key)}]" class="form-control"><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></div>
    <div class="form-group mt-2"><label>Ability</label><input type="text" name="ability[${escapeHtml(key)}]" class="form-control"></div>
    <div class="form-group mt-2"><label>Explanation</label><textarea name="explanation[${escapeHtml(key)}]" class="form-control" rows="2"></textarea></div>
    <button type="button" class="btn btn-outline-danger btn-sm mt-3" data-remove-question="1">Remove question</button>
  </div>
</div>`;
    }

    add.addEventListener('click', function() {
        const key = 'q' + Date.now() + Math.floor(Math.random() * 1000);
        list.insertAdjacentHTML('beforeend', cardHtml(key));
        renumber();
    });

    list.addEventListener('click', function(event) {
        const button = event.target.closest('[data-remove-question]');
        if (!button) { return; }
        const cards = list.querySelectorAll('[data-question-card]');
        if (cards.length <= 1) {
            alert('At least one question is required.');
            return;
        }
        button.closest('[data-question-card]').remove();
        renumber();
    });

    renumber();
})();
JS);

    echo html_writer::end_div();
    echo html_writer::end_div();
}

function local_aisn_ass_redirect_self(int $courseid, string $message = ''): void {
    redirect(new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]), $message, $message !== '' ? 1 : 0);
}

if ($action === 'delete') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    $assessment = local_aisn_ass_get_assessment($id, $courseid);
    if (local_aisn_ass_table_exists('local_aiskillnav_ass_att')) {
        $DB->delete_records('local_aiskillnav_ass_att', ['assessmentid' => $assessment->id]);
    }
    $DB->delete_records('local_aiskillnav_assessment', ['id' => $assessment->id]);
    local_aisn_ass_redirect_self($courseid, 'Assessment deleted.');
}

if ($action === 'toggle') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    $assessment = local_aisn_ass_get_assessment($id, $courseid);
    $assessment->visible = (int)!$assessment->visible;
    $assessment->timemodified = time();
    $DB->update_record('local_aiskillnav_assessment', $assessment);
    local_aisn_ass_redirect_self($courseid, $assessment->visible ? 'Assessment published to students.' : 'Assessment hidden from students.');
}

if ($action === 'update') {
    require_sesskey();
    try {
        $id = required_param('id', PARAM_INT);
        $assessment = local_aisn_ass_get_assessment($id, $courseid);
        $attemptcount = local_aisn_ass_get_attempt_count((int)$assessment->id);
        $oldquizjson = (string)$assessment->quizjson;

        $title = required_param('title', PARAM_TEXT);
        $focus = optional_param('focus', '', PARAM_TEXT);
        $difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);
        $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium';
        $visible = optional_param('visible', 0, PARAM_BOOL);
        $questions = local_aisn_ass_questions_from_form();

        $oldquiz = json_decode($oldquizjson, true);
        if (!is_array($oldquiz)) {
            $oldquiz = [];
        }
        $oldquiz['title'] = $title;
        $oldquiz['topic'] = $focus !== '' ? $focus : ($oldquiz['topic'] ?? 'Assessment');
        $oldquiz['type'] = (string)$assessment->assessmenttype;
        $oldquiz['questions'] = $questions;

        $newquizjson = json_encode($oldquiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $quizchanged = $newquizjson !== $oldquizjson;

        $assessment->title = $title;
        $assessment->focus = $focus;
        $assessment->difficulty = $difficulty;
        $assessment->visible = $visible ? 1 : 0;
        $assessment->quizjson = $newquizjson;
        $assessment->timemodified = time();
        $DB->update_record('local_aiskillnav_assessment', $assessment);

        if ($quizchanged && $attemptcount > 0 && local_aisn_ass_table_exists('local_aiskillnav_ass_att')) {
            $DB->delete_records('local_aiskillnav_ass_att', ['assessmentid' => $assessment->id]);
            local_aisn_ass_redirect_self($courseid, 'Assessment updated. Previous attempts were reset because the questions changed.');
        }
        local_aisn_ass_redirect_self($courseid, 'Assessment updated.');
    } catch (Throwable $e) {
        $error = 'Could not save assessment: ' . $e->getMessage();
        $action = '';
    }
}

if ($action === 'generate') {
    require_sesskey();
    $type = optional_param('assessmenttype', 'pre', PARAM_ALPHA);
    $type = in_array($type, ['pre', 'final'], true) ? $type : 'pre';
    $title = required_param('title', PARAM_TEXT);
    $focus = optional_param('focus', '', PARAM_TEXT);
    $difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);
    $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium';
    $visible = optional_param('visible', 0, PARAM_BOOL);

    $readablematerials = local_aiskillnavigator_material_source_get_readable_materials($courseid);
    $embeddingservice = new embedding_service();
    $contexttext = '';
    $selectedmaterials = [];
    $recordmaterialids = [];

    if ($type === 'final') {
        $sourcemode = local_aiskillnavigator_material_source_mode_from_request(0);
        $selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);
        if (empty($readablematerials)) {
            $error = 'Final test cannot be generated yet: add at least one readable course material first.';
        }
        if ($error === '' && empty($selectedmaterialids)) {
            $error = 'Final test requires at least one selected course material.';
        }
        if ($error === '') {
            $selectedmaterials = local_aiskillnavigator_material_source_selected_materials($readablematerials, $sourcemode, $selectedmaterialids);
            if (empty($selectedmaterials)) {
                $error = 'Final test requires at least one readable selected material.';
            }
        }
        if ($error === '') {
            $recordmaterialids = array_values(array_map(static function($material): int {
                return (int)$material->id;
            }, $selectedmaterials));
            $totalchunks = $embeddingservice->count_indexed_chunks($courseid);
            if ($totalchunks > 0) {
                $query = trim($focus) !== '' ? $focus : 'final test course material concepts';
                $results = local_aiskillnavigator_material_source_search($embeddingservice, $query, $courseid, 8, $sourcemode, $selectedmaterialids);
                if (!empty($results)) {
                    $contexttext = $embeddingservice->build_context($results, 8000);
                }
            }
            if ($contexttext === '') {
                $contexttext = local_aisn_ass_context_from_materials($selectedmaterials, 8000);
            }
            if (trim($contexttext) === '') {
                $error = 'Final test could not be generated because selected materials have no usable text.';
            }
        }
    } else {
        $sourcemode = 'manual';
        $selectedmaterialids = [];
    }

    if ($error === '') {
        $kgcontext = function_exists('local_aisn_kg_prompt_context')
            ? local_aisn_kg_prompt_context((int)$courseid, (string)$focus, 32)
            : '';

        if ($kgcontext !== '') {
            $contexttext = trim($contexttext . "\n\nINITIAL_FINAL_TEST_KNOWLEDGE_GRAPH_CONTEXT\n" . $kgcontext);
        }
    }

    if ($error === '') {
        try {
            [$quiz, $rawresponse] = local_aisn_ass_generate($type, $focus, $difficulty, $contexttext);
        } catch (Throwable $e) {
            $quiz = null;
            $error = 'AI generation failed: ' . $e->getMessage();
        }
    }

    if ($error === '') {
        if ($quiz === null) {
            $error = 'The AI response could not be parsed as valid assessment JSON.';
        } else {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->userid = $USER->id;
            $record->title = $title;
            $record->assessmenttype = $type;
            $record->focus = $focus;
            $record->difficulty = $difficulty;
            $record->quizjson = json_encode($quiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $record->sourcemode = $type === 'pre' ? 'manual' : $sourcemode;
            $record->materialids = json_encode($type === 'pre' ? [] : $recordmaterialids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $record->visible = $visible ? 1 : 0;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('local_aiskillnav_assessment', $record);
            local_aisn_ass_redirect_self($courseid, $type === 'final' ? 'Final test generated. Use Edit test before publishing if needed.' : 'Initial diagnostic quiz generated. Use Edit test before publishing if needed.');
        }
    }
}

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials($courseid);
$embeddingservice = new embedding_service();
$sourcemode = local_aiskillnavigator_material_source_mode_from_request(0);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);
$assessments = local_aisn_ass_table_exists('local_aiskillnav_assessment')
    ? $DB->get_records('local_aiskillnav_assessment', ['courseid' => $courseid], 'timecreated DESC')
    : [];

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();
echo html_writer::tag('style', <<<'CSS'
/* AISN_INITIAL_FINAL_MATERIAL_VISIBILITY_FIX_V1 */
/* I materiali devono comparire solo per il final test, non per initial/pre-test. */
body.path-local-aiskillnavigator #aisn-final-material-source {
    display: none !important;
}

body.path-local-aiskillnavigator #aisn-final-material-source.aisn-visible-for-final {
    display: block !important;
}

body.path-local-aiskillnavigator .aisn-final-material-note {
    background: #eef7ff !important;
    border: 1px solid #bfdbfe !important;
    color: #0f3b68 !important;
    border-radius: 14px !important;
    padding: 10px 14px !important;
    margin: 12px 0 0 0 !important;
    font-weight: 700 !important;
}
CSS);

echo html_writer::script(<<<'JS'
// AISN_INITIAL_FINAL_MATERIAL_VISIBILITY_FIX_V1
(function () {
    function updateInitialFinalMaterialsVisibility() {
        var type = document.getElementById('assessmenttype');
        var box = document.getElementById('aisn-final-material-source');

        if (!type || !box) {
            return;
        }

        var isFinal = String(type.value || '').toLowerCase() === 'final';

        if (isFinal) {
            box.classList.add('aisn-visible-for-final');
            box.style.setProperty('display', 'block', 'important');
        } else {
            box.classList.remove('aisn-visible-for-final');
            box.style.setProperty('display', 'none', 'important');
        }

        box.querySelectorAll('input, select, textarea, button').forEach(function (el) {
            if (isFinal) {
                el.removeAttribute('disabled');
            } else {
                el.setAttribute('disabled', 'disabled');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateInitialFinalMaterialsVisibility);
    } else {
        updateInitialFinalMaterialsVisibility();
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'assessmenttype') {
            updateInitialFinalMaterialsVisibility();
        }
    });

    setTimeout(updateInitialFinalMaterialsVisibility, 100);
    setTimeout(updateInitialFinalMaterialsVisibility, 500);
})();
JS);

echo html_writer::tag('style', <<<'CSS'
/* AISN_ASS_FORM_VISUAL_FIX_V1 */
body.path-local-aiskillnavigator .card .card-body form .form-group {
    display: block !important;
    width: 100% !important;
    margin-bottom: 20px !important;
}

body.path-local-aiskillnavigator .card .card-body form label {
    display: block !important;
    width: 100% !important;
    margin: 0 0 8px 0 !important;
    font-weight: 900 !important;
    color: #0f172a !important;
    line-height: 1.25 !important;
}

body.path-local-aiskillnavigator .card .card-body form input.form-control,
body.path-local-aiskillnavigator .card .card-body form select.form-control,
body.path-local-aiskillnavigator .card .card-body form textarea.form-control {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    min-height: 46px !important;
    height: auto !important;
    padding: 10px 14px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 12px !important;
    box-sizing: border-box !important;
    line-height: 1.45 !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 15px !important;
}

body.path-local-aiskillnavigator .card .card-body form select.form-control {
    appearance: auto !important;
    -webkit-appearance: menulist !important;
    padding-right: 42px !important;
    cursor: pointer !important;
}

body.path-local-aiskillnavigator #assessmenttype,
body.path-local-aiskillnavigator select[name="difficulty"] {
    width: 100% !important;
    max-width: 720px !important;
    min-width: 360px !important;
}

body.path-local-aiskillnavigator .form-control-plaintext {
    display: block !important;
    width: 100% !important;
    max-width: 720px !important;
    min-height: 46px !important;
    padding: 10px 14px !important;
    border-radius: 12px !important;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
    box-sizing: border-box !important;
}

body.path-local-aiskillnavigator .form-check {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    margin-top: 18px !important;
}

body.path-local-aiskillnavigator .form-check-input {
    position: static !important;
    margin: 0 !important;
    width: 18px !important;
    height: 18px !important;
}

body.path-local-aiskillnavigator .form-check-label {
    display: inline-block !important;
    width: auto !important;
    margin: 0 !important;
}

body.path-local-aiskillnavigator .card .card-body {
    overflow: visible !important;
}

@media (max-width: 700px) {
    body.path-local-aiskillnavigator #assessmenttype,
    body.path-local-aiskillnavigator select[name="difficulty"] {
        min-width: 0 !important;
        max-width: 100% !important;
    }
}
CSS);


echo html_writer::tag('style', <<<'CSS'
/* AISN_ASS_SELECT_VISIBILITY_FIX_V1 */
body.path-local-aiskillnavigator form,
body.path-local-aiskillnavigator .card,
body.path-local-aiskillnavigator .card-body,
body.path-local-aiskillnavigator .container-fluid,
body.path-local-aiskillnavigator #region-main,
body.path-local-aiskillnavigator [role="main"] {
    overflow: visible !important;
}

body.path-local-aiskillnavigator select,
body.path-local-aiskillnavigator select.form-control,
body.path-local-aiskillnavigator select.custom-select {
    display: block !important;
    width: min(720px, 100%) !important;
    max-width: 100% !important;
    min-width: 360px !important;
    min-height: 48px !important;
    height: auto !important;
    padding: 10px 42px 10px 14px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 12px !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    font-size: 15px !important;
    line-height: 1.45 !important;
    box-sizing: border-box !important;
    position: relative !important;
    z-index: 50 !important;
    appearance: auto !important;
    -webkit-appearance: menulist !important;
}

body.path-local-aiskillnavigator .aisn-select-visible-group {
    display: block !important;
    width: 100% !important;
    overflow: visible !important;
    position: relative !important;
    z-index: 80 !important;
}

body.path-local-aiskillnavigator .aisn-select-visible-group.aisn-select-open-space {
    margin-bottom: 150px !important;
    z-index: 10000 !important;
}

body.path-local-aiskillnavigator .aisn-select-visible-group select {
    z-index: 10001 !important;
}

@media (max-width: 700px) {
    body.path-local-aiskillnavigator select,
    body.path-local-aiskillnavigator select.form-control,
    body.path-local-aiskillnavigator select.custom-select {
        width: 100% !important;
        min-width: 0 !important;
    }

    body.path-local-aiskillnavigator .aisn-select-visible-group.aisn-select-open-space {
        margin-bottom: 170px !important;
    }
}
CSS);

echo html_writer::script(<<<'JS'
// AISN_ASS_SELECT_VISIBILITY_FIX_V1
(function () {
    function applySelectVisibilityFix() {
        var selects = document.querySelectorAll('select');

        selects.forEach(function (select) {
            if (select.dataset.aisnSelectVisibilityFix === '1') {
                return;
            }

            select.dataset.aisnSelectVisibilityFix = '1';
            select.classList.add('aisn-select-visible');

            select.style.display = 'block';
            select.style.width = window.innerWidth <= 700 ? '100%' : 'min(720px, 100%)';
            select.style.maxWidth = '100%';
            select.style.minWidth = window.innerWidth <= 700 ? '0' : '360px';
            select.style.minHeight = '48px';
            select.style.height = 'auto';
            select.style.padding = '10px 42px 10px 14px';
            select.style.boxSizing = 'border-box';
            select.style.position = 'relative';
            select.style.zIndex = '10001';

            var group = select.closest('.form-group, .mb-3, .form-inline, .fitem') || select.parentElement;

            if (!group) {
                return;
            }

            group.classList.add('aisn-select-visible-group');
            group.style.overflow = 'visible';
            group.style.position = 'relative';

            function openSpace() {
                group.classList.add('aisn-select-open-space');
                group.style.marginBottom = window.innerWidth <= 700 ? '170px' : '150px';
                group.style.zIndex = '10000';
            }

            function closeSpace() {
                window.setTimeout(function () {
                    group.classList.remove('aisn-select-open-space');
                    group.style.marginBottom = '';
                    group.style.zIndex = '';
                }, 250);
            }

            select.addEventListener('focus', openSpace);
            select.addEventListener('mousedown', openSpace);
            select.addEventListener('touchstart', openSpace);
            select.addEventListener('blur', closeSpace);
            select.addEventListener('change', closeSpace);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applySelectVisibilityFix);
    } else {
        applySelectVisibilityFix();
    }

    window.addEventListener('resize', applySelectVisibilityFix);
})();
JS);

echo html_writer::start_div('container-fluid');
echo html_writer::tag('h2', 'Initial/final tests');
echo html_writer::tag('p', 'Create an initial diagnostic quiz before the lesson and a final test grounded on selected course materials. The teacher can edit tests before publishing.', ['class' => 'lead']);
echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if ($action === 'edit') {
    $id = required_param('id', PARAM_INT);
    $editassessment = local_aisn_ass_get_assessment($id, $courseid);
    $editquiz = json_decode((string)$editassessment->quizjson, true);
    if (!is_array($editquiz)) {
        $editquiz = ['title' => $editassessment->title, 'topic' => $editassessment->focus, 'questions' => []];
    }
    $attemptcount = local_aisn_ass_get_attempt_count((int)$editassessment->id);
    local_aisn_ass_render_edit_form($editassessment, $editquiz, $courseid, $attemptcount);
    echo html_writer::end_div();
    echo local_aisn_back_to_course_autofix((int)$courseid);
    if (function_exists('local_aisn_ai_output_formatter_assets')) {
        echo local_aisn_ai_output_formatter_assets();
    }
    echo $OUTPUT->footer();
    exit;
}

if ($message !== '') {
    echo html_writer::div(s($message), 'alert alert-success');
}
if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
    if ($rawresponse !== '') {
        echo html_writer::tag('pre', s($rawresponse), ['style' => 'white-space: pre-wrap; background:#f8f9fa; padding:12px; border-radius:8px;']);
    }
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', 'Generate initial/final assessment');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php')]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'generate']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Assessment title');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'title', 'class' => 'form-control', 'required' => 'required', 'placeholder' => 'Example: Initial diagnostic quiz - HTML basics']);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Assessment type');
echo html_writer::select(['pre' => 'Initial diagnostic quiz / pre-test', 'final' => 'Final comprehension test / post-test'], 'assessmenttype', 'pre', false, ['class' => 'form-control custom-select aisn-wide-select', 'id' => 'assessmenttype']);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3', ['id' => 'aisn-final-material-source']);
echo html_writer::tag('div', 'Initial quiz ignores teacher materials. Final test is grounded on selected course materials.', ['class' => 'alert alert-info py-2']);
echo local_aiskillnavigator_material_source_selector_html($readablematerials, $embeddingservice, $courseid, $sourcemode, $selectedmaterialids, 'Course material', 'Used only for the final test.');
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Focus / topic');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'focus', 'class' => 'form-control', 'placeholder' => 'Example: HTML structure, functions, IoT sensors...']);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Difficulty');
echo html_writer::select(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'], 'difficulty', 'medium', false, ['class' => 'form-control custom-select aisn-wide-select']);
echo html_writer::end_div();

echo html_writer::start_div('form-check mt-3');
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'visible', 'value' => 0]);
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'visible', 'id' => 'visible', 'class' => 'form-check-input', 'value' => 1]);
echo html_writer::tag('label', 'Publish immediately to students', ['for' => 'visible', 'class' => 'form-check-label']);
echo html_writer::end_div();

echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary mt-3', 'value' => 'Generate and save assessment']);
echo html_writer::end_tag('form');
echo html_writer::script("(function(){var type=document.getElementById('assessmenttype');var box=document.getElementById('aisn-final-material-source');if(!type||!box){return;}function sync(){box.style.display=type.value==='final'?'':'none';}type.addEventListener('change',sync);sync();})();");
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('h3', 'Saved assessments');
if (empty($assessments)) {
    echo html_writer::div('No assessments created yet.', 'alert alert-info');
} else {
    foreach ($assessments as $assessment) {
        $attempts = local_aisn_ass_table_exists('local_aiskillnav_ass_att')
            ? $DB->get_records('local_aiskillnav_ass_att', ['assessmentid' => $assessment->id], 'percentage DESC, timecreated ASC')
            : [];
        $count = count($attempts);
        $sum = 0;
        $high = 0;
        $low = 0;
        foreach ($attempts as $attempt) {
            $sum += (int)$attempt->percentage;
            if ((int)$attempt->percentage >= 75) { $high++; }
            if ((int)$attempt->percentage < 50) { $low++; }
        }
        $avg = $count > 0 ? round($sum / $count, 1) : 0;
        $quiz = json_decode((string)$assessment->quizjson, true);
        $qcount = is_array($quiz) && !empty($quiz['questions']) && is_array($quiz['questions']) ? count($quiz['questions']) : 0;

        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h4', s($assessment->title) . ' ' . local_aisn_ass_badge((string)$assessment->assessmenttype));
        echo html_writer::tag('p', 'Type: ' . local_aisn_ass_type_label((string)$assessment->assessmenttype) . ' | Focus: ' . s($assessment->focus !== '' ? $assessment->focus : 'General') . ' | Difficulty: ' . s($assessment->difficulty) . ' | Questions: ' . $qcount . ' | Status: ' . ($assessment->visible ? 'Published' : 'Hidden') . ' | Created: ' . userdate($assessment->timecreated), ['class' => 'text-muted']);

        echo html_writer::start_div('row mb-3');
        echo html_writer::div(html_writer::tag('strong', (string)$count) . html_writer::empty_tag('br') . 'Attempts', 'col-md-3 alert alert-secondary');
        echo html_writer::div(html_writer::tag('strong', (string)$avg . '%') . html_writer::empty_tag('br') . 'Average score', 'col-md-3 alert alert-info');
        echo html_writer::div(html_writer::tag('strong', (string)$high) . html_writer::empty_tag('br') . 'Strong results', 'col-md-3 alert alert-success');
        echo html_writer::div(html_writer::tag('strong', (string)$low) . html_writer::empty_tag('br') . 'Below 50%', 'col-md-3 alert alert-warning');
        echo html_writer::end_div();

        echo html_writer::link(new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid, 'action' => 'edit', 'id' => $assessment->id]), 'Edit test', ['class' => 'btn btn-outline-primary btn-sm mr-2']);
echo html_writer::link(new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid, 'action' => 'toggle', 'id' => $assessment->id, 'sesskey' => sesskey()]), $assessment->visible ? 'Hide from students' : 'Publish to students', ['class' => 'btn btn-outline-secondary btn-sm mr-2']);
        echo html_writer::link(new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid, 'action' => 'delete', 'id' => $assessment->id, 'sesskey' => sesskey()]), 'Delete', ['class' => 'btn btn-outline-danger btn-sm']);
        // AISN_EXPORT_LINKS_FINAL_OK
        echo html_writer::start_div('mt-2 mb-2');
        echo html_writer::span('Export: ', 'text-muted mr-1');
        foreach ([
            'json' => 'JSON',
            'gift' => 'Moodle GIFT',
            'google' => 'Google Forms CSV',
            'csv' => 'Generic CSV'
        ] as $format => $label) {
            echo html_writer::link(
                new moodle_url('/local/aiskillnavigator/pages/export_assessment.php', [
                    'courseid' => $courseid,
                    'assessmentid' => (int)$assessment->id,
                    'format' => $format,
                    'sesskey' => sesskey(),
                ]),
                $label,
                ['class' => 'btn btn-sm btn-outline-secondary mr-1']
            );
        }
        echo html_writer::end_div();

        if (!empty($attempts)) {
            echo html_writer::tag('h5', 'Student results', ['class' => 'mt-4']);
            echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
            echo html_writer::start_tag('thead');
            echo html_writer::tag('tr', html_writer::tag('th', 'Student') . html_writer::tag('th', 'Score') . html_writer::tag('th', 'Percentage') . html_writer::tag('th', 'Submitted'));
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($attempts as $attempt) {
                $student = $DB->get_record('user', ['id' => $attempt->userid], 'id, firstname, lastname, email');
                $studentname = $student ? fullname($student) : 'User ' . $attempt->userid;
                echo html_writer::tag('tr', html_writer::tag('td', s($studentname)) . html_writer::tag('td', (int)$attempt->score . '/' . (int)$attempt->maxscore) . html_writer::tag('td', (int)$attempt->percentage . '%') . html_writer::tag('td', userdate($attempt->timecreated)));
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo html_writer::div(html_writer::link(new moodle_url('/local/aiskillnavigator/pages/gap_analysis.php', ['courseid' => $courseid]), 'Open AI learning-gap analysis', ['class' => 'btn btn-outline-primary mt-3']) . ' ' . html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Back to course', ['class' => 'btn btn-secondary mt-3']));
echo html_writer::end_div();
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();

