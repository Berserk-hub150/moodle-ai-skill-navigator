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
 * Serialise assessment editing and grading against the same stored revision.
 */
class assessment_submission {
    /**
     * Lock an assessment for a short database-only mutation.
     *
     * @param int $assessmentid Assessment ID.
     * @return \core\lock\lock Acquired lock; the caller must release it in finally.
     */
    public static function lock(int $assessmentid): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory('local_aiskillnavigator');
        $lock = $factory->get_lock('assessment_' . $assessmentid, 10);
        if (!$lock) {
            throw new \moodle_exception('assessmentbusy', 'local_aiskillnavigator');
        }
        return $lock;
    }

    /**
     * Grade only the visible, current revision after obtaining the shared lock.
     *
     * The entry point must first check its write capability, POST and sesskey.
     *
     * @param int $courseid Course containing the assessment.
     * @param int $assessmentid Assessment ID.
     * @param string $revision Hash of the quiz shown to the student.
     * @param array $answers Submitted option indices.
     * @return \stdClass Persisted result.
     */
    public static function submit(int $courseid, int $assessmentid, string $revision, array $answers): \stdClass {
        global $DB, $USER;
        $lock = self::lock($assessmentid);
        try {
            $assessment = $DB->get_record('local_aiskillnavigator_assessment', [
                'id' => $assessmentid, 'courseid' => $courseid, 'visible' => 1,
            ], '*', MUST_EXIST);
            if (!hash_equals(hash('sha256', $assessment->quizjson), $revision)) {
                throw new \moodle_exception('assessmentchanged', 'local_aiskillnavigator');
            }
            $quiz = json_decode($assessment->quizjson, true);
            if (empty($quiz['questions']) || !is_array($quiz['questions'])) {
                throw new \moodle_exception('invalidgeneratedquiz', 'local_aiskillnavigator');
            }
            $score = 0;
            $submitted = [];
            foreach (array_values($quiz['questions']) as $index => $question) {
                $correct = $question['correct_index'] ?? null;
                $options = $question['options'] ?? [];
                if (!is_int($correct) || !is_array($options) || !array_key_exists($correct, $options)) {
                    throw new \moodle_exception('invalidgeneratedquiz', 'local_aiskillnavigator');
                }
                $answer = $answers[$index] ?? -1;
                $answer = is_int($answer) && array_key_exists($answer, $options) ? $answer : -1;
                $submitted[$index] = $answer;
                if ($answer === $correct) {
                    $score++;
                }
            }
            $total = count($quiz['questions']);
            $record = (object)[
                'assessmentid' => $assessmentid, 'courseid' => $courseid, 'userid' => (int)$USER->id,
                'score' => $score, 'maxscore' => $total, 'percentage' => (int)round(100 * $score / $total),
                'answersjson' => json_encode($submitted), 'timecreated' => time(),
            ];
            $record->id = $DB->insert_record('local_aiskillnavigator_ass_att', $record);
            return $record;
        } finally {
            $lock->release();
        }
    }
}
