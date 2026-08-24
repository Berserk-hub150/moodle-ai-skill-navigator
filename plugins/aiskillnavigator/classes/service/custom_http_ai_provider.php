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

require_once(__DIR__ . '/provider/http_json_client.php');

/**
 * Custom http ai provider implementation.
 */
class custom_http_ai_provider implements ai_provider_interface {
    /** @var string Endpoint. */
    private string $endpoint;
    /** @var string Model. */
    private string $model;
    /** @var string Apikey. */
    private string $apikey;
    /** @var string Requesttemplate. */
    private string $requesttemplate;
    /** @var string Headersjson. */
    private string $headersjson;
    /** @var string Responsepath. */
    private string $responsepath;

    /**
     * Construct helper.
     */
    public function __construct(
        string $endpoint,
        string $model,
        string $apikey,
        string $requesttemplate,
        string $headersjson,
        string $responsepath
    ) {
        $this->endpoint = trim($endpoint);
        $this->model = trim($model);
        $this->apikey = trim($apikey);
        $this->requesttemplate = trim($requesttemplate);
        $this->headersjson = trim($headersjson);
        $this->responsepath = trim($responsepath);
    }

    /**
     * Get name helper.
     */
    public function get_name(): string {
        return 'custom_http';
    }

    /**
     * Generate helper.
     */
    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        if ($this->endpoint === '') {
            return 'Errore Custom HTTP API: endpoint mancante.';
        }

        $systemprompt = $systemprompt !== '' ? $systemprompt : 'You are a Moodle tutor. Follow the requested format exactly.';
        $template = $this->requesttemplate !== '' ? $this->requesttemplate : $this->default_template();

        $json = $this->render_template($template, [
            'model' => $this->model !== '' ? $this->model : 'default',
            'system' => $systemprompt,
            'prompt' => $prompt,
            'max_tokens' => (string)$maxtokens,
            'apikey' => $this->apikey,
        ]);

        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            return 'Errore Custom HTTP API: request template non produce JSON valido. JSON error: ' . json_last_error_msg();
        }

        $client = new provider\http_json_client();
        $response = $client->post($this->endpoint, $payload, $this->build_headers(), 75);

        if ($this->responsepath === '_raw') {
            $raw = trim((string)($response['raw'] ?? ''));
            return $raw !== '' ? $raw : 'Errore Custom HTTP API: risposta raw vuota.';
        }

        if (empty($response['ok'])) {
            return $this->error($response);
        }

        $body = $response['body'] ?? null;

        if (!is_array($body)) {
            return trim((string)($response['raw'] ?? ''));
        }

        $path = $this->responsepath !== '' ? $this->responsepath : 'choices.0.message.content';
        $answer = $this->value_by_path($body, $path);

        if (is_array($answer)) {
            return json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $answer = trim((string)$answer);
        return $answer !== '' ? $answer : 'Errore Custom HTTP API: response path vuoto o non trovato.';
    }

    /**
     * Default template helper.
     */
    private function default_template(): string {
        return '{
          "model": "{{model}}",
          "messages": [
            {"role": "system", "content": "{{system}}"},
            {"role": "user", "content": "{{prompt}}"}
          ],
          "temperature": 0.2,
          "max_tokens": {{max_tokens}}
        }';
    }

    /**
     * Render template helper.
     */
    private function render_template(string $template, array $values): string {
        foreach ($values as $key => $value) {
            $replacement = $key === 'max_tokens'
                ? (string)((int)$value)
                : $this->escape_json_string((string)$value);

            $template = str_replace('{{' . $key . '}}', $replacement, $template);
        }

        return $template;
    }

    /**
     * Escape json string helper.
     */
    private function escape_json_string(string $value): string {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return '';
        }

        return substr($encoded, 1, -1);
    }

    /**
     * Build headers helper.
     */
    private function build_headers(): array {
        $headers = [];
        $decoded = json_decode($this->headersjson, true);

        if (is_array($decoded)) {
            foreach ($decoded as $name => $value) {
                $name = trim((string)$name);
                $value = str_replace('{{apikey}}', $this->apikey, (string)$value);

                if ($name !== '' && $value !== '') {
                    $headers[] = $name . ': ' . $value;
                }
            }
        }

        if (empty($headers)) {
            $headers[] = 'Content-Type: application/json';

            if ($this->apikey !== '') {
                $headers[] = 'Authorization: Bearer ' . $this->apikey;
            }
        }

        return $headers;
    }

    /**
     * Value by path helper.
     */
    private function value_by_path(array $data, string $path) {
        $current = $data;

        foreach (explode('.', $path) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
                continue;
            }

            if (is_array($current) && ctype_digit($part) && array_key_exists((int)$part, $current)) {
                $current = $current[(int)$part];
                continue;
            }

            return null;
        }

        return $current;
    }

    /**
     * Error helper.
     */
    private function error(array $response): string {
        $status = (int)($response['status'] ?? 0);
        $body = $response['body'] ?? null;
        $raw = trim((string)($response['raw'] ?? ''));
        $error = trim((string)($response['error'] ?? ''));

        if (is_array($body)) {
            return 'Errore Custom HTTP API HTTP ' . $status . ': ' .
                substr(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 900);
        }

        if ($error !== '') {
            return 'Errore Custom HTTP API HTTP ' . $status . ': ' . substr($error, 0, 900);
        }

        return 'Errore Custom HTTP API HTTP ' . $status . ': ' . substr($raw, 0, 900);
    }
}
