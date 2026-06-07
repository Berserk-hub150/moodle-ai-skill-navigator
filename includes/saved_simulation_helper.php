<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Saved simulation helper.
 *
 * This helper keeps saved simulator exercises clean:
 * - it removes Moodle UI/CSS/JS dumps from old records;
 * - it preserves only the generated exercise text;
 * - it renders saved exercises as readable web pages;
 * - it linkifies URLs without injecting unsafe HTML.
 */

if (!function_exists('local_aisn_saved_sim_text')) {
    function local_aisn_saved_sim_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(["\xC2\xA0", "\t"], ' ', $text);
        $text = preg_replace('/[ ]{2,}/u', ' ', (string)$text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string)$text);
        return trim((string)$text);
    }
}

if (!function_exists('local_aisn_saved_sim_strip_html_blocks')) {
    function local_aisn_saved_sim_strip_html_blocks(string $text): string {
        $text = preg_replace('#<script\b[^>]*>.*?</script>#is', "\n", $text);
        $text = preg_replace('#<style\b[^>]*>.*?</style>#is', "\n", (string)$text);
        $text = preg_replace('#<[^>]+>#u', "\n", (string)$text);
        return local_aisn_saved_sim_text((string)$text);
    }
}

if (!function_exists('local_aisn_saved_sim_labels')) {
    function local_aisn_saved_sim_labels(): array {
        return [
            'Exercise and simulator suggestion',
            'Titolo dell\'esercizio',
            'Titolo esercizio',
            'Istruzioni',
            'Dominio e codominio',
            'Zeri della funzione',
            'Rappresentazione grafica',
            'Simulatore/tool consigliato',
            'Link/fonte',
            'Perché questo simulatore è adatto',
            'Alternativa se nessun simulatore è disponibile',
            'Criteri di valutazione',
            'Exercise title',
            'Instructions',
            'Recommended simulator/tool',
            'Source link',
            'Why this simulator is suitable',
            'Alternative if no simulator is available',
            'Evaluation criteria',
        ];
    }
}

