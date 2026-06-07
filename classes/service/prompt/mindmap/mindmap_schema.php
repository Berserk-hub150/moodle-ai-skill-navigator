<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Returns the JSON example used by mind map prompts.
class mindmap_schema {

    public function get(string $topic): string {
        return "Schema JSON:\n"
            . "{\n"
            . "\"title\":\"Titolo corto\",\n"
            . "\"central_topic\":\"{$topic}\",\n"
            . "\"summary\":\"Sintesi breve\",\n"
            . "\"central_description\":\"Descrizione centrale\",\n"
            . "\"branches\":[{\"title\":\"Ramo\",\"description\":\"Descrizione\",\"children\":[{\"title\":\"Nodo 1\",\"description\":\"Descrizione\"},{\"title\":\"Nodo 2\",\"description\":\"Descrizione\"}]}]\n"
            . "}";
    }
}
