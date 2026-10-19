// This file is part of Moodle - https://moodle.org/
// Moodle is distributed under the GNU GPL v3 or later.

/**
 * Keep generation feedback in sync with native form navigation.
 *
 * @copyright 2026 Nikhil Jangid
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    const init = function() {
        const form = document.getElementById('aisn-quiz-generator-form');
        const submit = document.getElementById('aisn-quiz-generator-submit');
        const status = document.getElementById('aisn-quiz-generator-status');
        if (!form || !submit || !status) {
            return;
        }
        const label = submit.value;
        let submitting = false;
        const reset = function() {
            submitting = false;
            submit.disabled = false;
            submit.value = label;
            form.removeAttribute('aria-busy');
            status.textContent = '';
            status.classList.remove('alert', 'alert-info', 'mt-3');
        };
        form.addEventListener('submit', function(event) {
            if (event.defaultPrevented) {
                return;
            }
            if (submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            submit.disabled = true;
            submit.value = submit.dataset.loadingLabel;
            form.setAttribute('aria-busy', 'true');
            // The live region stays exposed, outside the busy form, and starts empty.
            status.classList.add('alert', 'alert-info', 'mt-3');
            status.textContent = status.dataset.message;
            // Another validation listener can still cancel this submission.
            Promise.resolve().then(function() {
                if (event.defaultPrevented) {
                    reset();
                }
            });
        });
        // Back/forward cache restores the disabled DOM without rerunning init.
        window.addEventListener('pageshow', reset);
        reset();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})();
