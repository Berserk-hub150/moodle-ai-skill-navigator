<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/simulator_materials_helper.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../classes/service/web_search_service.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);

$action = optional_param('action', '', PARAM_ALPHA);
$topic = optional_param('topic', '', PARAM_TEXT);
$level = optional_param('level', 'medium', PARAM_ALPHA);
$notes = optional_param('notes', '', PARAM_RAW_TRIMMED);

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

local_aisn_sim_require_materials_for_post((int)$courseid);

// AISN_SIM_DIRECT_MATERIAL_CONTEXT_V1
// Keep the selected course materials in a real variable.
// Do not rely only on $_POST/$_REQUEST mutation, because $notes may already be read.
$selectedmaterialids = local_aisn_sim_selected_ids();
$selectedmaterialcontext = '';

if (!empty($selectedmaterialids)) {
    $selectedmaterialcontext = local_aisn_sim_material_context((int)$courseid, $selectedmaterialids);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Simulator Finder');
$PAGE->set_heading('AI Simulator Finder');

function local_aiskillnavigator_sim_clean(string $text): string {
    $text = trim($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function local_aiskillnavigator_sim_known_catalogue(): array {
    return [
        'Arduino, IoT, sensors, circuits' => 'Wokwi, Tinkercad Circuits, Arduino Web Editor',
        'electronics and physics' => 'PhET Interactive Simulations, Falstad Circuit Simulator',
        'programming and algorithms' => 'Replit, Trinket, Python Tutor, CodePen',
        'web development' => 'CodePen, JSFiddle, StackBlitz',
        'networking and security' => 'Cisco Packet Tracer, TryHackMe rooms, Wireshark sample labs',
        'math and functions' => 'GeoGebra, Desmos, PhET',
        'machine learning and data' => 'Google Teachable Machine, Orange Data Mining, Kaggle notebooks',
        'databases and SQL' => 'DB Fiddle, SQLite Online, SQLBolt',
        'cloud and containers' => 'Play with Docker, Killercoda, Katacoda-style labs',
    ];
}

function local_aiskillnavigator_sim_call_ai(string $prompt, string $systemprompt): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate($prompt, 1800, $systemprompt);
        }

        if (
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_config') &&
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_selector')
        ) {
            $config = new \local_aiskillnavigator\service\provider\ai_provider_config();
            $selector = new \local_aiskillnavigator\service\provider\ai_provider_selector();
            $provider = $selector->create($config);
            return $provider->generate($prompt, 1800, $systemprompt);
        }
    } catch (Throwable $e) {
        return 'AI error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}

function local_aiskillnavigator_sim_search_context(array $results): string {
    if (empty($results)) {
        return "No live search results available.\n";
    }

    $context = '';

    foreach ($results as $index => $row) {
        $title = trim((string)($row['title'] ?? 'Untitled result'));
        $url = trim((string)($row['url'] ?? ''));
        $snippet = trim((string)($row['snippet'] ?? ''));

        $context .= 'Result ' . ($index + 1) . "\n";
        $context .= 'Title: ' . $title . "\n";
        $context .= 'URL: ' . $url . "\n";
        $context .= 'Snippet: ' . $snippet . "\n\n";
    }

    return $context;
}

function local_aiskillnavigator_sim_inline_format(string $line): string {
    $line = trim($line);

    if ($line === '') {
        return '';
    }

    $safe = s($line);

    $safe = preg_replace('/\*\*(.*?)\*\*/u', '<strong>$1</strong>', $safe);

    $safe = preg_replace_callback('/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/u', function ($matches) {
        return html_writer::link(
            $matches[2],
            s($matches[1]),
            ['target' => '_blank', 'rel' => 'noopener noreferrer']
        );
    }, $safe);

    $safe = preg_replace_callback('/(?<!href=")(https?:\/\/[^\s<]+)/u', function ($matches) {
        $url = rtrim($matches[1], '.,;)');
        return html_writer::link(
            $url,
            s($url),
            ['target' => '_blank', 'rel' => 'noopener noreferrer']
        );
    }, $safe);

    return $safe;
}

function local_aiskillnavigator_sim_section_title(string $raw): string {
    $raw = trim($raw);
    $raw = preg_replace('/^\*\*(.*?)\*\*:?\s*$/u', '$1', $raw);
    $raw = preg_replace('/:\s*$/u', '', $raw);
    return trim($raw);
}

function local_aiskillnavigator_render_simulator_result(string $text): string {
    $text = local_aiskillnavigator_sim_clean($text);

    if ($text === '') {
        return html_writer::div('No simulator suggestion generated yet.', 'alert alert-info');
    }

    $html = html_writer::tag('style', '
.aisn-sim-result {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 22px;
    padding: 18px;
}
.aisn-sim-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 18px 20px;
    margin-bottom: 14px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, .05);
}
.aisn-sim-section:last-child {
    margin-bottom: 0;
}
.aisn-sim-section h4 {
    margin: 0 0 10px 0;
    color: #0f172a;
    font-size: 1.08rem;
    font-weight: 850;
}
.aisn-sim-section p {
    margin: 0 0 8px 0;
    color: #334155;
    line-height: 1.58;
}
.aisn-sim-section ul {
    margin: 8px 0 0 1.2rem;
    padding: 0;
}
.aisn-sim-section li {
    margin-bottom: 6px;
    color: #334155;
    line-height: 1.52;
}
.aisn-sim-section a {
    font-weight: 800;
}
.aisn-sim-badge {
    display: inline-block;
    background: #e0f2fe;
    color: #075985;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: .78rem;
    font-weight: 800;
    margin-bottom: 8px;
}
');

    $lines = preg_split('/\n+/', $text);
    $html .= html_writer::start_div('aisn-sim-result');

    $open = false;
    $listopen = false;
    $sectionnumber = 0;

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (preg_match('/^\s*(\d+)\.\s*(.+)$/u', $line, $matches)) {
            if ($listopen) {
                $html .= html_writer::end_tag('ul');
                $listopen = false;
            }

            if ($open) {
                $html .= html_writer::end_div();
            }

            $sectionnumber = (int)$matches[1];
            $title = local_aiskillnavigator_sim_section_title($matches[2]);

            $html .= html_writer::start_div('aisn-sim-section');
            $html .= html_writer::span('Section ' . $sectionnumber, 'aisn-sim-badge');
            $html .= html_writer::tag('h4', s($title !== '' ? $title : 'Result section'));
            $open = true;
            continue;
        }

        if (!$open) {
            $html .= html_writer::start_div('aisn-sim-section');
            $html .= html_writer::span('AI result', 'aisn-sim-badge');
            $html .= html_writer::tag('h4', 'Exercise and simulator suggestion');
            $open = true;
        }

        if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $matches)) {
            if (!$listopen) {
                $html .= html_writer::start_tag('ul');
                $listopen = true;
            }

            $html .= html_writer::tag('li', local_aiskillnavigator_sim_inline_format($matches[1]));
            continue;
        }

        if ($listopen) {
            $html .= html_writer::end_tag('ul');
            $listopen = false;
        }

        $html .= html_writer::tag('p', local_aiskillnavigator_sim_inline_format($line));
    }

    if ($listopen) {
        $html .= html_writer::end_tag('ul');
    }

    if ($open) {
        $html .= html_writer::end_div();
    }

    $html .= html_writer::end_div();

    return $html;
}

