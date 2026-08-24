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
 * Local aisn back to course autofix helper.
 */
function local_aisn_back_to_course_autofix(int $courseid): string {
    if ($courseid <= 0) {
        return '';
    }

    $url = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
    $urljson = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return html_writer::tag('script', "
document.addEventListener('DOMContentLoaded', function () {
    var courseUrl = {$urljson};

    /**
     * Labelof helper.
     */
    function labelOf(el) {
        return String(el.textContent || el.value || el.getAttribute('aria-label') || '').trim().toLowerCase();
    }

    /**
     * Isbackbutton helper.
     */
    function isBackButton(el) {
        var t = labelOf(el);
        return t.indexOf('back to course') !== -1 ||
               t.indexOf('back to plugin home') !== -1 ||
               t.indexOf('torna al corso') !== -1 ||
               t.indexOf('torna alla home plugin') !== -1 ||
               t.indexOf('torna alla home del plugin') !== -1;
    }

    var found = false;

    document.querySelectorAll('a,button,input[type=\"button\"],input[type=\"submit\"]').forEach(function (el) {
        if (!isBackButton(el)) {
            return;
        }

        found = true;

        if (el.tagName.toLowerCase() === 'a') {
            el.setAttribute('href', courseUrl);
        } else {
            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                window.location.href = courseUrl;
            });
        }

        if ('value' in el && el.value) {
            el.value = 'Back to course';
        } else {
            el.textContent = 'Back to course';
        }

        el.classList.add('btn', 'btn-secondary');
    });

    if (!found) {
        // phpcs:ignore moodle.Files.LineLength
        var container = document.querySelector('.container-fluid') || document.querySelector('#region-main') || document.querySelector('main') || document.body;

        if (container) {
            var wrap = document.createElement('div');
            wrap.className = 'mt-3 mb-3';

            var a = document.createElement('a');
            a.href = courseUrl;
            a.className = 'btn btn-secondary';
            a.textContent = 'Back to course';

            wrap.appendChild(a);
            container.appendChild(wrap);
        }
    }
});
");
}
