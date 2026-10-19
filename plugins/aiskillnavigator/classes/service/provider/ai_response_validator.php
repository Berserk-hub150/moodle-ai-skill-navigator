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

namespace local_aiskillnavigator\service\provider;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Validates AI JSON response structures against expected schemas.
 */
class ai_response_validator {
    /**
     * Validate a decoded array or JSON string against a target type ('quiz', 'mindmap', 'summary', 'blueprint', etc.).
     *
     * @param mixed $data Decoded array or JSON string.
     * @param string $type Expected type.
     * @return bool True if valid according to structure.
     */
    public function validate($data, string $type): bool {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return false;
            }
            $data = $decoded;
        }

        if (!is_array($data)) {
            return false;
        }

        $type = strtolower(trim($type));

        if ($type === 'quiz') {
            if (!isset($data['title'], $data['questions']) || !is_array($data['questions']) || count($data['questions']) === 0) {
                return false;
            }
            foreach ($data['questions'] as $q) {
                if (!is_array($q)) {
                    return false;
                }
                $questiontext = trim((string)($q['question'] ?? ''));
                $options = $q['options'] ?? null;

                if ($questiontext === '' || !is_array($options) || count($options) !== 4) {
                    return false;
                }

                $options = array_values($options);
                foreach ($options as $option) {
                    if (!is_string($option) || trim($option) === '') {
                        return false;
                    }
                }

                $correct = $q['correct_index'] ?? null;
                if ($correct !== null) {
                    if (
                        (!is_int($correct) && !(is_string($correct) && preg_match('/^[0-3]$/D', $correct)))
                            || (int)$correct < 0 || (int)$correct > 3
                    ) {
                        return false;
                    }
                } else {
                    $answer = $q['correct'] ?? $q['answer'] ?? $q['correct_answer'] ?? null;
                    if (!is_string($answer) || array_search($answer, $options, true) === false) {
                        return false;
                    }
                }
            }
            return true;
        }

        if ($type === 'mindmap') {
            return isset($data['title'], $data['branches']) && is_array($data['branches']);
        }

        if ($type === 'summary') {
            return isset($data['title'], $data['summary']) && is_string($data['summary']);
        }

        if ($type === 'blueprint' || $type === 'xr_blueprint') {
            return isset($data['title']) && (isset($data['scenes']) || isset($data['steps'])) && (is_array($data['scenes'] ?? null) || is_array($data['steps'] ?? null));
        }

        return false;
    }
}
