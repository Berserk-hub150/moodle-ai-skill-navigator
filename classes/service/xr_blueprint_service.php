<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/blueprint/*.php') as $file) {
    require_once($file);
}

// Generates exportable XR blueprints.
class xr_blueprint_service {
    private ai_provider_interface $provider;
    private blueprint\xr_blueprint_prompt $prompt;

    public function __construct(?ai_provider_interface $provider = null) {
        $this->provider = $provider ?? ai_provider_factory::create_from_config();
        $this->prompt = new blueprint\xr_blueprint_prompt();
    }

    public function generate_blueprint(string $topic, string $environment): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->provider->generate($this->prompt->build($topic, $environment, ''), 4200, $this->prompt->system());
    }

    public function generate_blueprint_from_course_materials(string $focus, string $environment, array $materials): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';
        $context = empty($materials) ? '' : (new blueprint\blueprint_material_context())->build($materials, 3200);

        return $this->provider->generate($this->prompt->build($focus, $environment, $context), 4500, $this->prompt->system());
    }

    public function generate_blueprint_with_rag_context(string $focus, string $environment, string $context): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->provider->generate($this->prompt->build($focus, $environment, $context), 4500, $this->prompt->system());
    }
}
