<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/markdown_table_formatter.php');
local_aisn_start_mdtable_formatter();
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');
require_once(__DIR__ . '/../includes/tutor_signal_helper.php');
require_once(__DIR__ . '/../includes/ai_output_helper.php');

global $DB, $PAGE, $OUTPUT, $USER;

if (!function_exists('local_aisn_tutor_ai_format_rules_clean_v2')) {
    /**
     * Local aisn tutor ai format rules clean v2 helper.
     */
    function local_aisn_tutor_ai_format_rules_clean_v2(): string {
        return "\n\nAISN_TUTOR_AI_FORMAT_RULES_CLEAN_V2\n"
            . "Regole di risposta obbligatorie:\n"
            . "- Rispondi in italiano.\n"
            . "- Usa Markdown pulito e ben strutturato.\n"
            . "- Usa titoli brevi con ## quando la risposta contiene più parti.\n"
            . "- Usa elenchi puntati per caratteristiche, vantaggi, esempi, differenze e casi d'uso.\n"
            . "- Se mostri codice o comandi, scegli tu il linguaggio corretto del blocco markdown in base al contenuto.\n"
            . "- Non etichettare un blocco come javascript se non è realmente JavaScript applicativo.\n"
            // phpcs:ignore moodle.Files.LineLength
            . "- Per comandi database usa il linguaggio più adatto se lo riconosci, ad esempio sql, mongodb, cql, cypher, redis, oppure text se non sei sicuro.\n"
            . "- Non iniziare ripetendo il nome del file materiale.\n"
            . "- Non scrivere tutto in un unico paragrafo lungo.\n"
            . "- Chiudi con una breve sezione ## In sintesi quando utile.\n";
    }
}


if (!function_exists('local_aisn_tutor_ai_format_rules_clean')) {
    /**
     * Local aisn tutor ai format rules clean helper.
     */
    function local_aisn_tutor_ai_format_rules_clean(): string {
        return "\n\nAISN_TUTOR_AI_FORMAT_RULES_CLEAN_V1\n"
            . "Regole di risposta obbligatorie:\n"
            . "- Rispondi in italiano.\n"
            . "- Usa Markdown pulito e ben strutturato.\n"
            . "- Usa titoli brevi con ## quando la risposta contiene più parti.\n"
            . "- Usa elenchi puntati per caratteristiche, vantaggi, esempi, differenze e casi d'uso.\n"
            . "- Se mostri codice o comandi, scegli tu il linguaggio corretto del blocco markdown in base al contenuto.\n"
            . "- Non etichettare un blocco come javascript se non è realmente JavaScript applicativo.\n"
            // phpcs:ignore moodle.Files.LineLength
            . "- Per comandi database usa il linguaggio più adatto se lo riconosci, ad esempio sql, mongodb, cql, cypher, redis, oppure text se non sei sicuro.\n"
            . "- Non iniziare ripetendo il nome del file materiale.\n"
            . "- Non scrivere tutto in un unico paragrafo lungo.\n"
            . "- Chiudi con una breve sezione ## In sintesi quando utile.\n";
    }
}


$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

