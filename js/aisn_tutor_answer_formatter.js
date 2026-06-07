(function () {
    'use strict';

    if (window.aisnTutorAnswerFormatterLoaded) {
        return;
    }

    window.aisnTutorAnswerFormatterLoaded = true;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function inlineFormat(value) {
        let html = escapeHtml(value);

        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

        return html;
    }

    function normalizeTutorText(text) {
        text = String(text || '')
            .replace(/\r\n/g, '\n')
            .replace(/\n{3,}/g, '\n\n')
            .trim();

        // Se l'AI ha prodotto un blocco enorme senza markdown, prova a spezzare i punti chiave.
        text = text
            .replace(/\s+(Caratteristiche principali[^:]*:)/gi, '\n\n$1')
            .replace(/\s+(Quando si usa[^:?]*\??)/gi, '\n\n$1')
            .replace(/\s+(Esempi[^:]*:)/gi, '\n\n$1')
            .replace(/\s+(Differenza[^:]*:)/gi, '\n\n$1')
            .replace(/\s+(In sintesi[,:\s])/gi, '\n\nIn sintesi: ')
            .replace(/\.\s+(Schema flessibile:)/gi, '.\n- $1')
            .replace(/\.\s+(Alta scalabilità:)/gi, '.\n- $1')
            .replace(/\.\s+(Scalabilità:)/gi, '.\n- $1')
            .replace(/\.\s+(Velocità:)/gi, '.\n- $1')
            .replace(/\.\s+(Supporto[^:]*:)/gi, '.\n- $1')
            .replace(/\.\s+(MongoDB|Cassandra|HBase)\b/gi, '.\n- $1');

        return text;
    }

    function renderText(text) {
        text = normalizeTutorText(text);

        if (!text) {
            return '';
        }

        const lines = text.split('\n').map(function (line) {
            return line.trim();
        }).filter(Boolean);

        let html = '';
        let listOpen = false;

        function closeList() {
            if (listOpen) {
                html += '</ul>';
                listOpen = false;
            }
        }

        lines.forEach(function (line) {
            const bullet = line.match(/^[-•*]\s+(.+)$/);
            const numbered = line.match(/^\d+[.)]\s+(.+)$/);

            if (bullet || numbered) {
                if (!listOpen) {
                    html += '<ul>';
                    listOpen = true;
                }

                const value = bullet ? bullet[1] : numbered[1];
                html += '<li>' + inlineFormat(value) + '</li>';
                return;
            }

            closeList();

            if (/^#{2,4}\s+/.test(line)) {
                html += '<h3>' + inlineFormat(line.replace(/^#{2,4}\s+/, '')) + '</h3>';
                return;
            }

            if (line.length <= 80 && /:$/.test(line)) {
                html += '<h3>' + inlineFormat(line.replace(/:$/, '')) + '</h3>';
                return;
            }

            if (/^in sintesi[:\s]/i.test(line)) {
                html += '<div class="aisn-summary">' + inlineFormat(line) + '</div>';
                return;
            }

            if (/^[A-ZÀ-Ú][^:]{2,45}:\s+/.test(line)) {
                html += '<div class="aisn-keypoint">' + inlineFormat(line) + '</div>';
                return;
            }

            html += '<p>' + inlineFormat(line) + '</p>';
        });

        closeList();

        return html;
    }

    function findAnswerCards() {
        const headings = Array.from(document.querySelectorAll('h1, h2, h3'));
        const cards = [];

        headings.forEach(function (heading) {
            const text = (heading.textContent || '').trim().toLowerCase();

            if (text !== 'answer' && text !== 'risposta') {
                return;
            }

            let current = heading.parentElement;

            for (let i = 0; i < 7 && current; i++) {
                const currentText = current.innerText || '';

                if (/Used materials:/i.test(currentText) && !current.querySelector('textarea')) {
                    cards.push(current);
                    return;
                }

                current = current.parentElement;
            }
        });

        return cards;
    }

    function enhanceAnswerCard(card) {
        if (!card || card.dataset.aisnTutorFormatted === '1') {
            return;
        }

        const fullText = (card.innerText || '').trim();

        if (!fullText || !/Used materials:/i.test(fullText)) {
            return;
        }

        const lines = fullText.split('\n').map(function (line) {
            return line.trim();
        }).filter(Boolean);

        let usedMaterials = '';
        const answerLines = [];

        lines.forEach(function (line) {
            if (/^(Answer|Risposta)$/i.test(line)) {
                return;
            }

            if (/^Used materials:/i.test(line)) {
                usedMaterials = line;
                return;
            }

            answerLines.push(line);
        });

        const answerText = answerLines.join('\n\n').trim();

        if (!answerText) {
            return;
        }

        card.dataset.aisnTutorFormatted = '1';
        card.classList.add('aisn-tutor-answer-card');

        card.innerHTML =
            '<h2 class="aisn-tutor-answer-title">Answer</h2>' +
            (usedMaterials ? '<div class="aisn-used-materials">' + escapeHtml(usedMaterials.replace(/^Used materials:\s*/i, '')) + '</div>' : '') +
            '<div class="aisn-rendered-answer">' + renderText(answerText) + '</div>';
    }

    function run() {
        findAnswerCards().forEach(enhanceAnswerCard);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    setTimeout(run, 400);
    setTimeout(run, 1200);
})();
