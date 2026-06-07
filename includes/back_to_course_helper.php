<?php

defined('MOODLE_INTERNAL') || die();

function local_aisn_back_to_course_autofix(int $courseid): string {
    if ($courseid <= 0) {
        return '';
    }

    $url = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
    $urljson = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return html_writer::tag('script', "
document.addEventListener('DOMContentLoaded', function () {
    var courseUrl = {$urljson};

    function labelOf(el) {
        return String(el.textContent || el.value || el.getAttribute('aria-label') || '').trim().toLowerCase();
    }

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