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
// phpcs:ignore moodle.Files.LineLength
foreach (['shared/text_tools.php', 'shared/material_context_builder.php', 'shared/style_notes.php', 'base_prompt_helper.php'] as $file) {
    require_once(__DIR__ . '/prompt/' . $file);
}

$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/prompt'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        require_once((string) $file);
    }
}

// Keeps the old prompt methods while the code is split into smaller files.
/**
 * Ai prompt builder implementation.
 */
class ai_prompt_builder {
    /** @var array Builders. */
    private array $builders;

    /**
     * Construct helper.
     */
    public function __construct() {
        $ns = '\\local_aiskillnavigator\\service\\prompt\\';
        $this->builders = [
            'tutor' => new ($ns . 'tutor_prompt_builder')(),
            'quiz' => new ($ns . 'quiz_prompt_builder')(),
            'mindmap' => new ($ns . 'mindmap_prompt_builder')(),
            'xr' => new ($ns . 'xr_prompt_builder')(),
            'summary' => new ($ns . 'summary_prompt_builder')(),
        ];
    }

    /**
     * Tutor prompt helper.
     */
    public function tutor_prompt(string $question): string {
        return $this->builders['tutor']->plain($question);
    }
    /**
     * Tutor with materials prompt helper.
     */
    public function tutor_with_materials_prompt(string $question, array $materials): string {
        return $this->builders['tutor']->with_materials($question, $materials);
    }
    /**
     * Tutor with rag prompt helper.
     */
    public function tutor_with_rag_prompt(string $question, string $ragcontext): string {
        return $this->builders['tutor']->with_rag($question, $ragcontext);
    }
    /**
     * Quiz prompt helper.
     */
    public function quiz_prompt(string $topic, string $difficulty): string {
        return $this->builders['quiz']->plain($topic, $difficulty);
    }
    /**
     * Quiz from materials prompt helper.
     */
    public function quiz_from_materials_prompt(string $focus, string $difficulty, array $materials): string {
        return $this->builders['quiz']->from_materials($focus, $difficulty, $materials);
    }
    /**
     * Quiz with rag prompt helper.
     */
    public function quiz_with_rag_prompt(string $focus, string $difficulty, string $ragcontext): string {
        return $this->builders['quiz']->with_rag($focus, $difficulty, $ragcontext);
    }
    /**
     * Mindmap prompt helper.
     */
    public function mindmap_prompt(string $topic): string {
        return $this->builders['mindmap']->plain($topic);
    }
    /**
     * Mindmap from materials prompt helper.
     */
    public function mindmap_from_materials_prompt(string $focus, array $materials): string {
        return $this->builders['mindmap']->from_materials($focus, $materials);
    }
    /**
     * Mindmap with rag prompt helper.
     */
    public function mindmap_with_rag_prompt(string $focus, string $ragcontext): string {
        return $this->builders['mindmap']->with_rag($focus, $ragcontext);
    }
    /**
     * Xr scenario prompt helper.
     */
    public function xr_scenario_prompt(string $topic, string $environment): string {
        return $this->builders['xr']->plain($topic, $environment);
    }
    /**
     * Xr scenario from materials prompt helper.
     */
    public function xr_scenario_from_materials_prompt(string $focus, string $environment, array $materials): string {
        return $this->builders['xr']->from_materials($focus, $environment, $materials);
    }
    /**
     * Xr scenario with rag prompt helper.
     */
    public function xr_scenario_with_rag_prompt(string $focus, string $environment, string $ragcontext): string {
        return $this->builders['xr']->with_rag($focus, $environment, $ragcontext);
    }
    /**
     * Summarize materials prompt helper.
     */
    public function summarize_materials_prompt(string $focus, array $materials): string {
        return $this->builders['summary']->from_materials($focus, $materials);
    }
    /**
     * Summarize rag prompt helper.
     */
    public function summarize_rag_prompt(string $focus, string $ragcontext): string {
        return $this->builders['summary']->with_rag($focus, $ragcontext);
    }
}
