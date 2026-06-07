<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/service/web_search_service.php');

function local_aiskillnavigator_error_remediation_css(): string {
    return <<<'HTML'
<style id="aisn-error-remediation-v1">
.aisn-remediation-card {
    margin-top: 16px;
    border: 1px solid #bfdbfe;
    border-left: 7px solid #0f6cbf;
    background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    border-radius: 18px;
    padding: 16px 18px;
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.aisn-remediation-card h4 {
    margin: 0 0 10px 0;
    font-weight: 900;
    color: #0f172a;
}
.aisn-remediation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 10px;
    margin-top: 10px;
}
.aisn-remediation-chip {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 12px;
    color: #334155;
}
.aisn-remediation-resource {
    margin-top: 12px;
    padding: 14px;
    border-radius: 16px;
    background: #ffffff;
    border: 1px solid #dbeafe;
}
.aisn-remediation-resource a {
    color: #0f6cbf;
    font-weight: 850;
    word-break: break-word;
}
.aisn-remediation-muted {
    margin-top: 12px;
    padding: 12px;
    border-radius: 14px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-weight: 750;
}
.aisn-remediation-activity {
    margin-top: 12px;
    padding: 12px;
    border-radius: 14px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #14532d;
}
</style>
HTML;
}

function local_aiskillnavigator_error_remediation_pick_resource(string $query): array {
    if (!class_exists('\local_aiskillnavigator\service\web_search_service')) {
        return [
            'ok' => false,
            'message' => 'Search API service non disponibile.',
        ];
    }

    $service = new \local_aiskillnavigator\service\web_search_service();

    if (!$service->is_enabled()) {
        return [
            'ok' => false,
            'message' => 'Search API non attiva. Configura Tavily/Search API nelle impostazioni del plugin.',
        ];
    }

    $results = $service->search($query, 5);

    if (empty($results)) {
        return [
            'ok' => false,
            'message' => 'Nessuna risorsa online trovata per questo errore.',
            'provider' => $service->provider_name(),
        ];
    }

    $best = null;

    foreach ($results as $candidate) {
        $url = strtolower((string)($candidate['url'] ?? ''));
        if (
            str_contains($url, 'youtube.com') ||
            str_contains($url, 'youtu.be') ||
            str_contains($url, 'khanacademy.org') ||
            str_contains($url, 'geogebra.org') ||
            str_contains($url, 'w3schools.com') ||
            str_contains($url, 'mdn') ||
            str_contains($url, 'phet.colorado.edu')
        ) {
            $best = $candidate;
            break;
        }
    }

    if ($best === null) {
        $best = $results[0];
    }

    return [
        'ok' => true,
        'provider' => $service->provider_name(),
        'title' => (string)($best['title'] ?? 'Risorsa online consigliata'),
        'url' => (string)($best['url'] ?? ''),
        'snippet' => (string)($best['content'] ?? $best['snippet'] ?? ''),
    ];
}

function local_aiskillnavigator_error_remediation_card(
    array $question,
    int $selectedanswer,
    int $correctindex,
    string $topic,
    int $courseid
): string {
    if ($selectedanswer < 0 || $selectedanswer === $correctindex) {
        return '';
    }

    $questiontext = trim((string)($question['question'] ?? ''));
    $options = isset($question['options']) && is_array($question['options']) ? array_values($question['options']) : [];

    $wronganswer = $options[$selectedanswer] ?? 'Risposta selezionata';
    $correctanswer = $options[$correctindex] ?? 'Risposta corretta';
    $skill = trim((string)($question['skill'] ?? 'Ability not specified'));
    $explanation = trim((string)($question['explanation'] ?? ''));

    $concept = $skill !== '' && $skill !== 'Ability not specified' ? $skill : $questiontext;
    $query = trim($topic . ' ' . $concept . ' spiegazione video tutorial esempio didattico');

    if (core_text::strlen($query) > 240) {
        $query = core_text::substr($query, 0, 240);
    }

    $resource = local_aiskillnavigator_error_remediation_pick_resource($query);

    $html = '';

    $html .= html_writer::start_div('aisn-remediation-card');
    $html .= html_writer::tag('h4', 'Recupero adattivo guidato dall’errore');

    $html .= html_writer::start_div('aisn-remediation-grid');

    $html .= html_writer::div(
        html_writer::tag('strong', 'Ability involved') . '<br>' . s($skill),
        'aisn-remediation-chip'
    );

    $html .= html_writer::div(
        html_writer::tag('strong', 'La tua risposta') . '<br>' . s($wronganswer),
        'aisn-remediation-chip'
    );

    $html .= html_writer::div(
        html_writer::tag('strong', 'Risposta corretta') . '<br>' . s($correctanswer),
        'aisn-remediation-chip'
    );

    $html .= html_writer::end_div();

    if ($explanation !== '') {
        $html .= html_writer::tag(
            'p',
            html_writer::tag('strong', 'Perché hai sbagliato: ') . s($explanation),
            ['class' => 'mt-3 mb-2']
        );
    }

    if (!empty($resource['ok']) && !empty($resource['url'])) {
        $html .= html_writer::start_div('aisn-remediation-resource');
        $html .= html_writer::tag('strong', 'Risorsa esterna consigliata');
        $html .= html_writer::tag('br');
        $html .= html_writer::link(
            $resource['url'],
            s($resource['title']),
            ['target' => '_blank', 'rel' => 'noopener noreferrer']
        );

        if (!empty($resource['snippet'])) {
            $html .= html_writer::tag('p', s($resource['snippet']));
        }

        $html .= html_writer::tag(
            'small',
            'Fonte trovata tramite Search API: ' . s((string)($resource['provider'] ?? 'search'))
        );

        $html .= html_writer::end_div();
    } else {
        $html .= html_writer::div(
            s((string)($resource['message'] ?? 'Risorsa esterna non disponibile.')),
            'aisn-remediation-muted'
        );
    }

    $html .= html_writer::div(
        html_writer::tag('strong', 'Mini-attività di recupero: ') .
        'guarda la risorsa consigliata, poi rispondi di nuovo spiegando in 3 righe perché la risposta corretta è "' .
        s($correctanswer) . '".',
        'aisn-remediation-activity'
    );

    $html .= html_writer::end_div();

    return $html;
}
