<?php

defined('MOODLE_INTERNAL') || die();

function local_aiskillnavigator_call_ai_from_page(string $prompt, string $systemprompt = '', int $maxtokens = 2200): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate($prompt, $maxtokens, $systemprompt);
        }
    } catch (Throwable $e) {
        return 'AI generation error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}