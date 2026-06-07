<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Returns the JSON example used by quiz prompts.
class quiz_schema {

    public function get(string $topic, string $difficulty): string {
        return "Schema JSON:\n"
            . "{\n"
            . "\"title\":\"Titolo del test\",\n"
            . "\"topic\":\"{$topic}\",\n"
            . "\"difficulty\":\"{$difficulty}\",\n"
            . "\"questions\":[{\"question\":\"Testo domanda\",\"options\":[\"A\",\"B\",\"C\",\"D\"],\"correct_index\":0,\"explanation\":\"Spiegazione\",\"skill\":\"Concetto\"}]\n"
            . "}";
    }
}
