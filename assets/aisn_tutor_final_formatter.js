(function () {
    "use strict";

    if (window.aisnTutorFinalFormatterLoaded) {
        return;
    }
    window.aisnTutorFinalFormatterLoaded = true;

    function clean(value) {
        return String(value || "")
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n")
            .replace(/\u00A0/g, " ")
            .replace(/\n{3,}/g, "\n\n")
            .trim();
    }

    function esc(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function inline(value) {
        let html = esc(value);
        html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        html = html.replace(/`([^`]+)`/g, "<code>$1</code>");
        return html;
    }

    function smartNormalize(text) {
        text = clean(text);

        text = text
            .replace(/\s+(Caratteristiche principali(?:\s+del\s+[A-Za-zÀ-ÿ0-9]+)?\:)/gi, "\n\n## $1")
            .replace(/\s+(Quando si usa(?:\s+il|\s+la|\s+i|\s+gli|\s+le)?\s*[A-Za-zÀ-ÿ0-9]*\??)/gi, "\n\n## $1")
            .replace(/\s+(Tipologie(?:\s+di)?[^:]{0,80}:)/gi, "\n\n## $1")
            .replace(/\s+(Esempi(?:\s+di)?[^:]{0,80}:)/gi, "\n\n## $1")
            .replace(/\s+(Vantaggi(?:\s+rispetto)?[^:]{0,100}:)/gi, "\n\n## $1")
            .replace(/\s+(Differenza(?:\s+rispetto)?[^:]{0,120}:)/gi, "\n\n## $1")
            .replace(/\s+(In sintesi[,:\s])/gi, "\n\n## In sintesi\n");

        const labels = [
            "Schema flessibile",
            "Alta scalabilità",
            "Scalabilità",
            "Velocità",
            "Accesso veloce",
            "Supporto a dati strutturati e non strutturati",
            "Document store",
            "Columnar store",
            "Key-value store",
            "Graph database"
        ];

        labels.forEach(function (label) {
            const re = new RegExp("\\s+(" + label.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "\\s*:)", "gi");
            text = text.replace(re, "\n- $1");
        });

        text = text.replace(/\.\s+(-\s+)/g, ".\n$1");

        return clean(text);
    }

    function render(text) {
        text = smartNormalize(text);

        const lines = text.split("\n").map(function (x) {
            return x.trim();
        }).filter(Boolean);

        let html = "";
        let listOpen = false;

        function closeList() {
            if (listOpen) {
                html += "</ul>";
                listOpen = false;
            }
        }

        lines.forEach(function (line) {
            if (/^##\s+/.test(line)) {
                closeList();
                html += '<h3 class="aisn-tutor-section-title">' + inline(line.replace(/^##\s+/, "").replace(/:$/, "")) + "</h3>";
                return;
            }

            const bullet = line.match(/^[-•*]\s+(.+)$/);
            const numbered = line.match(/^\d+[.)]\s+(.+)$/);

            if (bullet || numbered) {
                if (!listOpen) {
                    html += "<ul>";
                    listOpen = true;
                }
                html += "<li>" + inline(bullet ? bullet[1] : numbered[1]) + "</li>";
                return;
            }

            closeList();

            if (/^in sintesi/i.test(line)) {
                html += '<div class="aisn-summary-box">' + inline(line) + "</div>";
                return;
            }

            if (/^[A-ZÀ-Ú][^:]{2,55}:\s+/.test(line)) {
                html += '<div class="aisn-keypoint">' + inline(line) + "</div>";
                return;
            }

            html += "<p>" + inline(line) + "</p>";
        });

        closeList();
        return html;
    }

    function enhance() {
        document.querySelectorAll(".aisn-tutor-answer-card").forEach(function (card) {
            if (card.dataset.aisnFinalFormatted === "1") {
                return;
            }

            const body = card.querySelector(".aisn-tutor-answer-body");
            if (!body) {
                return;
            }

            const rendered = body.querySelector(".aisn-rendered-answer") || body;
            const hasStructure = rendered.querySelector("h3,h4,ul,ol,table,.aisn-keypoint,.aisn-summary-box");

            if (hasStructure) {
                card.dataset.aisnFinalFormatted = "1";
                return;
            }

            const raw = clean(rendered.innerText || rendered.textContent || "");
            if (!raw) {
                return;
            }

            rendered.innerHTML = render(raw);
            card.dataset.aisnFinalFormatted = "1";
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", enhance);
    } else {
        enhance();
    }

    setTimeout(enhance, 300);
    setTimeout(enhance, 900);
    setTimeout(enhance, 1600);
})();
