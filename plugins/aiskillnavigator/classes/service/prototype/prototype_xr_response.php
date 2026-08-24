<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI Skill Navigator plugin file.
 *
 * @package    local_aiskillnavigator
 * @copyright  2026 Luca Magrini
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiskillnavigator\service\prototype;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

// Demo XR scenario used by the prototype provider.
/**
 * Prototype xr response implementation.
 */
class prototype_xr_response {
    /**
     * Get helper.
     */
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
