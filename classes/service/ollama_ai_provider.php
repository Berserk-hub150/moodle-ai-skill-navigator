<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Strategy implementation for Ollama.
 */
class ollama_ai_provider extends abstract_curl_ai_provider {

    public function get_name(): string {
        return 'ollama';
    }

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

    private function ends_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }
}
