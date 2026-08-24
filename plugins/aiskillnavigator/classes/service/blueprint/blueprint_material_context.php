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

namespace local_aiskillnavigator\service\blueprint;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Formats course materials for XR blueprint prompts.
/**
 * Blueprint material context implementation.
 */
class blueprint_material_context {
    /**
     * Build helper.
     */
    public function build(array $materials, int $limit): string {
        $context = '';
        $seen = [];
        $source = 1;

        foreach ($materials as $material) {
            $content = trim((string) ($material->content ?? ''));
            $content = trim((string) preg_replace('/\s+/u', ' ', $content));

            if ($content === '') {
                continue;
            }

            $title = trim((string) ($material->title ?? 'Materiale senza titolo'));
            $type = trim((string) ($material->materialtype ?? 'text'));
            $key = $this->identity_key($material, $title, $type, $content);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($content, 'UTF-8') > $limit) {
                $content = mb_substr($content, 0, $limit, 'UTF-8') . '...';
            } else if (strlen($content) > $limit) {
                $content = substr($content, 0, $limit) . '...';
            }

            $context .= 'FONTE ' . $source . "\n"
                . 'Titolo: ' . $title . "\n"
                . 'Tipo: ' . $type . "\n"
                . "Contenuto: {$content}\n\n";

            $source++;
        }

        return trim($context);
    }

    /**
     * Identity key helper.
     */
    private function identity_key(\stdClass $material, string $title, string $type, string $content): string {
        if (!empty($material->id)) {
            return 'id:' . (int) $material->id;
        }

        if (preg_match('/cm\s*#\s*([0-9]+)\s*\]/i', $title, $matches)) {
            return 'cm:' . (int) $matches[1];
        }

        $normalised = trim((string) preg_replace('/\s+/u', ' ', $title . "\n" . $content));

        if (class_exists('\core_text')) {
            $normalised = \core_text::strtolower($normalised);
        } else {
            $normalised = strtolower($normalised);
        }

        return strtolower($type) . ':' . md5($normalised);
    }
}
