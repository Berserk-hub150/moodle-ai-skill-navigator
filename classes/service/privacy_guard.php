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

require_once(__DIR__ . '/../../includes/production_guard.php');

/**
 * Privacy guard implementation.
 */
class privacy_guard {
    /**
     * Provider helper.
     */
    public static function provider(): string {
        return strtolower(trim((string)get_config('local_aiskillnavigator', 'provider')));
    }

    /**
     * Endpoint helper.
     */
    public static function endpoint(): string {
        $endpoint = trim((string)get_config('local_aiskillnavigator', 'endpoint'));
        return $endpoint !== '' ? $endpoint : 'http://host.docker.internal:11434';
    }

    /**
     * Is local endpoint helper.
     */
    public static function is_local_endpoint(string $endpoint): bool {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            return false;
        }

        $parts = parse_url($endpoint);
        $host = strtolower((string)($parts['host'] ?? ''));

        if ($host === '') {
            return false;
        }

        return in_array($host, [
            'localhost',
            '127.0.0.1',
            '::1',
            'host.docker.internal',
            'ollama',
        ], true);
    }

    /**
     * Is local ollama helper.
     */
    public static function is_local_ollama(): bool {
        return in_array(self::provider(), ['ollama', 'local', 'local_ollama'], true)
            && self::is_local_endpoint(self::endpoint());
    }

    /**
     * Is local provider helper.
     */
    public static function is_local_provider(): bool {
        $provider = self::provider();
        $endpoint = self::endpoint();

        if ($provider === '' || $provider === 'prototype') {
            return true;
        }

        if (in_array($provider, ['ollama', 'local', 'local_ollama'], true)) {
            return true;
        }

        return self::is_local_endpoint($endpoint);
    }

    /**
     * Can use teacher materials with current provider helper.
     */
    public static function can_use_teacher_materials_with_current_provider(): bool {
        if (function_exists('local_aisn_prod_can_use_teacher_materials_with_current_provider')) {
            return local_aisn_prod_can_use_teacher_materials_with_current_provider();
        }
        return self::is_local_provider();
    }

    /**
     * Safe embedding endpoint helper.
     */
    public static function safe_embedding_endpoint(): string {
        $endpoint = self::endpoint();
        if (function_exists('local_aisn_prod_endpoint_is_allowed') && local_aisn_prod_endpoint_is_allowed($endpoint)) {
            return $endpoint;
        }
        return 'http://host.docker.internal:11434';
    }

    /**
     * Teacher materials external block message helper.
     */
    public static function teacher_materials_external_block_message(): string {
        if (function_exists('local_aisn_prod_external_block_message')) {
            return local_aisn_prod_external_block_message();
        }
        return 'Teacher materials are blocked for the current external AI provider.';
    }
}