if (!function_exists('local_aisn_saved_sim_noise_line')) {
    function local_aisn_saved_sim_noise_line(string $line): bool {
        $line = trim($line);

        if ($line === '') {
            return false;
        }

        if (preg_match('/^[-–—•*]+$/u', $line)) {
            return true;
        }

        if (preg_match('/^Section\s*[0-9]+$/iu', $line)) {
            return true;
        }

        $exact = [
            'Skip to main content',
            'Side panel',
            'AI Skill Navigator Thesis',
            'Home',
            'Dashboard',
            'My courses',
            'Site administration',
            'Question bank',
            'Content bank',
            'Course completion',
            'Badges',
            'Competencies',
            'Filters',
            'LTI External tools',
            'Recycle bin',
            'Course reuse',
            'More',
            'Notifications',
            'You have no notifications',
            'See all',
            'Profile',
            'Calendar',
            'Private files',
            'Preferences',
            'Switch role to...',
            'Log out',
            'Edit mode',
            'Documentation for this page',
            'Contact site support',
            'Services and support',
            'Data retention summary',
            'Powered by Moodle',
            'Messaging',
            'Contacts',
            'No contacts',
            'Cancel',
            'OK',
            'Settings',
            'Search people and messages',
            'Privacy',
            'General',
            'Back to course',
            'AI Simulator Finder',
            'Saved simulations',
            'Mostra dato grezzo salvato',
        ];

        if (in_array($line, $exact, true)) {
            return true;
        }

        if (preg_match('/^(body|html|main|section|div|span|button|input|textarea|select|label|a)(\.|#|\[|\s|:)/i', $line)) {
            return true;
        }

        if (preg_match('/^(\.|#)[a-z0-9_\-]+/i', $line)) {
            return true;
        }

        if (preg_match('/^(@media|@keyframes|\/\*|\*\/|\{|\})/i', $line)) {
            return true;
        }

        if (preg_match('/^\s*[a-z\-]+\s*:\s*[^;]+;?\s*$/i', $line) &&
            preg_match('/(padding|margin|background|border|font|display|width|height|overflow|color|position|radius|shadow|important|cursor|opacity|transition|grid|z-index)/i', $line)) {
            return true;
        }

        if (preg_match('/(document\.addEventListener|function\s*\(|window\.|querySelector|const\s+|let\s+|var\s+|=>|\(\s*function)/i', $line)) {
            return true;
        }

        if (preg_match('/(class=|id=|aria-label|<\/?[a-z][^>]*>)/i', $line)) {
            return true;
        }

        if (preg_match('/^(\/\/|\/\*|\*)/u', $line)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('local_aisn_saved_sim_is_heading')) {
    function local_aisn_saved_sim_is_heading(string $line): bool {
        $line = trim(preg_replace('/^\s*[0-9]+[.)]\s*/u', '', $line));

        foreach (local_aisn_saved_sim_labels() as $label) {
            if (core_text::strtolower($line) === core_text::strtolower($label)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('local_aisn_saved_sim_normalize_heading')) {
    function local_aisn_saved_sim_normalize_heading(string $line): string {
        $line = trim(preg_replace('/^\s*[0-9]+[.)]\s*/u', '', $line));

        foreach (local_aisn_saved_sim_labels() as $label) {
            if (core_text::strtolower($line) === core_text::strtolower($label)) {
                return $label;
            }
        }

        return $line;
    }
}

if (!function_exists('local_aisn_sim_canonicalize_generated_result')) {
    function local_aisn_sim_canonicalize_generated_result(string $text): string {
        $text = local_aisn_saved_sim_text($text);

        // Remove section markers even when the AI attached them to text, e.g. "CODAPSection 2".
        $text = preg_replace('/\s*Section\s*[0-9]+\s*/iu', "\n\n", (string)$text);

        // Convert numbered headings and glued headings to clean heading lines.
        foreach (local_aisn_saved_sim_labels() as $label) {
            $quoted = preg_quote($label, '/');
            $text = preg_replace('/(?:^|\s)(?:[0-9]+[.)]\s*)?(' . $quoted . ')\s*/ium', "\n\n$1\n", (string)$text);
        }

        // Keep URLs isolated so the renderer can make them clickable.
        $text = preg_replace('/(https?:\/\/[^\s<]+)(?=(Perché|Alternativa|Criteri|Titolo|Istruzioni|Simulatore|Link|$))/iu', "$1\n\n", (string)$text);

        // Split only obvious sentence boundaries. Do not insert bullets here.
        $text = preg_replace('/\.(?=[A-ZÀ-Ù])/u', ".\n", (string)$text);
        $text = preg_replace('/\)(?=[A-ZÀ-Ù])/u', ")\n", (string)$text);

        $lines = preg_split('/\R/u', local_aisn_saved_sim_text((string)$text));
        $clean = [];

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '') {
                if (!empty($clean) && end($clean) !== '') {
                    $clean[] = '';
                }
                continue;
            }

            if (local_aisn_saved_sim_noise_line($line)) {
                continue;
            }

            $clean[] = $line;
        }

        return local_aisn_saved_sim_text(implode("\n", $clean));
    }
}

if (!function_exists('local_aisn_sim_clean_generated_result')) {
    function local_aisn_sim_clean_generated_result(string $raw): string {
        $raw = local_aisn_saved_sim_strip_html_blocks($raw);

        if ($raw === '') {
            return '';
        }

        $markers = [
            'Exercise and simulator suggestion',
            'Titolo dell\'esercizio',
            'Titolo esercizio',
            'Istruzioni',
            'Simulatore/tool consigliato',
            'Criteri di valutazione',
        ];

        $bestpos = null;

        foreach ($markers as $marker) {
            $pos = stripos($raw, $marker);
            if ($pos !== false && ($bestpos === null || $pos < $bestpos)) {
                $bestpos = $pos;
            }
        }

        if ($bestpos !== null) {
            $raw = substr($raw, $bestpos);
        }

        $endmarkers = [
            'document.addEventListener',
            'Documentation for this page',
            'Services and support',
            'Contact site support',
            'Powered by Moodle',
            'Messaging',
            'Data retention summary',
            'Privacy',
            'You are logged in as',
            'Version 4.',
        ];

        foreach ($endmarkers as $marker) {
            $pos = stripos($raw, $marker);
            if ($pos !== false && $pos > 80) {
                $raw = substr($raw, 0, $pos);
            }
        }

        $raw = local_aisn_sim_canonicalize_generated_result($raw);

        $lines = preg_split('/\R/u', $raw);
        $clean = [];

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '') {
                if (!empty($clean) && end($clean) !== '') {
                    $clean[] = '';
                }
                continue;
            }

            if (local_aisn_saved_sim_noise_line($line)) {
                continue;
            }

            if (core_text::strlen($line) > 1400 &&
                preg_match('/(body|padding|margin|background|border|font|display|important|class=|id=|querySelector|function)/i', $line)) {
                continue;
            }

            $clean[] = $line;
        }

        $result = local_aisn_sim_canonicalize_generated_result(implode("\n", $clean));
        return core_text::substr(local_aisn_saved_sim_text($result), 0, 65000);
    }
}

if (!function_exists('local_aisn_saved_sim_preview')) {
    function local_aisn_saved_sim_preview(string $text, int $max = 260): string {
        $clean = local_aisn_sim_clean_generated_result($text);
        $clean = preg_replace('/\s+/u', ' ', (string)$clean);
        $clean = trim((string)$clean);

        if ($clean === '') {
            return 'Nessuna anteprima disponibile.';
        }

        if (core_text::strlen($clean) > $max) {
            return core_text::substr($clean, 0, $max) . '...';
        }

        return $clean;
    }
}

if (!function_exists('local_aisn_saved_sim_is_bad_raw')) {
    function local_aisn_saved_sim_is_bad_raw(string $text): bool {
        return preg_match('/(Skip to main content|Powered by Moodle|document\.addEventListener|querySelector|body\.path-local-aiskillnavigator|Data retention summary|Messaging|Contacts)/i', $text) === 1;
    }
}

if (!function_exists('local_aisn_saved_sim_linkify')) {
    function local_aisn_saved_sim_linkify(string $text): string {
        $parts = preg_split('~(https?://[^\s<]+)~i', (string)$text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('~^https?://~i', $part)) {
                $url = rtrim($part, ".,);]");
                $tail = substr($part, strlen($url));

                $html .= html_writer::link($url, s($url), [
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'class' => 'aisn-web-link',
                ]);
                $html .= s($tail);
            } else {
                $html .= s($part);
            }
        }

        return $html;
    }
}

