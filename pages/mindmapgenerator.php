<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/mojibake_guard.php');
require_once(__DIR__ . '/../includes/mindmap_polish_assets.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');
require_once(__DIR__ . '/../classes/service/web_search_service.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$generate = optional_param('generate', 0, PARAM_BOOL);
$topic = optional_param('topic', '', PARAM_RAW_TRIMMED);
$difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

local_aisn_require_student_area($context);
require_capability('local/aiskillnavigator:viewstudent', $context);

if (function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/mindmapgenerator.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Mind Map Generator');
$PAGE->set_heading('AI Mind Map Generator');


function local_aiskillnavigator_mm_bad_score(string $label): int {
    $label = trim($label);
    if ($label === '') {
        return 1000;
    }

    $score = 0;
    $badpatterns = ['Ãƒ', 'Ã‚', 'Ã¢â‚¬', 'â€™', 'â€œ', 'Ã¢â‚¬\u009d', 'â€“', 'â€”', 'ï¿½', 'Æ’Ã†', 'â€šÃ‚'];
    foreach ($badpatterns as $badpattern) {
        if (strpos($label, $badpattern) !== false) {
            $score += 100;
        }
    }

    if (preg_match('/[{}\\[\\]<>]/', $label)) {
        $score += 20;
    }
    if (core_text::strlen($label) > 140) {
        $score += 30;
    }

    return $score;
}


function local_aiskillnavigator_mm_clean_text($value): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $replacements = [
        "ÃƒÆ’ " => "ÃƒÂ ", "ÃƒÆ’Ã‚Â¨" => "ÃƒÂ¨", "ÃƒÆ’Ã‚Â©" => "ÃƒÂ©", "ÃƒÆ’Ã‚Â¬" => "ÃƒÂ¬", "ÃƒÆ’Ã‚Â²" => "ÃƒÂ²", "ÃƒÆ’Ã‚Â¹" => "ÃƒÂ¹",
        "ÃƒÆ’Ã¢â€šÂ¬" => "Ãƒâ‚¬", "ÃƒÆ’Ã‹â€ " => "ÃƒË†", "ÃƒÆ’Ã¢â‚¬Â°" => "Ãƒâ€°", "ÃƒÆ’Ã…â€™" => "ÃƒÅ’", "ÃƒÆ’Ã¢â‚¬â„¢" => "Ãƒâ€™", "ÃƒÆ’Ã¢â€žÂ¢" => "Ãƒâ„¢",
        "ÃƒÆ’Ã†â€™Ãƒâ€š " => "ÃƒÂ ", "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¨" => "ÃƒÂ¨", "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©" => "ÃƒÂ©", "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¬" => "ÃƒÂ¬", "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â²" => "ÃƒÂ²", "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¹" => "ÃƒÂ¹",
        "ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€š " => "ÃƒÂ ", "ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¨" => "ÃƒÂ¨", "ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©" => "ÃƒÂ©", "ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬" => "ÃƒÂ¬", "ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â²" => "ÃƒÂ²", "ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¹" => "ÃƒÂ¹",
        "Ãƒâ€šÃ‚Â·" => "Ã‚Â·", "ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â·" => "Ã‚Â·", "Ãƒâ€šÃ‚Â°" => "Ã‚Â°", "ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°" => "Ã‚Â°",
        "â€™" => "'", "â€˜" => "'", "â€œ" => '"', "Ã¢â‚¬\u009d" => '"', "â€“" => "-", "â€”" => "-",
        "â€™" => "'", "â€œ" => '"', "Ã¢â‚¬\u009d" => '"', "â€“" => "-",
    ];
    $text = strtr($text, $replacements);

    $text = preg_replace('/(?:Ãƒ|Ã‚|Ã‚|Ã¢â‚¬|â€™|â€œ|â€“|â€”|â€šÃ‚|Æ’Ã†)[\\s\\S]*/u', '', $text);
    $text = preg_replace('/\\s+/u', ' ', trim($text));

    return $text;
}

function local_aiskillnavigator_mm_clean_array($value, string $fallback = '') {
    if (is_string($value)) {
        return local_aiskillnavigator_mm_clean_text($value, $fallback);
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = local_aiskillnavigator_mm_clean_array($item, $fallback);
        }

        return $value;
    }

    if (is_object($value)) {
        foreach ($value as $key => $item) {
            $value->$key = local_aiskillnavigator_mm_clean_array($item, $fallback);
        }

        return $value;
    }

    return $value;
}

function local_aiskillnavigator_mm_call_ai(string $prompt, string $systemprompt): string {
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
        return '';
    }

    return '';
}

function local_aiskillnavigator_mm_extract_json(string $raw): ?array {
    $raw = trim($raw);
    $raw = preg_replace('/^```json\s*/i', '', $raw);
    $raw = preg_replace('/^```\s*/', '', $raw);
    $raw = preg_replace('/```\s*$/', '', $raw);

    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');

    if ($start !== false && $end !== false && $end > $start) {
        $raw = substr($raw, $start, $end - $start + 1);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return null;
    }

    return local_aiskillnavigator_mm_clean_array($data);
}

