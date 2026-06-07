<?php

defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aiskillnavigator_mojibake_guard')) {
    function local_aiskillnavigator_mojibake_guard(): string {
        return <<<'HTML'
<script id="aisn-mojibake-guard-v2">
(function () {
    function fixText(s) {
        return String(s || "")
            .replace(/Attivit[\s\S]{0,220}?suggerita:\s*/g, "")
            .replace(/Mini-attivit[\s\S]{0,220}?:/g, "Mini-attivita:")
            .replace(/\u00e2\u20ac\u2122/g, "'")
            .replace(/\u00e2\u20ac\u0153/g, '"')
            .replace(/\u00e2\u20ac\u009d/g, '"')
            .replace(/\u00c3\u00a0/g, "a'")
            .replace(/\u00c3\u00a8/g, "e'")
            .replace(/\u00c3\u00a9/g, "e'")
            .replace(/\u00c3\u00ac/g, "i'")
            .replace(/\u00c3\u00b2/g, "o'")
            .replace(/\u00c3\u00b9/g, "u'")
            .replace(/Ãƒ[^|<\n\r]{0,180}/g, " ")
            .replace(/Â·/g, " | ")
            .replace(/\s+\|\s+\|\s+/g, " | ")
            .replace(/\s{2,}/g, " ")
            .trim();
    }

    function walk(node) {
        if (!node) return;

        if (node.nodeType === Node.TEXT_NODE) {
            var fixed = fixText(node.nodeValue);
            if (fixed !== node.nodeValue) {
                node.nodeValue = fixed;
            }
            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (["SCRIPT", "STYLE", "TEXTAREA", "INPUT"].includes(node.tagName)) return;

        node.childNodes.forEach(walk);
    }

    function run() {
        if (document.body) walk(document.body);
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 300);
        setTimeout(run, 1000);
        setTimeout(run, 2500);
    });

    new MutationObserver(run).observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true
    });
})();
</script>
HTML;
    }
}
