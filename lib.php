<?php

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

function local_aiskillnavigator_coursemodule_standard_elements($formwrapper, $mform): void {
    $modname = local_aiskillnavigator_ai_policy_current_modname($formwrapper);

    if (!in_array($modname, local_aiskillnavigator_ai_policy_supported_modnames(), true)) {
        return;
    }

    if (method_exists($mform, 'elementExists') &&
        $mform->elementExists('local_aiskillnavigator_external_ai')) {
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

function local_aiskillnavigator_coursemodule_edit_post_actions($data, $course) {
    global $CFG, $USER;

    if (empty($data->coursemodule) || empty($course->id)) {
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

    if (file_exists($syncfile)) {
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

        if (!str_starts_with($title, $prefix)) {
            continue;
        }

        $material->externalaiallowed = $externalallowed ? 1 : 0;
        $material->aipolicy = $externalallowed ? 'external_allowed' : 'local_only';
        $material->timemodified = time();

        $DB->update_record('local_aiskillnav_material', $material);
    }
}


/**
 * AISN_BEFORE_FOOTER_OCR_TOGGLE_V1
 *
 * Injects the per-course OCR toggle inside the AI Skill Navigator sidebar.
 * The button is visible only to users who can update the course.
 */
function local_aiskillnavigator_before_footer(): string {
    $helper = __DIR__ . '/includes/document_ocr_toggle_helper.php';

    if (file_exists($helper)) {
        require_once($helper);
    }

    if (!function_exists('local_aisn_document_ocr_current_courseid') ||
        !function_exists('local_aisn_document_ocr_user_can_toggle') ||
        !function_exists('local_aisn_document_ocr_course_enabled') ||
        !function_exists('local_aisn_document_ocr_toggle_url')) {
        return '';
    }

    $courseid = local_aisn_document_ocr_current_courseid();

    if (!local_aisn_document_ocr_user_can_toggle($courseid)) {
        return '';
    }

    $enabled = local_aisn_document_ocr_course_enabled($courseid);

    $payload = [
        'enabled' => $enabled,
        'label' => $enabled ? 'Disattiva OCR' : 'Attiva OCR',
        'status' => $enabled ? 'OCR attivo per questo corso' : 'OCR disattivato per questo corso',
        'url' => local_aisn_document_ocr_toggle_url($courseid, $enabled)->out(false),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return '';
    }

    return '<style>
        #aisn-ocr-sidebar-toggle {
            margin-top: 14px;
        }

        #aisn-ocr-sidebar-toggle .aisn-ocr-title {
            font-weight: 800;
            font-size: 12px;
            color: #111827;
            margin-bottom: 7px;
            text-transform: uppercase;
        }

        #aisn-ocr-sidebar-toggle .aisn-ocr-btn {
            display: block;
            text-align: center;
            padding: 9px 12px;
            border: 1px solid;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            background: #f0fdf4;
            border-color: #15803d;
            color: #166534;
        }

        #aisn-ocr-sidebar-toggle .aisn-ocr-btn.aisn-ocr-active {
            background: #fff5f5;
            border-color: #dc2626;
            color: #991b1b;
        }

        #aisn-ocr-sidebar-toggle .aisn-ocr-status {
            font-size: 11px;
            color: #64748b;
            margin-top: 6px;
            line-height: 1.35;
        }
    </style>
    <script>
    (function() {
        var cfg = ' . $json . ';

        function textOf(el) {
            return (el && (el.innerText || el.textContent) || "").trim();
        }

        function findSidebarPanel() {
            var nodes = document.querySelectorAll("aside, .drawer, .block, .card, [data-region], div");

            for (var i = 0; i < nodes.length; i++) {
                var txt = textOf(nodes[i]);

                if (
                    txt.indexOf("AI Skill Navigator") !== -1 &&
                    (
                        txt.indexOf("Course materials / RAG") !== -1 ||
                        txt.indexOf("TEACHER TOOLS") !== -1 ||
                        txt.indexOf("KNOWLEDGE BASE") !== -1
                    )
                ) {
                    return nodes[i];
                }
            }

            return null;
        }

        function makeToggle() {
            var wrapper = document.createElement("div");
            wrapper.id = "aisn-ocr-sidebar-toggle";

            var title = document.createElement("div");
            title.className = "aisn-ocr-title";
            title.textContent = "Document OCR";

            var link = document.createElement("a");
            link.className = "aisn-ocr-btn" + (cfg.enabled ? " aisn-ocr-active" : "");
            link.href = cfg.url;
            link.textContent = cfg.label;

            var status = document.createElement("div");
            status.className = "aisn-ocr-status";
            status.textContent = cfg.status;

            wrapper.appendChild(title);
            wrapper.appendChild(link);
            wrapper.appendChild(status);

            return wrapper;
        }

        function installToggle() {
            if (document.getElementById("aisn-ocr-sidebar-toggle")) {
                return;
            }

            var panel = findSidebarPanel();

            if (!panel) {
                return;
            }

            var toggle = makeToggle();
            var links = panel.querySelectorAll("a, button");
            var ragElement = null;

            for (var i = 0; i < links.length; i++) {
                if (textOf(links[i]).indexOf("Course materials") !== -1) {
                    ragElement = links[i];
                    break;
                }
            }

            if (ragElement && ragElement.parentNode) {
                ragElement.parentNode.insertAdjacentElement("afterend", toggle);
            } else {
                panel.appendChild(toggle);
            }
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", installToggle);
        } else {
            installToggle();
        }

        setTimeout(installToggle, 500);
        setTimeout(installToggle, 1500);
    })();
    </script>';
}

