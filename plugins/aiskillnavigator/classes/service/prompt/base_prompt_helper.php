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
/**
 * Base prompt helper implementation.
 */
abstract class base_prompt_helper {
    /** @var text_tools Text. */
    private text_tools $text;
    /** @var material_context_builder Materials. */
    private material_context_builder $materials;
    /** @var style_notes Style. */
    private style_notes $style;

    /**
     * Construct helper.
     */
    public function __construct() {
        $this->text = new text_tools();
        $this->materials = new material_context_builder($this->text);
        $this->style = new style_notes();
    }

    /**
     * Default if empty helper.
     */
    protected function default_if_empty(string $value, string $default): string {
        return $this->text->fallback($value, $default);
    }

    /**
     * Material context helper.
     */
    protected function material_context(array $materials, int $limit): string {
        return $this->materials->build($materials, $limit);
    }

    /**
     * Plain style rules helper.
     */
    protected function plain_style_rules(): string {
        return $this->style->plain();
    }
}
