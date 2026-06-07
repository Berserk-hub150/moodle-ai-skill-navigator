<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Keeps the quiz output strict enough for JSON parsing.
class quiz_rules {

    public function format(): string {
        return "Formato:\n"
            . "- Rispondi solo con JSON valido.\n"
            . "- Niente Markdown.\n"
            . "- Niente testo prima o dopo il JSON.\n"
            . "- Esattamente 3 domande.\n"
            . "- Ogni domanda deve avere 4 opzioni.\n"
            . "- Spiegazioni sotto i 180 caratteri.\n\n";
    }

    public function quality(): string {
        return "Domande:\n"
            . "- Evita domande troppo ovvie.\n"
            . "- Le opzioni sbagliate devono sembrare credibili.\n"
            . "- Verifica comprensione, confronto o applicazione.\n"
            . "- La spiegazione deve chiarire la risposta corretta.\n\n";
    }
}
