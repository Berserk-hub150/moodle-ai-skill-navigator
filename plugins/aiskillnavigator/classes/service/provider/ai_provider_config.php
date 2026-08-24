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

namespace local_aiskillnavigator\service\provider;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Ai provider config implementation.
 */
class ai_provider_config {
    /** @var string Provider. */
    public string $provider;
    /** @var string Endpoint. */
    public string $endpoint;
    /** @var string Model. */
    public string $model;
    /** @var string Apikey. */
    public string $apikey;
    /** @var string Customrequesttemplate. */
    public string $customrequesttemplate;
    /** @var string Customheadersjson. */
    public string $customheadersjson;
    /** @var string Customresponsepath. */
    public string $customresponsepath;

    /**
     * Construct helper.
     */
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

    /**
     * Default model helper.
     */
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

    /**
     * Default endpoint helper.
     */
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
