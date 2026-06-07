<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Stores the short writing note reused by prose prompts.
class style_notes {

    public function plain(): string {
        return "Stile:\n"
            . "- Scrivi in modo normale, non solenne.\n"
            . "- Vai al punto senza introduzioni lunghe.\n"
            . "- Evita frasi da brochure o da comunicato.\n"
            . "- Evita parole come cruciale, fondamentale, significativo, rivoluzionario, innovativo, panorama, sinergia.\n"
            . "- Non usare emoji.\n"
            . "- Non chiudere con frasi vaghe o celebrative.\n\n";
    }
}