if (function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('page_tutor_title', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('page_tutor_heading', 'local_aiskillnavigator'));
if (!function_exists('local_aisn_tutor_formatting_suffix')) {
    /**
     * Local aisn tutor formatting suffix helper.
     */
    function local_aisn_tutor_formatting_suffix(): string {
        return "\n\nAISN_TUTOR_FORMATTING_SUFFIX_V5\n"
            . "Regole obbligatorie per la risposta del tutor:\n"
            . "- Rispondi sempre in italiano, salvo richiesta esplicita dello studente in un'altra lingua.\n"
            . "- Usa PRIMA i materiali selezionati del corso. Non comportarti come un chatbot generico.\n"
            // phpcs:ignore moodle.Files.LineLength
            . "- Se una parte della risposta deriva da conoscenza generale non presente nei materiali, scrivilo chiaramente con una breve sezione: ## Nota esterna.\n"
            // phpcs:ignore moodle.Files.LineLength
            . "- Se i materiali non contengono abbastanza informazioni, dillo chiaramente e poi aggiungi solo una spiegazione generale breve e separata.\n"
            . "- Non inventare nomi di slide, pagine, file, definizioni o fonti non presenti nel contesto.\n"
            . "- Non chiudere con frasi tipo: Se vuoi posso..., Fammi sapere..., Posso fornirti..., Dimmi se vuoi....\n"
            . "- Usa Markdown pulito e ben strutturato.\n"
            . "- Usa titoli brevi con ##, ad esempio: Concetto, Esempio, Quando si usa, In sintesi.\n"
            . "- Se mostri codice o comandi, usa il linguaggio corretto del blocco Markdown.\n"
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            . "- Per comandi MongoDB shell/mongosh usa ```mongodb oppure ```mongosh, MAI ```javascript.\n"
            // phpcs:ignore moodle.Files.LineLength
            . "- Esempi MongoDB come use nomeDatabase, db.createCollection(...), db.collezione.insertOne(...) NON sono JavaScript applicativo: etichettali come mongodb o mongosh.\n"
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            . "- Per Cassandra usa ```cql, non ```sql.\n"
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            . "- Per Neo4j usa ```cypher.\n"
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            . "- Per Redis usa ```redis oppure ```text.\n"
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            . "- Se non sei sicuro del linguaggio, usa ```text.\n"
            . "- Evita blocchi di codice se non sono necessari.\n"
            . "- Mantieni la risposta adatta a uno studente universitario: chiara, sintetica e collegata al corso.\n"
            . "- Chiudi, quando utile, con una breve sezione ## In sintesi.\n";
    }
}

/**
 * Local aiskillnavigator tutor limit context helper.
 */
function local_aiskillnavigator_tutor_limit_context(string $text, int $limit = 9000): string {
    $text = local_aiskillnavigator_fix_mojibake(trim($text));

    if (\core_text::strlen($text) > $limit) {
        return \core_text::substr($text, 0, $limit) . "\n[Content truncated]";
    }

    return $text;
}


/**
 * Local aisn tutor cleanup answer helper.
 */
function local_aisn_tutor_cleanup_answer(string $answer): string {
    $answer = trim($answer);

    $patterns = [
        '/\n?\s*Se vuoi,?\s+posso\s+.*$/ius',
        '/\n?\s*Fammi sapere\s+.*$/ius',
        '/\n?\s*Posso fornirti\s+.*$/ius',
        '/\n?\s*Dimmi se vuoi\s+.*$/ius',
        '/\n?\s*Se ti serve,?\s+posso\s+.*$/ius',
    ];

    foreach ($patterns as $pattern) {
        $answer = preg_replace($pattern, '', $answer);
    }

    // Correzioni leggere di formule ricorrenti brutte.
    $answer = str_replace('Quando si usa Nei database', 'Quando si usa nei database', $answer);
    $answer = str_replace('SQL Copy', 'CQL', $answer);
    $answer = str_replace('JavaScript Copy', 'JavaScript', $answer);

    return trim($answer);
}
/**
 * Local aiskillnavigator tutor call ai helper.
 */
function local_aiskillnavigator_tutor_call_ai(string $prompt, string $systemprompt): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate($prompt, 2600, $systemprompt);
        }

        if (
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_config') &&
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_selector')
        ) {
            $config = new \local_aiskillnavigator\service\provider\ai_provider_config();
            $selector = new \local_aiskillnavigator\service\provider\ai_provider_selector();
            $provider = $selector->create($config);
            return $provider->generate($prompt, 2600, $systemprompt);
        }
    } catch (Throwable $e) {
        return 'AI error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials((int)$courseid);

$sourcemode = local_aiskillnavigator_material_source_mode_from_request(-1);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);
$question = optional_param('question', '', PARAM_RAW_TRIMMED);

if ($sourcemode === 'all') {
    $sourcemode = 'selected';
}

