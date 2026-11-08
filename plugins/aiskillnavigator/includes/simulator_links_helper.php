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
 * Safe, descriptive Simulator Finder links.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Nikhil Jangid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Build a descriptive external link and communicate its new-tab behaviour.
 *
 * @param string $url Destination URL.
 * @param string $label Link label.
 * @return string Escaped link HTML.
 */
function local_aiskillnavigator_sim_external_link(string $url, string $label): string {
    $label = trim($label);

    if ($label === '' || $label === $url) {
        $label = 'Open simulator or tool website';
    }

    return html_writer::link(
        $url,
        s($label . ' (opens in a new tab)'),
        ['target' => '_blank', 'rel' => 'noopener noreferrer']
    );
}

/**
 * Local aiskillnavigator sim inline format helper.
 *
 * @param string $line Line.
 * @return string Escaped formatted HTML.
 */
function local_aiskillnavigator_sim_inline_format(string $line): string {
    $line = trim($line);

    if ($line === '') {
        return '';
    }

    // Tokenise the original text once: generated HTML must never be scanned as input.
    $pattern = "~\[([^\]\r\n]*)\]\((https?://[^\s)]+)\)|(https?://[^\s<>\"']+)~u";
    preg_match_all($pattern, $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $formattext = static function(string $text): string {
        return preg_replace('/\*\*(.*?)\*\*/u', '<strong>$1</strong>', s($text));
    };
    $html = '';
    $offset = 0;
    foreach ($matches as $match) {
        $html .= $formattext(substr($line, $offset, $match[0][1] - $offset));
        if ($match[2][1] >= 0) {
            $html .= local_aiskillnavigator_sim_external_link($match[2][0], $match[1][0]);
        } else {
            $rawurl = $match[3][0];
            $url = rtrim($rawurl, '.,;)');
            $html .= local_aiskillnavigator_sim_external_link($url, '');
            $html .= s(substr($rawurl, strlen($url)));
        }
        $offset = $match[0][1] + strlen($match[0][0]);
    }
    return $html . $formattext(substr($line, $offset));
}

