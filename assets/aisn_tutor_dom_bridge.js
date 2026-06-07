(function () {
    "use strict";

    if (window.aisnTutorDomBridgeLoaded) {
        return;
    }

    window.aisnTutorDomBridgeLoaded = true;

    function text(el) {
        return (el && (el.innerText || el.textContent) || "").trim();
    }

    function findAnswerHeading() {
        return Array.from(document.querySelectorAll("h1,h2,h3,h4")).find(function (h) {
            return text(h).toLowerCase() === "answer" || text(h).toLowerCase() === "risposta";
        });
    }

    function findBestCard(heading) {
        if (!heading) {
            return null;
        }

        let node = heading;

        for (let i = 0; i < 10 && node; i++) {
            const hasMaterials = !!node.querySelector(".aisn-used-materials,.text-muted");
            const hasRenderer = !!node.querySelector(".aisn-rendered-answer,.aisn-answer,.aisn-code-editor");
            const hasAnswerText = /Used materials|Esempio|NoSQL|MongoDB|Answer/i.test(text(node));

            if ((hasMaterials || hasRenderer) && hasAnswerText) {
                return node;
            }

            node = node.parentElement;
        }

        return heading.closest(".card") || heading.parentElement;
    }

    function enhance() {
        const heading = findAnswerHeading();

        if (!heading) {
            return;
        }

        const card = findBestCard(heading);

        if (!card) {
            return;
        }

        card.classList.add("aisn-tutor-dom-card");
        heading.classList.add("aisn-tutor-dom-title");

        const body =
            card.querySelector(".aisn-tutor-answer-body") ||
            card.querySelector(".aisn-answer") ||
            card.querySelector(".aisn-rendered-answer");

        if (body) {
            body.classList.add("aisn-tutor-dom-body");
        }

        const pill =
            card.querySelector(".aisn-used-materials") ||
            Array.from(card.querySelectorAll(".text-muted,div,p")).find(function (el) {
                return /^Used materials:/i.test(text(el));
            });

        if (pill) {
            pill.classList.add("aisn-used-materials");
        }

        card.querySelectorAll(".aisn-code-editor").forEach(function (code) {
            code.style.maxWidth = "100%";
            code.style.boxSizing = "border-box";
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
    setTimeout(enhance, 2500);
})();
