<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Builds mind map prompts from a topic, materials or RAG text.
class mindmap_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 2600;
    private mindmap_rules $rules;
    private mindmap_schema $schema;

    public function __construct() {
        parent::__construct();
        $this->rules = new mindmap_rules();
        $this->schema = new mindmap_schema();
    }

    public function plain(string $topic): string {
        $topic = $this->default_if_empty($topic, 'Digital Twin');

        return "Crea una mappa mentale in italiano.\n"
            . "Tema centrale: {$topic}\n\n"
            . $this->rules->format() . $this->rules->quality()
            . $this->schema->get($topic);
    }

    public function from_materials(string $focus, array $materials): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');

        return "Crea una mappa mentale usando solo i materiali qui sotto.\n"
            . "Tema centrale: {$topic}\n\n"
            . "Materiali:\n" . $this->material_context($materials, self::MATERIAL_LIMIT) . "\n"
            . $this->rules->format() . $this->rules->quality()
            . $this->schema->get($topic);
    }

    public function with_rag(string $focus, string $ragcontext): string {
        return $this->from_materials($focus, [(object) ['content' => $ragcontext]]);
    }
}
