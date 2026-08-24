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

/**
 * Adds an AI privacy checkbox to Moodle activity/resource edit forms.
 */

function local_aiskillnavigator_ai_policy_supported_modnames(): array {
    return [
        'resource',
        'page',
        'folder',
        'url',
        'book',
        'label',
    ];
}

/**
 * Local aiskillnavigator ai policy current modname helper.
 */
function local_aiskillnavigator_ai_policy_current_modname($formwrapper): string {
    $modname = '';

    try {
        if (is_object($formwrapper) && method_exists($formwrapper, 'get_current')) {
            $current = $formwrapper->get_current();

            if (!empty($current->modname)) {
                $modname = (string)$current->modname;
            }
        }
    } catch (Throwable $e) {
        $modname = '';
    }

    if ($modname === '') {
        $modname = optional_param('add', '', PARAM_ALPHANUMEXT);
    }

    if ($modname === '') {
        $cmid = optional_param('update', 0, PARAM_INT);

        if ($cmid > 0) {
            $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);

            if ($cm && !empty($cm->modname)) {
                $modname = (string)$cm->modname;
            }
        }
    }

    return $modname;
}

/**
 * Local aiskillnavigator coursemodule standard elements helper.
 */
function local_aiskillnavigator_coursemodule_standard_elements($formwrapper, $mform): void {
    $modname = local_aiskillnavigator_ai_policy_current_modname($formwrapper);

    if (!in_array($modname, local_aiskillnavigator_ai_policy_supported_modnames(), true)) {
        return;
    }

    if (
        method_exists($mform, 'elementExists') &&
        $mform->elementExists('local_aiskillnavigator_external_ai')
    ) {
        return;
    }

    $cmid = optional_param('update', 0, PARAM_INT);
    $default = 0;

    if ($cmid > 0) {
        $stored = get_config('local_aiskillnavigator', 'cm_external_ai_' . $cmid);
        $default = ((string)$stored === '1') ? 1 : 0;
    }

    $mform->addElement('header', 'local_aiskillnavigator_ai_header', 'AI Skill Navigator');

    $mform->addElement(
        'advcheckbox',
        'local_aiskillnavigator_external_ai',
        'AI access policy',
        'Allow this material to be used with external AI providers. If unchecked, it remains usable only with local/prototype AI.',
        null,
        [0, 1]
    );

    $mform->setDefault('local_aiskillnavigator_external_ai', $default);
}

/**
 * Local aiskillnavigator coursemodule edit post actions helper.
 */
function local_aiskillnavigator_coursemodule_edit_post_actions($data, $course) {
    global $CFG, $USER;

    if (empty($data->coursemodule) || empty($course->id)) {
        return $data;
    }

    $modname = trim((string)($data->modulename ?? $data->modname ?? ''));

    if ($modname === '') {
        $cm = get_coursemodule_from_id('', (int)$data->coursemodule, 0, false, IGNORE_MISSING);
        $modname = $cm && !empty($cm->modname) ? (string)$cm->modname : '';
    }

    if ($modname === '' || !in_array($modname, local_aiskillnavigator_ai_policy_supported_modnames(), true)) {
        return $data;
    }

    $cmid = (int)$data->coursemodule;
    $allowed = !empty($data->local_aiskillnavigator_external_ai);

    set_config(
        'cm_external_ai_' . $cmid,
        $allowed ? '1' : '0',
        'local_aiskillnavigator'
    );

    $syncfile = $CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php';

    $autosyncenabled = (string)get_config('local_aiskillnavigator', 'autosynccourseresources') === '1';

    if ($autosyncenabled && file_exists($syncfile)) {
        require_once($syncfile);

        if (function_exists('local_aiskillnavigator_sync_course_resources')) {
            local_aiskillnavigator_sync_course_resources(
                (int)$course->id,
                !empty($USER->id) ? (int)$USER->id : 0,
                true
            );
        }
    }

    local_aiskillnavigator_apply_cm_ai_policy_to_material(
        (int)$course->id,
        $cmid,
        $allowed
    );

    return $data;
}

/**
 * Local aiskillnavigator apply cm ai policy to material helper.
 */
function local_aiskillnavigator_apply_cm_ai_policy_to_material(
    int $courseid,
    int $cmid,
    bool $externalallowed
): void {
    global $DB;

    if ($courseid <= 1 || $cmid <= 0) {
        return;
    }

    if (!$DB->get_manager()->table_exists(new xmldb_table('local_aiskillnav_material'))) {
        return;
    }

    $materials = $DB->get_records('local_aiskillnav_material', [
        'courseid' => $courseid,
        'materialtype' => 'course_resource',
    ]);

    $prefix = '[Course #' . $courseid . ' / cm #' . $cmid . ']';

    foreach ($materials as $material) {
        $title = (string)($material->title ?? '');

        $sourcecmid = isset($material->sourcecmid) ? (int)$material->sourcecmid : 0;

        if ($sourcecmid !== $cmid && !str_starts_with($title, $prefix)) {
            continue;
        }

        $material->externalaiallowed = $externalallowed ? 1 : 0;
        $material->aipolicy = $externalallowed ? 'external_allowed' : 'local_only';
        $material->timemodified = time();

        $DB->update_record('local_aiskillnav_material', $material);
    }
}

/**
 * AISN_RESTORE_OLD_BLOCK_UI_V9
 *
 * No floating OCR card and no custom AI Tools drawer.
 * OCR is rendered inside block_aiskillnavigator, like the original UI.
 */
function local_aiskillnavigator_before_footer(): string {
    return '';
}
