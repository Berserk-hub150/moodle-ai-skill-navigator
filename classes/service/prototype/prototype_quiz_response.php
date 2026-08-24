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

// Demo quiz JSON used when no external model is configured.
/**
 * Prototype quiz response implementation.
 */
class prototype_quiz_response {
    /**
     * Get helper.
     */
    public function get(): string {
        return json_encode([
            'title' => 'Micro-test dimostrativo',
            'topic' => 'AI, IoT e Digital Twin',
            'difficulty' => 'medium',
            'questions' => [
                // phpcs:ignore moodle.Files.LineLength
                $this->question('Qual è il ruolo principale dell IoT in un Digital Twin?', 'Fornire dati dal sistema fisico', 'IoT e Digital Twin'),
                // phpcs:ignore moodle.Files.LineLength
                $this->question('Perché un tutor AI può essere utile in un LMS?', 'Supportare studio e recupero personalizzato', 'AI per apprendimento'),
                // phpcs:ignore moodle.Files.LineLength
                $this->question('Che vantaggio dà il RAG rispetto a una risposta generica?', 'Usa materiali del corso come contesto', 'RAG'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Question helper.
     */
    private function question(string $text, string $answer, string $skill): array {
        return [
            'question' => $text,
            'options' => [$answer, 'Sostituire il docente', 'Ignorare i materiali', 'Disabilitare la valutazione'],
            'correct_index' => 0,
            'explanation' => 'La risposta corretta collega il concetto al corso.',
            'skill' => $skill,
        ];
    }
}
