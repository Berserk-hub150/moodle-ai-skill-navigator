(function () {
    "use strict";

    if (window.aisnTutorDomDirectStyleLoaded) {
        return;
    }

    window.aisnTutorDomDirectStyleLoaded = true;

    function text(el) {
        return (el && (el.innerText || el.textContent) || "").trim();
    }

    function important(el, styles) {
        if (!el) {
            return;
        }

        Object.keys(styles).forEach(function (key) {
            el.style.setProperty(key, styles[key], "important");
        });
    }

    function findAnswerHeading() {
        return Array.from(document.querySelectorAll("h1,h2,h3,h4")).find(function (h) {
            const value = text(h).toLowerCase();
            return value === "answer" || value === "risposta";
        });
    }

    function findAnswerCard(heading) {
        if (!heading) {
            return null;
        }

        let node = heading;

        for (let i = 0; i < 12 && node; i++) {
            const value = text(node);
            const hasMaterial = !!node.querySelector(".aisn-used-materials,.text-muted");
            const hasCode = !!node.querySelector(".aisn-code-editor");
            const hasAnswer = /Used materials|Esempio|NoSQL|MongoDB|Answer/i.test(value);

            if ((hasMaterial || hasCode) && hasAnswer) {
                return node;
            }

            node = node.parentElement;
        }

        return heading.closest(".card") || heading.parentElement;
    }

    function enhance() {
        const heading = findAnswerHeading();

        if (!heading) {
            console.warn("AISN direct style: heading Answer non trovato");
            return;
        }

        const card = findAnswerCard(heading);

        if (!card) {
            console.warn("AISN direct style: card Answer non trovata");
            return;
        }

        card.classList.add("aisn-tutor-dom-direct-card");

        important(card, {
            "box-sizing": "border-box",
            "margin-top": "32px",
            "margin-bottom": "40px",
            "padding-top": "42px",
            "padding-right": "52px",
            "padding-bottom": "48px",
            "padding-left": "52px",
            "border": "1px solid #dbe7f3",
            "border-radius": "30px",
            "background": "linear-gradient(180deg, #ffffff 0%, #f8fbff 100%)",
            "box-shadow": "0 24px 62px rgba(15, 23, 42, 0.11)",
            "overflow": "visible"
        });

        const cardBody = card.querySelector(".card-body");
        important(cardBody, {
            "padding": "0",
            "margin": "0"
        });

        important(heading, {
            "display": "flex",
            "align-items": "center",
            "gap": "14px",
            "margin-top": "0",
            "margin-right": "0",
            "margin-bottom": "26px",
            "margin-left": "0",
            "padding": "0",
            "border": "0",
            "color": "#071226",
            "font-size": "35px",
            "font-weight": "950",
            "line-height": "1.08",
            "letter-spacing": "-0.045em"
        });

        const pill =
            card.querySelector(".aisn-used-materials") ||
            Array.from(card.querySelectorAll(".text-muted,div,p")).find(function (el) {
                return /^Used materials:/i.test(text(el));
            });

        if (pill) {
            pill.classList.add("aisn-used-materials");

            important(pill, {
                "display": "inline-flex",
                "align-items": "center",
                "gap": "10px",
                "max-width": "100%",
                "margin-top": "0",
                "margin-right": "0",
                "margin-bottom": "38px",
                "margin-left": "0",
                "padding-top": "13px",
                "padding-right": "20px",
                "padding-bottom": "13px",
                "padding-left": "20px",
                "border-radius": "999px",
                "background": "#eef6ff",
                "color": "#27496d",
                "border": "1px solid #cfe6ff",
                "font-size": "14px",
                "font-weight": "850",
                "line-height": "1.4",
                "box-shadow": "0 8px 20px rgba(15, 111, 217, 0.08)"
            });
        }

        const answerBody =
            card.querySelector(".aisn-tutor-answer-body") ||
            card.querySelector(".aisn-answer") ||
            card.querySelector(".aisn-rendered-answer");

        important(answerBody, {
            "box-sizing": "border-box",
            "width": "100%",
            "max-width": "100%",
            "margin": "0",
            "padding": "0",
            "color": "#0f172a",
            "font-size": "18px",
            "line-height": "1.86"
        });

        card.querySelectorAll("p").forEach(function (p) {
            important(p, {
                "margin-top": "0",
                "margin-bottom": "22px"
            });
        });

        card.querySelectorAll("ul,ol").forEach(function (list) {
            important(list, {
                "margin-top": "12px",
                "margin-right": "0",
                "margin-bottom": "26px",
                "margin-left": "32px",
                "padding": "0"
            });
        });

        card.querySelectorAll("li").forEach(function (li) {
            important(li, {
                "margin-bottom": "10px",
                "padding-left": "4px"
            });
        });

        card.querySelectorAll("code,.aisn-inline-code").forEach(function (code) {
            if (code.closest(".aisn-code-editor")) {
                return;
            }

            important(code, {
                "padding-top": "3px",
                "padding-right": "8px",
                "padding-bottom": "3px",
                "padding-left": "8px",
                "border-radius": "8px",
                "background": "#eef2f7",
                "color": "#111827",
                "font-size": "0.92em"
            });
        });

        card.querySelectorAll(".aisn-code-editor").forEach(function (box) {
            important(box, {
                "box-sizing": "border-box",
                "width": "100%",
                "max-width": "100%",
                "min-width": "0",
                "margin-top": "30px",
                "margin-right": "0",
                "margin-bottom": "38px",
                "margin-left": "0",
                "border-radius": "22px",
                "overflow": "hidden",
                "background": "#1f2430",
                "border": "1px solid #2f3848",
                "box-shadow": "0 18px 42px rgba(15, 23, 42, 0.18)"
            });
        });

        card.querySelectorAll(".aisn-code-head").forEach(function (head) {
            important(head, {
                "padding-top": "17px",
                "padding-right": "22px",
                "padding-bottom": "17px",
                "padding-left": "22px",
                "background": "linear-gradient(180deg, #20242f 0%, #1a1e27 100%)",
                "border-bottom": "1px solid rgba(255, 255, 255, 0.08)"
            });
        });

        card.querySelectorAll(".aisn-code-lang-label").forEach(function (label) {
            important(label, {
                "font-size": "1rem",
                "font-weight": "900",
                "color": "#ffffff"
            });
        });

        card.querySelectorAll(".aisn-copy-btn").forEach(function (btn) {
            important(btn, {
                "padding-top": "8px",
                "padding-right": "15px",
                "padding-bottom": "8px",
                "padding-left": "15px",
                "border-radius": "11px",
                "border": "1px solid rgba(255, 255, 255, 0.14)",
                "background": "rgba(255, 255, 255, 0.08)",
                "color": "#f8fafc",
                "font-size": "0.88rem",
                "font-weight": "850"
            });
        });

        card.querySelectorAll(".aisn-code-editor pre").forEach(function (pre) {
            important(pre, {
                "margin": "0",
                "padding-top": "30px",
                "padding-right": "34px",
                "padding-bottom": "30px",
                "padding-left": "34px",
                "background": "#f8fafc",
                "color": "#0f172a",
                "overflow-x": "auto",
                "overflow-y": "hidden",
                "font-family": 'Consolas, Monaco, "Courier New", monospace',
                "font-size": "0.98rem",
                "line-height": "1.78",
                "white-space": "pre"
            });
        });

        card.querySelectorAll(".aisn-code-editor pre code").forEach(function (code) {
            important(code, {
                "display": "block",
                "margin": "0",
                "padding": "0",
                "background": "transparent",
                "color": "inherit",
                "font-family": "inherit",
                "font-size": "inherit",
                "line-height": "inherit",
                "white-space": "pre"
            });
        });

        console.log("AISN direct style applied", {
            cardClass: card.className,
            padding: getComputedStyle(card).padding
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", enhance);
    } else {
        enhance();
    }

    setTimeout(enhance, 250);
    setTimeout(enhance, 750);
    setTimeout(enhance, 1500);
    setTimeout(enhance, 3000);
})();
