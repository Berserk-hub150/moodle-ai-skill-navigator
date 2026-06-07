<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class gemini_ai_provider extends abstract_curl_ai_provider {
    public function get_name(): string {
        return 'gemini';
    }

    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        if ($this->apikey === '') {
            return 'Gemini API key missing. Configure it in AI Skill Navigator settings.';
        }

        $endpoint = rtrim($this->endpoint, '/');
        if ($endpoint === '') {
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta';
        }

        $model = $this->model !== '' ? $this->model : 'gemini-1.5-flash';
        $url = $endpoint . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($this->apikey);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => $maxtokens,
            ],
        ];

        if ($systemprompt !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemprompt],
                ],
            ];
        }

        return $this->post_json_and_extract_answer(
            $url,
            $payload,
            ['Content-Type: application/json'],
            'gemini'
        );
    }
}