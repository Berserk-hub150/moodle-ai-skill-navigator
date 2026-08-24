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

namespace local_aiskillnavigator\service;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/provider/*.php') as $file) {
    require_once($file);
}

// Base class for providers that call an HTTP JSON API.
/**
 * Abstract curl ai provider implementation.
 */
abstract class abstract_curl_ai_provider implements ai_provider_interface {
    /** @var string Endpoint. */
    protected string $endpoint;
    /** @var string Model. */
    protected string $model;
    /** @var string Apikey. */
    protected string $apikey;

    /**
     * Construct helper.
     */
    public function __construct(string $endpoint, string $model, string $apikey = '') {
        $this->endpoint = rtrim(trim($endpoint), '/');
        $this->model = trim($model);
        $this->apikey = trim($apikey);
    }

    /**
     * Post json and extract answer helper.
     */
    protected function post_json_and_extract_answer(string $url, array $payload, array $headers, string $format): string {
        $client = new provider\http_json_client();
        $reader = new provider\ai_response_reader();
        $cleaner = new provider\model_output_cleaner();

        return $cleaner->clean($reader->answer($client->post($url, $payload, $headers), $format));
    }

    /**
     * Clean model output helper.
     */
    protected function clean_model_output(string $answer): string {
        return (new provider\model_output_cleaner())->clean($answer);
    }

    /**
     * Default system prompt helper.
     */
    protected function default_system_prompt(): string {
        return 'You are a Moodle tutor. Follow the requested format exactly.';
    }
}
