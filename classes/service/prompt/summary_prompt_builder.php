<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Builds summary prompts for uploaded materials or RAG text.
class summary_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 3000;

    public function from_materials(string $focus, array $materials): string {
        return $this->base($focus)
            . "\nMateriali:\n"
            . $this->material_context($materials, self::MATERIAL_LIMIT);
    }

    public function with_rag(string $focus, string $ragcontext): string {
        return $this->base($focus) . "\nMateriali:\n" . trim($ragcontext);
    }

    private function base(string $focus): string {
        $prompt = "Riassumi questi materiali in italiano.\n"
            . "Usa solo il contenuto fornito.\n"
            . "Scrivi come appunti per studiare: chiari, pratici, senza frasi decorative.\n";

        return trim($focus) !== '' ? $prompt . "Focus: " . trim($focus) . "\n" : $prompt;
    }
}
