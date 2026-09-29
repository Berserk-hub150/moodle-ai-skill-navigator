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

namespace local_aiskillnavigator\service;

/**
 * Encode assessment text for interchange without treating it as format syntax.
 */
class assessment_export {
    /**
     * Export multiple-choice questions with explicit plain-text formatting.
     *
     * @param array $questions Assessment questions.
     * @return string GIFT file contents.
     */
    public static function gift(array $questions): string {
        $output = '';
        foreach (array_values($questions) as $index => $question) {
            $output .= '::Q' . ($index + 1) . '::[plain]' . self::gift_text($question['question']) . " {\n";
            foreach (array_values($question['options']) as $optionindex => $option) {
                $prefix = $optionindex === (int)$question['correct_index'] ? '=' : '~';
                $output .= $prefix . '[plain]' . self::gift_text($option) . "\n";
            }
            $output .= "}\n\n";
        }
        return $output;
    }

    /**
     * Escape the reserved GIFT characters, including backslashes and newlines.
     *
     * @param string $text Literal question or answer text.
     * @return string Escaped text.
     */
    private static function gift_text(string $text): string {
        return str_replace(
            ['\\', '#', '=', '~', '{', '}', ':', "\n", "\r"],
            ['\\\\', '\\#', '\\=', '\\~', '\\{', '\\}', '\\:', '\\n', ''],
            $text
        );
    }

    /**
     * Prevent spreadsheet programs from evaluating generated text as a formula.
     *
     * @param array $cells Row of scalar values.
     * @return array Text cells safe to pass to fputcsv.
     */
    public static function csv_row(array $cells): array {
        return array_map(static function ($cell): string {
            $text = (string)$cell;
            if (preg_match('/^[\x00-\x20]*[=+@-]/', $text) || preg_match('/^[\t\r\n]/', $text)) {
                return "'" . $text;
            }
            return $text;
        }, $cells);
    }
}
