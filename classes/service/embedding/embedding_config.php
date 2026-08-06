<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

class embedding_config {
    public string $provider;
    public string $endpoint;
    public string $model;
    public string $apikey;
    public string $requesttemplate;
    public string $headersjson;
    public string $responsepath;

    public function __construct() {
        $chatprovider = strtolower(trim((string) get_config('local_aiskillnavigator', 'provider')));
        $requestedprovider = strtolower(trim((string) get_config('local_aiskillnavigator', 'embeddingprovider')));
        $chatprovider = $chatprovider !== '' ? $chatprovider : 'prototype';

        if ($requestedprovider === '' || $requestedprovider === 'same_as_chat') {
            $embeddingprovider = $this->provider_from_chat($chatprovider);
        } else {
            $embeddingprovider = $requestedprovider;
        }

        if (in_array($embeddingprovider, [
            'openrouter',
            'groq',
            'deepseek',
            'mistral',
            'together',
            'fireworks',
            'perplexity',
            'openai_compatible',
        ], true)) {
            $embeddingprovider = 'openai';
        }

        $chatendpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $embeddingendpoint = trim((string) get_config('local_aiskillnavigator', 'embeddingendpoint'));

        $this->provider = in_array($embeddingprovider, ['keyword', 'ollama', 'openai', 'custom_http'], true)
            ? $embeddingprovider
            : 'keyword';
        $this->endpoint = $this->resolve_endpoint(
            $this->provider,
            $requestedprovider,
            $chatprovider,
            $chatendpoint,
            $embeddingendpoint
        );
        $this->model = trim((string) get_config('local_aiskillnavigator', 'embeddingmodel'));
        $this->apikey = trim((string) get_config('local_aiskillnavigator', 'embeddingapikey'));
        $this->requesttemplate = trim((string) get_config('local_aiskillnavigator', 'embeddingrequesttemplate'));
        $this->headersjson = trim((string) get_config('local_aiskillnavigator', 'embeddingheadersjson'));
        $this->responsepath = trim((string) get_config('local_aiskillnavigator', 'embeddingresponsepath'));

        if ($this->apikey === '') {
            $this->apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));
        }

        if ($this->model === '') {
            if ($this->provider === 'ollama') {
                $this->model = 'nomic-embed-text';
            } else if ($this->provider === 'openai') {
                $this->model = 'text-embedding-3-small';
            } else {
                $this->model = 'keyword';
            }
        }

        if ($this->responsepath === '') {
            $this->responsepath = 'data.0.embedding';
        }
    }

    public function is_keyword_only(): bool {
        return $this->provider === 'keyword' || $this->endpoint === '';
    }

    public function uses_external_service(): bool {
        if ($this->is_keyword_only()) {
            return false;
        }

        $parts = parse_url($this->endpoint);
        $host = strtolower((string)($parts['host'] ?? ''));

        if ($host === '') {
            return false;
        }

        return !in_array($host, [
            'localhost',
            '127.0.0.1',
            '::1',
            'host.docker.internal',
            'ollama',
        ], true) && !str_ends_with($host, '.local');
    }

    private function provider_from_chat(string $chatprovider): string {
        if (in_array($chatprovider, ['ollama', 'local', 'local_ollama'], true)) {
            return 'ollama';
        }

        if ($chatprovider === 'openai') {
            return 'openai';
        }

        // Prototype, Gemini and Anthropic do not use the OpenAI embeddings
        // protocol implemented by this plugin. Use deterministic keyword
        // fallback instead of silently contacting an unrelated endpoint.
        return 'keyword';
    }

    private function resolve_endpoint(
        string $provider,
        string $requestedprovider,
        string $chatprovider,
        string $chatendpoint,
        string $embeddingendpoint
    ): string {
        if ($provider === 'keyword') {
            return '';
        }

        if ($embeddingendpoint !== '') {
            return $embeddingendpoint;
        }

        if ($provider === 'custom_http') {
            return '';
        }

        if ($provider === 'ollama') {
            return in_array($chatprovider, ['ollama', 'local', 'local_ollama'], true) && $chatendpoint !== ''
                ? $chatendpoint
                : 'http://host.docker.internal:11434';
        }

        if ($provider === 'openai') {
            if (($requestedprovider === '' || $requestedprovider === 'same_as_chat') && $chatprovider === 'openai') {
                return $chatendpoint !== '' ? $chatendpoint : 'https://api.openai.com/v1';
            }

            return $chatendpoint !== '' && $chatprovider === 'openai'
                ? $chatendpoint
                : 'https://api.openai.com/v1';
        }

        return '';
    }
}
