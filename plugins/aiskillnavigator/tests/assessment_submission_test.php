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

namespace local_aiskillnavigator;

use local_aiskillnavigator\service\assessment_submission;

/**
 * Assessment results must correspond to the exact published question revision.
 *
 * @covers \local_aiskillnavigator\service\assessment_submission
 */
final class assessment_submission_test extends \advanced_testcase {
    /**
     * Store a real assessment for the current test user and course.
     *
     * @return \stdClass Assessment record.
     */
    private function assessment(): \stdClass {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $record = (object)[
            'courseid' => $course->id, 'userid' => $USER->id, 'title' => 'Diagnostic', 'focus' => 'Databases',
            'quizjson' => json_encode(['questions' => [[
                'question' => 'Choose C', 'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 2,
            ]]]),
            'visible' => 1,
        ];
        $record->id = $DB->insert_record('local_aiskillnavigator_assessment', $record);
        return $record;
    }

    /**
     * Grading uses the database answer and ignores option indices outside the question.
     */
    public function test_grading_uses_current_stored_answer(): void {
        $assessment = $this->assessment();
        $revision = hash('sha256', $assessment->quizjson);
        $correct = assessment_submission::submit($assessment->courseid, $assessment->id, $revision, [2]);
        $this->assertEquals(100, $correct->percentage);
        $invalid = assessment_submission::submit($assessment->courseid, $assessment->id, $revision, [-1]);
        $this->assertEquals(0, $invalid->score);
        $this->assertSame('[-1]', $invalid->answersjson);
    }

    /**
     * Editing while a learner answers must not record a result against the new questions.
     */
    public function test_stale_revision_does_not_insert_result(): void {
        global $DB;
        $assessment = $this->assessment();
        $revision = hash('sha256', $assessment->quizjson);
        $assessment->quizjson = str_replace('Choose C', 'New question', $assessment->quizjson);
        $DB->update_record('local_aiskillnavigator_assessment', $assessment);
        try {
            assessment_submission::submit($assessment->courseid, $assessment->id, $revision, [2]);
            $this->fail('A stale assessment must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('assessmentchanged', $e->errorcode);
            $this->assertEquals(0, $DB->count_records('local_aiskillnavigator_ass_att'));
        }
        // A fresh form can still submit; the exception must have released the lock.
        $result = assessment_submission::submit(
            $assessment->courseid,
            $assessment->id,
            hash('sha256', $assessment->quizjson),
            [2]
        );
        $this->assertEquals(1, $result->score);
    }

    /**
     * Recheck publication and owning course while holding the lock.
     */
    public function test_hidden_and_other_course_assessments_are_rejected(): void {
        global $DB;
        $assessment = $this->assessment();
        $revision = hash('sha256', $assessment->quizjson);
        foreach ([$assessment->courseid + 1, $assessment->courseid] as $courseid) {
            if ($courseid === $assessment->courseid) {
                $DB->set_field('local_aiskillnavigator_assessment', 'visible', 0, ['id' => $assessment->id]);
            }
            try {
                assessment_submission::submit($courseid, $assessment->id, $revision, [2]);
                $this->fail('An unavailable assessment must be rejected.');
            } catch (\dml_missing_record_exception $e) {
                $this->assertEquals(0, $DB->count_records('local_aiskillnavigator_ass_att'));
            }
        }
    }
}
