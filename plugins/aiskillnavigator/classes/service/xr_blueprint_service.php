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

foreach (glob(__DIR__ . '/blueprint/*.php') as $file) {
    require_once($file);
}

// Generates exportable XR blueprints.
/**
 * Xr blueprint service implementation.
 */
class xr_blueprint_service {
    /** @var ai_provider_interface Provider. */
    private ai_provider_interface $provider;
    /** @var blueprint\xr_blueprint_prompt Prompt. */
    private blueprint\xr_blueprint_prompt $prompt;

    /**
     * Construct helper.
     */
    public function __construct(?ai_provider_interface $provider = null) {
        $this->provider = $provider ?? ai_provider_factory::create_from_config();
        $this->prompt = new blueprint\xr_blueprint_prompt();
    }

    /**
     * Generate blueprint helper.
     */
    public function generate_blueprint(string $topic, string $environment): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->provider->generate($this->prompt->build($topic, $environment, ''), 4200, $this->prompt->system());
    }

    /**
     * Generate blueprint from course materials helper.
     */
    public function generate_blueprint_from_course_materials(string $focus, string $environment, array $materials): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';
        $context = empty($materials) ? '' : (new blueprint\blueprint_material_context())->build($materials, 3200);

        return $this->provider->generate($this->prompt->build($focus, $environment, $context), 4500, $this->prompt->system());
    }

    /**
     * Generate blueprint with rag context helper.
     */
    public function generate_blueprint_with_rag_context(string $focus, string $environment, string $context): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->provider->generate($this->prompt->build($focus, $environment, $context), 4500, $this->prompt->system());
    }
}