if (!function_exists('local_aisn_saved_sim_split_sentences')) {
    function local_aisn_saved_sim_split_sentences(string $text): array {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+(?=[A-ZÀ-Ù])/u', $text);
        $parts = array_map('trim', (array)$parts);

        return array_values(array_filter($parts, static function ($part): bool {
            return $part !== '' && !preg_match('/^[-–—•*]+$/u', $part);
        }));
    }
}

if (!function_exists('local_aisn_saved_sim_render_paragraphs')) {
    function local_aisn_saved_sim_render_paragraphs(array $lines): string {
        $html = '';

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '' || local_aisn_saved_sim_noise_line($line)) {
                continue;
            }

            if (preg_match('/^https?:\/\//i', $line)) {
                $html .= html_writer::tag('p', local_aisn_saved_sim_linkify($line), ['class' => 'aisn-web-linkline']);
            } else {
                $html .= html_writer::tag('p', local_aisn_saved_sim_linkify($line));
            }
        }

        return $html;
    }
}

if (!function_exists('local_aisn_saved_sim_render_instruction_list')) {
    function local_aisn_saved_sim_render_instruction_list(array $lines): string {
        $joined = trim(implode(' ', array_filter(array_map('trim', $lines))));
        $items = local_aisn_saved_sim_split_sentences($joined);

        if (count($items) < 2) {
            return local_aisn_saved_sim_render_paragraphs($lines);
        }

        $html = html_writer::start_tag('ol', ['class' => 'aisn-web-list aisn-web-ordered-list']);

        foreach ($items as $item) {
            $html .= html_writer::tag('li', local_aisn_saved_sim_linkify($item));
        }

        $html .= html_writer::end_tag('ol');
        return $html;
    }
}

