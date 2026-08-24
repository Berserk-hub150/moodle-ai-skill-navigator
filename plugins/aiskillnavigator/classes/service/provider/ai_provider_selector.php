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

use local_aiskillnavigator\service\anthropic_ai_provider;
use local_aiskillnavigator\service\custom_http_ai_provider;
use local_aiskillnavigator\service\deepseek_ai_provider;
use local_aiskillnavigator\service\gemini_ai_provider;
use local_aiskillnavigator\service\ollama_ai_provider;
use local_aiskillnavigator\service\openai_compatible_ai_provider;
use local_aiskillnavigator\service\prototype_ai_provider;
use local_aiskillnavigator\service\ai_provider_interface;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../anthropic_ai_provider.php');
require_once(__DIR__ . '/../custom_http_ai_provider.php');
require_once(__DIR__ . '/../deepseek_ai_provider.php');
require_once(__DIR__ . '/../gemini_ai_provider.php');
require_once(__DIR__ . '/../ollama_ai_provider.php');
require_once(__DIR__ . '/../openai_compatible_ai_provider.php');
require_once(__DIR__ . '/../prototype_ai_provider.php');

/**
 * Ai provider selector implementation.
 */
class ai_provider_selector {
    /**
     * Create helper.
     */
    public function create(ai_provider_config $config): ai_provider_interface {
        $provider = strtolower(trim($config->provider));

        if ($provider === '' || $provider === 'prototype') {
            return new prototype_ai_provider();
        }

        if ($provider === 'custom_http') {
            return $config->endpoint === ''
                ? new prototype_ai_provider()
                : new custom_http_ai_provider(
                    $config->endpoint,
                    $config->model ?: 'default',
                    $config->apikey,
                    $config->customrequesttemplate,
                    $config->customheadersjson,
                    $config->customresponsepath
                );
        }

        if ($provider === 'anthropic' || $provider === 'claude') {
            return $config->apikey === ''
                ? new prototype_ai_provider()
                : new anthropic_ai_provider(
                    $config->endpoint ?: 'https://api.anthropic.com/v1',
                    $config->model ?: 'claude-3-5-sonnet-latest',
                    $config->apikey
                );
        }

        if ($provider === 'gemini' || $provider === 'google') {
            return $config->apikey === ''
                ? new prototype_ai_provider()
                : new gemini_ai_provider(
                    $config->endpoint ?: 'https://generativelanguage.googleapis.com/v1beta',
                    $config->model ?: 'gemini-1.5-flash',
                    $config->apikey
                );
        }

        if ($provider === 'deepseek') {
            return $config->apikey === ''
                ? new prototype_ai_provider()
                : new deepseek_ai_provider(
                    $config->endpoint ?: 'https://api.deepseek.com',
                    $config->model ?: 'deepseek-chat',
                    $config->apikey
                );
        }

        if ($provider === 'ollama' || $provider === 'local' || $provider === 'local_ollama') {
            return new ollama_ai_provider(
                $config->endpoint ?: 'http://host.docker.internal:11434',
                $config->model ?: 'qwen2.5:3b'
            );
        }

        if (
            in_array($provider, [
            'openai',
            'openrouter',
            'openai_compatible',
            'groq',
            'mistral',
            'together',
            'fireworks',
            'perplexity',
            'lmstudio',
            'vllm',
            'text-generation-webui',
            ], true)
        ) {
            if ($config->endpoint === '') {
                return new prototype_ai_provider();
            }

            if (!in_array($provider, ['lmstudio', 'vllm', 'text-generation-webui'], true) && $config->apikey === '') {
                return new prototype_ai_provider();
            }

            return new openai_compatible_ai_provider(
                $config->endpoint,
                $config->model ?: 'default',
                $config->apikey
            );
        }

        return new prototype_ai_provider();
    }
}
