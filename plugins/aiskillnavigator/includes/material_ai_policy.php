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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/production_guard.php');

/**
 * Local aiskillnavigator current ai is local helper.
 */
function local_aiskillnavigator_current_ai_is_local(): bool {
    return function_exists('local_aisn_prod_current_ai_is_local')
        ? local_aisn_prod_current_ai_is_local()
        : true;
}

/**
 * Local aiskillnavigator material external allowed helper.
 *
 * @param stdClass $material Material.
 */
function local_aiskillnavigator_material_external_allowed(stdClass $material): bool {
    if (isset($material->externalaiallowed)) {
        return ((int)$material->externalaiallowed) === 1;
    }

    if (isset($material->aipolicy)) {
        return ((string)$material->aipolicy) === 'external_allowed';
    }

    return false;
}

/**
 * Local aiskillnavigator material can be sent to current ai helper.
 *
 * @param stdClass $material Material.
 */
function local_aiskillnavigator_material_can_be_sent_to_current_ai(stdClass $material): bool {
    if (function_exists('local_aisn_prod_can_send_material_to_current_ai')) {
        return local_aisn_prod_can_send_material_to_current_ai($material);
    }
    if (local_aiskillnavigator_current_ai_is_local()) {
        return true;
    }
    return local_aiskillnavigator_material_external_allowed($material);
}

/**
 * Local aiskillnavigator filter materials for current ai helper.
 *
 * @param array $materials Course materials used for this operation.
 */
function local_aiskillnavigator_filter_materials_for_current_ai(array $materials): array {
    $filtered = [];

    foreach ($materials as $key => $material) {
        if (local_aiskillnavigator_material_can_be_sent_to_current_ai($material)) {
            $filtered[$key] = $material;
        }
    }

    return $filtered;
}

/**
 * Local aiskillnavigator ai policy label helper.
 *
 * @param stdClass $material Material.
 */
function local_aiskillnavigator_ai_policy_label(stdClass $material): string {
    return local_aiskillnavigator_material_external_allowed($material)
        ? 'Allowed for external AI'
        : 'Local AI only';
}

/**
 * Local aiskillnavigator ai policy badge class helper.
 *
 * @param stdClass $material Material.
 */
function local_aiskillnavigator_ai_policy_badge_class(stdClass $material): string {
    return local_aiskillnavigator_material_external_allowed($material)
        ? 'badge badge-success'
        : 'badge badge-secondary';
}

/**
 * Local aiskillnavigator set material ai policy helper.
 *
 * @param int $materialid Stored material ID.
 * @param int $courseid Moodle course ID.
 * @param bool $externalallowed Externalallowed.
 */
function local_aiskillnavigator_set_material_ai_policy(int $materialid, int $courseid, bool $externalallowed): bool {
    global $DB;

    $material = $DB->get_record('local_aiskillnavigator_material', [
        'id' => $materialid,
        'courseid' => $courseid,
    ]);

    if (!$material) {
        return false;
    }

    $material->externalaiallowed = $externalallowed ? 1 : 0;
    $material->aipolicy = $externalallowed ? 'external_allowed' : 'local_only';
    $material->timemodified = time();

    $DB->update_record('local_aiskillnavigator_material', $material);

    // Sync identifies resources by sourcecmid; titles may have been renamed.
    $cmid = (int)($material->sourcecmid ?? 0);
    if ($cmid <= 0 && preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', (string)$material->title, $matches)) {
        $cmid = (int)$matches[1];
    }
    if ($cmid > 0 && ($material->materialtype ?? '') === 'course_resource') {
        set_config('cm_external_ai_' . $cmid, $externalallowed ? '1' : '0', 'local_aiskillnavigator');
    }

    return true;
}

/**
 * Local aiskillnavigator provider privacy notice helper.
 */
function local_aiskillnavigator_provider_privacy_notice(): string {
    if (local_aiskillnavigator_current_ai_is_local()) {
        return 'Current AI provider is local/prototype: course materials can be used without sending them to an external provider.';
    }
    if (function_exists('local_aisn_prod_external_ai_globally_enabled') && !local_aisn_prod_external_ai_globally_enabled()) {
        return 'Current AI provider is external, but external AI is not globally approved by the site administrator.';
    }
    return 'Current AI provider is external: only materials explicitly allowed by the teacher can be sent to the AI provider.';
}
