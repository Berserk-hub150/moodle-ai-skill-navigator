<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Builds the opening part of an XR scenario prompt.
class xr_intro {

    public function get(string $topic, string $environment, string $context): string {
        $prompt = "Prepara uno scenario formativo per un ambiente virtuale.\n\n"
            . "Tema: {$topic}\n"
            . "Ambiente: {$environment}\n\n";

        if (trim($context) !== '') {
            $prompt .= "Materiali da usare:\n"
                . trim($context)
                . "\nUsa solo questi materiali.\n\n";
        }

        return $prompt;
    }
}
