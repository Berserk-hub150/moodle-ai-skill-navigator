<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * DeepSeek provider.
 *
 * DeepSeek is exposed as a dedicated provider name, while internally it uses
 * the OpenAI-compatible chat completions strategy.
 */
class deepseek_ai_provider extends openai_compatible_ai_provider {

    public function get_name(): string {
        return 'deepseek';
    }
}