function local_aiskillnavigator_mm_fallback(string $topic): array {
    $topic = local_aiskillnavigator_mm_clean_text($topic, 'Argomento');

    return [
        'title' => $topic,
        'subtitle' => 'Mappa concettuale generata automaticamente',
        'center' => $topic,
        'branches' => [
            [
                'title' => 'Concetti principali',
                'summary' => 'Raccoglie le idee fondamentali da conoscere per comprendere l argomento.',
                'children' => [
                    ['title' => 'Definizione', 'summary' => 'Spiega il significato generale del concetto.'],
                    ['title' => 'Elementi base', 'summary' => 'Indica le parti principali che compongono l argomento.'],
                ],
            ],
            [
                'title' => 'Esempi',
                'summary' => 'Mostra casi pratici e situazioni in cui il concetto viene applicato.',
                'children' => [
                    ['title' => 'Caso semplice', 'summary' => 'Un esempio introduttivo utile per iniziare.'],
                    ['title' => 'Caso avanzato', 'summary' => 'Un esempio piÃ¹ completo per approfondire.'],
                ],
            ],
            [
                'title' => 'Collegamenti',
                'summary' => 'Evidenzia relazioni con altri concetti del corso.',
                'children' => [
                    ['title' => 'Prerequisiti', 'summary' => 'Conoscenze utili prima di studiare questo tema.'],
                    ['title' => 'Applicazioni', 'summary' => 'Possibili usi del concetto in esercizi o progetti.'],
                ],
            ],
            [
                'title' => 'Ripasso',
                'summary' => 'Suggerisce i punti da rivedere per consolidare l apprendimento.',
                'children' => [
                    ['title' => 'Domande guida', 'summary' => 'Domande utili per verificare la comprensione.'],
                    ['title' => 'Errori comuni', 'summary' => 'Aspetti a cui prestare attenzione durante lo studio.'],
                ],
            ],
        ],
    ];
}

function local_aiskillnavigator_mm_normalize(array $data, string $topic): array {
    $fallback = local_aiskillnavigator_mm_fallback($topic);

    $title = local_aiskillnavigator_mm_clean_text((string)($data['title'] ?? ''), $fallback['title']);
    $subtitle = local_aiskillnavigator_mm_clean_text((string)($data['subtitle'] ?? ''), $fallback['subtitle']);
    $center = local_aiskillnavigator_mm_clean_text((string)($data['center'] ?? ''), $topic !== '' ? $topic : $fallback['center']);

    $branches = [];

    if (!empty($data['branches']) && is_array($data['branches'])) {
        foreach (array_values($data['branches']) as $branch) {
            if (!is_array($branch)) {
                continue;
            }

            $bt = local_aiskillnavigator_mm_clean_text((string)($branch['title'] ?? ''), 'Concetto');
            $bs = local_aiskillnavigator_mm_clean_text((string)($branch['summary'] ?? ''), 'Questo ramo riassume un concetto importante della mappa.');

            $children = [];

            if (!empty($branch['children']) && is_array($branch['children'])) {
                foreach (array_values($branch['children']) as $child) {
                    if (is_array($child)) {
                        $children[] = [
                            'title' => local_aiskillnavigator_mm_clean_text((string)($child['title'] ?? ''), 'Sotto-concetto'),
                            'summary' => local_aiskillnavigator_mm_clean_text((string)($child['summary'] ?? ''), 'Questo nodo approfondisce un aspetto collegato.'),
                        ];
                    }
                }
            }

            $branches[] = [
                'title' => $bt,
                'summary' => $bs,
                'children' => array_slice($children, 0, 4),
            ];
        }
    }

    if (empty($branches)) {
        $branches = $fallback['branches'];
    }

    return [
        'title' => $title,
        'subtitle' => $subtitle,
        'center' => $center,
        'branches' => array_slice($branches, 0, 7),
    ];
}

function local_aiskillnavigator_mm_material_context(array $materials): string {
    $parts = [];

    foreach ($materials as $material) {
        $title = local_aiskillnavigator_material_source_clean_title($material);
        $content = local_aiskillnavigator_mm_clean_text((string)$material->content, '');

        if (\core_text::strlen($content) > 4500) {
            $content = \core_text::substr($content, 0, 4500) . "\n[contenuto tagliato]";
        }

        $parts[] = "Materiale: " . $title . "\n" . $content;
    }

    return implode("\n\n---\n\n", $parts);
}

function local_aiskillnavigator_mm_generate(array $materials, string $topic, string $difficulty): array {
    $topic = local_aiskillnavigator_mm_clean_text($topic, '');
    $context = local_aiskillnavigator_mm_material_context($materials);

    $system = 'You generate clean UTF-8 JSON for a Moodle mind map. Return only valid JSON. No markdown. No code fences. Use normal Italian text. Avoid special smart quotes. Use only this schema: {"title":"...","subtitle":"...","center":"...","branches":[{"title":"...","summary":"...","children":[{"title":"...","summary":"..."}]}]}.';

    $prompt = "Crea una mappa mentale didattica in italiano.\n";
    $prompt .= "Difficolta: " . $difficulty . "\n";
    $prompt .= "Argomento/focus: " . ($topic !== '' ? $topic : 'contenuto dei materiali') . "\n\n";

    if ($context !== '') {
        $prompt .= "Usa questi materiali del corso come base. Non inventare contenuti non presenti se i materiali sono insufficienti:\n\n";
        $prompt .= $context . "\n\n";
    } else {
        $prompt .= "Genera la mappa dal solo argomento indicato.\n\n";
    }

    $prompt .= "Regole obbligatorie:\n";
    $prompt .= "- JSON valido e basta.\n";
    $prompt .= "- 4-7 rami principali.\n";
    $prompt .= "- 2-4 figli per ramo.\n";
    $prompt .= "- Testi brevi e puliti.\n";
    $prompt .= "- Niente caratteri strani, niente markdown, niente HTML.\n";

    $raw = local_aiskillnavigator_mm_call_ai($prompt, $system);
    $parsed = local_aiskillnavigator_mm_extract_json($raw);

    if (!$parsed) {
        return local_aiskillnavigator_mm_fallback($topic !== '' ? $topic : 'Mappa mentale');
    }

    return local_aiskillnavigator_mm_normalize($parsed, $topic !== '' ? $topic : 'Mappa mentale');
}


