<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/prototype/*.php') as $file) {
    require_once($file);
}

// Demo provider used when no external AI is configured.
class prototype_ai_provider implements ai_provider_interface {
    public function get_name(): string {
        return 'prototype';
    }

    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        return (new prototype\prototype_output_router())->route($prompt);
    }
}
