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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Local aiskillnavigator mojibake score helper.
 */
function local_aiskillnavigator_mojibake_score(string $text): int {
    $bad = ['Ã', 'Â', 'â€', 'â€™', 'â€œ', 'â€', 'â€“', 'â€”', '�'];

    $score = 0;

    foreach ($bad as $token) {
        $score += substr_count($text, $token);
    }

    return $score;
}

/**
 * Local aiskillnavigator fix mojibake helper.
 */
function local_aiskillnavigator_fix_mojibake(string $text): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (function_exists('mb_convert_encoding')) {
        for ($i = 0; $i < 3; $i++) {
            $before = local_aiskillnavigator_mojibake_score($text);
            $candidate = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            $after = local_aiskillnavigator_mojibake_score($candidate);

            if ($candidate !== '' && $after < $before) {
                $text = $candidate;
            } else {
                break;
            }
        }
    }

    $map = [
        'ÃƒÂ ' => 'à',
        'ÃƒÂ¨' => 'è',
        'ÃƒÂ©' => 'é',
        'ÃƒÂ¬' => 'ì',
        'ÃƒÂ²' => 'ò',
        'ÃƒÂ¹' => 'ù',
        'Ã ' => 'à',
        'Ã¨' => 'è',
        'Ã©' => 'é',
        'Ã¬' => 'ì',
        'Ã²' => 'ò',
        'Ã¹' => 'ù',
        'Ã€' => 'À',
        'Ãˆ' => 'È',
        'Ã‰' => 'É',
        'ÃŒ' => 'Ì',
        'Ã’' => 'Ò',
        'Ã™' => 'Ù',
        'â€™' => "'",
        'â€˜' => "'",
        'â€œ' => '"',
        'â€' => '"',
        'â€“' => '-',
        'â€”' => '-',
        'â€¦' => '...',
        'Â«' => '«',
        'Â»' => '»',
        'Â°' => '°',
        'Â' => '',
        'Ã‚' => '',
    ];

    for ($i = 0; $i < 3; $i++) {
        $text = str_replace(array_keys($map), array_values($map), $text);
    }

    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace("/\r\n|\r/", "\n", $text);

    return trim($text);
}

/**
 * Local aiskillnavigator fix mojibake recursive helper.
 */
function local_aiskillnavigator_fix_mojibake_recursive($value) {
    if (is_string($value)) {
        return local_aiskillnavigator_fix_mojibake($value);
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = local_aiskillnavigator_fix_mojibake_recursive($item);
        }

        return $value;
    }

    if (is_object($value)) {
        foreach ($value as $key => $item) {
            $value->$key = local_aiskillnavigator_fix_mojibake_recursive($item);
        }

        return $value;
    }

    return $value;
}

/**
 * Local aiskillnavigator render ai inline helper.
 */
function local_aiskillnavigator_render_ai_inline(string $text): string {
    $safe = s(local_aiskillnavigator_fix_mojibake($text));
    $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe);
    $safe = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $safe);
    return $safe;
}

/**
 * Local aiskillnavigator render ai answer helper.
 */
function local_aiskillnavigator_render_ai_answer(string $text): string {
    $text = local_aiskillnavigator_fix_mojibake($text);
    $lines = preg_split("/\n/", $text);

    $html = html_writer::start_div('aisn-answer formatted');
    $inlist = false;

    foreach ($lines as $line) {
        $raw = trim($line);

        if ($raw === '') {
            if ($inlist) {
                $html .= html_writer::end_tag('ul');
                $inlist = false;
            }

            continue;
        }

        if (preg_match('/^#{1,4}\s*(.+)$/', $raw, $m)) {
            if ($inlist) {
                $html .= html_writer::end_tag('ul');
                $inlist = false;
            }

            $html .= html_writer::tag('h4', local_aiskillnavigator_render_ai_inline($m[1]));
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $raw, $m)) {
            if (!$inlist) {
                $html .= html_writer::start_tag('ul');
                $inlist = true;
            }

            $html .= html_writer::tag('li', local_aiskillnavigator_render_ai_inline($m[1]));
            continue;
        }

        if ($inlist) {
            $html .= html_writer::end_tag('ul');
            $inlist = false;
        }

        $html .= html_writer::tag('p', local_aiskillnavigator_render_ai_inline($raw));
    }

    if ($inlist) {
        $html .= html_writer::end_tag('ul');
    }

    $html .= html_writer::end_div();

    return $html;
}