function local_aiskillnavigator_mm_add_web_examples(array $nodes, string $topic): array {
    if (!class_exists('\local_aiskillnavigator\service\web_search_service')) {
        return $nodes;
    }

    $search = new \local_aiskillnavigator\service\web_search_service();

    if (!$search->is_enabled()) {
        foreach ($nodes as &$node) {
            $node['webexample'] = null;
        }
        unset($node);
        return $nodes;
    }

    $done = 0;
    $maxsearches = 12;

    foreach ($nodes as &$node) {
        $node['webexample'] = null;

        $type = (string)($node['type'] ?? '');
        if (!in_array($type, ['child', 'branch'], true)) {
            continue;
        }

        if ($done >= $maxsearches) {
            continue;
        }

        $title = trim((string)($node['title'] ?? ''));
        if ($title === '') {
            continue;
        }

        $query = trim($topic . ' ' . $title . ' educational example tutorial simulator official');
        $results = $search->search($query, 1);
        $first = $results[0] ?? null;

        if (!is_array($first) || empty($first['url'])) {
            continue;
        }

        $done++;

        $node['webexample'] = [
            'title' => (string)($first['title'] ?? 'Risorsa online'),
            'url' => (string)($first['url'] ?? ''),
            'snippet' => (string)($first['content'] ?? $first['snippet'] ?? ''),
            'activity' => 'Esempio didattico: apri la risorsa, osserva il concetto "' . $title . '" in un contesto reale e scrivi un breve confronto con la spiegazione presente nella mappa.',
        ];
    }

    unset($node);

    return $nodes;
}
function local_aiskillnavigator_mm_flatten(array $map): array {
    $nodes = [];
    $edges = [];

    $nodes[] = [
        'id' => 'center',
        'title' => $map['center'],
        'summary' => 'Nodo centrale della mappa.',
        'type' => 'center',
        'x' => 50,
        'y' => 50,
    ];

    $branches = array_values($map['branches']);
    $branchcount = max(1, count($branches));
    $radius = 35;

    foreach ($branches as $i => $branch) {
        $angle = (-90 + ($i * (360 / $branchcount))) * pi() / 180;
        $x = 50 + cos($angle) * $radius;
        $y = 50 + sin($angle) * $radius;

        $bid = 'b' . $i;

        $nodes[] = [
            'id' => $bid,
            'title' => $branch['title'],
            'summary' => $branch['summary'],
            'type' => 'branch',
            'x' => round($x, 2),
            'y' => round($y, 2),
        ];

        $edges[] = ['from' => 'center', 'to' => $bid];

        $children = array_values($branch['children'] ?? []);
        $childcount = max(1, count($children));

        foreach ($children as $j => $child) {
            $offset = ($j - (($childcount - 1) / 2)) * 12;
            $cx = 50 + cos($angle) * ($radius + 20) + cos($angle + pi() / 2) * $offset;
            $cy = 50 + sin($angle) * ($radius + 20) + sin($angle + pi() / 2) * $offset;

            $cid = 'b' . $i . 'c' . $j;

            $nodes[] = [
                'id' => $cid,
                'title' => $child['title'],
                'summary' => $child['summary'],
                'type' => 'child',
                'x' => round($cx, 2),
                'y' => round($cy, 2),
            ];

            $edges[] = ['from' => $bid, 'to' => $cid];
        }
    }

    return [$nodes, $edges];
}

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials((int)$courseid);
$sourcemode = local_aiskillnavigator_material_source_mode_from_request(-1);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);

if ($sourcemode === 'all') {
    $sourcemode = 'selected';
}

$selectedmaterials = local_aiskillnavigator_material_source_selected_materials(
    $readablematerials,
    $sourcemode,
    $selectedmaterialids
);

$error = '';
$map = null;

