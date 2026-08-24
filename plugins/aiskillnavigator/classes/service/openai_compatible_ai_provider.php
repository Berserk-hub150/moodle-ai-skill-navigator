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
 * Strategy implementation for OpenAI-compatible APIs.
 */
class openai_compatible_ai_provider extends abstract_curl_ai_provider {
    /**
     * Get name helper.
     */
    public function get_name(): string {
        return 'openai_compatible';
    }

    /**
     * Generate helper.
     */
    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        $baseurl = trim((string)$this->endpoint);

        if ($baseurl === '') {
            // phpcs:ignore moodle.Files.LineLength
            return 'AI provider endpoint is not configured. Set an endpoint such as https://openrouter.ai/api/v1, https://api.openai.com/v1, https://api.groq.com/openai/v1, or use Prototype/Ollama.';
        }

        $baseurl = rtrim($baseurl, '/');

        $url = $this->ends_with($baseurl, '/chat/completions')
            ? $baseurl
            : $baseurl . '/chat/completions';

        $headers = ['Content-Type: application/json'];

        if ($this->apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apikey;
        }

        $payload = [
            'model' => $this->model !== '' ? $this->model : 'default',
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
            'temperature' => 0.2,
            'max_tokens' => $maxtokens,
        ];

        return $this->post_json_and_extract_answer($url, $payload, $headers, 'openai');
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
