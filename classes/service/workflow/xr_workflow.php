<?php

namespace local_aiskillnavigator\service\workflow;

defined('MOODLE_INTERNAL') || die();

// Runs XR scenario prompts.
class xr_workflow extends base_workflow {
    public function plain(string $topic, string $environment): string {
        return $this->provider->generate($this->prompts->xr_scenario_prompt($topic, $environment), 2800);
    }

    public function materials(string $focus, string $environment, array $materials): string {
        if (empty($materials)) {
            return $this->plain($this->fallback($focus, 'Digital Twin and IoT'), $environment);
        }

        return $this->provider->generate($this->prompts->xr_scenario_from_materials_prompt($focus, $environment, $materials), 3000);
    }

    public function rag(string $focus, string $environment, string $context): string {
        if (trim($context) === '') {
            return $this->plain($this->fallback($focus, 'Digital Twin and IoT'), $environment);
        }

        return $this->provider->generate($this->prompts->xr_scenario_with_rag_prompt($focus, $environment, $context), 3000);
    }
}
