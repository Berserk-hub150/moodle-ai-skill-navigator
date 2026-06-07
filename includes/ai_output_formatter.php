<?php

defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aisn_fix_mojibake')) {
    function local_aisn_fix_mojibake(string $text): string {
        $map = [
            'â€™' => '’', 'â€˜' => '‘', 'â€œ' => '“', 'â€' => '”',
            'â€“' => '–', 'â€”' => '—', 'â€¦' => '…', 'â€¢' => '•',
            'Â ' => ' ', 'Â°' => '°',
            'Ã ' => 'à', 'Ã¨' => 'è', 'Ã©' => 'é', 'Ã¬' => 'ì', 'Ã²' => 'ò', 'Ã¹' => 'ù',
            'Ã€' => 'À', 'Ãˆ' => 'È', 'Ã‰' => 'É', 'ÃŒ' => 'Ì', 'Ã’' => 'Ò', 'Ã™' => 'Ù',
            'Ã§' => 'ç',
        ];

        return str_replace(array_keys($map), array_values($map), $text);
    }
}

if (!function_exists('local_aisn_ai_output_formatter_assets')) {
    function local_aisn_ai_output_formatter_assets(): string {
        $css = <<<'CSS'
.aisn-ai-output {
    line-height: 1.7;
    font-size: 1rem;
}
.aisn-ai-output p {
    margin: 0 0 12px;
}
.aisn-ai-table-wrap {
    overflow-x: auto;
    margin: 16px 0;
}
.aisn-ai-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(15, 23, 42, .05);
}
.aisn-ai-table th {
    background: #eff6ff;
    color: #0f172a;
    font-weight: 900;
}
.aisn-ai-table th,
.aisn-ai-table td {
    border-bottom: 1px solid #dbeafe;
    border-right: 1px solid #dbeafe;
    padding: 12px 14px;
    vertical-align: top;
}
.aisn-ai-table tr:last-child td {
    border-bottom: 0;
}
.aisn-ai-table th:last-child,
.aisn-ai-table td:last-child {
    border-right: 0;
}
.aisn-ai-list {
    margin: 10px 0 14px 24px;
}
.aisn-ai-list li {
    margin-bottom: 6px;
}
.aisn-code-block {
    background: #0f172a;
    color: #e5e7eb;
    border-radius: 14px;
    padding: 14px;
    overflow-x: auto;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.aisn-math-block {
    background: #f8fafc;
    border: 1px solid #dbeafe;
    border-left: 5px solid #0f6cbf;
    border-radius: 12px;
    padding: 12px 14px;
    margin: 12px 0;
    overflow-x: auto;
    font-family: "Cambria Math", "Times New Roman", serif;
    font-size: 1.08rem;
}
.aisn-answer-used-materials {
    color: #64748b;
    margin-bottom: 14px;
}
CSS;

        $js = <<<'JS'
(function () {
    if (window.aisnNotionFormatterLoaded) {
        return;
    }
    window.aisnNotionFormatterLoaded = true;

    const mojibakeMap = {
        "â€™": "’", "â€˜": "‘", "â€œ": "“", "â€": "”",
        "â€“": "–", "â€”": "—", "â€¦": "…", "â€¢": "•",
        "Â ": " ", "Â°": "°",
        "Ã ": "à", "Ã¨": "è", "Ã©": "é", "Ã¬": "ì", "Ã²": "ò", "Ã¹": "ù",
        "Ã€": "À", "Ãˆ": "È", "Ã‰": "É", "ÃŒ": "Ì", "Ã’": "Ò", "Ã™": "Ù",
        "Ã§": "ç"
    };

    function fixText(value) {
        let out = String(value || "");
        Object.keys(mojibakeMap).forEach(function (bad) {
            out = out.split(bad).join(mojibakeMap[bad]);
        });
        return out;
    }

    function esc(value) {
        return String(value || "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function isTableSeparator(line) {
        line = String(line || "").trim();
        return /^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/.test(line);
    }

    function isTableLine(line) {
        line = String(line || "").trim();
        return line.startsWith("|") && line.endsWith("|") && line.split("|").length >= 3;
    }

    function splitTableRow(line) {
        return String(line || "")
            .trim()
            .replace(/^\|/, "")
            .replace(/\|$/, "")
            .split("|")
            .map(x => x.trim());
    }

    function looksMath(line) {
        line = String(line || "").trim();
        return (
            /\\\(|\\\[|\$\$|\\frac|\\sqrt|\\sum|\\int/.test(line) ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(line) ||
            /[a-zA-Z0-9]\s*\^\s*[0-9]/.test(line) ||
            /\b(sin|cos|tan|log|ln)\s*\(/i.test(line) ||
            /=\s*[-+]?[0-9a-zA-Z(]/.test(line)
        );
    }

    function renderMarkdownish(raw) {
        let text = fixText(raw)
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n")
            .replace(/\n{3,}/g, "\n\n")
            .trim();

        const lines = text.split("\n");
        let html = "";
        let i = 0;

        while (i < lines.length) {
            let line = lines[i].trim();

            if (!line) {
                i++;
                continue;
            }

            if (/^```/.test(line)) {
                const code = [];
                i++;
                while (i < lines.length && !/^```/.test(lines[i].trim())) {
                    code.push(lines[i]);
                    i++;
                }
                i++;
                html += '<pre class="aisn-code-block">' + esc(code.join("\n")) + '</pre>';
                continue;
            }

            if (isTableLine(line) && i + 1 < lines.length && isTableSeparator(lines[i + 1])) {
                const headers = splitTableRow(line);
                i += 2;

                const rows = [];
                while (i < lines.length && isTableLine(lines[i])) {
                    rows.push(splitTableRow(lines[i]));
                    i++;
                }

                html += '<div class="aisn-ai-table-wrap"><table class="aisn-ai-table"><thead><tr>';
                headers.forEach(h => html += '<th>' + esc(h) + '</th>');
                html += '</tr></thead><tbody>';

                rows.forEach(row => {
                    html += '<tr>';
                    for (let c = 0; c < headers.length; c++) {
                        html += '<td>' + esc(row[c] || '') + '</td>';
                    }
                    html += '</tr>';
                });

                html += '</tbody></table></div>';
                continue;
            }

            if (/^[-*]\s+/.test(line)) {
                html += '<ul class="aisn-ai-list">';
                while (i < lines.length && /^[-*]\s+/.test(lines[i].trim())) {
                    html += '<li>' + esc(lines[i].trim().replace(/^[-*]\s+/, "")) + '</li>';
                    i++;
                }
                html += '</ul>';
                continue;
            }

            if (/^\d+\.\s+/.test(line)) {
                html += '<ol class="aisn-ai-list">';
                while (i < lines.length && /^\d+\.\s+/.test(lines[i].trim())) {
                    html += '<li>' + esc(lines[i].trim().replace(/^\d+\.\s+/, "")) + '</li>';
                    i++;
                }
                html += '</ol>';
                continue;
            }

            if (looksMath(line)) {
                html += '<div class="aisn-math-block">' + esc(line) + '</div>';
                i++;
                continue;
            }

            const paragraph = [line];
            i++;

            while (
                i < lines.length &&
                lines[i].trim() &&
                !/^```/.test(lines[i].trim()) &&
                !isTableLine(lines[i]) &&
                !/^[-*]\s+/.test(lines[i].trim()) &&
                !/^\d+\.\s+/.test(lines[i].trim())
            ) {
                paragraph.push(lines[i].trim());
                i++;
            }

            html += '<p>' + esc(paragraph.join(" ")) + '</p>';
        }

        return '<div class="aisn-ai-output">' + html + '</div>';
    }

    function findAnswerCards() {
        const cards = [];

        document.querySelectorAll("h1,h2,h3,h4").forEach(function (h) {
            const title = fixText(h.textContent || "").trim().toLowerCase();

            if (title !== "answer" && title !== "risposta") {
                return;
            }

            const card = h.closest(".card, .generalbox, .box, section, div");

            if (card && !cards.includes(card)) {
                cards.push(card);
            }
        });

        document.querySelectorAll(".aisn-answer, .ai-answer, .aisn-response").forEach(function (el) {
            if (!cards.includes(el)) {
                cards.push(el);
            }
        });

        return cards;
    }

    function formatAnswerCard(card) {
        if (
            !card ||
            card.dataset.aisnNotionFormatted === "1" ||
            card.dataset.aisnAnswerRendererV3Done === "1" ||
            card.dataset.aisnForceFormatted === "1" ||
            card.dataset.aisnBrutalMdDone === "1" ||
            card.dataset.aisnFinalMdDone === "1" ||
            card.dataset.aisnUnifiedAnswerDone === "1"
        ) {
            return;
        }

        if (card.querySelector("form, textarea, input, select")) {
            return;
        }

        const alerts = Array.from(card.querySelectorAll(".alert")).map(x => x.cloneNode(true));

        let text = fixText(card.innerText || card.textContent || "");
        text = text.replace(/^Answer\s*/i, "");
        text = text.replace(/^Risposta\s*/i, "");

        let used = "";
        text = text.replace(/^Used materials:\s*([^\n]*)/im, function (_, m) {
            used = m.trim();
            return "";
        });

        text = text.trim();

        if (!text) {
            return;
        }

        const hasMarkdownTable = text.split(/\n/).some((line, idx, arr) => isTableLine(line) && idx + 1 < arr.length && isTableSeparator(arr[idx + 1]));
        const hasMath = text.split(/\n/).some(looksMath);
        const hasList = text.split(/\n/).some(line => /^[-*]\s+/.test(line.trim()) || /^\d+\.\s+/.test(line.trim()));

        if (!hasMarkdownTable && !hasMath && !hasList && !text.includes("```")) {
            return;
        }

        let newHtml = "";

        const heading = card.querySelector("h1,h2,h3,h4");
        if (heading) {
            newHtml += '<h3>' + esc(fixText(heading.textContent || "Answer").trim()) + '</h3>';
        }

        if (used) {
            newHtml += '<p class="aisn-answer-used-materials">Used materials: ' + esc(used) + '</p>';
        }

        newHtml += renderMarkdownish(text);

        card.innerHTML = newHtml;

        alerts.forEach(function (a) {
            card.appendChild(a);
        });

        card.dataset.aisnNotionFormatted = "1";
        card.dataset.aisnAnswerRendererV3Done = "1";
        card.dataset.aisnForceFormatted = "1";
        card.dataset.aisnBrutalMdDone = "1";
        card.dataset.aisnFinalMdDone = "1";
        card.dataset.aisnUnifiedAnswerDone = "1";
    }

    function fixMojibakeEverywhere() {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(function (node) {
            const parent = node.parentElement;
            if (!parent) {
                return;
            }

            const tag = parent.tagName.toLowerCase();
            if (["script", "style", "textarea", "input", "select", "option"].includes(tag)) {
                return;
            }

            const fixed = fixText(node.nodeValue);
            if (fixed !== node.nodeValue) {
                node.nodeValue = fixed;
            }
        });
    }

    function run() {
        fixMojibakeEverywhere();
        findAnswerCards().forEach(formatAnswerCard);
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 300);
        setTimeout(run, 1000);
        setTimeout(run, 2000);
    });
})();
JS;

        return html_writer::tag('style', $css) . html_writer::tag('script', $js);
    }
}