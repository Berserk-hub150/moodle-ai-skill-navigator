<?php

namespace local_aiskillnavigator\service\prototype;

defined('MOODLE_INTERNAL') || die();

// Demo XR scenario used by the prototype provider.
class prototype_xr_response {
    public function get(): string {
        return "# Scenario dimostrativo\n\n"
            . "## Obiettivo didattico\nComprendere il rapporto tra IoT, dati e Digital Twin.\n\n"
            . "## Ambiente virtuale\nSmart factory con sensori, dashboard e macchine interattive.\n\n"
            . "## Task dello studente\n"
            . "1. Identificare i sensori.\n"
            . "2. Analizzare i dati.\n"
            . "3. Trovare l'anomalia.\n"
            . "4. Aggiornare il Digital Twin.\n"
            . "5. Rispondere al quiz finale.\n\n"
            . "## Criteri di valutazione\n"
            . "- Correttezza dell'analisi.\n"
            . "- Uso dei dati.\n"
            . "- Motivazione della scelta.\n"
            . "- Comprensione del modello digitale.";
    }
}
