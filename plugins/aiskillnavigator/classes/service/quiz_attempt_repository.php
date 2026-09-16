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

defined('MOODLE_INTERNAL') || die();

/** Read quiz results without copying Moodle attempts into plugin-owned storage. */
class quiz_attempt_repository {
    /**
     * Return plugin attempts and scored, finished Moodle quiz attempts, newest first.
     *
     * The caller must require the plugin's teacher capability in the course.
     * Native quiz reports additionally require mod/quiz:viewreports per activity.
     *
     * @param int $courseid Course being reported on.
     * @return array Normalised attempts with an explicit source.
     */
    public function for_course(int $courseid): array {
        global $DB, $USER;

        $attempts = array_values($DB->get_records_sql(
            "SELECT a.*, u.firstname, u.lastname, u.email
               FROM {local_aiskillnav_attempt} a
               JOIN {user} u ON u.id = a.userid
              WHERE a.courseid = :courseid AND u.deleted = 0",
            ['courseid' => $courseid]
        ));
        foreach ($attempts as $attempt) {
            $attempt->source = 'AI Skill Navigator';
        }

        $quizconditions = [];
        $params = [];
        foreach (get_fast_modinfo($courseid)->get_instances_of('quiz') as $cm) {
            $context = \context_module::instance($cm->id);
            if (!$cm->uservisible || !has_capability('mod/quiz:viewreports', $context)) {
                continue;
            }
            $key = 'quizid' . (int)$cm->id;
            $condition = 'q.id = :' . $key;
            if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS
                    && !has_capability('moodle/site:accessallgroups', $context)) {
                $groups = groups_get_all_groups($courseid, $USER->id, $cm->groupingid, 'g.id');
                if (!$groups) {
                    continue;
                }
                [$groupsql, $groupparams] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'group' . $key);
                $params += $groupparams;
                $condition .= " AND qa.userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid $groupsql)";
            }
            $params[$key] = (int)$cm->instance;
            $quizconditions[] = '(' . $condition . ')';
        }
        if ($quizconditions) {
            $quizscope = implode(' OR ', $quizconditions);
            $params['courseid'] = $courseid;
            $params['finished'] = 'finished';
            $native = $DB->get_records_sql(
                "SELECT qa.id, qa.userid, qa.quiz, qa.sumgrades AS score,
                        q.sumgrades AS maxscore, q.name AS topic, qa.timefinish AS timecreated,
                        u.firstname, u.lastname, u.email
                   FROM {quiz_attempts} qa
                   JOIN {quiz} q ON q.id = qa.quiz
                   JOIN {user} u ON u.id = qa.userid
                  WHERE q.course = :courseid AND ($quizscope)
                    AND qa.state = :finished AND qa.preview = 0 AND u.deleted = 0
                    AND qa.sumgrades IS NOT NULL AND q.sumgrades > 0",
                $params
            );
            foreach ($native as $attempt) {
                $attempt->percentage = (int)round(100 * (float)$attempt->score / (float)$attempt->maxscore);
                $attempt->difficulty = '';
                $attempt->source = 'Moodle quiz';
                // Append values: both tables can contain the same numeric attempt ID.
                $attempts[] = $attempt;
            }
        }

        usort($attempts, static function ($a, $b): int {
            return ((int)$b->timecreated <=> (int)$a->timecreated)
                ?: strcmp($a->source, $b->source)
                ?: ((int)$b->id <=> (int)$a->id);
        });
        return $attempts;
    }
}
