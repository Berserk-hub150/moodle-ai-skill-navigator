<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiskillnavigator\service\embedding;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Embedding config implementation.
 */
class embedding_config {
    /** @var string Provider. */
    public string $provider;
    /** @var string Endpoint. */
    public string $endpoint;
    /** @var string Model. */
    public string $model;
    /** @var string Apikey. */
    public string $apikey;
    /** @var string Requesttemplate. */
    public string $requesttemplate;
    /** @var string Headersjson. */
    public string $headersjson;
    /** @var string Responsepath. */
    public string $responsepath;

    /**
     * Construct helper.
     */
    public function __construct() {
        $chatprovider = strtolower(trim((string) get_config('local_aiskillnavigator', 'provider')));
        $requestedprovider = strtolower(trim((string) get_config('local_aiskillnavigator', 'embeddingprovider')));
        $chatprovider = $chatprovider !== '' ? $chatprovider : 'prototype';

        if ($requestedprovider === '' || $requestedprovider === 'same_as_chat') {
            $embeddingprovider = $this->provider_from_chat($chatprovider);
        } else {
            $embeddingprovider = $requestedprovider;
        }

        if (
            in_array($embeddingprovider, [
            'openrouter',
            'groq',
            'deepseek',
            'mistral',
            'together',
            'fireworks',
            'perplexity',
            'openai_compatible',
            ], true)
        ) {
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

    /**
     * Is keyword only helper.
     */
    public function is_keyword_only(): bool {
        return $this->provider === 'keyword' || $this->endpoint === '';
    }

    /**
     * Uses external service helper.
     */
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

    /**
     * Provider from chat helper.
     */
    private function provider_from_chat(string $chatprovider): string {
        if (in_array($chatprovider, ['ollama', 'local', 'local_ollama'], true)) {
            return 'ollama';
        }

        if ($chatprovider === 'openai') {
            return 'openai';
        }

        // Prototype, Gemini and Anthropic do not use the OpenAI embeddings.
        // Protocol implemented by this plugin. Use deterministic keyword.
        // Fallback instead of silently contacting an unrelated endpoint.
        return 'keyword';
    }

    /**
     * Resolve endpoint helper.
     */
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