if ($generate) {
    if ($sourcemode === 'manual' && trim($topic) === '') {
        $error = 'Inserisci un argomento oppure seleziona materiali del corso.';
    } else if ($sourcemode === 'selected' && empty($selectedmaterials)) {
        $error = 'Seleziona almeno un materiale consentito oppure usa Question/topic only.';
    } else {
        $map = local_aiskillnavigator_mm_generate($selectedmaterials, $topic, $difficulty);
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

echo html_writer::tag('style', '
.aisn-mm-wrap {
    background: #f8fbff;
    border: 1px solid #dbeafe;
    border-radius: 24px;
    padding: 26px;
    margin-top: 24px;
}
.aisn-mm-head {
    text-align: center;
    margin-bottom: 18px;
}
.aisn-mm-titlebar {
    background: linear-gradient(135deg, #0f6cbf 0%, #2b82d9 55%, #68b3ff 100%);
    color: white;
    border-radius: 22px;
    padding: 24px 28px;
    margin-bottom: 18px;
    box-shadow: 0 18px 40px rgba(15, 108, 191, 0.20);
}
.aisn-mm-titlebar h2 {
    color: white !important;
    margin: 0;
}
.aisn-mm-controls {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 16px 0 4px 0;
}
.aisn-mm-controls button {
    border: 0;
    background: #e2e8f0;
    color: #0f172a;
    border-radius: 10px;
    padding: 8px 12px;
    font-weight: 800;
}
.aisn-mm-controls button:hover {
    background: #cbd5e1;
}
.aisn-mm-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(300px, .9fr);
    gap: 22px;
}
.aisn-mm-canvas {
    position: relative;
    height: 640px;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #dbe3ef;
    border-radius: 22px;
    cursor: grab;
    touch-action: none;
}
.aisn-mm-canvas.is-panning {
    cursor: grabbing;
}
.aisn-mm-canvas svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}
.aisn-mm-node {
    position: absolute;
    transform: translate(-50%, -50%);
    border: 2px solid #1a73e8;
    background: #ffffff;
    border-radius: 12px;
    padding: 9px 14px;
    min-width: 135px;
    max-width: 200px;
    text-align: center;
    font-weight: 800;
    color: #172033;
    box-shadow: 0 10px 22px rgba(15, 23, 42, .13);
    cursor: grab;
    transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
    font-size: 13px;
    user-select: none;
    z-index: 2;
}
.aisn-mm-node:hover,
.aisn-mm-node.is-active {
    background: #eaf3ff;
    box-shadow: 0 14px 26px rgba(15, 23, 42, .18);
}
.aisn-mm-node.center {
    background: #1a73e8;
    color: #ffffff;
    font-size: 18px;
    min-width: 190px;
}
.aisn-mm-node.child {
    border-color: #94a3b8;
    font-weight: 650;
    font-size: 12px;
}
.aisn-mm-panel {
    background: #ffffff;
    border: 1px solid #dbe3ef;
    border-radius: 22px;
    padding: 22px;
    min-height: 300px;
}
.aisn-mm-panel h3 {
    margin-top: 0;
}
.aisn-mm-badge {
    display: inline-block;
    padding: 4px 9px;
    background: #0f6cbf;
    color: white;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 14px;
}
.aisn-mm-summary {
    line-height: 1.7;
    color: #334155;
    font-size: 16px;
}
@media (max-width: 900px) {
    .aisn-mm-grid {
        grid-template-columns: 1fr;
    }
}
');

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Mind Map Generator');

echo html_writer::tag(
    'p',
    'Generate a draggable and zoomable AI mind map from a topic or from selected course materials.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/pages/mindmapgenerator.php'),
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'generate', 'value' => 1]);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo local_aiskillnavigator_material_source_selector_html(
    $readablematerials,
    null,
    $courseid,
    $sourcemode,
    $selectedmaterialids,
    'Generation source',
    ''
);

echo html_writer::start_div('form-group mt-4');
echo html_writer::tag('label', 'Topic or optional focus', ['for' => 'topic']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
    'placeholder' => 'Example: HTML, funzioni lineari, Digital Twin...',
]);
echo html_writer::tag('small', 'With Question/topic only this is the mind map topic. With course materials this is an optional focus.', ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Difficulty', ['for' => 'difficulty']);
echo html_writer::select(
    ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'],
    'difficulty',
    $difficulty,
    false,
    ['class' => 'form-control custom-select aisn-direct-fixed-select', 'id' => 'difficulty', 'style' => 'display:block!important;width:360px!important;min-width:360px!important;max-width:100%!important;height:50px!important;min-height:50px!important;padding:10px 46px 10px 14px!important;box-sizing:border-box!important;font-size:15px!important;line-height:1.45!important;border-radius:12px!important;appearance:auto!important;-webkit-appearance:menulist!important;']
);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Generate mind map',
]);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');