$known = local_aiskillnavigator_sim_known_catalogue();
$searchresults = [];
$searchnote = '';
$error = '';
$result = '';

$searchservice = new \local_aiskillnavigator\service\web_search_service();
$searchenabled = $searchservice->is_enabled();

if ($action === 'generate') {
    if (!confirm_sesskey()) {
        $error = 'Invalid session key. Reload the page and try again.';
    } else if (trim($topic) === '') {
        $error = 'Insert a topic before generating the exercise.';
    } else {
        if ($searchenabled) {
            $query = $topic . ' online simulator educational exercise official tool';
            $searchresults = $searchservice->search($query, 5);

            if (false && !empty($searchresults)) {
                $searchnote = 'Live web search enabled via ' . $searchservice->provider_name() . '. Results were used in the AI prompt.';
            } else {
                $searchnote = 'Live web search enabled via ' . $searchservice->provider_name() . ', but no useful results were returned.';
            }
        } else {
            $searchnote = 'Live web search disabled. The AI uses only the curated simulator catalogue.';
        }

        $prompt = "A teacher wants a practical exercise and an online simulator/tool for a Moodle course.\n";
        $prompt .= "Topic: " . $topic . "\n";
        $prompt .= "Level: " . $level . "\n";

        if ($notes !== '') {
            $prompt .= "Teacher notes/material:\n" . $notes . "\n";
        }

        // AISN_SIM_DIRECT_MATERIAL_PROMPT_CONTEXT_V1
        // Force selected Moodle course materials into the AI prompt.
        if (!empty($selectedmaterialcontext)) {
            $prompt .= "\nSelected Moodle course materials:\n" . $selectedmaterialcontext . "\n";
        }

        $prompt .= "\nCurated simulator/tool catalogue:\n";

        foreach ($known as $area => $tools) {
            $prompt .= "- " . $area . ": " . $tools . "\n";
        }

        $prompt .= "\nLive web search results:\n";
        $prompt .= local_aiskillnavigator_sim_search_context($searchresults);

        $prompt .= "\nRules:\n";
        $prompt .= "- Respond in Italian.\n";
        $prompt .= "- Create one concrete exercise the teacher can assign.\n";
        $prompt .= "- Recommend the best simulator/tool only if it is a strong fit.\n";
        $prompt .= "- If live search results contain a relevant official tool, prefer that result and mention the URL.\n";
        $prompt .= "- If no reliable simulator/tool is known or found, write clearly: No suitable online simulator found.\n";
        $prompt .= "- Do not invent fake websites, fake tools or fake URLs.\n";
        $prompt .= "- If a URL is uncertain, say that the teacher should verify the official site.\n";
        $prompt .= "- Keep the output short and directly usable.\n";
        $prompt .= "- Use plain text sections. Do not use Markdown tables.\n\n";
        $prompt .= "Return exactly these numbered sections:\n";
        $prompt .= "1. Titolo dell'esercizio\n";
        $prompt .= "2. Istruzioni\n";
        $prompt .= "3. Simulatore/tool consigliato\n";
        $prompt .= "4. Link/fonte\n";
        $prompt .= "5. PerchÃ© questo simulatore Ã¨ adatto\n";
        $prompt .= "6. Alternativa se nessun simulatore Ã¨ disponibile\n";
        $prompt .= "7. Criteri di valutazione\n";

        $result = local_aiskillnavigator_sim_call_ai(
            $prompt,
            'You help teachers find practical online simulators/tools. Avoid fake links. Use live search results when available. Say no if no suitable simulator is known. Return numbered sections.'
        );

        if (
            trim($result) !== '' &&
            !preg_match('/^(AI error|AI provider not available|Errore)/i', trim($result))
        ) {
            $selectedids = local_aisn_sim_selected_ids();
            $selectedmaterials = local_aisn_sim_selected_materials((int)$courseid, $selectedids);
            $selectedtitles = [];

            foreach ($selectedmaterials as $material) {
                $selectedtitles[] = local_aisn_sim_clean_title((string)($material->title ?? ''));
            }

            local_aisn_sim_save_generated(
                (int)$courseid,
                (int)$USER->id,
                $topic,
                $level,
                $selectedids,
                $selectedtitles,
                $result
            );
        }
    }
}

