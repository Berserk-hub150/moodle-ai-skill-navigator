<?php

defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aisn_mindmap_polish_prof')) {
    function local_aisn_mindmap_polish_prof(): string {
        return <<<'HTML'
<style id="aisn-mindmap-big-view-v1">
body.path-local-aiskillnavigator .container-fluid,
body[id^="page-local-aiskillnavigator"] .container-fluid {
    max-width: 96vw !important;
}

.aisn-mm-wrap {
    max-width: 100% !important;
    width: 100% !important;
}

.aisn-mm-head {
    margin-bottom: 12px !important;
}

.aisn-mm-controls {
    gap: 10px !important;
}

.aisn-mm-controls button,
.aisn-mm-extra-btn {
    border: 0 !important;
    border-radius: 12px !important;
    padding: 10px 14px !important;
    font-weight: 850 !important;
    cursor: pointer !important;
}

.aisn-mm-extra-btn-primary {
    background: #0f6cbf !important;
    color: #ffffff !important;
    box-shadow: 0 10px 20px rgba(15,108,191,.20) !important;
}

.aisn-mm-extra-btn-secondary {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}

.aisn-mm-grid {
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 16px !important;
    width: 100% !important;
}

.aisn-mm-canvas {
    width: 100% !important;
    height: 78vh !important;
    min-height: 760px !important;
    max-height: 980px !important;
    border-radius: 24px !important;
    overflow: hidden !important;
}

.aisn-mm-panel {
    display: none !important;
    width: 100% !important;
    min-height: 0 !important;
    max-height: 280px !important;
    overflow: auto !important;
    margin-top: 0 !important;
    border-radius: 18px !important;
}

.aisn-mm-wrap.aisn-mm-panel-open .aisn-mm-panel {
    display: block !important;
}

.aisn-mm-panel h3 {
    font-size: 1.35rem !important;
    font-weight: 900 !important;
}

.aisn-mm-summary {
    font-size: 1rem !important;
    line-height: 1.55 !important;
}

.aisn-mm-wrap.aisn-mm-fullscreen-open {
    position: fixed !important;
    inset: 0 !important;
    z-index: 99999 !important;
    background: #f8fafc !important;
    padding: 18px !important;
    overflow: auto !important;
}

.aisn-mm-wrap.aisn-mm-fullscreen-open .aisn-mm-titlebar {
    border-radius: 18px !important;
    padding: 18px 22px !important;
    margin-bottom: 8px !important;
}

.aisn-mm-wrap.aisn-mm-fullscreen-open .aisn-mm-canvas {
    height: calc(100vh - 210px) !important;
    min-height: 620px !important;
    max-height: none !important;
}

.aisn-mm-wrap.aisn-mm-fullscreen-open.aisn-mm-panel-open .aisn-mm-canvas {
    height: calc(100vh - 440px) !important;
}

.aisn-mm-wrap.aisn-mm-fullscreen-open .aisn-mm-panel {
    max-height: 240px !important;
}

body.aisn-mm-fullscreen-body {
    overflow: hidden !important;
}

@media (max-width: 900px) {
    .aisn-mm-canvas {
        height: 72vh !important;
        min-height: 620px !important;
    }
}
</style>

<script id="aisn-mindmap-big-view-v1-js">
(function () {
    function forceRefresh() {
        setTimeout(function () {
            window.dispatchEvent(new Event('resize'));
            var reset = document.getElementById('aisn-mm-reset');
            if (reset) {
                reset.click();
            }
        }, 80);
    }

    function makeButton(id, text, cssClass) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = id;
        btn.textContent = text;
        btn.className = 'aisn-mm-extra-btn ' + cssClass;
        return btn;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var wrap = document.querySelector('.aisn-mm-wrap');
        var controls = document.querySelector('.aisn-mm-controls');
        var panel = document.querySelector('.aisn-mm-panel');
        var canvas = document.querySelector('.aisn-mm-canvas');

        if (!wrap || !controls || !canvas) {
            return;
        }

        if (!document.getElementById('aisn-mm-toggle-panel')) {
            var togglePanel = makeButton(
                'aisn-mm-toggle-panel',
                'Mostra dettagli sottoconcetto',
                'aisn-mm-extra-btn-secondary'
            );

            togglePanel.addEventListener('click', function () {
                wrap.classList.toggle('aisn-mm-panel-open');
                var open = wrap.classList.contains('aisn-mm-panel-open');
                togglePanel.textContent = open ? 'Nascondi dettagli sottoconcetto' : 'Mostra dettagli sottoconcetto';
                forceRefresh();
            });

            controls.appendChild(togglePanel);
        }

        if (!document.getElementById('aisn-mm-fullscreen-toggle')) {
            var fullscreen = makeButton(
                'aisn-mm-fullscreen-toggle',
                'Vedi mind map intera',
                'aisn-mm-extra-btn-primary'
            );

            fullscreen.addEventListener('click', function () {
                wrap.classList.toggle('aisn-mm-fullscreen-open');
                document.body.classList.toggle('aisn-mm-fullscreen-body', wrap.classList.contains('aisn-mm-fullscreen-open'));

                var open = wrap.classList.contains('aisn-mm-fullscreen-open');
                fullscreen.textContent = open ? 'Chiudi mind map intera' : 'Vedi mind map intera';

                forceRefresh();
            });

            controls.appendChild(fullscreen);
        }

        if (panel) {
            wrap.classList.remove('aisn-mm-panel-open');
        }

        forceRefresh();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }

        var wrap = document.querySelector('.aisn-mm-wrap');
        var fullscreen = document.getElementById('aisn-mm-fullscreen-toggle');

        if (wrap && wrap.classList.contains('aisn-mm-fullscreen-open')) {
            wrap.classList.remove('aisn-mm-fullscreen-open');
            document.body.classList.remove('aisn-mm-fullscreen-body');
            if (fullscreen) {
                fullscreen.textContent = 'Vedi mind map intera';
            }
            forceRefresh();
        }
    });
})();
</script>
HTML;
    }
}