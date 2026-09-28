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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Browser steps for the plugin's real course pages.
 *
 * @package local_aiskillnavigator
 * @copyright 2026 Luca Magrini
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_aiskillnavigator extends behat_base {
    /**
     * Select a deterministic provider without credentials or external calls.
     *
     * @Given /^the AI provider is in prototype mode$/
     */
    public function the_ai_provider_is_in_prototype_mode(): void {
        set_config('provider', 'prototype', 'local_aiskillnavigator');
    }

    /**
     * Open a tool using the fixture course's actual ID.
     *
     * @When /^I open AI Skill Navigator "([a-z_]+)" for course "([^"]*)"$/
     * @param string $page Tool page.
     * @param string $shortname Course shortname.
     */
    public function i_open_ai_skill_navigator(string $page, string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $path = '/local/aiskillnavigator/pages/' . $page . '.php?courseid=' . $courseid;
        $this->getSession()->visit($this->locate_path($path));
    }

    /**
     * Verify persistence after a browser submission.
     *
     * @Then /^the AI practice attempt count for course "([^"]*)" is "([0-9]+)"$/
     * @param string $shortname Course shortname.
     * @param int $expected Expected attempts.
     */
    public function the_ai_practice_attempt_count(string $shortname, int $expected): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        \PHPUnit\Framework\Assert::assertEquals(
            $expected,
            $DB->count_records('local_aiskillnavigator_attempt', ['courseid' => $courseid])
        );
    }

    /**
     * Verify the browser is never given a hidden answer-key payload.
     *
     * @Then /^the AI quiz form has no client answer key$/
     */
    public function the_ai_quiz_form_has_no_client_answer_key(): void {
        \PHPUnit\Framework\Assert::assertNull($this->getSession()->getPage()->find('css', 'input[name="quizdata"]'));
        \PHPUnit\Framework\Assert::assertNotNull($this->getSession()->getPage()->find('css', 'input[name="quiztoken"]'));
    }
}
