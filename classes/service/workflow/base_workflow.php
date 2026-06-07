<?php

namespace local_aiskillnavigator\service\workflow;

use local_aiskillnavigator\service\ai_provider_interface;
use local_aiskillnavigator\service\ai_prompt_builder;

defined('MOODLE_INTERNAL') || die();

// Shared state for workflow classes.
abstract class base_workflow {
    protected ai_provider_interface $provider;
    protected ai_prompt_builder $prompts;

    public function __construct(ai_provider_interface $provider, ai_prompt_builder $prompts) {
        $this->provider = $provider;
        $this->prompts = $prompts;
    }

    protected function fallback(string $value, string $fallback): string {
        $value = trim($value);
        return $value !== '' ? $value : $fallback;
    }
}
