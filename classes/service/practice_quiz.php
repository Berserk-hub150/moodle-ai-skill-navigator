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

namespace local_aiskillnavigator\service;

/**
 * Keep generated questions and grading authority in the authenticated session.
 */
class practice_quiz {
    /** @var int Lifetime of an unfinished practice quiz, in seconds. */
    public const LIFETIME = 7200;
    /** @var int Maximum quizzes retained per session, including completed quizzes. */
    public const MAX_QUIZZES = 10;

    /**
     * Validate model output without inventing a missing correct answer.
     *
     * @param array $quiz Generated quiz.
     * @return array|null A bounded, renderable quiz or null when invalid.
     */
    public static function normalise(array $quiz): ?array {
        if (empty($quiz['questions']) || !is_array($quiz['questions']) || count($quiz['questions']) > 3) {
            return null;
        }
        $questions = [];
        foreach (array_values($quiz['questions']) as $question) {
            if (
                !is_array($question) || !is_string($question['question'] ?? null)
                    || trim($question['question']) === '' || !is_array($question['options'] ?? null)
                    || count($question['options']) !== 4
            ) {
                return null;
            }
            $options = array_values($question['options']);
            foreach ($options as $option) {
                if (!is_string($option) || trim($option) === '') {
                    return null;
                }
            }
            $correct = $question['correct_index'] ?? null;
            if ($correct === null) {
                $answer = $question['correct'] ?? $question['answer'] ?? $question['correct_answer'] ?? null;
                $correct = is_string($answer) ? array_search($answer, $options, true) : false;
            }
            if (
                (!is_int($correct) && !(is_string($correct) && preg_match('/^[0-3]$/D', $correct)))
                    || (int)$correct < 0 || (int)$correct > 3
            ) {
                return null;
            }
            foreach (['explanation', 'skill'] as $field) {
                if (isset($question[$field]) && !is_string($question[$field])) {
                    return null;
                }
            }
            $questions[] = [
                'question' => \core_text::substr($question['question'], 0, 4000),
                'options' => array_map(static fn($value) => \core_text::substr($value, 0, 2000), $options),
                'correct_index' => (int)$correct,
                'explanation' => \core_text::substr($question['explanation'] ?? '', 0, 4000),
                'skill' => \core_text::substr($question['skill'] ?? '', 0, 255),
            ];
        }
        foreach (['title', 'topic', 'difficulty'] as $field) {
            if (isset($quiz[$field]) && !is_string($quiz[$field])) {
                return null;
            }
        }
        return [
            'title' => \core_text::substr($quiz['title'] ?? 'Generated AI test', 0, 255),
            'topic' => \core_text::substr($quiz['topic'] ?? 'General', 0, 255),
            'difficulty' => in_array($quiz['difficulty'] ?? '', ['easy', 'medium', 'hard'], true)
                ? $quiz['difficulty'] : 'medium',
            'questions' => $questions,
        ];
    }

    /**
     * Issue an opaque token; the answer key never needs to enter the form.
     *
     * @param int $courseid Owning course.
     * @param array $quiz Generated quiz.
     * @return string Random, session-bound token.
     */
    public static function create(int $courseid, array $quiz): string {
        global $SESSION, $USER;
        $quiz = self::normalise($quiz);
        if ($quiz === null) {
            throw new \moodle_exception('invalidgeneratedquiz', 'local_aiskillnavigator');
        }
        $quizzes = $SESSION->aiskillnavigatorquizzes ?? [];
        foreach ($quizzes as $token => $entry) {
            if ($entry['expires'] < time()) {
                unset($quizzes[$token]);
            }
        }
        while (count($quizzes) >= self::MAX_QUIZZES) {
            array_shift($quizzes);
        }
        $token = bin2hex(random_bytes(32));
        $quizzes[$token] = [
            'courseid' => $courseid,
            'userid' => (int)$USER->id,
            'expires' => time() + self::LIFETIME,
            'quiz' => $quiz,
        ];
        $SESSION->aiskillnavigatorquizzes = $quizzes;
        return $token;
    }

    /**
     * Resolve a token only for the issuing user and course.
     *
     * @param int $courseid Owning course.
     * @param string $token Opaque token.
     * @return array Session state.
     */
    public static function get(int $courseid, string $token): array {
        global $SESSION, $USER;
        $entry = $SESSION->aiskillnavigatorquizzes[$token] ?? null;
        if (
            !$entry || $entry['courseid'] !== $courseid || $entry['userid'] !== (int)$USER->id
                || $entry['expires'] < time()
        ) {
            throw new \moodle_exception('expiredpracticequiz', 'local_aiskillnavigator');
        }
        return $entry;
    }

    /**
     * Grade server-held questions once; refreshing a submission reuses the result.
     *
     * Moodle's session lock serialises submissions for the same authenticated session.
     * Callers must retain that lock and validate the POST request and sesskey.
     *
     * @param int $courseid Owning course.
     * @param string $token Opaque token.
     * @param array $answers Submitted option indices.
     * @return array Quiz, answers and persisted result.
     */
    public static function submit(int $courseid, string $token, array $answers): array {
        global $DB, $SESSION, $USER;
        $entry = self::get($courseid, $token);
        if (isset($entry['attemptid'])) {
            return $entry;
        }
        $score = 0;
        $submitted = [];
        foreach ($entry['quiz']['questions'] as $index => $question) {
            $answer = $answers[$index] ?? -1;
            $answer = is_int($answer) && $answer >= 0 && $answer < 4 ? $answer : -1;
            $submitted[$index] = $answer;
            if ($answer === $question['correct_index']) {
                $score++;
            }
        }
        $total = count($entry['quiz']['questions']);
        $record = (object)[
            'courseid' => $courseid,
            'userid' => (int)$USER->id,
            'topic' => $entry['quiz']['topic'],
            'difficulty' => $entry['quiz']['difficulty'],
            'score' => $score,
            'maxscore' => $total,
            'percentage' => (int)round(100 * $score / $total),
            'quizjson' => json_encode($entry['quiz'], JSON_UNESCAPED_UNICODE),
            'answersjson' => json_encode($submitted),
            'timecreated' => time(),
        ];
        $entry['attemptid'] = $DB->insert_record('local_aiskillnavigator_attempt', $record);
        $entry['answers'] = $submitted;
        $entry['score'] = $score;
        $SESSION->aiskillnavigatorquizzes[$token] = $entry;
        return $entry;
    }
}
