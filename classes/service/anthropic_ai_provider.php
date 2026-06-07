<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/abstract_curl_ai_provider.php');
require_once(__DIR__ . '/provider/http_json_client.php');

// Anthropic Claude provider.
class anthropic_ai_provider extends abstract_curl_ai_provider {
    public function get_name(): string {
        return 'anthropic';
    }

    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        if (trim($this->apikey) === '') {
            return 'Errore Anthropic API: API key mancante.';
        }

        $endpoint = $this->endpoint !== '' ? rtrim($this->endpoint, '/') : 'https://api.anthropic.com/v1';
        $url = preg_match('#/messages$#', $endpoint) ? $endpoint : $endpoint . '/messages';

        $payload = [
            'model' => $this->model !== '' ? $this->model : 'claude-3-5-sonnet-latest',
            'max_tokens' => max(1, $maxtokens),
            'temperature' => 0.2,
            'system' => $systemprompt !== '' ? $systemprompt : $this->default_system_prompt(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $client = new provider\http_json_client();

        $response = $client->post($url, $payload, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apikey,
            'anthropic-version: 2023-06-01',
        ], 90);

        if (empty($response['ok'])) {
            return $this->error($response);
        }

        $body = $response['body'] ?? null;

        if (!is_array($body)) {
            return trim((string)($response['raw'] ?? ''));
        }

        if (!empty($body['content']) && is_array($body['content'])) {
            $parts = [];

            foreach ($body['content'] as $part) {
                if (is_array($part) && ($part['type'] ?? '') === 'text') {
                    $text = trim((string)($part['text'] ?? ''));
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
            }

            $answer = trim(implode("\n\n", $parts));

            if ($answer !== '') {
                return $this->clean_model_output($answer);
            }
        }

        return 'Errore Anthropic API: risposta vuota o formato non riconosciuto.';
    }

    private function error(array $response): string {
        $status = (int)($response['status'] ?? 0);
        $raw = trim((string)($response['raw'] ?? ''));
        $error = trim((string)($response['error'] ?? ''));

        if ($raw !== '') {
            return 'Errore Anthropic API HTTP ' . $status . ': ' . $raw;
        }

        if ($error !== '') {
            return 'Errore Anthropic API HTTP ' . $status . ': ' . $error;
        }

        return 'Errore Anthropic API HTTP ' . $status . '.';
    }
}