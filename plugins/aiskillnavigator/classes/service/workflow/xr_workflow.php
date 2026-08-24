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

// Runs XR scenario prompts.
/**
 * Xr workflow implementation.
 */
class xr_workflow extends base_workflow {
    /**
     * Plain helper.
     */
    public function plain(string $topic, string $environment): string {
        return $this->provider->generate($this->prompts->xr_scenario_prompt($topic, $environment), 2800);
    }

    /**
     * Materials helper.
     */
    public function materials(string $focus, string $environment, array $materials): string {
        if (empty($materials)) {
            return $this->plain($this->fallback($focus, 'Digital Twin and IoT'), $environment);
        }

        return $this->provider->generate($this->prompts->xr_scenario_from_materials_prompt($focus, $environment, $materials), 3000);
    }

    /**
     * Rag helper.
     */
    public function rag(string $focus, string $environment, string $context): string {
        if (trim($context) === '') {
            return $this->plain($this->fallback($focus, 'Digital Twin and IoT'), $environment);
        }

        return $this->provider->generate($this->prompts->xr_scenario_with_rag_prompt($focus, $environment, $context), 3000);
    }
}
