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

namespace local_aiskillnavigator\service\embedding;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Embedding client implementation.
 */
class embedding_client {
    /** @var embedding_config Config. */
    private embedding_config $config;

    /**
     * Construct helper.
     */
    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

    /**
     * Generate helper.
     */
    public function generate(string $text): ?array {
        $text = trim($text);

        if ($text === '' || $this->config->is_keyword_only()) {
            return null;
        }

        if ($this->config->provider === 'ollama') {
            return $this->ollama($text);
        }

        if ($this->config->provider === 'custom_http') {
            return $this->custom($text);
        }

        return $this->openai($text);
    }

    /**
     * Ollama helper.
     */
    private function ollama(string $text): ?array {
        $url = rtrim($this->config->endpoint, '/') . '/api/embeddings';
        $body = (new embedding_http_client())->post($url, ['model' => $this->config->model, 'prompt' => $text], []);

        return isset($body['embedding']) && is_array($body['embedding']) ? $body['embedding'] : null;
    }

    /**
     * Openai helper.
     */
    private function openai(string $text): ?array {
        $headers = $this->config->apikey !== '' ? ['Authorization: Bearer ' . $this->config->apikey] : [];
        $body = (new embedding_http_client())->post(
            $this->openai_url(),
            ['model' => $this->config->model, 'input' => $text],
            $headers
        );

        return isset($body['data'][0]['embedding']) && is_array($body['data'][0]['embedding'])
            ? $body['data'][0]['embedding']
            : null;
    }

    /**
     * Openai url helper.
     */
    private function openai_url(): string {
        $endpoint = rtrim($this->config->endpoint, '/');
        $endpoint = preg_replace('#/(?:chat/completions|embeddings)$#', '', $endpoint);
        $endpoint = preg_replace('#/v1$#', '', (string)$endpoint);

        return rtrim((string)$endpoint, '/') . '/v1/embeddings';
    }

    /**
     * Custom helper.
     */
    private function custom(string $text): ?array {
        if ($this->config->endpoint === '') {
            return null;
        }

        $template = $this->config->requesttemplate !== ''
            ? $this->config->requesttemplate
            : '{"model":"{{model}}","input":"{{input}}"}';

        $json = $this->render_template($template, [
            'model' => $this->config->model,
            'input' => $text,
            'apikey' => $this->config->apikey,
        ]);

        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            return null;
        }

        $headers = $this->build_headers();
        $body = (new embedding_http_client())->post($this->config->endpoint, $payload, $headers);

        if (!is_array($body)) {
            return null;
        }

        $value = $this->value_by_path($body, $this->config->responsepath);

        if (is_array($value)) {
            return array_values(array_map('floatval', $value));
        }

        return null;
    }

    /**
     * Render template helper.
     */
    private function render_template(string $template, array $values): string {
        foreach ($values as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $this->escape_json_string((string) $value), $template);
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
        $decoded = json_decode($this->config->headersjson, true);

        if (is_array($decoded)) {
            foreach ($decoded as $name => $value) {
                $name = trim((string) $name);
                $value = str_replace('{{apikey}}', $this->config->apikey, (string) $value);

                if ($name !== '' && $value !== '') {
                    $headers[] = $name . ': ' . $value;
                }
            }
        }

        if (empty($headers)) {
            $headers[] = 'Content-Type: application/json';

            if ($this->config->apikey !== '') {
                $headers[] = 'Authorization: Bearer ' . $this->config->apikey;
            }
        }

        return $headers;
    }

    /**
     * Value by path helper.
     */
    private function value_by_path(array $data, string $path) {
        $path = trim($path) !== '' ? trim($path) : 'data.0.embedding';
        $current = $data;

        foreach (explode('.', $path) as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
                continue;
            }

            if (is_array($current) && ctype_digit($part) && array_key_exists((int) $part, $current)) {
                $current = $current[(int) $part];
                continue;
            }

            return null;
        }

        return $current;
    }
}
