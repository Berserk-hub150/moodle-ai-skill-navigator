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

namespace local_aiskillnavigator\service\embedding;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Turns search results into a compact prompt context.
/**
 * Rag context builder implementation.
 */
class rag_context_builder {
    /**
     * Build helper.
     */
    public function build(array $results, int $maxchars = 6000): string {
        $context = '';
        $total = 0;
        $source = 1;
        $seen = [];

        foreach ($results as $result) {
            $text = trim((string) ($result->chunktext ?? ''));

            if ($text === '') {
                continue;
            }

            $title = trim((string) ($result->title ?? 'Materiale'));
            $key = $this->identity_key($result, $title, $text);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $score = (string) ($result->similarity ?? 'n/a');
            $block = "FONTE {$source} (materiale: {$title}, rilevanza: {$score})\n{$text}\n\n";
            $length = \core_text::strlen($block);

            if ($total + $length > $maxchars) {
                $remaining = $maxchars - $total;
                if ($remaining > 250) {
                    $context .= \core_text::substr($block, 0, $remaining) . "...\n\n";
                }
                break;
            }

            $context .= $block;
            $total += $length;
            $source++;
        }

        return trim($context);
    }

    /**
     * Identity key helper.
     */
    private function identity_key(\stdClass $result, string $title, string $text): string {
        if (!empty($result->materialid) && isset($result->chunkindex)) {
            return 'material-chunk:' . (int) $result->materialid . ':' . (int) $result->chunkindex;
        }

        $normalised = trim((string) preg_replace('/\s+/u', ' ', $title . "\n" . $text));

        if (class_exists('\core_text')) {
            $normalised = \core_text::strtolower($normalised);
        } else {
            $normalised = strtolower($normalised);
        }

        return md5($normalised);
    }
}
