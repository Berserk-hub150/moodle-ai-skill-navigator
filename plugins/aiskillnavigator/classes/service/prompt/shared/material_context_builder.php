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

namespace local_aiskillnavigator\service\prompt;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Formats uploaded Moodle materials for prompt text.
/**
 * Material context builder implementation.
 */
class material_context_builder {
    /** @var text_tools Text. */
    private text_tools $text;

    /**
     * Construct helper.
     */
    public function __construct(text_tools $text) {
        $this->text = $text;
    }

    /**
     * Build helper.
     */
    public function build(array $materials, int $limit): string {
        $context = '';
        $seen = [];
        $source = 1;

        foreach ($materials as $material) {
            $title = $this->read($material, 'title', 'Materiale senza titolo');
            $type = $this->read($material, 'materialtype', 'text');
            $content = $this->text->clean($this->read($material, 'content', ''));

            if ($content === '') {
                continue;
            }

            $key = $this->identity_key($material, $title, $type, $content);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $context .= "Fonte {$source}\n"
                . 'Titolo: ' . $title . "\n"
                . 'Tipo: ' . $type . "\n"
                . 'Testo: ' . $this->text->cut($content, $limit) . "\n\n";

            $source++;
        }

        return trim($context);
    }

    /**
     * Read helper.
     */
    private function read($material, string $field, string $default): string {
        if (is_array($material) && array_key_exists($field, $material)) {
            return trim((string) $material[$field]);
        }

        if (is_object($material) && isset($material->{$field})) {
            return trim((string) $material->{$field});
        }

        return $default;
    }

    /**
     * Identity key helper.
     */
    private function identity_key($material, string $title, string $type, string $content): string {
        $id = $this->read($material, 'id', '');

        if ($id !== '') {
            return 'id:' . $id;
        }

        if (preg_match('/cm\s*#\s*([0-9]+)\s*\]/i', $title, $matches)) {
            return 'cm:' . (int) $matches[1];
        }

        return strtolower($type) . ':' . md5($this->normalise($title) . "\n" . $this->normalise($content));
    }

    /**
     * Normalise helper.
     */
    private function normalise(string $value): string {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (class_exists('\core_text')) {
            return \core_text::strtolower($value);
        }

        return strtolower($value);
    }
}