$answer = '';
$error = '';
$usedmaterialnames = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($question === '') {
        $error = 'Write a question first.';
    } else {
        $selectedmaterials = local_aiskillnavigator_material_source_selected_materials(
            $readablematerials,
            $sourcemode,
            $selectedmaterialids
        );

        if ($sourcemode === 'selected' && empty($selectedmaterials)) {
            $error = 'Select at least one course material allowed for the current AI provider.';
        }

        if ($error === '') {
            if ($sourcemode === 'manual') {
                // phpcs:ignore moodle.Files.LineLength
                $systemprompt = 'You are a Moodle AI tutor. Answer using only the student question and general knowledge. Do not claim that course materials were used.';
                $prompt = "Student question:\n" . $question;
            } else {
                $contextparts = [];

                foreach ($selectedmaterials as $material) {
                    $name = local_aiskillnavigator_material_source_clean_title($material);
                    $usedmaterialnames[] = $name;

                    $contextparts[] =
                        "SOURCE: " . $name . "\n" .
                        local_aiskillnavigator_tutor_limit_context((string)$material->content);
                }

                // phpcs:ignore moodle.Files.LineLength
                $systemprompt = 'You are AI Skill Navigator, a Moodle course-aware tutor. Your primary source is the selected Moodle course material provided in the prompt. Answer as a university teaching assistant, not as a generic chatbot. If the material is sufficient, answer using it directly. If the material is incomplete, say clearly that the selected material does not contain enough information and then provide a short clearly separated general explanation only when useful. Do not invent sources, slide numbers, file contents or citations. Avoid final follow-up offers. Answer in the same language as the student, preferably Italian.';

                $prompt =
                    "Selected course materials:\n\n" .
                    implode("\n\n---\n\n", $contextparts) .
                    "\n\nStudent question:\n" .
                    $question;
            }

            // phpcs:ignore moodle.Files.LineLength
            $answer = local_aisn_tutor_cleanup_answer(local_aiskillnavigator_fix_mojibake(local_aiskillnavigator_tutor_call_ai($prompt, $systemprompt . local_aisn_tutor_formatting_suffix())));
        }
    }
}

$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/aisn_tutor_final_formatter.css', ['v' => time()]));
$PAGE->requires->js(new moodle_url('/local/aiskillnavigator/assets/aisn_tutor_final_formatter.js', ['v' => time()]));
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/aisn_tutor_clean_answer.css', ['v' => time()]));
echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Tutor');

echo html_writer::tag(
    'p',
    'Ask a question grounded only on the selected Moodle course materials.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo local_aiskillnavigator_material_source_selector_html(
    $readablematerials,
    null,
    $courseid,
    $sourcemode,
    $selectedmaterialids,
    '1. Source',
    ''
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', '2. Question');

echo html_writer::tag('textarea', s($question), [
    'name' => 'question',
    'id' => 'question',
    'class' => 'form-control',
    'rows' => 5,
    'placeholder' => 'Esempio: spiegami la differenza tra funzione lineare e funzione quadratica.',
    'required' => 'required',
]);

echo html_writer::start_div('mt-3');

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => 'Ask AI',
]);

echo ' ';

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');

if ($answer !== '') {
    // AISN_TUTOR_CLEAN_CARD_V2.
    echo html_writer::start_div('card mb-4 aisn-tutor-answer-card');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Answer', ['class' => 'aisn-tutor-answer-title']);

    if (!empty($usedmaterialnames)) {
        echo html_writer::div(
            'Used materials: ' . s(implode(', ', $usedmaterialnames)),
            'text-muted mb-3 aisn-used-materials'
        );
    } else {
        echo html_writer::div('Used materials: none', 'text-muted mb-3 aisn-used-materials');
    }

    echo html_writer::start_div('aisn-answer aisn-tutor-answer-body');
    echo local_aiskillnavigator_render_ai_answer($answer);
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo local_aiskillnavigator_tutor_signal_capture_assets((int)$courseid);
// phpcs:ignore moodle.Files.LineLength
echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_mdtable_assets')) {
    echo local_aisn_mdtable_assets();
}
// phpcs:ignore moodle.Files.LineLength
echo '<link rel="stylesheet" href="' . (new moodle_url('/local/aiskillnavigator/assets/aisn_answer_renderer_v3.css', ['v' => time()]))->out(false) . '">';
echo '<script>
window.MathJax = {
    tex: {
        inlineMath: [["\\\\(", "\\\\)"], ["$", "$"]],
        displayMath: [["\\\\[", "\\\\]"], ["$$", "$$"]]
    },
    svg: { fontCache: "global" }
};
</script>';
if ((string)get_config('local_aiskillnavigator', 'enablemathjaxcdn') === '1') {
    echo '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>';
}
// phpcs:ignore moodle.Files.LineLength
echo '<script src="' . (new moodle_url('/local/aiskillnavigator/assets/aisn_answer_renderer_v3.js', ['v' => time()]))->out(false) . '"></script>';
echo html_writer::tag('style', file_get_contents(__DIR__ . '/../assets/aisn_tutor_visual_override.css'));
// AISN_TUTOR_VISUAL_OVERRIDE_LOAD_V4.
echo html_writer::tag('style', file_get_contents(__DIR__ . '/../assets/aisn_tutor_dom_bridge.css'));
echo html_writer::script(file_get_contents(__DIR__ . '/../assets/aisn_tutor_dom_bridge.js'));
// AISN_TUTOR_DOM_BRIDGE_LOAD_V1.
echo html_writer::script(file_get_contents(__DIR__ . '/../assets/aisn_tutor_dom_direct_style.js'));
// AISN_TUTOR_DIRECT_STYLE_LOAD_V1.
echo $OUTPUT->footer();

