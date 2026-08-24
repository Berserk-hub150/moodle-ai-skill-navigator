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

namespace local_aiskillnavigator\service\workflow;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Runs quiz prompts.
/**
 * Quiz workflow implementation.
 */
class quiz_workflow extends base_workflow {
    /**
     * Plain helper.
     */
    public function plain(string $topic, string $difficulty): string {
        return $this->provider->generate($this->prompts->quiz_prompt($topic, $difficulty), 2200);
    }

    /**
     * Materials helper.
     */
    public function materials(string $focus, string $difficulty, array $materials): string {
        if (empty($materials)) {
            return $this->plain($this->fallback($focus, 'Course materials'), $difficulty);
        }

        return $this->provider->generate($this->prompts->quiz_from_materials_prompt($focus, $difficulty, $materials), 2400);
    }

    /**
     * Rag helper.
     */
    public function rag(string $focus, string $difficulty, string $context): string {
        if (trim($context) === '') {
            return $this->plain($this->fallback($focus, 'Course materials'), $difficulty);
        }

        return $this->provider->generate($this->prompts->quiz_with_rag_prompt($focus, $difficulty, $context), 2400);
    }
}
