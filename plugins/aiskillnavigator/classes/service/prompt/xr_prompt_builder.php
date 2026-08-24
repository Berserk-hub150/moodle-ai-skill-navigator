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

namespace local_aiskillnavigator\service\prompt;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();
// Builds XR scenario prompts from a topic, materials or RAG text.
/**
 * Xr prompt builder implementation.
 */
class xr_prompt_builder extends base_prompt_helper {
    private const MATERIAL_LIMIT = 3200;
    /** @var xr_intro Intro. */
    private xr_intro $intro;
    /** @var xr_rules Rules. */
    private xr_rules $rules;
    /** @var xr_sections Sections. */
    private xr_sections $sections;

    /**
     * Construct helper.
     */
    public function __construct() {
        parent::__construct();
        $this->intro = new xr_intro();
        $this->rules = new xr_rules();
        $this->sections = new xr_sections();
    }

    /**
     * Plain helper.
     */
    public function plain(string $topic, string $environment): string {
        $topic = $this->default_if_empty($topic, 'Digital Twin and IoT');
        $environment = $this->default_if_empty($environment, 'Smart Factory');
        return $this->make($topic, $environment, '', false);
    }

    /**
     * From materials helper.
     */
    public function from_materials(string $focus, string $environment, array $materials): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');
        $environment = $this->default_if_empty($environment, 'Smart Factory');
        $context = $this->material_context($materials, self::MATERIAL_LIMIT);

        return $this->make($topic, $environment, $context, true);
    }

    /**
     * With rag helper.
     */
    public function with_rag(string $focus, string $environment, string $ragcontext): string {
        $topic = $this->default_if_empty($focus, 'Materiali del docente');
        $environment = $this->default_if_empty($environment, 'Smart Factory');
        return $this->make($topic, $environment, trim($ragcontext), true);
    }

    /**
     * Make helper.
     */
    private function make(string $topic, string $environment, string $context, bool $usesources): string {
        return $this->intro->get($topic, $environment, $context)
            . $this->plain_style_rules()
            . $this->rules->get($usesources)
            . $this->sections->get($usesources);
    }
}
