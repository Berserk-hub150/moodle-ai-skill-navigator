<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/role_guard.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../classes/service/web_search_service.php');

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$skill = required_param('skill', PARAM_TEXT);
$question = optional_param('question', '', PARAM_TEXT);
$topic = optional_param('topic', '', PARAM_TEXT);

$course = get_course($courseid);
require_login($course);
// AISN_FINAL_QUIZ_VIDEO_SESSKEY
require_sesskey();

$context = context_course::instance($courseid);


local_aisn_require_student_area($context);
if (
    !has_capability('local/aiskillnavigator:viewstudent', $context) &&
    !has_capability('local/aiskillnavigator:viewteacher', $context) &&
    !has_capability('moodle/course:view', $context) &&
    !is_siteadmin()
) {
    throw new required_capability_exception($context, 'moodle/course:view', 'nopermissions', '');
}

header('Content-Type: application/json; charset=utf-8');

function local_aisn_clean_text(string $text, int $max = 700): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string)$text);

    if (core_text::strlen($text) > $max) {
        $text = core_text::substr($text, 0, $max) . '...';
    }

    return $text;
}

try {
    $service = new \local_aiskillnavigator\service\web_search_service();

    if (!$service->is_enabled()) {
        echo json_encode([
            'ok' => false,
            'message' => 'Search API non attiva: configura Tavily nelle impostazioni del plugin.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $base = local_aisn_clean_text(trim($topic . ' ' . $skill . ' ' . $question), 180);

    if ($base === '') {
        $base = $skill !== '' ? $skill : 'quiz concept';
    }

    $queries = [
        'site:youtube.com ' . $base . ' tutorial spiegazione video',
        'site:youtube.com ' . $base . ' explanation tutorial',
        $base . ' video tutorial spiegazione didattica',
    ];

    $best = null;
    $fallback = null;

    foreach ($queries as $query) {
        $results = $service->search($query, 5);

        foreach ($results as $r) {
            $url = (string)($r['url'] ?? '');
            $lower = strtolower($url);

            if ($url === '') {
                continue;
            }

            if ($fallback === null) {
                $fallback = $r;
            }

            if (str_contains($lower, 'youtube.com') || str_contains($lower, 'youtu.be')) {
                $best = $r;
                break 2;
            }
        }
    }

    if ($best === null) {
        $best = $fallback;
    }

    if ($best === null || empty($best['url'])) {
        echo json_encode([
            'ok' => false,
            'message' => 'Tavily non ha trovato un video o tutorial utile per questa competenza.',
            'provider' => $service->provider_name(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $url = (string)($best['url'] ?? '');
    $isvideo = str_contains(strtolower($url), 'youtube.com') || str_contains(strtolower($url), 'youtu.be');

    echo json_encode([
        'ok' => true,
        'provider' => $service->provider_name(),
        'skill' => local_aisn_clean_text($skill, 120),
        'isvideo' => $isvideo,
        'title' => local_aisn_clean_text((string)($best['title'] ?? 'Risorsa consigliata'), 180),
        'url' => $url,
        'snippet' => local_aisn_clean_text((string)($best['content'] ?? $best['snippet'] ?? ''), 500),
        'activity' => 'Guarda la risorsa trovata da Tavily, poi scrivi in 3 righe il concetto collegato alla competenza "' . local_aisn_clean_text($skill, 120) . '".',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'message' => 'Errore Tavily/Search API: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
