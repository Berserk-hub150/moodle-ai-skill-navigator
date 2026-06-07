<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../includes/production_guard.php');

class privacy_guard {
    public static function provider(): string {
        return strtolower(trim((string)get_config('local_aiskillnavigator', 'provider')));
    }

    public static function endpoint(): string {
        $endpoint = trim((string)get_config('local_aiskillnavigator', 'endpoint'));
        return $endpoint !== '' ? $endpoint : 'http://host.docker.internal:11434';
    }

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

    public static function is_local_ollama(): bool {
        return in_array(self::provider(), ['ollama', 'local', 'local_ollama'], true)
            && self::is_local_endpoint(self::endpoint());
    }

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

    public static function can_use_teacher_materials_with_current_provider(): bool {
        if (function_exists('local_aisn_prod_can_use_teacher_materials_with_current_provider')) {
            return local_aisn_prod_can_use_teacher_materials_with_current_provider();
        }
        return self::is_local_provider();
    }

    public static function safe_embedding_endpoint(): string {
        $endpoint = self::endpoint();
        if (function_exists('local_aisn_prod_endpoint_is_allowed') && local_aisn_prod_endpoint_is_allowed($endpoint)) {
            return $endpoint;
        }
        return 'http://host.docker.internal:11434';
    }

    public static function teacher_materials_external_block_message(): string {
        if (function_exists('local_aisn_prod_external_block_message')) {
            return local_aisn_prod_external_block_message();
        }
        return 'Teacher materials are blocked for the current external AI provider.';
    }
}