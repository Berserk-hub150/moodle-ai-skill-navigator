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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Production safety guard for AI Skill Navigator.
 */
if (!function_exists('local_aisn_prod_bool_config')) {
    /**
     * Local aisn prod bool config helper.
     */
    function local_aisn_prod_bool_config(string $name, bool $default = false): bool {
        $value = get_config('local_aiskillnavigator', $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('local_aisn_prod_provider')) {
    /**
     * Local aisn prod provider helper.
     */
    function local_aisn_prod_provider(): string {
        return strtolower(trim((string)get_config('local_aiskillnavigator', 'provider')));
    }
}

if (!function_exists('local_aisn_prod_endpoint')) {
    /**
     * Local aisn prod endpoint helper.
     */
    function local_aisn_prod_endpoint(): string {
        return trim((string)get_config('local_aiskillnavigator', 'endpoint'));
    }
}

if (!function_exists('local_aisn_prod_is_local_host')) {
    /**
     * Local aisn prod is local host helper.
     */
    function local_aisn_prod_is_local_host(string $host): bool {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1', 'host.docker.internal', 'ollama'], true)) {
            return true;
        }
        return (bool)preg_match('/(^|\.)local$/', $host);
    }
}

if (!function_exists('local_aisn_prod_endpoint_is_local')) {
    /**
     * Local aisn prod endpoint is local helper.
     */
    function local_aisn_prod_endpoint_is_local(string $endpoint): bool {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return false;
        }
        $parts = parse_url($endpoint);
        return local_aisn_prod_is_local_host((string)($parts['host'] ?? ''));
    }
}

if (!function_exists('local_aisn_prod_current_ai_is_local')) {
    /**
     * Local aisn prod current ai is local helper.
     */
    function local_aisn_prod_current_ai_is_local(): bool {
        $provider = local_aisn_prod_provider();
        if ($provider === '' || $provider === 'prototype') {
            return true;
        }
        if (in_array($provider, ['ollama', 'local', 'local_ollama'], true)) {
            return true;
        }
        return local_aisn_prod_endpoint_is_local(local_aisn_prod_endpoint());
    }
}

if (!function_exists('local_aisn_prod_external_ai_globally_enabled')) {
    /**
     * Local aisn prod external ai globally enabled helper.
     */
    function local_aisn_prod_external_ai_globally_enabled(): bool {
        return local_aisn_prod_bool_config('externalaiapproved', false);
    }
}

if (!function_exists('local_aisn_prod_material_external_flag')) {
    /**
     * Local aisn prod material external flag helper.
     */
    function local_aisn_prod_material_external_flag(stdClass $material): bool {
        if (isset($material->externalaiallowed)) {
            return ((int)$material->externalaiallowed) === 1;
        }
        if (isset($material->aipolicy)) {
            return ((string)$material->aipolicy) === 'external_allowed';
        }
        return false;
    }
}

if (!function_exists('local_aisn_prod_can_send_material_to_current_ai')) {
    /**
     * Local aisn prod can send material to current ai helper.
     */
    function local_aisn_prod_can_send_material_to_current_ai(stdClass $material): bool {
        if (local_aisn_prod_current_ai_is_local()) {
            return true;
        }
        return local_aisn_prod_external_ai_globally_enabled()
            && local_aisn_prod_material_external_flag($material);
    }
}

if (!function_exists('local_aisn_prod_can_use_teacher_materials_with_current_provider')) {
    /**
     * Local aisn prod can use teacher materials with current provider helper.
     */
    function local_aisn_prod_can_use_teacher_materials_with_current_provider(): bool {
        return local_aisn_prod_current_ai_is_local() || local_aisn_prod_external_ai_globally_enabled();
    }
}

if (!function_exists('local_aisn_prod_external_block_message')) {
    /**
     * Local aisn prod external block message helper.
     */
    function local_aisn_prod_external_block_message(): string {
        // phpcs:ignore moodle.Files.LineLength
        return 'External AI use is not approved for this site. Use a local/prototype provider, or ask a site administrator to enable external AI and mark each material as allowed.';
    }
}

if (!function_exists('local_aisn_prod_endpoint_is_allowed')) {
    /**
     * Local aisn prod endpoint is allowed helper.
     */
    function local_aisn_prod_endpoint_is_allowed(string $endpoint): bool {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return true;
        }
        if (local_aisn_prod_endpoint_is_local($endpoint)) {
            return true;
        }
        $parts = parse_url($endpoint);
        return strtolower((string)($parts['scheme'] ?? '')) === 'https';
    }
}

if (!function_exists('local_aisn_prod_course_builder_destructive_enabled')) {
    /**
     * Local aisn prod course builder destructive enabled helper.
     */
    function local_aisn_prod_course_builder_destructive_enabled(): bool {
        return local_aisn_prod_bool_config('allowdestructivecoursebuilder', false);
    }
}

if (!function_exists('local_aisn_prod_course_builder_action_allowed')) {
    /**
     * Local aisn prod course builder action allowed helper.
     */
    function local_aisn_prod_course_builder_action_allowed(string $action): bool {
        $action = strtolower(trim($action));

        $safeactions = [
            'create_section',
            'attach_files',
        ];

        if (in_array($action, $safeactions, true)) {
            return true;
        }

        $destructiveactions = [
            'rename_section',
            'update_section_html',
            'update_summary',
            'delete_section',
            'delete_all_sections',
            'clear_section_zero',
            'clear_section_content',
            'hide_section',
            'show_section',
            'duplicate_section',
            'move_section',
            'delete_material',
            'move_material',
            'rename_material',
            'hide_material',
            'show_material',
            'set_material_visibility',
        ];

        if (in_array($action, $destructiveactions, true)) {
            return local_aisn_prod_course_builder_destructive_enabled();
        }

        return false;
    }
}
if (!function_exists('local_aisn_prod_clean_request_text')) {
    /**
     * Local aisn prod clean request text helper.
     */
    function local_aisn_prod_clean_request_text(string $text, int $maxchars = 12000): string {
        $text = trim($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        if (core_text::strlen($text) > $maxchars) {
            $text = core_text::substr($text, 0, $maxchars);
        }
        return trim($text);
    }
}
