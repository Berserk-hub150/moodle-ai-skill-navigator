(function () {
    if (window.aisnUnifiedAnswerRendererLoaded) {
        return;
    }
    window.aisnUnifiedAnswerRendererLoaded = true;

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function fixBadChars(value) {
        return String(value == null ? "" : value)
            .replace(/Ã¨/g, "è")
            .replace(/Ã©/g, "é")
            .replace(/Ã /g, "à")
            .replace(/Ã²/g, "ò")
            .replace(/Ã¹/g, "ù")
            .replace(/Ã¬/g, "ì")
            .replace(/â€™/g, "'")
            .replace(/â€œ/g, '"')
            .replace(/â€/g, '"')
            .replace(/â€“/g, "-")
            .replace(/Â /g, " ");
    }

    function cleanText(value) {
        return fixBadChars(value)
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n");
    }

    function line(value) {
        return cleanText(value).replace(/\u00A0/g, " ").trim();
    }

    function inlineMd(value) {
        var text = esc(value);
        text = text.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        text = text.replace(/`([^`]+)`/g, '<code class="aisn-inline-code">$1</code>');
        return text;
    }

    function pipeCount(value) {
        var match = String(value || "").match(/\|/g);
        return match ? match.length : 0;
    }

    function isTableRow(value) {
        return pipeCount(line(value)) >= 2;
    }

    function isSeparatorRow(value) {
        value = line(value);
        if (pipeCount(value) < 2) {
            return false;
        }

        var cleaned = value.replace(/\|/g, "").trim();
        return /^[:\-\s]+$/.test(cleaned) && cleaned.indexOf("-") !== -1;
    }

    function splitRow(value) {
        value = line(value);

        if (value.charAt(0) === "|") {
            value = value.substring(1);
        }

        if (value.charAt(value.length - 1) === "|") {
            value = value.substring(0, value.length - 1);
        }

        return value.split("|").map(function (cell) {
            return line(cell);
        });
    }

    function nextNonEmpty(lines, start) {
        for (var i = start; i < lines.length; i++) {
            if (line(lines[i]) !== "") {
                return i;
            }
        }
        return -1;
    }

    function startsTable(lines, index) {
        if (!isTableRow(lines[index])) {
            return false;
        }

        var separator = nextNonEmpty(lines, index + 1);
        return separator !== -1 && isSeparatorRow(lines[separator]);
    }

    function renderTable(headers, rows) {
        var html = '<div class="aisn-table-wrap"><table><thead><tr>';

        headers.forEach(function (header) {
            html += "<th>" + inlineMd(header) + "</th>";
        });

        html += "</tr></thead><tbody>";

        rows.forEach(function (row) {
            html += "<tr>";

            for (var i = 0; i < headers.length; i++) {
                html += "<td>" + inlineMd(row[i] || "") + "</td>";
            }

            html += "</tr>";
        });

        html += "</tbody></table></div>";
        return html;
    }

    function stripMathDelimiters(value) {
        var text = line(value);

        text = text.replace(/^\\\\\[/, "\\[").replace(/\\\\\]$/, "\\]");
        text = text.replace(/^\\\\\(/, "\\(").replace(/\\\\\)$/, "\\)");

        var changed = true;
        while (changed) {
            var old = text;
            text = text.replace(/^\\\[\s*([\s\S]*?)\s*\\\]$/m, "$1").trim();
            text = text.replace(/^\\\(\s*([\s\S]*?)\s*\\\)$/m, "$1").trim();
            text = text.replace(/^\$\$\s*([\s\S]*?)\s*\$\$$/m, "$1").trim();
            text = text.replace(/^\$\s*([\s\S]*?)\s*\$$/m, "$1").trim();
            changed = old !== text;
        }

        return text;
    }

    function normalizeTex(value) {
        var text = stripMathDelimiters(value);

        text = text.replace(/ℝ/g, "\\mathbb{R}");
        text = text.replace(/→/g, "\\to");
        text = text.replace(/->/g, "\\to");
        text = text.replace(/×/g, "\\cdot ");
        text = text.replace(/\*/g, "\\cdot ");
        text = text.replace(/([a-zA-Z0-9\)])\^([0-9]+)/g, "$1^{$2}");
        text = text.replace(/sqrt\s*\(([^)]+)\)/gi, "\\sqrt{$1}");

        return text.trim();
    }

    function isMathStart(value) {
        value = line(value);
        return value === "\\[" || value === "\\(" || value === "$$" || value === "\\\\[" || value === "\\\\(";
    }

    function isMathEnd(value) {
        value = line(value);
        return value === "\\]" || value === "\\)" || value === "$$" || value === "\\\\]" || value === "\\\\)";
    }

    function containsNaturalLanguage(value) {
        return /\b(nome|funzione|definizione|indica|input|output|prende|restituisce|dove|calcola|passaggi|esempio|questa|questo|sono|viene|serve|con|della|delle|degli|è| e )\b/i.test(value);
    }

    function isPureFormula(value) {
        var text = stripMathDelimiters(value);

        if (!text || text.length > 180 || containsNaturalLanguage(text)) {
            return false;
        }

        return (
            /\\frac|\\sqrt|\\sum|\\int|\\mathbb|\\to|\\begin/.test(text) ||
            /ℝ|→/.test(text) ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(text) ||
            /[a-zA-Z0-9]\s*\^\s*[0-9]/.test(text) ||
            /\b(sin|cos|tan|log|ln)\s*\(/i.test(text) ||
            /^[a-zA-Z]\s*:\s*.*(R|ℝ|\\mathbb)/.test(text) ||
            (text.indexOf("=") !== -1 && /[0-9]/.test(text) && /^[a-zA-Z0-9\s\+\-\*\/\^\(\)=.,]+$/.test(text))
        );
    }

    function renderMath(value) {
        var tex = normalizeTex(value);
        return '<div class="aisn-math-block">\\[' + esc(tex) + '\\]</div>';
    }

    function detectLanguage(code, explicit) {
        explicit = line(explicit).toLowerCase();

        if (explicit.indexOf("python") !== -1 || explicit === "py") {
            return "Python";
        }
        if (explicit.indexOf("javascript") !== -1 || explicit === "js") {
            return "JavaScript";
        }
        if (explicit.indexOf("typescript") !== -1 || explicit === "ts") {
            return "TypeScript";
        }
        if (explicit.indexOf("java") !== -1) {
            return "Java";
        }
        if (explicit.indexOf("cpp") !== -1 || explicit.indexOf("c++") !== -1) {
            return "C++";
        }
        if (explicit === "c") {
            return "C";
        }
        if (explicit.indexOf("php") !== -1) {
            return "PHP";
        }
        if (explicit.indexOf("sql") !== -1) {
            return "SQL";
        }
        // AISN_MONGODB_LANGUAGE_SUPPORT_CLEAN_V1
        // Supporta il linguaggio scelto dall'AI nel blocco markdown: ```mongodb.
        // Non forza il linguaggio analizzando il contenuto.
        if (explicit.indexOf("mongodb") !== -1 || explicit.indexOf("mongo") !== -1 || explicit === "mongosh") {
            return "MongoDB";
        }
        if (explicit.indexOf("html") !== -1) {
            return "HTML";
        }
        if (explicit.indexOf("css") !== -1) {
            return "CSS";
        }

        if (/^\s*def\s+\w+\s*\(/m.test(code)) {
            return "Python";
        }
        if (/public\s+class|class\s+Solution|System\.out\.println/.test(code)) {
            return "Java";
        }
        if (/^\s*#include|std::|cout\s*<</m.test(code)) {
            return "C++";
        }
        if (/^\s*(const|let|var)\s+|function\s+\w+\s*\(/m.test(code)) {
            return "JavaScript";
        }
        if (/<\?php|\$[a-zA-Z_]\w*\s*=/.test(code)) {
            return "PHP";
        }
        if (/^\s*(SELECT|INSERT|UPDATE|DELETE)\b/im.test(code)) {
            return "SQL";
        }

        return "Code";
    }

    function stripBrokenHighlightArtifacts(code) {
        return cleanText(code)
            .replace(/class="aisn-code-str">/g, "")
            .replace(/class="aisn-code-num">/g, "")
            .replace(/class="aisn-code-kw">/g, "")
            .replace(/class="aisn-code-com">/g, "")
            .replace(/<\/span>/g, "")
            .replace(/[ \t]+$/gm, "")
            .replace(/\n{3,}/g, "\n\n")
            .trim();
    }

    function renderCode(code, explicitLanguage) {
        code = stripBrokenHighlightArtifacts(code);
        var language = detectLanguage(code, explicitLanguage);

        return '<div class="aisn-code-editor">' +
            '<div class="aisn-code-head"><span class="aisn-code-lang-label">' + esc(language) + '</span><button type="button" class="aisn-copy-btn">Copy</button></div>' +
            '<pre><code>' + esc(code) + '</code></pre>' +
            '</div>';
    }

    function isCodeStart(value) {
        value = line(value);
        return (
            /^def\s+\w+\s*\(/.test(value) ||
            /^class\s+\w+/.test(value) ||
            /^function\s+\w+\s*\(/.test(value) ||
            /^public\s+/.test(value) ||
            /^private\s+/.test(value) ||
            /^protected\s+/.test(value) ||
            /^const\s+/.test(value) ||
            /^let\s+/.test(value) ||
            /^var\s+/.test(value) ||
            /^return\b/.test(value) ||
            /^if\s*\(/.test(value) ||
            /^for\s*\(/.test(value) ||
            /^while\s*\(/.test(value) ||
            /^<\?php/.test(value) ||
            /^SELECT\b/i.test(value)
        );
    }

    function renderMarkdown(raw) {
        raw = cleanText(raw).trim();

        var lines = raw.split("\n");
        var html = "";
        var paragraph = [];
        var i = 0;

        function flushParagraph() {
            if (paragraph.length > 0) {
                html += "<p>" + inlineMd(paragraph.join(" ")) + "</p>";
                paragraph = [];
            }
        }

        while (i < lines.length) {
            var current = line(lines[i]);

            if (!current) {
                flushParagraph();
                i++;
                continue;
            }

            if (isMathStart(current)) {
                flushParagraph();

                var math = [];
                i++;

                while (i < lines.length && !isMathEnd(lines[i])) {
                    math.push(lines[i]);
                    i++;
                }

                if (i < lines.length) {
                    i++;
                }

                html += renderMath(math.join("\n"));
                continue;
            }

            if (/^\\begin\{/.test(current) || current.indexOf("\\begin{cases}") !== -1) {
                flushParagraph();

                var mathBlock = [current];
                i++;

                while (i < lines.length) {
                    mathBlock.push(lines[i]);
                    if (line(lines[i]).indexOf("\\end{") !== -1) {
                        i++;
                        break;
                    }
                    i++;
                }

                html += renderMath(mathBlock.join("\n"));
                continue;
            }

            if (isPureFormula(current)) {
                flushParagraph();
                html += renderMath(current);
                i++;
                continue;
            }

            if (/^`{3}/.test(current)) {
                flushParagraph();

                var explicitLanguage = current.replace(/^`{3}/, "").trim();
                var codeLines = [];
                i++;

                while (i < lines.length && !/^`{3}/.test(line(lines[i]))) {
                    codeLines.push(lines[i]);
                    i++;
                }

                if (i < lines.length) {
                    i++;
                }

                html += renderCode(codeLines.join("\n"), explicitLanguage);
                continue;
            }

            if (startsTable(lines, i)) {
                flushParagraph();

                var headers = splitRow(lines[i]);
                var separator = nextNonEmpty(lines, i + 1);
                var rows = [];
                i = separator + 1;

                while (i < lines.length) {
                    if (line(lines[i]) === "") {
                        i++;
                        continue;
                    }

                    if (!isTableRow(lines[i]) || isSeparatorRow(lines[i])) {
                        break;
                    }

                    rows.push(splitRow(lines[i]));
                    i++;
                }

                html += renderTable(headers, rows);
                continue;
            }

            if (/^[-*]\s+/.test(current)) {
                flushParagraph();
                html += "<ul>";

                while (i < lines.length && /^[-*]\s+/.test(line(lines[i]))) {
                    html += "<li>" + inlineMd(line(lines[i]).replace(/^[-*]\s+/, "")) + "</li>";
                    i++;
                }

                html += "</ul>";
                continue;
            }

            if (/^\d+\.\s+/.test(current)) {
                flushParagraph();
                html += "<ol>";

                while (i < lines.length && /^\d+\.\s+/.test(line(lines[i]))) {
                    html += "<li>" + inlineMd(line(lines[i]).replace(/^\d+\.\s+/, "")) + "</li>";
                    i++;
                }

                html += "</ol>";
                continue;
            }

            if (isCodeStart(current)) {
                flushParagraph();

                var codeBlock = [lines[i]];
                i++;

                while (i < lines.length) {
                    var next = line(lines[i]);

                    if (!next) {
                        var after = nextNonEmpty(lines, i + 1);

                        if (after === -1) {
                            i++;
                            break;
                        }

                        if (!isCodeStart(lines[after]) && !/^[\s]+/.test(lines[after]) && !/^(["']{3}|#|\/\/|\*|else:|elif\b|except\b|finally:|case\b|default:)/.test(line(lines[after]))) {
                            break;
                        }

                        codeBlock.push("");
                        i++;
                        continue;
                    }

                    if (
                        isCodeStart(next) ||
                        /^[\s]+/.test(lines[i]) ||
                        /^(["']{3}|#|\/\/|\*|else:|elif\b|except\b|finally:|case\b|default:)/.test(next) ||
                        /^[A-Za-z_]\w*\s*=/.test(next) ||
                        /^print\s*\(/.test(next) ||
                        /^\}/.test(next)
                    ) {
                        codeBlock.push(lines[i]);
                        i++;
                        continue;
                    }

                    break;
                }

                html += renderCode(codeBlock.join("\n"), "");
                continue;
            }

            paragraph.push(current);
            i++;
        }

        flushParagraph();

        return '<div class="aisn-rendered-answer">' + html + "</div>";
    }

    function findAnswerHeadings() {
        var headings = Array.prototype.slice.call(document.querySelectorAll("h1,h2,h3,h4"));
        var result = [];

        headings.forEach(function (heading) {
            var text = line(heading.textContent).toLowerCase();
            if (text === "answer" || text === "risposta") {
                result.push(heading);
            }
        });

        return result;
    }

    function findAnswerBox(heading) {
        var node = heading.parentElement;
        var best = heading.parentElement;

        for (var i = 0; i < 8 && node && node !== document.body; i++) {
            var text = cleanText(node.innerText || node.textContent || "");

            if (text.indexOf("Used materials:") !== -1 || text.length > 80) {
                best = node;
            }

            if (
                node.classList &&
                (
                    node.classList.contains("aisn-answer-card") ||
                    node.classList.contains("aisn-card") ||
                    node.classList.contains("card") ||
                    node.classList.contains("generalbox") ||
                    node.classList.contains("box")
                ) &&
                text.length < 60000
            ) {
                return node;
            }

            node = node.parentElement;
        }

        return best;
    }

    function shouldRender(raw) {
        raw = cleanText(raw || "");
        return (
            raw.indexOf("|") !== -1 ||
            raw.indexOf("```") !== -1 ||
            raw.indexOf("``") !== -1 ||
            raw.indexOf("\\[") !== -1 ||
            raw.indexOf("\\(") !== -1 ||
            raw.indexOf("\\begin") !== -1 ||
            raw.indexOf("\\mathbb") !== -1 ||
            raw.indexOf("Used materials:") !== -1 ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(raw) ||
            /\bdef\s+\w+\s*\(/.test(raw) ||
            /\bclass\s+\w+/.test(raw) ||
            /\bfunction\s+\w+\s*\(/.test(raw) ||
            /\breturn\b/.test(raw) ||
            /\n\s*[-*]\s+/.test(raw) ||
            /\n\s*\d+\.\s+/.test(raw)
        );
    }

    function renderAnswerBox(box) {
        if (!box || box.dataset.aisnUnifiedAnswerRendered === "1") {
            return;
        }

        if (box.querySelector("textarea,input,select,form")) {
            return;
        }

        if (box.querySelector(".aisn-rendered-answer")) {
            box.dataset.aisnUnifiedAnswerRendered = "1";
            return;
        }

        var raw = cleanText(box.innerText || box.textContent || "");

        if (!shouldRender(raw)) {
            return;
        }

        var alerts = Array.prototype.slice.call(box.querySelectorAll(".alert")).map(function (alert) {
            return alert.cloneNode(true);
        });

        raw = raw.replace(/^\s*Answer\s*/i, "");
        raw = raw.replace(/^\s*Risposta\s*/i, "");

        var usedMaterials = "";
        raw = raw.replace(/Used materials:\s*([^\n]*)/i, function (_, value) {
            usedMaterials = line(value);
            return "";
        });

        box.innerHTML =
            "<h3>Answer</h3>" +
            (usedMaterials ? '<p class="aisn-used-materials">Used materials: ' + esc(usedMaterials) + "</p>" : "") +
            renderMarkdown(raw.trim());

        alerts.forEach(function (alert) {
            box.appendChild(alert);
        });

        box.dataset.aisnUnifiedAnswerRendered = "1";

        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise([box]).catch(function () {});
        }
    }

    function run() {
        findAnswerHeadings().forEach(function (heading) {
            renderAnswerBox(findAnswerBox(heading));
        });
    }

    var scheduled = false;

    function scheduleRun() {
        if (scheduled) {
            return;
        }

        scheduled = true;

        window.setTimeout(function () {
            scheduled = false;
            run();
        }, 80);
    }

    document.addEventListener("click", function (event) {
        var target = event.target;

        if (!target || !target.classList || !target.classList.contains("aisn-copy-btn")) {
            return;
        }

        var editor = target.closest(".aisn-code-editor");
        var code = editor ? editor.querySelector("pre code") : null;

        if (!code) {
            return;
        }

        var originalText = target.textContent;
        var text = code.textContent || "";

        function done() {
            target.textContent = "Copied";
            window.setTimeout(function () {
                target.textContent = originalText;
            }, 1200);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {});
            return;
        }

        var textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
        done();
    });

    run();
    document.addEventListener("DOMContentLoaded", run);
    window.setTimeout(run, 300);
    window.setTimeout(run, 1000);
    window.setTimeout(run, 2000);

    new MutationObserver(scheduleRun).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();