function local_aiskillnavigator_mm_web_enrich_nodes(array $nodes, string $topic): array {
    if (!class_exists('\local_aiskillnavigator\service\web_search_service')) {
        foreach ($nodes as &$node) {
            $node['webexample'] = null;
            $node['webstatus'] = 'Search service not available.';
        }
        unset($node);
        return $nodes;
    }

    $search = new \local_aiskillnavigator\service\web_search_service();
    $enabled = $search->is_enabled();

    foreach ($nodes as &$node) {
        $node['webexample'] = null;
        $node['webstatus'] = $enabled ? 'Search API active.' : 'Search API disabled.';
    }
    unset($node);

    if (!$enabled) {
        return $nodes;
    }

    $done = 0;
    $maxsearches = 10;

    foreach ($nodes as &$node) {
        $type = (string)($node['type'] ?? '');

        if (!in_array($type, ['branch', 'child'], true)) {
            continue;
        }

        if ($done >= $maxsearches) {
            $node['webstatus'] = 'Search limit reached for this map.';
            continue;
        }

        $title = trim((string)($node['title'] ?? ''));

        if ($title === '') {
            continue;
        }

        $query = trim($topic . ' ' . $title . ' educational example tutorial official site');
        $results = $search->search($query, 1);
        $first = $results[0] ?? null;
        $done++;

        if (!is_array($first) || empty($first['url'])) {
            $node['webstatus'] = 'No useful online resource found for this node.';
            continue;
        }

        $node['webexample'] = [
            'title' => (string)($first['title'] ?? 'Risorsa online'),
            'url' => (string)($first['url'] ?? ''),
            'snippet' => (string)($first['content'] ?? $first['snippet'] ?? ''),
            'activity' => 'Apri la risorsa, osserva un esempio pratico del concetto "' . $title . '" e scrivi 3 righe su come si collega alla mappa.',
        ];

        $node['webstatus'] = 'Online resource found via ' . $search->provider_name() . '.';
    }

    unset($node);

    return $nodes;
}
if ($map !== null) {
    [$nodes, $edges] = local_aiskillnavigator_mm_flatten($map);

    $nodesjson = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $edgesjson = json_encode($edges, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    echo html_writer::start_div('aisn-mm-wrap');

    echo html_writer::start_div('aisn-mm-head');
    echo html_writer::start_div('aisn-mm-titlebar');
    echo html_writer::tag('h2', s($map['title']));
    echo html_writer::end_div();

    echo html_writer::tag('p', s($map['subtitle']), ['class' => 'text-muted']);

    if (!empty($selectedmaterials)) {
        $names = array_map(function($m) {
            return local_aiskillnavigator_material_source_clean_title($m);
        }, $selectedmaterials);

        echo html_writer::tag('p', 'Generated from materials: ' . s(implode(', ', $names)), ['class' => 'text-muted']);
    } else {
        echo html_writer::tag('p', 'Generated from manual topic, without teacher materials.', ['class' => 'text-muted']);
    }

    echo html_writer::tag('p', 'Drag the canvas, drag nodes, use mouse wheel to zoom, or use the controls below.', ['class' => 'text-muted']);

    echo html_writer::start_div('aisn-mm-controls');
    echo html_writer::tag('button', 'Zoom -', ['type' => 'button', 'id' => 'aisn-mm-zoom-out']);
    echo html_writer::tag('button', 'Reset view', ['type' => 'button', 'id' => 'aisn-mm-reset']);
    echo html_writer::tag('button', 'Zoom +', ['type' => 'button', 'id' => 'aisn-mm-zoom-in']);
    echo html_writer::end_div();

    echo html_writer::end_div();

    echo html_writer::tag('style', '
.aisn-mm-web-example-card {
    margin-top: 18px;
    padding: 16px;
    border-radius: 18px;
    border: 1px solid #bfdbfe;
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
}
.aisn-mm-web-example-card h4 {
    margin: 0 0 8px 0;
    font-weight: 900;
    color: #0f172a;
}
.aisn-mm-web-example-card a {
    color: #0f6cbf;
    font-weight: 850;
    word-break: break-word;
}
.aisn-mm-web-example-card p {
    margin: 8px 0 0 0;
    color: #475569;
    line-height: 1.5;
}
.aisn-mm-web-example-empty {
    margin-top: 18px;
    padding: 14px;
    border-radius: 16px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-weight: 700;
}
');
        echo html_writer::tag('style', '
.aisn-mm-panel-web {
    margin-top: 18px;
}
.aisn-mm-web-visible-v4 {
    padding: 16px;
    border-radius: 18px;
    border: 1px solid #bfdbfe;
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.aisn-mm-web-visible-v4 h4 {
    margin: 0 0 8px 0;
    color: #0f172a;
    font-weight: 900;
}
.aisn-mm-web-visible-v4 a {
    color: #0f6cbf;
    font-weight: 850;
    word-break: break-word;
}
.aisn-mm-web-visible-v4 p {
    margin: 9px 0 0 0;
    color: #475569;
    line-height: 1.5;
}
.aisn-mm-web-muted-v4 {
    padding: 14px;
    border-radius: 16px;
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
    color: #64748b;
    font-weight: 700;
}
');
    echo html_writer::start_div('aisn-mm-grid');

    echo html_writer::start_div('aisn-mm-canvas', ['id' => 'aisn-mm-canvas']);

    echo html_writer::tag('svg', '', ['id' => 'aisn-mm-svg']);

    foreach ($nodes as $node) {
        $class = 'aisn-mm-node ' . s($node['type']);

        echo html_writer::tag('button', s($node['title']), [
            'type' => 'button',
            'class' => $class,
            'data-id' => s($node['id']),
        ]);
    }

    echo html_writer::end_div();

    echo html_writer::start_div('aisn-mm-panel');
    echo html_writer::tag('h3', s($nodes[0]['title']), ['id' => 'aisn-mm-panel-title']);
    echo html_writer::span('Nodo centrale', 'aisn-mm-badge', ['id' => 'aisn-mm-panel-type']);
    echo html_writer::tag('p', s($nodes[0]['summary']), ['class' => 'aisn-mm-summary', 'id' => 'aisn-mm-panel-summary']);
    echo html_writer::end_div();

    echo html_writer::end_div();

    echo html_writer::end_div();

    $js = <<<'JS'
(function() {
    const nodes = __NODES__;
    const edges = __EDGES__;

    const canvas = document.getElementById("aisn-mm-canvas");
    const svg = document.getElementById("aisn-mm-svg");
    const resetBtn = document.getElementById("aisn-mm-reset");
    const zoomInBtn = document.getElementById("aisn-mm-zoom-in");
    const zoomOutBtn = document.getElementById("aisn-mm-zoom-out");

    if (!canvas || !svg) {
        return;
    }

    const byId = {};
    nodes.forEach(function(node) {
        byId[node.id] = node;
        node.px = null;
        node.py = null;
    });

    const state = {
        scale: 1,
        panX: 0,
        panY: 0,
        baseW: 1280,
        baseH: 840,
        initialized: false
    };

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function initPositions(force) {
        if (state.initialized && !force) {
            return;
        }

        state.baseW = Math.max(canvas.clientWidth * 1.65, 1280);
        state.baseH = Math.max(canvas.clientHeight * 1.45, 840);

        nodes.forEach(function(node) {
            if (force || node.px === null || node.py === null) {
                node.px = (node.x / 100) * state.baseW;
                node.py = (node.y / 100) * state.baseH;
            }
        });

        state.initialized = true;
    }

    function screenX(node) {
        return state.panX + node.px * state.scale;
    }

    function screenY(node) {
        return state.panY + node.py * state.scale;
    }

    function render() {
        initPositions(false);

        const w = canvas.clientWidth;
        const h = canvas.clientHeight;

        svg.setAttribute("viewBox", "0 0 " + w + " " + h);
        svg.innerHTML = "";

        edges.forEach(function(edge) {
            const a = byId[edge.from];
            const b = byId[edge.to];

            if (!a || !b) {
                return;
            }

            const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line.setAttribute("x1", screenX(a));
            line.setAttribute("y1", screenY(a));
            line.setAttribute("x2", screenX(b));
            line.setAttribute("y2", screenY(b));
            line.setAttribute("stroke", "#1a73e8");
            line.setAttribute("stroke-width", b.type === "child" ? "2" : "3");
            line.setAttribute("stroke-opacity", b.type === "child" ? ".50" : ".85");
            line.setAttribute("stroke-linecap", "round");
            svg.appendChild(line);
        });

        nodes.forEach(function(node) {
            const el = canvas.querySelector("[data-id=\"" + CSS.escape(node.id) + "\"]");

            if (!el) {
                return;
            }

            el.style.left = screenX(node) + "px";
            el.style.top = screenY(node) + "px";
        });
    }

    function resetView() {
        initPositions(true);
        state.scale = 0.72;
        state.panX = (canvas.clientWidth - state.baseW * state.scale) / 2;
        state.panY = (canvas.clientHeight - state.baseH * state.scale) / 2;
        render();
    }

    function zoomAt(clientX, clientY, factor) {
        const rect = canvas.getBoundingClientRect();
        const cx = clientX - rect.left;
        const cy = clientY - rect.top;

        const beforeX = (cx - state.panX) / state.scale;
        const beforeY = (cy - state.panY) / state.scale;

        state.scale = clamp(state.scale * factor, 0.35, 2.2);

        state.panX = cx - beforeX * state.scale;
        state.panY = cy - beforeY * state.scale;

        render();
    }

    function zoomCenter(factor) {
        const rect = canvas.getBoundingClientRect();
        zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, factor);
    }

    function escapeHtml(value) {
        return String(value || "").replace(/[&<>"']/g, function(m) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[m];
        });
    }

    function renderWebExample(node) {
        const box = document.getElementById("aisn-mm-web-example");

        if (!box) {
            return;
        }

        if (!node || (node.type !== "child" && node.type !== "branch")) {
            box.innerHTML = "";
            return;
        }

        if (!node.webexample || !node.webexample.url) {
            box.innerHTML = '<div class="aisn-mm-web-example-empty">Nessun esempio online collegato a questo nodo. La mappa resta basata sui materiali/argomento inserito.</div>';
            return;
        }

        const ex = node.webexample;

        box.innerHTML =
            '<div class="aisn-mm-web-example-card">' +
            '<h4>Esempio online collegato</h4>' +
            '<a href="' + escapeHtml(ex.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(ex.title || ex.url) + '</a>' +
            (ex.snippet ? '<p>' + escapeHtml(ex.snippet) + '</p>' : '') +
            '<p>' + escapeHtml(ex.activity || '') + '</p>' +
            '</div>';
    }
    function selectNode(id) {
        const node = byId[id];

        if (!node) {
            return;
        }

        document.querySelectorAll(".aisn-mm-node").forEach(function(el) {
            el.classList.toggle("is-active", el.dataset.id === id);
        });

        document.getElementById("aisn-mm-panel-title").textContent = node.title;
        document.getElementById("aisn-mm-panel-summary").textContent = node.summary;
        renderWebExample(node);

        let label = "Nodo";

        if (node.type === "center") {
            label = "Nodo centrale";
        } else if (node.type === "branch") {
            label = "Ramo principale";
        } else if (node.type === "child") {
            label = "Sotto-concetto";
        }

        document.getElementById("aisn-mm-panel-type").textContent = label;
    }

    let pan = null;
    let draggedNode = null;

    canvas.addEventListener("pointerdown", function(event) {
        const targetNode = event.target.closest(".aisn-mm-node, [data-node-id], [data-id], .aisn-mm-branch, .aisn-mm-child");

        if (targetNode) {
            const node = byId[targetNode.dataset.id];

            if (!node) {
                return;
            }

            draggedNode = {
                node: node,
                startX: event.clientX,
                startY: event.clientY,
                nodeX: node.px,
                nodeY: node.py,
                moved: false
            };

            targetNode.setPointerCapture(event.pointerId);
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        pan = {
            startX: event.clientX,
            startY: event.clientY,
            panX: state.panX,
            panY: state.panY
        };

        canvas.classList.add("is-panning");
        canvas.setPointerCapture(event.pointerId);
        event.preventDefault();
    });

    canvas.addEventListener("pointermove", function(event) {
        if (draggedNode) {
            const dx = event.clientX - draggedNode.startX;
            const dy = event.clientY - draggedNode.startY;

            if (Math.abs(dx) + Math.abs(dy) > 3) {
                draggedNode.moved = true;
            }

            draggedNode.node.px = draggedNode.nodeX + dx / state.scale;
            draggedNode.node.py = draggedNode.nodeY + dy / state.scale;

            render();
            event.preventDefault();
            return;
        }

        if (pan) {
            state.panX = pan.panX + (event.clientX - pan.startX);
            state.panY = pan.panY + (event.clientY - pan.startY);

            render();
            event.preventDefault();
        }
    });

    canvas.addEventListener("pointerup", function(event) {
        if (draggedNode) {
            if (!draggedNode.moved) {
                selectNode(draggedNode.node.id);
            }

            draggedNode = null;
            event.preventDefault();
            return;
        }

        pan = null;
        canvas.classList.remove("is-panning");
    });

    canvas.addEventListener("pointercancel", function() {
        pan = null;
        draggedNode = null;
        canvas.classList.remove("is-panning");
    });

    canvas.addEventListener("wheel", function(event) {
        event.preventDefault();
        zoomAt(event.clientX, event.clientY, event.deltaY < 0 ? 1.12 : 0.88);
    }, { passive: false });

    document.querySelectorAll(".aisn-mm-node").forEach(function(button) {
        button.addEventListener("click", function(event) {
            event.preventDefault();
            selectNode(button.dataset.id);
        });
    });

    if (resetBtn) {
        resetBtn.addEventListener("click", resetView);
    }

    if (zoomInBtn) {
        zoomInBtn.addEventListener("click", function() {
            zoomCenter(1.15);
        });
    }

    if (zoomOutBtn) {
        zoomOutBtn.addEventListener("click", function() {
            zoomCenter(0.85);
        });
    }

    window.addEventListener("resize", function() {
        render();
    });

    resetView();
    selectNode("center");
})();
JS;

    $js = str_replace(
        ['__NODES__', '__EDGES__'],
        [$nodesjson, $edgesjson],
        $js
    );

    echo html_writer::tag('script', $js);
}

echo html_writer::end_div();

echo local_aisn_mindmap_polish_prof();
echo local_aiskillnavigator_mindmap_live_web_assets((int)$courseid);
echo local_aiskillnavigator_mojibake_guard();
echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
echo html_writer::tag('script', "
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('aisn-force-mindmap-back-to-course')) {
        return;
    }

    var courseUrl = '/course/view.php?id=' + encodeURIComponent(String(new URLSearchParams(window.location.search).get('courseid') || new URLSearchParams(window.location.search).get('id') || '2'));

    var btn = document.createElement('a');
    btn.id = 'aisn-force-mindmap-back-to-course';
    btn.href = courseUrl;
    btn.className = 'btn btn-secondary mt-2 mb-3';
    btn.textContent = 'Back to course';
    btn.style.display = 'inline-block';

    var inserted = false;

    document.querySelectorAll('p,div,span').forEach(function (el) {
        if (inserted) {
            return;
        }

        var txt = String(el.textContent || '').trim().toLowerCase();

        if (txt.indexOf('course:') === 0 || txt.indexOf('corso:') === 0) {
            el.insertAdjacentElement('afterend', btn);
            inserted = true;
        }
    });

    if (!inserted) {
        var card = document.querySelector('.container-fluid') || document.querySelector('#region-main') || document.body;
        card.insertBefore(btn, card.firstChild);
    }
});
");
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();
if (!function_exists('local_aisn_mindmap_polish_prof')) {
    function local_aisn_mindmap_polish_prof(): string {
        return <<<'HTML'
<style id="aisn-mindmap-polish-prof-v1">
.aisn-mm-rendered{background:#f8fafc;border:1px solid #e2e8f0;border-radius:24px;padding:22px;margin-top:18px}
.aisn-mm-title{font-size:1.6rem;font-weight:900;color:#0f172a;margin:0 0 8px}
.aisn-mm-summary{color:#64748b;margin:0 0 18px;line-height:1.55}
.aisn-mm-central{background:linear-gradient(135deg,#0f6cbf,#2563eb);color:#fff;border-radius:18px;padding:18px 20px;margin-bottom:18px;box-shadow:0 14px 28px rgba(15,108,191,.18)}
.aisn-mm-central strong{display:block;font-size:1.1rem;margin-bottom:4px}
.aisn-mm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
.aisn-mm-branch{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:18px;box-shadow:0 10px 24px rgba(15,23,42,.06)}
.aisn-mm-branch h4{font-size:1.08rem;font-weight:850;margin:0 0 8px;color:#0f172a}
.aisn-mm-branch p{margin:0 0 12px;color:#475569;line-height:1.55}
.aisn-mm-child{border-left:4px solid #0f6cbf;background:#f8fafc;border-radius:12px;padding:10px 12px;margin-top:10px}
.aisn-mm-child strong{display:block;color:#0f172a;margin-bottom:3px}
.aisn-mm-child span{color:#64748b;line-height:1.45}
.aisn-mm-json-hidden{display:none!important}
</style>
<script id="aisn-mindmap-polish-prof-v1-js">
(function(){
function esc(s){return String(s||'').replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];});}
function parse(t){t=(t||'').trim();if(!t||t.indexOf('{')<0||t.indexOf('branches')<0)return null;t=t.replace(/^```json\s*/i,'').replace(/^```\s*/i,'').replace(/```\s*$/i,'').trim();try{var d=JSON.parse(t);return d&&d.branches?d:null;}catch(e){return null;}}
function render(d){var h='<div class="aisn-mm-rendered">';h+='<h3 class="aisn-mm-title">'+esc(d.title||d.central_topic||'Mind map')+'</h3>';if(d.summary)h+='<p class="aisn-mm-summary">'+esc(d.summary)+'</p>';h+='<div class="aisn-mm-central"><strong>'+esc(d.central_topic||d.title||'Tema centrale')+'</strong><span>'+esc(d.central_description||'')+'</span></div>';h+='<div class="aisn-mm-grid">';(d.branches||[]).forEach(function(b){h+='<div class="aisn-mm-branch"><h4>'+esc(b.title||'Ramo')+'</h4>';if(b.description)h+='<p>'+esc(b.description)+'</p>';(b.children||[]).forEach(function(c){h+='<div class="aisn-mm-child"><strong>'+esc(c.title||'Nodo')+'</strong><span>'+esc(c.description||'')+'</span></div>';});h+='</div>';});h+='</div></div>';return h;}
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('pre').forEach(function(pre){var d=parse(pre.textContent);if(!d)return;var div=document.createElement('div');div.innerHTML=render(d);pre.classList.add('aisn-mm-json-hidden');pre.parentNode.insertBefore(div.firstElementChild,pre);});});
})();
</script>
HTML;
    }
}

function local_aiskillnavigator_mindmap_live_web_assets(int $courseid): string {
    $endpoint = new moodle_url('/local/aiskillnavigator/pages/mindmap_web_example.php', ['courseid' => $courseid]);
    $endpointjson = json_encode($endpoint->out(false), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return <<<HTML
<style id="aisn-mindmap-live-web-v1">
#aisn-mm-live-web-box {
    margin-top: 18px;
}
.aisn-mm-live-card {
    padding: 16px;
    border-radius: 18px;
    border: 1px solid #bfdbfe;
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.aisn-mm-live-card h4 {
    margin: 0 0 8px 0;
    color: #0f172a;
    font-weight: 900;
}
.aisn-mm-live-card a {
    color: #0f6cbf;
    font-weight: 850;
    word-break: break-word;
}
.aisn-mm-live-card p {
    margin: 9px 0 0 0;
    color: #475569;
    line-height: 1.5;
}
.aisn-mm-live-muted {
    padding: 14px;
    border-radius: 16px;
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
    color: #64748b;
    font-weight: 750;
}
.aisn-mm-live-loading {
    padding: 14px;
    border-radius: 16px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-weight: 800;
}
</style>

<script id="aisn-mindmap-live-web-v1-js">
(function () {
    const endpoint = {$endpointjson};

    function escapeHtml(value) {
        return String(value || "").replace(/[&<>"']/g, function(m) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","'":"&#039;"}[m];
        });
    }

    function ensureBox() {
        const panel = document.querySelector(".aisn-mm-panel");
        if (!panel) {
            return null;
        }

        let box = document.getElementById("aisn-mm-live-web-box");
        if (!box) {
            box = document.createElement("div");
            box.id = "aisn-mm-live-web-box";
            panel.appendChild(box);
        }

        return box;
    }

    function currentTopic() {
        const titlebar = document.querySelector(".aisn-mm-titlebar h2");
        const topicInput = document.querySelector('input[name="topic"]');
        const panelTitle = document.getElementById("aisn-mm-panel-title");

        return (titlebar && titlebar.textContent.trim()) ||
            (topicInput && topicInput.value.trim()) ||
            (panelTitle && panelTitle.textContent.trim()) ||
            "";
    }

    async function loadExample(title) {
        const box = ensureBox();
        if (!box || !title) {
            return;
        }

        box.innerHTML = '<div class="aisn-mm-live-loading">Cerco esempio online per: ' + escapeHtml(title) + '...</div>';

        const url = endpoint +
            "&title=" + encodeURIComponent(title) +
            "&topic=" + encodeURIComponent(currentTopic());

        try {
            const response = await fetch(url, {credentials: "same-origin"});
            const data = await response.json();

            if (!data.ok) {
                box.innerHTML = '<div class="aisn-mm-live-muted">' + escapeHtml(data.message || "Nessun esempio online trovato.") + '</div>';
                return;
            }

            box.innerHTML =
                '<div class="aisn-mm-live-card">' +
                '<h4>Esempio online collegato</h4>' +
                '<a href="' + escapeHtml(data.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.title || data.url) + '</a>' +
                (data.snippet ? '<p>' + escapeHtml(data.snippet) + '</p>' : '') +
                '<p>' + escapeHtml(data.activity || '') + '</p>' +
                '<p><small>Fonte trovata tramite Search API: ' + escapeHtml(data.provider || '') + '</small></p>' +
                '</div>';
        } catch (e) {
            box.innerHTML = '<div class="aisn-mm-live-muted">Errore durante la ricerca online. Controlla configurazione Search API.</div>';
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        const box = ensureBox();
        if (box) {
            box.innerHTML = '<div class="aisn-mm-live-muted">Clicca un ramo o sotto-concetto della mappa per caricare un esempio online collegato.</div>';
        }
    });

    document.addEventListener("click", function (event) {
        const node = event.target.closest(".aisn-mm-node, [data-node-id], [data-id], .aisn-mm-branch, .aisn-mm-child");
        if (!node) {
            return;
        }

        const title = (node.textContent || "").trim();

        setTimeout(function () {
            loadExample(title);
        }, 120);
    });
})();
</script>
HTML;
}


