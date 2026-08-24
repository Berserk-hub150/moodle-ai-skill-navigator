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

// Demo mind map JSON used by the prototype provider.
/**
 * Prototype mindmap response implementation.
 */
class prototype_mindmap_response {
    /**
     * Get helper.
     */
    public function get(): string {
        return json_encode([
            'title' => 'Mappa AI Skill Navigator',
            'central_topic' => 'AI Skill Navigator',
            'summary' => 'Sintesi dei concetti principali del plugin Moodle.',
            'central_description' => 'Supporta tutor, quiz, RAG e scenari XR.',
            'branches' => [
                $this->branch('Tutor AI', 'Supporta domande dello studente.'),
                $this->branch('Quiz', 'Produce micro-test formativi.'),
                $this->branch('Mind Map', 'Visualizza relazioni tra concetti.'),
                $this->branch('XR', 'Genera scenari per mondi virtuali.'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Branch helper.
     */
    private function branch(string $title, string $description): array {
        return [
            'title' => $title,
            'description' => $description,
            'children' => [
                ['title' => 'Nodo 1', 'description' => 'Primo punto da ripassare.'],
                ['title' => 'Nodo 2', 'description' => 'Secondo punto da ripassare.'],
            ],
        ];
    }
}