echo $OUTPUT->header();
echo html_writer::tag('style', <<<'CSS'
/* AISN_DIRECT_SELECT_FIX_FINAL_V1 */
body.path-local-aiskillnavigator select#difficulty,
body.path-local-aiskillnavigator select#level,
body.path-local-aiskillnavigator select[name="difficulty"],
body.path-local-aiskillnavigator select[name="level"] {
    display: block !important;
    width: 360px !important;
    min-width: 360px !important;
    max-width: 100% !important;
    height: 50px !important;
    min-height: 50px !important;
    padding: 10px 46px 10px 14px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 12px !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    font-size: 15px !important;
    line-height: 1.45 !important;
    box-sizing: border-box !important;
    appearance: auto !important;
    -webkit-appearance: menulist !important;
}

body.path-local-aiskillnavigator label[for="difficulty"],
body.path-local-aiskillnavigator label[for="level"] {
    display: block !important;
    width: 100% !important;
    margin: 0 0 8px 0 !important;
    font-weight: 900 !important;
    color: #0f172a !important;
}

body.path-local-aiskillnavigator select#difficulty + *,
body.path-local-aiskillnavigator select#level + * {
    margin-top: 18px !important;
}

body.path-local-aiskillnavigator form,
body.path-local-aiskillnavigator .card,
body.path-local-aiskillnavigator .card-body,
body.path-local-aiskillnavigator .container-fluid,
body.path-local-aiskillnavigator #region-main,
body.path-local-aiskillnavigator [role="main"] {
    overflow: visible !important;
}

@media (max-width: 700px) {
    body.path-local-aiskillnavigator select#difficulty,
    body.path-local-aiskillnavigator select#level,
    body.path-local-aiskillnavigator select[name="difficulty"],
    body.path-local-aiskillnavigator select[name="level"] {
        width: 100% !important;
        min-width: 0 !important;
    }
}
CSS);

