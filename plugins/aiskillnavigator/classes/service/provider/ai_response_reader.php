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

namespace local_aiskillnavigator\service\provider;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Ai response reader implementation.
 */
class ai_response_reader {
    /**
     * Answer helper.
     */
    public function answer(array $response, string $format): string {
        if (empty($response['ok'])) {
            return $this->error($response);
        }

        $body = $response['body'] ?? null;

        if (!is_array($body)) {
            return 'Errore AI API: risposta vuota o non JSON.';
        }

        $format = strtolower(trim($format));
        $answer = '';

        if ($format === 'ollama') {
            $answer = trim((string)($body['message']['content'] ?? $body['response'] ?? ''));
        } else if ($format === 'gemini') {
            $answer = trim((string)($body['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        } else {
            $answer = trim((string)(
                $body['choices'][0]['message']['content']
                ?? $body['choices'][0]['text']
                ?? $body['content'][0]['text']
                ?? $body['message']['content']
                ?? ''
            ));
        }

        if ($answer !== '') {
            return $answer;
        }

        return 'Errore AI API: risposta valida ma contenuto mancante. Raw: ' .
            substr(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 700);
    }

    /**
     * Error helper.
     */
    private function error(array $response): string {
        $status = (int)($response['status'] ?? 0);
        $body = $response['body'] ?? null;
        $raw = (string)($response['raw'] ?? '');
        $curlerror = trim((string)($response['error'] ?? ''));

        if (is_array($body)) {
            $message = $body['error']['message']
                ?? $body['error']
                ?? $body['message']
                ?? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return 'Errore AI API HTTP ' . $status . ': ' . substr((string)$message, 0, 700);
        }

        if ($curlerror !== '') {
            return 'Errore AI API/cURL HTTP ' . $status . ': ' . substr($curlerror, 0, 700);
        }

        if ($raw !== '') {
            return 'Errore AI API: risposta non JSON. HTTP status ' . $status . '. Raw: ' . substr($raw, 0, 700);
        }

        return 'Errore AI API: nessuna risposta. HTTP status ' . $status . '.';
    }
}
