<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

class embedding_client {
    private embedding_config $config;

    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

    public function generate(string $text): ?array {
        $text = trim($text);

        if ($text === '') {
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

    private function ollama(string $text): ?array {
        $url = rtrim($this->config->endpoint, '/') . '/api/embeddings';
        $body = (new embedding_http_client())->post($url, ['model' => $this->config->model, 'prompt' => $text], []);

        return isset($body['embedding']) && is_array($body['embedding']) ? $body['embedding'] : null;
    }

    private function openai(string $text): ?array {
        $endpoint = preg_replace('#/v1$#', '', preg_replace('#/embeddings$#', '', preg_replace('#/chat/completions$#', '', rtrim($this->config->endpoint, '/'))));
        $headers = $this->config->apikey !== '' ? ['Authorization: Bearer ' . $this->config->apikey] : [];
        $body = (new embedding_http_client())->post($endpoint . '/v1/embeddings', ['model' => $this->config->model, 'input' => $text], $headers);

        return isset($body['data'][0]['embedding']) && is_array($body['data'][0]['embedding'])
            ? $body['data'][0]['embedding']
            : null;
    }

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

    private function render_template(string $template, array $values): string {
        foreach ($values as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $this->escape_json_string((string) $value), $template);
        }

        return $template;
    }

    private function escape_json_string(string $value): string {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return '';
        }

        return substr($encoded, 1, -1);
    }

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