echo html_writer::script(<<<'JS'
// AISN_DIRECT_SELECT_FIX_FINAL_V1
(function () {
    function fixSelect(id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }

        el.classList.add('aisn-direct-fixed-select');

        el.style.setProperty('display', 'block', 'important');
        el.style.setProperty('width', window.innerWidth <= 700 ? '100%' : '360px', 'important');
        el.style.setProperty('min-width', window.innerWidth <= 700 ? '0' : '360px', 'important');
        el.style.setProperty('max-width', '100%', 'important');
        el.style.setProperty('height', '50px', 'important');
        el.style.setProperty('min-height', '50px', 'important');
        el.style.setProperty('padding', '10px 46px 10px 14px', 'important');
        el.style.setProperty('box-sizing', 'border-box', 'important');
        el.style.setProperty('font-size', '15px', 'important');
        el.style.setProperty('line-height', '1.45', 'important');
        el.style.setProperty('border-radius', '12px', 'important');
        el.style.setProperty('appearance', 'auto', 'important');
        el.style.setProperty('-webkit-appearance', 'menulist', 'important');

        var group = el.closest('.form-group, .mb-3') || el.parentElement;
        if (group) {
            group.style.setProperty('overflow', 'visible', 'important');
            group.style.setProperty('margin-bottom', '32px', 'important');
        }
    }

    function run() {
        fixSelect('difficulty');
        fixSelect('level');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    window.addEventListener('resize', run);
    setTimeout(run, 300);
    setTimeout(run, 1000);
})();
JS);

local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Simulator Finder');
echo html_writer::tag(
    'p',
    'Insert a topic and let the AI propose a practical exercise plus a suitable online simulator/tool. If a Search API is configured, the plugin also checks live web results.',
    ['class' => 'lead']
);
echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);


echo html_writer::div(
    local_aisn_sim_saved_link_html((int)$courseid),
    'mb-3'
);

if ($searchenabled) {
    echo html_writer::div(
        'Live web search enabled via ' . s($searchservice->provider_name()) . '.',
        'alert alert-success'
    );
} else {
    echo html_writer::div(
        'Live web search disabled. The AI uses only the curated simulator catalogue.',
        'alert alert-warning'
    );
}

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'generate']);

echo html_writer::div(
    local_aisn_sim_material_selector_html((int)$courseid),
    'mb-3'
);


echo html_writer::tag('label', 'Topic', ['for' => 'topic']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control mb-3',
    'value' => s($topic),
    'placeholder' => 'Example: circuits, Arduino IoT, functions, networking, HTML, machine learning...',
    'required' => 'required',
]);

echo html_writer::tag('label', 'Level', ['for' => 'level']);
echo html_writer::select(
    ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'],
    'level',
    $level,
    false,
    ['class' => 'form-control custom-select aisn-direct-fixed-select mb-3', 'id' => 'level', 'style' => 'display:block!important;width:360px!important;min-width:360px!important;max-width:100%!important;height:50px!important;min-height:50px!important;padding:10px 46px 10px 14px!important;box-sizing:border-box!important;font-size:15px!important;line-height:1.45!important;border-radius:12px!important;appearance:auto!important;-webkit-appearance:menulist!important;']
);

echo html_writer::tag('label', 'Optional teacher notes/material', ['for' => 'notes']);
echo html_writer::tag('textarea', s($notes), [
    'name' => 'notes',
    'id' => 'notes',
    'class' => 'form-control mb-3',
    'rows' => 6,
    'placeholder' => 'Optional: paste lesson context, constraints, available devices, assessment objective...',
]);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => 'Generate exercise and simulator',
]);

echo ' ';

echo html_writer::link(
    new moodle_url('/local/aiskillnavigator/pages/index.php', ['courseid' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

if ($searchnote !== '') {
    echo html_writer::div(s($searchnote), 'alert alert-secondary');
}

if (false && !empty($searchresults)) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', 'Live search results used');

    echo html_writer::start_tag('ol');

    foreach ($searchresults as $row) {
        $url = trim((string)($row['url'] ?? ''));
        $title = trim((string)($row['title'] ?? $url));
        $snippet = trim((string)($row['snippet'] ?? ''));

        if ($url !== '') {
            $link = html_writer::link($url, s($title !== '' ? $title : $url), ['target' => '_blank', 'rel' => 'noopener noreferrer']);
        } else {
            $link = s($title);
        }

        echo html_writer::tag('li', $link . html_writer::tag('p', s($snippet), ['class' => 'text-muted']));
    }

    echo html_writer::end_tag('ol');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($result !== '') {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', 'Exercise and simulator suggestion');
    echo local_aiskillnavigator_render_simulator_result($result);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();



