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

/**
 * Strategy implementation for Ollama.
 */
class ollama_ai_provider extends abstract_curl_ai_provider {
    /**
     * Get name helper.
     */
    public function get_name(): string {
        return 'ollama';
    }

    /**
     * Generate helper.
     */
    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        $url = $this->ends_with($this->endpoint, '/api/chat') ? $this->endpoint : $this->endpoint . '/api/chat';

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemprompt !== '' ? $systemprompt : $this->default_system_prompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => false,
            'options' => [
                'temperature' => 0.2,
                'num_predict' => $maxtokens,
            ],
        ];

        return $this->post_json_and_extract_answer($url, $payload, ['Content-Type: application/json'], 'ollama');
    }

    /**
     * Ends with helper.
     */
    private function ends_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }
}
