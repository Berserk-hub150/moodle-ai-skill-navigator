<?php

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/production_guard.php');

function local_aiskillnavigator_current_ai_is_local(): bool {
    return function_exists('local_aisn_prod_current_ai_is_local')
        ? local_aisn_prod_current_ai_is_local()
        : true;
}

function local_aiskillnavigator_material_external_allowed(stdClass $material): bool {
    if (isset($material->externalaiallowed)) {
        return ((int)$material->externalaiallowed) === 1;
    }

    if (isset($material->aipolicy)) {
        return ((string)$material->aipolicy) === 'external_allowed';
    }

    return false;
}

function local_aiskillnavigator_material_can_be_sent_to_current_ai(stdClass $material): bool {
    if (function_exists('local_aisn_prod_can_send_material_to_current_ai')) {
        return local_aisn_prod_can_send_material_to_current_ai($material);
    }
    if (local_aiskillnavigator_current_ai_is_local()) {
        return true;
    }
    return local_aiskillnavigator_material_external_allowed($material);
}

function local_aiskillnavigator_filter_materials_for_current_ai(array $materials): array {
    $filtered = [];

    foreach ($materials as $key => $material) {
        if (local_aiskillnavigator_material_can_be_sent_to_current_ai($material)) {
            $filtered[$key] = $material;
        }
    }

    return $filtered;
}

function local_aiskillnavigator_ai_policy_label(stdClass $material): string {
    return local_aiskillnavigator_material_external_allowed($material)
        ? 'Allowed for external AI'
        : 'Local AI only';
}

function local_aiskillnavigator_ai_policy_badge_class(stdClass $material): string {
    return local_aiskillnavigator_material_external_allowed($material)
        ? 'badge badge-success'
        : 'badge badge-secondary';
}

function local_aiskillnavigator_set_material_ai_policy(int $materialid, int $courseid, bool $externalallowed): bool {
    global $DB;

    $material = $DB->get_record('local_aiskillnav_material', [
        'id' => $materialid,
        'courseid' => $courseid,
    ]);

    if (!$material) {
        return false;
    }

    $material->externalaiallowed = $externalallowed ? 1 : 0;
    $material->aipolicy = $externalallowed ? 'external_allowed' : 'local_only';
    $material->timemodified = time();

    $DB->update_record('local_aiskillnav_material', $material);

    return true;
}

function local_aiskillnavigator_provider_privacy_notice(): string {
    if (local_aiskillnavigator_current_ai_is_local()) {
        return 'Current AI provider is local/prototype: course materials can be used without sending them to an external provider.';
    }
    if (function_exists('local_aisn_prod_external_ai_globally_enabled') && !local_aisn_prod_external_ai_globally_enabled()) {
        return 'Current AI provider is external, but external AI is not globally approved by the site administrator.';
    }
    return 'Current AI provider is external: only materials explicitly allowed by the teacher can be sent to the AI provider.';
}