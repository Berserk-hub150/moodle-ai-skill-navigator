<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/provider/*.php') as $file) {
    require_once($file);
}

// Base class for providers that call an HTTP JSON API.
abstract class abstract_curl_ai_provider implements ai_provider_interface {
    protected string $endpoint;
    protected string $model;
    protected string $apikey;

    public function __construct(string $endpoint, string $model, string $apikey = '') {
        $this->endpoint = rtrim(trim($endpoint), '/');
        $this->model = trim($model);
        $this->apikey = trim($apikey);
    }

    protected function post_json_and_extract_answer(string $url, array $payload, array $headers, string $format): string {
        $client = new provider\http_json_client();
        $reader = new provider\ai_response_reader();
        $cleaner = new provider\model_output_cleaner();

        return $cleaner->clean($reader->answer($client->post($url, $payload, $headers), $format));
    }

    protected function clean_model_output(string $answer): string {
        return (new provider\model_output_cleaner())->clean($answer);
    }

    protected function default_system_prompt(): string {
        return 'You are a Moodle tutor. Follow the requested format exactly.';
    }
}
