<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Builds tutor prompts for questions, materials and RAG text.
class tutor_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 2200;

    public function plain(string $question): string {
        return "Rispondi come tutor di un corso universitario.\n"
            . "Lingua: italiano.\n"
            . "Non fare una premessa lunga. Rispondi alla domanda.\n"
            . "Se la domanda è poco chiara, dichiara l'interpretazione scelta.\n"
            . "Se un dettaglio non lo sai, non inventarlo.\n\n"
            . "Domanda:\n" . trim($question);
    }

    public function with_materials(string $question, array $materials): string {
        return "Rispondi come tutor di un corso universitario.\n"
            . "Usa solo i materiali del docente riportati qui sotto.\n"
            . "Se nei materiali manca qualcosa, dillo chiaramente.\n"
            . "Alla fine aggiungi 'Fonti usate' con i titoli citati.\n\n"
            . "Materiali:\n" . $this->material_context($materials, self::MATERIAL_LIMIT)
            . "Domanda:\n" . trim($question);
    }

    public function with_rag(string $question, string $ragcontext): string {
        return "Rispondi come tutor di un corso universitario.\n"
            . "Usa solo i materiali recuperati qui sotto.\n"
            . "Se non bastano, scrivilo senza inventare il resto.\n"
            . "Alla fine aggiungi 'Fonti usate' con i titoli presenti nel contesto.\n\n"
            . "Materiali recuperati:\n" . trim($ragcontext)
            . "\n\nDomanda:\n" . trim($question);
    }
}
