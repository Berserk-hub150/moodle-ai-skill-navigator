<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Cleans short strings before they are used in prompts.
class text_tools {

    public function fallback(string $value, string $default): string {
        $value = trim($value);
        return $value !== '' ? $value : $default;
    }

    public function clean(string $text): string {
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    public function cut(string $text, int $limit): string {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '...' : $text;
        }

        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }
}
