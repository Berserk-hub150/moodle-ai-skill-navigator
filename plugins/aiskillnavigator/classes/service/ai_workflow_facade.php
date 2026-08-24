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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/workflow/*.php') as $file) {
    require_once($file);
}

// Keeps page code small while workflows stay split by feature.
/**
 * Ai workflow facade implementation.
 */
class ai_workflow_facade {
    /** @var workflow\tutor_workflow Tutor. */
    private workflow\tutor_workflow $tutor;
    /** @var workflow\quiz_workflow Quiz. */
    private workflow\quiz_workflow $quiz;
    /** @var workflow\mindmap_workflow Mindmap. */
    private workflow\mindmap_workflow $mindmap;
    /** @var workflow\xr_workflow Xr. */
    private workflow\xr_workflow $xr;
    /** @var workflow\summary_workflow Summary. */
    private workflow\summary_workflow $summary;

    /**
     * Construct helper.
     */
    public function __construct(ai_provider_interface $provider, ai_prompt_builder $prompts) {
        $this->tutor = new workflow\tutor_workflow($provider, $prompts);
        $this->quiz = new workflow\quiz_workflow($provider, $prompts);
        $this->mindmap = new workflow\mindmap_workflow($provider, $prompts);
        $this->xr = new workflow\xr_workflow($provider, $prompts);
        $this->summary = new workflow\summary_workflow($provider, $prompts);
    }

    /**
     * Ask tutor helper.
     */
    public function ask_tutor(string $q): string {
        return $this->tutor->ask($q);
    }
    /**
     * Ask with course materials helper.
     */
    public function ask_with_course_materials(string $q, array $m): string {
        return $this->tutor->materials($q, $m);
    }
    /**
     * Ask with rag context helper.
     */
    public function ask_with_rag_context(string $q, string $c): string {
        return $this->tutor->rag($q, $c);
    }
    /**
     * Generate quiz helper.
     */
    public function generate_quiz(string $t, string $d): string {
        return $this->quiz->plain($t, $d);
    }
    /**
     * Generate quiz from course materials helper.
     */
    public function generate_quiz_from_course_materials(string $f, string $d, array $m): string {
        return $this->quiz->materials($f, $d, $m);
    }
    /**
     * Generate quiz with rag context helper.
     */
    public function generate_quiz_with_rag_context(string $f, string $d, string $c): string {
        return $this->quiz->rag($f, $d, $c);
    }
    /**
     * Generate mindmap helper.
     */
    public function generate_mindmap(string $t): string {
        return $this->mindmap->plain($t);
    }
    /**
     * Generate mindmap from course materials helper.
     */
    public function generate_mindmap_from_course_materials(string $f, array $m): string {
        return $this->mindmap->materials($f, $m);
    }
    /**
     * Generate mindmap with rag context helper.
     */
    public function generate_mindmap_with_rag_context(string $f, string $c): string {
        return $this->mindmap->rag($f, $c);
    }
    /**
     * Generate xr scenario helper.
     */
    public function generate_xr_scenario(string $t, string $e): string {
        return $this->xr->plain($t, $e);
    }
    /**
     * Generate xr scenario from course materials helper.
     */
    public function generate_xr_scenario_from_course_materials(string $f, string $e, array $m): string {
        return $this->xr->materials($f, $e, $m);
    }
    /**
     * Generate xr scenario with rag context helper.
     */
    public function generate_xr_scenario_with_rag_context(string $f, string $e, string $c): string {
        return $this->xr->rag($f, $e, $c);
    }
    /**
     * Summarize course materials helper.
     */
    public function summarize_course_materials(string $f, array $m): string {
        return $this->summary->materials($f, $m);
    }
    /**
     * Summarize with rag context helper.
     */
    public function summarize_with_rag_context(string $f, string $c): string {
        return $this->summary->rag($f, $c);
    }
}
