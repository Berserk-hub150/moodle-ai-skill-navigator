<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Builds XR scenario prompts from a topic, materials or RAG text.
class xr_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 3200;
    private xr_intro $intro;
    private xr_rules $rules;
    private xr_sections $sections;

    public function __construct() {
        parent::__construct();
        $this->intro = new xr_intro();
        $this->rules = new xr_rules();
        $this->sections = new xr_sections();
    }

    public function plain(string $topic, string $environment): string {
        $topic = $this->default_if_empty($topic, 'Digital Twin and IoT');
        $environment = $this->default_if_empty($environment, 'Smart Factory');
        return $this->make($topic, $environment, '', false);
    }

    public function from_materials(string $focus, string $environment, array $materials): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');
        $environment = $this->default_if_empty($environment, 'Smart Factory');
        $context = $this->material_context($materials, self::MATERIAL_LIMIT);

        return $this->make($topic, $environment, $context, true);
    }

    public function with_rag(string $focus, string $environment, string $ragcontext): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');
        $environment = $this->default_if_empty($environment, 'Smart Factory');
        return $this->make($topic, $environment, trim($ragcontext), true);
    }

    private function make(string $topic, string $environment, string $context, bool $usesources): string {
        return $this->intro->get($topic, $environment, $context)
            . $this->plain_style_rules()
            . $this->rules->get($usesources)
            . $this->sections->get($usesources);
    }
}