/**
 * Local aiskillnavigator tutor signal capture assets helper.
 */
function local_aiskillnavigator_tutor_signal_capture_assets(int $courseid): string {
    $endpoint = new moodle_url('/local/aiskillnavigator/pages/tutor_signal_capture.php', [
        'courseid' => $courseid,
        'sesskey' => sesskey(),
    ]);

    $endpointjson = json_encode($endpoint->out(false), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return <<<HTML
<script id="aisn-tutor-signal-capture-v1">
(function () {
    const endpoint = {$endpointjson};

    /**
     * Clean helper.
     */
    function clean(value) {
        return String(value || "").replace(/\\s+/g, " ").trim();
    }

    /**
     * Findanswertext helper.
     */
    function findAnswerText() {
        const answerBox = document.querySelector(".aisn-answer");
        if (answerBox) {
            return clean(answerBox.innerText || answerBox.textContent || "");
        }

        const headings = Array.from(document.querySelectorAll("h1,h2,h3,h4"));
        const answerHeading = headings.find(function (h) {
            return clean(h.textContent).toLowerCase() === "answer";
        });

        if (!answerHeading) {
            return "";
        }

        const card = answerHeading.closest(".card") || answerHeading.parentElement;
        if (!card) {
            return "";
        }

        let text = clean(card.innerText || card.textContent || "");
        text = text.replace(/^Answer\\s*/i, "");
        text = text.replace(/^Used materials:\\s*[^\\n]+/i, "");

        return clean(text);
    }

    /**
     * Findquestiontext helper.
     */
    function findQuestionText() {
        const textarea = document.querySelector('textarea[name="question"], #question');
        if (textarea && textarea.value) {
            return clean(textarea.value);
        }

        return "";
    }

    /**
     * Findsourcemode helper.
     */
    function findSourceMode() {
        // phpcs:ignore moodle.Files.LineLength
        const checked = document.querySelector('input[name="sourcemode"]:checked, input[name="source"]:checked, input[name="materialsource"]:checked');
        if (checked) {
            return checked.value || "selected";
        }

        return "unknown";
    }

    /**
     * Findusedmaterials helper.
     */
    function findUsedMaterials() {
        const used = Array.from(document.querySelectorAll(".text-muted")).find(function (el) {
            return clean(el.textContent).toLowerCase().startsWith("used materials:");
        });

        if (!used) {
            return [];
        }

        const txt = clean(used.textContent).replace(/^Used materials:\\s*/i, "");
        if (!txt || txt.toLowerCase() === "none") {
            return [];
        }

        return txt.split(",").map(function (x) { return clean(x); }).filter(Boolean);
    }

    async function saveSignal() {
        const question = findQuestionText();
        const answer = findAnswerText();

        if (!question || !answer) {
            return;
        }

        const fingerprint = "aisn_tutor_signal_" + btoa(unescape(encodeURIComponent(question + "::" + answer))).slice(0, 80);

        if (sessionStorage.getItem(fingerprint) === "1") {
            return;
        }

        sessionStorage.setItem(fingerprint, "1");

        const body = new URLSearchParams();
        body.set("question", question);
        body.set("answer", answer);
        body.set("sourcemode", findSourceMode());
        body.set("materials", JSON.stringify(findUsedMaterials()));

        try {
            const response = await fetch(endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
                },
                body: body.toString()
            });

            const data = await response.json();

            const note = document.createElement("div");
            note.className = data.ok ? "alert alert-success mt-3" : "alert alert-warning mt-3";
            note.textContent = data.ok
                ? "Tutor-as-Sensor: domanda salvata nella dashboard docente."
                : "Tutor-as-Sensor: salvataggio non riuscito: " + (data.message || "errore sconosciuto");

            const answerBox = document.querySelector(".aisn-answer");
            if (answerBox && !document.getElementById("aisn-tutor-signal-note")) {
                note.id = "aisn-tutor-signal-note";
                answerBox.parentElement.appendChild(note);
            }
        } catch (e) {
            console.error("Tutor signal capture failed", e);
            sessionStorage.removeItem(fingerprint);
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(saveSignal, 300);
        setTimeout(saveSignal, 1200);
    });
})();
</script>
HTML;
}
