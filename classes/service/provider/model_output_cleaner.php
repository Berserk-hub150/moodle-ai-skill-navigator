<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

// Removes simple Markdown fences from model output.
class model_output_cleaner {
    public function clean(string $text): string {
        $text = trim($text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        if (preg_match('/^```[a-zA-Z0-9_-]*\s*(.*?)\s*```$/s', $text, $matches)) {
            return trim((string) $matches[1]);
        }

        if (substr($text, 0, 3) === '```') {
            $text = trim(substr($text, 3));
            $text = preg_replace('/^[a-zA-Z0-9_-]+\s*\n/', '', $text);
        }

        if (substr($text, -3) === '```') {
            $text = trim(substr($text, 0, -3));
        }

        return trim($text);
    }
}
