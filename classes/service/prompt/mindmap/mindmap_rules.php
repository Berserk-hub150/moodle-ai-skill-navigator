<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Keeps the mind map output strict enough for JSON parsing.
class mindmap_rules {

    public function format(): string {
        return "Formato:\n"
            . "- Rispondi solo con JSON valido.\n"
            . "- Niente Markdown.\n"
            . "- Esattamente 4 rami principali.\n"
            . "- Ogni ramo deve avere 2 sotto-nodi.\n"
            . "- Titoli brevi, massimo 4 parole.\n\n";
    }

    public function quality(): string {
        return "Nodi:\n"
            . "- Usa titoli concreti.\n"
            . "- Le descrizioni devono sembrare appunti da ripasso.\n"
            . "- Ogni nodo deve aggiungere qualcosa.\n\n";
    }
}
