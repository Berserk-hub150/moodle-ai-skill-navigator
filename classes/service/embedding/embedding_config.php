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
        $embeddingprovider = strtolower(trim((string) get_config('local_aiskillnavigator', 'embeddingprovider')));

        if ($embeddingprovider === '' || $embeddingprovider === 'same_as_chat') {
            $embeddingprovider = $chatprovider !== '' ? $chatprovider : 'ollama';
        }

        if (in_array($embeddingprovider, ['openrouter', 'groq', 'deepseek', 'openai_compatible'], true)) {
            $embeddingprovider = 'openai';
        }

        $chatendpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $embeddingendpoint = trim((string) get_config('local_aiskillnavigator', 'embeddingendpoint'));

        $this->provider = $embeddingprovider !== '' ? $embeddingprovider : 'ollama';
        $this->endpoint = $embeddingendpoint !== '' ? $embeddingendpoint : ($chatendpoint !== '' ? $chatendpoint : 'http://host.docker.internal:11434');
        $this->model = trim((string) get_config('local_aiskillnavigator', 'embeddingmodel'));
        $this->apikey = trim((string) get_config('local_aiskillnavigator', 'embeddingapikey'));
        $this->requesttemplate = trim((string) get_config('local_aiskillnavigator', 'embeddingrequesttemplate'));
        $this->headersjson = trim((string) get_config('local_aiskillnavigator', 'embeddingheadersjson'));
        $this->responsepath = trim((string) get_config('local_aiskillnavigator', 'embeddingresponsepath'));

        if ($this->apikey === '') {
            $this->apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));
        }

        if ($this->model === '') {
            $this->model = $this->provider === 'ollama' ? 'nomic-embed-text' : 'text-embedding-3-small';
        }

        if ($this->responsepath === '') {
            $this->responsepath = 'data.0.embedding';
        }
    }
}