if (!function_exists('local_aisn_saved_sim_render_criteria_list')) {
    function local_aisn_saved_sim_render_criteria_list(array $lines): string {
        $text = trim(implode("\n", array_filter(array_map('trim', $lines))));

        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s*[-–—•*]+\s*/u', "\n", (string)$text);

        $starts = [
            'Corretto caricamento del dataset',
            'Grafico a dispersione',
            'Calcolo corretto',
            'Filtro per umidità',
            'Descrizione testuale',
            'Totale:',
            'Uso appropriato',
            'Accuratezza',
            'Precisione',
            'Correttezza',
        ];

        foreach ($starts as $start) {
            $quoted = preg_quote($start, '/');
            $text = preg_replace('/(?<!^)(?<!\n)(' . $quoted . ')/iu', "\n$1", (string)$text);
        }

        $items = preg_split('/\R/u', $text);
        $items = array_map('trim', (array)$items);
        $items = array_values(array_filter($items, static function ($item): bool {
            return $item !== '' && !preg_match('/^[-–—•*]+$/u', $item);
        }));

        if (empty($items)) {
            return '';
        }

        $html = html_writer::start_tag('ul', ['class' => 'aisn-web-list']);

        foreach ($items as $item) {
            $html .= html_writer::tag('li', local_aisn_saved_sim_linkify($item));
        }

        $html .= html_writer::end_tag('ul');
        return $html;
    }
}

if (!function_exists('local_aisn_saved_sim_sections')) {
    function local_aisn_saved_sim_sections(string $text): array {
        $lines = preg_split('/\R/u', local_aisn_sim_clean_generated_result($text));
        $sections = [];
        $current = '';

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '' || local_aisn_saved_sim_noise_line($line)) {
                continue;
            }

            if (local_aisn_saved_sim_is_heading($line)) {
                $current = local_aisn_saved_sim_normalize_heading($line);
                if (!isset($sections[$current])) {
                    $sections[$current] = [];
                }
                continue;
            }

            if ($current === '') {
                $current = 'Contenuto';
                if (!isset($sections[$current])) {
                    $sections[$current] = [];
                }
            }

            $sections[$current][] = $line;
        }

        return $sections;
    }
}

if (!function_exists('local_aisn_saved_sim_render_content')) {
    function local_aisn_saved_sim_render_content(string $text): string {
        $sections = local_aisn_saved_sim_sections($text);

        if (empty($sections)) {
            return html_writer::div('Questa simulazione salvata non contiene un risultato leggibile. Rigenerala dal Simulator Finder.', 'aisn-web-empty');
        }

        $html = '';

        foreach ($sections as $heading => $lines) {
            $heading = trim((string)$heading);

            if ($heading !== 'Contenuto') {
                $html .= html_writer::tag('h3', s($heading), ['class' => 'aisn-web-section-title']);
            }

            $low = core_text::strtolower($heading);

            if (str_contains($low, 'istruzioni') || str_contains($low, 'instructions')) {
                $html .= local_aisn_saved_sim_render_instruction_list($lines);
                continue;
            }

            if (str_contains($low, 'criteri') || str_contains($low, 'criteria')) {
                $html .= local_aisn_saved_sim_render_criteria_list($lines);
                continue;
            }

            $html .= local_aisn_saved_sim_render_paragraphs($lines);
        }

        return $html;
    }
}
