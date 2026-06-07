<?php

defined('MOODLE_INTERNAL') || die();

function local_aisn_mdtable_fix_text(string $text): string {
    $map = [
        'Ã¨' => 'è',
        'Ã©' => 'é',
        'Ã ' => 'à',
        'Ã²' => 'ò',
        'Ã¹' => 'ù',
        'Ã¬' => 'ì',
        'â€™' => "'",
        'â€œ' => '"',
        'â€' => '"',
        'â€“' => '-',
        'Â ' => ' ',
    ];

    return str_replace(array_keys($map), array_values($map), $text);
}

function local_aisn_mdtable_is_separator(string $line): bool {
    $line = trim($line);

    if (substr_count($line, '|') < 2) {
        return false;
    }

    $clean = str_replace(['|', ':', '-', ' '], '', $line);

    return $clean === '' && strpos($line, '-') !== false;
}

function local_aisn_mdtable_is_row(string $line): bool {
    return substr_count(trim($line), '|') >= 2;
}

function local_aisn_mdtable_split_row(string $line): array {
    $line = trim(local_aisn_mdtable_fix_text($line));

    if (substr($line, 0, 1) === '|') {
        $line = substr($line, 1);
    }

    if (substr($line, -1) === '|') {
        $line = substr($line, 0, -1);
    }

    $cells = array_map('trim', explode('|', $line));

    return $cells;
}

function local_aisn_mdtable_render(array $headers, array $rows): string {
    $html = '<div class="aisn-mdtable-wrap"><table class="aisn-mdtable"><thead><tr>';

    foreach ($headers as $header) {
        $html .= '<th>' . s($header) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';

        for ($i = 0; $i < count($headers); $i++) {
            $html .= '<td>' . s($row[$i] ?? '') . '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function local_aisn_mdtable_convert_block(string $block): string {
    $block = preg_replace('/<br\s*\/?>/i', "\n", $block);
    $block = html_entity_decode($block, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $block = local_aisn_mdtable_fix_text($block);

    $lines = preg_split('/\n/u', $block);
    $lines = array_values(array_filter(array_map('trim', $lines), static function ($line) {
        return $line !== '';
    }));

    if (count($lines) < 3) {
        return s($block);
    }

    $headerline = null;
    $separatorindex = null;

    for ($i = 0; $i < count($lines) - 1; $i++) {
        if (local_aisn_mdtable_is_row($lines[$i]) && local_aisn_mdtable_is_separator($lines[$i + 1])) {
            $headerline = $lines[$i];
            $separatorindex = $i + 1;
            break;
        }
    }

    if ($headerline === null || $separatorindex === null) {
        return s($block);
    }

    $headers = local_aisn_mdtable_split_row($headerline);
    $rows = [];

    for ($i = $separatorindex + 1; $i < count($lines); $i++) {
        if (!local_aisn_mdtable_is_row($lines[$i]) || local_aisn_mdtable_is_separator($lines[$i])) {
            break;
        }

        $rows[] = local_aisn_mdtable_split_row($lines[$i]);
    }

    if (empty($headers) || empty($rows)) {
        return s($block);
    }

    return local_aisn_mdtable_render($headers, $rows);
}

function local_aisn_mdtable_filter_html(string $html): string {
    $pattern = '~((?:^|(?:\n|<br\s*/?>))\s*\|[^\n<]+\|\s*(?:\n|<br\s*/?>)\s*\|[\s:\-\|]+\|\s*(?:\n|<br\s*/?>)(?:\s*\|[^\n<]+\|\s*(?:\n|<br\s*/?>)?)+)~iu';

    return preg_replace_callback($pattern, static function ($matches) {
        return local_aisn_mdtable_convert_block($matches[1]);
    }, $html);
}

function local_aisn_start_mdtable_formatter(): void {
    static $started = false;

    if ($started) {
        return;
    }

    $started = true;

    ob_start(static function ($html) {
        return local_aisn_mdtable_filter_html($html);
    });
}

function local_aisn_mdtable_assets(): string {
    return html_writer::tag('style', '
.aisn-mdtable-wrap {
    margin: 14px 0 18px;
    overflow-x: auto;
}
.aisn-mdtable {
    border-collapse: collapse;
    background: #fff;
    width: auto;
    max-width: 100%;
    font-size: 1rem;
}
.aisn-mdtable th,
.aisn-mdtable td {
    border: 1px solid #d6d6d6;
    padding: 9px 13px;
    text-align: left;
    vertical-align: top;
}
.aisn-mdtable th {
    background: #f3f4f6;
    font-weight: 800;
}
.aisn-mdtable tr:nth-child(even) td {
    background: #fafafa;
}
');
}