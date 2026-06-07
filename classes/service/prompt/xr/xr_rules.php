<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Keeps XR scenarios practical enough for a demo.
class xr_rules {

    public function get(bool $usesources): string {
        $rules = "Scenario:\n"
            . "- Deve essere concreto.\n"
            . "- I task dello studente devono essere almeno 5.\n"
            . "- I criteri di valutazione devono essere almeno 4.\n"
            . "- Ogni task deve descrivere qualcosa che lo studente fa davvero.\n"
            . "- Descrivi cosa vede, cosa controlla e quale decisione prende.\n"
            . "- Evita frasi generiche tipo esperienza immersiva innovativa.\n\n";

        return $usesources ? $rules . "- Alla fine indica le fonti usate.\n\n" : $rules;
    }
}
