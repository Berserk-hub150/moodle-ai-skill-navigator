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

foreach (glob(__DIR__ . '/prototype_service/*.php') as $file) {
    require_once($file);
}

// Older demo service kept for compatibility.
/**
 * Prototype ai service implementation.
 */
class prototype_ai_service {
    /**
     * Answer question helper.
     */
    public function answer_question(string $question): array {
        return (new prototype_service\prototype_answer_demo())->get($question);
    }

    /**
     * Generate quiz helper.
     */
    public function generate_quiz(string $topic, string $difficulty): array {
        return (new prototype_service\prototype_quiz_demo())->get($topic, $difficulty);
    }

    /**
     * Generate xr scenario helper.
     */
    public function generate_xr_scenario(string $topic, string $environment): array {
        return (new prototype_service\prototype_xr_demo())->get($topic, $environment);
    }
}
