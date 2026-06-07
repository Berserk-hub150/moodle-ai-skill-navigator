<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Returns the Markdown sections used by the XR page.
class xr_sections {

    public function get(bool $usesources): string {
        $sections = "# Titolo\n"
            . "## Obiettivo didattico\n"
            . "## Ambiente virtuale\n"
            . "## Storia dello scenario\n"
            . "## Task dello studente\n"
            . "## Criteri di valutazione\n"
            . "## Competenze coinvolte\n";

        return $usesources ? $sections . "## Fonti usate\n" : $sections . "## Estensioni possibili\n";
    }
}
