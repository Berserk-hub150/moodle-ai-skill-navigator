<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

class ai_response_reader {
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