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

use local_aiskillnavigator\service\ai_provider_interface;
use local_aiskillnavigator\service\ai_prompt_builder;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Shared state for workflow classes.
/**
 * Base workflow implementation.
 */
abstract class base_workflow {
    /** @var ai_provider_interface Provider. */
    protected ai_provider_interface $provider;
    /** @var ai_prompt_builder Prompts. */
    protected ai_prompt_builder $prompts;

    /**
     * Construct helper.
     */
    public function __construct(ai_provider_interface $provider, ai_prompt_builder $prompts) {
        $this->provider = $provider;
        $this->prompts = $prompts;
    }

    /**
     * Fallback helper.
     */
    protected function fallback(string $value, string $fallback): string {
        $value = trim($value);
        return $value !== '' ? $value : $fallback;
    }
}
