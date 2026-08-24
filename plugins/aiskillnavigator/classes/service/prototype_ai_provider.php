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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/prototype/*.php') as $file) {
    require_once($file);
}

// Demo provider used when no external AI is configured.
/**
 * Prototype ai provider implementation.
 */
class prototype_ai_provider implements ai_provider_interface {
    /**
     * Get name helper.
     */
    public function get_name(): string {
        return 'prototype';
    }

    /**
     * Generate helper.
     */
    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        return (new prototype\prototype_output_router())->route($prompt);
    }
}
