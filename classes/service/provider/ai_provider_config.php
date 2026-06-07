<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

class ai_provider_config {
    public string $provider;
    public string $endpoint;
    public string $model;
    public string $apikey;
    public string $customrequesttemplate;
    public string $customheadersjson;
    public string $customresponsepath;

    public function __construct() {
        $this->provider = strtolower(trim((string) get_config('local_aiskillnavigator', 'provider')));
        $this->endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $this->model = trim((string) get_config('local_aiskillnavigator', 'model'));
        $this->apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));
        $this->customrequesttemplate = trim((string) get_config('local_aiskillnavigator', 'customrequesttemplate'));
        $this->customheadersjson = trim((string) get_config('local_aiskillnavigator', 'customheadersjson'));
        $this->customresponsepath = trim((string) get_config('local_aiskillnavigator', 'customresponsepath'));

        if ($this->provider === '') {
            $this->provider = 'prototype';
        }

        if ($this->model === '') {
            $this->model = $this->default_model($this->provider);
        }

        if ($this->endpoint === '') {
            $this->endpoint = $this->default_endpoint($this->provider);
        }

        if ($this->customresponsepath === '') {
            $this->customresponsepath = 'choices.0.message.content';
        }
    }

    private function default_model(string $provider): string {
        $defaults = [
            'prototype' => 'prototype',
            'ollama' => 'qwen2.5:3b',
            'local' => 'qwen2.5:3b',
            'openrouter' => 'deepseek/deepseek-chat',
            'openai' => 'gpt-4o-mini',
            'openai_compatible' => 'default',
            'deepseek' => 'deepseek-chat',
            'groq' => 'llama-3.1-8b-instant',
            'mistral' => 'mistral-small-latest',
            'gemini' => 'gemini-1.5-flash',
            'anthropic' => 'claude-3-5-sonnet-latest',
            'claude' => 'claude-3-5-sonnet-latest',
            'lmstudio' => 'local-model',
            'vllm' => 'local-model',
            'text-generation-webui' => 'local-model',
            'custom_http' => 'default',
        ];

        return $defaults[$provider] ?? 'default';
    }

    private function default_endpoint(string $provider): string {
        $defaults = [
            'ollama' => 'http://host.docker.internal:11434',
            'local' => 'http://host.docker.internal:11434',
            'openrouter' => 'https://openrouter.ai/api/v1',
            'openai' => 'https://api.openai.com/v1',
            'openai_compatible' => '',
            'deepseek' => 'https://api.deepseek.com',
            'groq' => 'https://api.groq.com/openai/v1',
            'mistral' => 'https://api.mistral.ai/v1',
            'together' => 'https://api.together.xyz/v1',
            'fireworks' => 'https://api.fireworks.ai/inference/v1',
            'perplexity' => 'https://api.perplexity.ai',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            'anthropic' => 'https://api.anthropic.com/v1',
            'claude' => 'https://api.anthropic.com/v1',
            'lmstudio' => 'http://host.docker.internal:1234/v1',
            'vllm' => '',
            'text-generation-webui' => '',
            'custom_http' => '',
            'prototype' => '',
        ];

        return $defaults[$provider] ?? '';
    }
}