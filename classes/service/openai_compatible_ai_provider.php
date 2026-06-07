<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Strategy implementation for OpenAI-compatible APIs.
 */
class openai_compatible_ai_provider extends abstract_curl_ai_provider {

    public function get_name(): string {
        return 'openai_compatible';
    }

    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        $url = $this->ends_with($this->endpoint, '/chat/completions')
            ? $this->endpoint
            : $this->endpoint . '/chat/completions';

        $headers = ['Content-Type: application/json'];

        if ($this->apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apikey;
        }

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
            'temperature' => 0.2,
            'max_tokens' => $maxtokens,
        ];

        return $this->post_json_and_extract_answer($url, $payload, $headers, 'openai');
    }

    private function ends_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }
}
