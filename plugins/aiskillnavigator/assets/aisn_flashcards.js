(function () {
    'use strict';

    if (typeof window === 'undefined' || window.aisnFlashcardsLoaded) {
        return;
    }
    window.aisnFlashcardsLoaded = true;

    function shuffle(items, random) {
        var pick = typeof random === 'function' ? random : Math.random;
        var result = items.slice();

        for (var i = result.length - 1; i > 0; i--) {
            var j = Math.floor(pick() * (i + 1));
            var swap = result[i];
            result[i] = result[j];
            result[j] = swap;
        }

        return result;
    }

    function progressText(template, position, total) {
        return String(template)
            .replace('%position%', String(position))
            .replace('%total%', String(total));
    }

    function init(root) {
        var list = root.querySelector('[data-flashcards-list]');
        var controls = root.querySelector('[data-flashcards-controls]');
        var progress = root.querySelector('[data-flashcards-progress]');
        var flip = root.querySelector('[data-flashcards-flip]');
        var previous = root.querySelector('[data-flashcards-previous]');
        var next = root.querySelector('[data-flashcards-next]');
        var shuffler = root.querySelector('[data-flashcards-shuffle]');
        var cards = Array.prototype.slice.call(root.querySelectorAll('[data-flashcard]'));

        if (!list || !controls || !flip || !cards.length) {
            return;
        }

        var fliplabel = flip.querySelector('[data-flashcards-flip-label]') || flip;
        var labels = {
            show: flip.getAttribute('data-label-show') || fliplabel.textContent,
            hide: flip.getAttribute('data-label-hide') || fliplabel.textContent
        };
        var template = progress ? progress.getAttribute('data-progress-template') || '' : '';
        var current = 0;

        function answerOf(card) {
            return card.querySelector('[data-flashcard-answer]');
        }

        function face(card, revealed) {
            var pane = card.querySelector('[data-flashcard-pane]');
            var front = card.querySelector('[data-flashcard-front]');
            var back = answerOf(card);

            if (pane && pane.classList) {
                pane.classList.toggle('aisn-is-flipped', revealed);
            }

            if (front) {
                front.setAttribute('aria-hidden', revealed ? 'true' : 'false');
            }

            if (back) {
                back.setAttribute('aria-hidden', revealed ? 'false' : 'true');
            }
        }

        function render(revealed) {
            cards.forEach(function (card, index) {
                card.hidden = index !== current;
                face(card, index === current && revealed);
            });

            var answer = answerOf(cards[current]);

            fliplabel.textContent = revealed ? labels.hide : labels.show;
            flip.setAttribute('aria-expanded', revealed ? 'true' : 'false');

            if (answer && answer.id) {
                flip.setAttribute('aria-controls', answer.id);
            }

            if (progress) {
                progress.textContent = progressText(template, current + 1, cards.length);
            }

            if (previous) {
                previous.disabled = current === 0;
            }

            if (next) {
                next.disabled = current === cards.length - 1;
            }

            keepFocusUsable();
        }

        function keepFocusUsable() {
            var active = root.ownerDocument ? root.ownerDocument.activeElement : null;

            if (active === previous && previous.disabled && next) {
                next.focus();
            } else if (active === next && next.disabled && previous) {
                previous.focus();
            }
        }

        function move(step) {
            var target = current + step;

            if (target < 0 || target >= cards.length) {
                return;
            }

            current = target;
            render(false);
        }

        function toggleFlip() {
            render(flip.getAttribute('aria-expanded') !== 'true');
        }

        flip.addEventListener('click', toggleFlip);

        list.addEventListener('click', function (event) {
            var target = event.target;

            if (target && typeof target.closest === 'function' && target.closest('a, button, input, select, textarea')) {
                return;
            }

            var view = root.ownerDocument ? root.ownerDocument.defaultView : null;
            var selection = view && typeof view.getSelection === 'function' ? view.getSelection() : null;

            if (selection && String(selection) !== '') {
                return;
            }

            toggleFlip();
        });

        if (previous) {
            previous.addEventListener('click', function () {
                move(-1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                move(1);
            });
        }

        if (shuffler) {
            shuffler.addEventListener('click', function () {
                shuffle(cards, Math.random).forEach(function (card) {
                    list.appendChild(card);
                });
                cards = Array.prototype.slice.call(root.querySelectorAll('[data-flashcard]'));
                current = 0;
                render(false);
            });
        }

        root.addEventListener('keydown', function (event) {
            if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                return;
            }

            if (event.key === 'ArrowRight') {
                move(1);
            } else if (event.key === 'ArrowLeft') {
                move(-1);
            } else {
                return;
            }

            event.preventDefault();
        });

        root.setAttribute('data-flashcards-ready', '');
        controls.hidden = false;
        render(false);
    }

    function initAll(doc) {
        var target = doc || (typeof document !== 'undefined' ? document : null);

        if (!target) {
            return;
        }

        Array.prototype.slice.call(target.querySelectorAll('[data-aisn-flashcards]')).forEach(init);
    }

    window.aisnFlashcards = {shuffle: shuffle, progressText: progressText, init: init, initAll: initAll};

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                initAll(document);
            });
        } else {
            initAll(document);
        }
    }
})();
