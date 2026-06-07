<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Production safety guard for AI Skill Navigator.
 */
if (!function_exists('local_aisn_prod_bool_config')) {
    function local_aisn_prod_bool_config(string $name, bool $default = false): bool {
        $value = get_config('local_aiskillnavigator', $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('local_aisn_prod_provider')) {
    function local_aisn_prod_provider(): string {
        return strtolower(trim((string)get_config('local_aiskillnavigator', 'provider')));
    }
}

if (!function_exists('local_aisn_prod_endpoint')) {
    function local_aisn_prod_endpoint(): string {
        return trim((string)get_config('local_aiskillnavigator', 'endpoint'));
    }
}

if (!function_exists('local_aisn_prod_is_local_host')) {
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
    function local_aisn_prod_external_ai_globally_enabled(): bool {
        return local_aisn_prod_bool_config('externalaiapproved', false);
    }
}

if (!function_exists('local_aisn_prod_material_external_flag')) {
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
    function local_aisn_prod_can_send_material_to_current_ai(stdClass $material): bool {
        if (local_aisn_prod_current_ai_is_local()) {
            return true;
        }
        return local_aisn_prod_external_ai_globally_enabled()
            && local_aisn_prod_material_external_flag($material);
    }
}

if (!function_exists('local_aisn_prod_can_use_teacher_materials_with_current_provider')) {
    function local_aisn_prod_can_use_teacher_materials_with_current_provider(): bool {
        return local_aisn_prod_current_ai_is_local() || local_aisn_prod_external_ai_globally_enabled();
    }
}

if (!function_exists('local_aisn_prod_external_block_message')) {
    function local_aisn_prod_external_block_message(): string {
        return 'External AI use is not approved for this site. Use a local/prototype provider, or ask a site administrator to enable external AI and mark each material as allowed.';
    }
}

if (!function_exists('local_aisn_prod_endpoint_is_allowed')) {
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
    function local_aisn_prod_course_builder_destructive_enabled(): bool {
        return local_aisn_prod_bool_config('allowdestructivecoursebuilder', false);
    }
}

if (!function_exists('local_aisn_prod_course_builder_action_allowed')) {
    function local_aisn_prod_course_builder_action_allowed(string $action): bool {
        $action = strtolower(trim($action));
        if (local_aisn_prod_course_builder_destructive_enabled()) {
            return true;
        }
        return in_array($action, ['create_section', 'attach_files'], true);
    }
}

if (!function_exists('local_aisn_prod_clean_request_text')) {
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