<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Builds quiz prompts from a topic, materials or RAG text.
class quiz_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 2600;
    private quiz_rules $rules;
    private quiz_schema $schema;

    public function __construct() {
        parent::__construct();
        $this->rules = new quiz_rules();
        $this->schema = new quiz_schema();
    }

    public function plain(string $topic, string $difficulty): string {
        $topic = $this->default_if_empty($topic, 'Digital Twin');
        $difficulty = $this->default_if_empty($difficulty, 'medium');

        return "Prepara un breve test universitario in italiano.\n"
            . "Argomento: {$topic}\nDifficoltà: {$difficulty}\n\n"
            . $this->rules->format() . $this->rules->quality()
            . $this->schema->get($topic, $difficulty);
    }

    public function from_materials(string $focus, string $difficulty, array $materials): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');
        $difficulty = $this->default_if_empty($difficulty, 'medium');

        return "Prepara un breve test usando solo i materiali qui sotto.\n"
            . "Focus: {$topic}\nDifficoltà: {$difficulty}\n\n"
            . "Materiali:\n" . $this->material_context($materials, self::MATERIAL_LIMIT) . "\n"
            . $this->rules->format() . $this->rules->quality()
            . "Nel campo skill scrivi il concetto valutato.\n\n"
            . $this->schema->get($topic, $difficulty);
    }

    public function with_rag(string $focus, string $difficulty, string $ragcontext): string {
        return $this->from_materials($focus, $difficulty, [(object) ['content' => $ragcontext]]);
    }
}
