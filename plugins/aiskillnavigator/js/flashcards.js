function local_aiskillnavigator_init_flashcards(Y, decks) {
    'use strict';

    if (!decks || decks.length === 0) {
        return;
    }

    const deckSelector = document.getElementById('deck-selector');
    const deckDescription = document.getElementById('deck-description');
    const flashcard = document.getElementById('flashcard');
    const cardQuestion = document.getElementById('card-question');
    const cardAnswer = document.getElementById('card-answer');
    const cardProgress = document.getElementById('card-progress-info');
    const ariaLiveRegion = document.getElementById('aisn-aria-live-region');

    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnFlip = document.getElementById('btn-flip');
    const btnShuffle = document.getElementById('btn-shuffle');

    let currentDeck = decks[0];
    let activeCards = [...currentDeck.cards];
    let currentIndex = 0;
    let isFlipped = false;

    function announceAccessibility(text) {
        if (ariaLiveRegion) {
            ariaLiveRegion.textContent = text;
        }
    }

    function updateCardUI(announceChange = false, announcePrefix = '') {
        if (activeCards.length === 0) {
            return;
        }

        const card = activeCards[currentIndex];
        cardQuestion.textContent = card.question;
        cardAnswer.textContent = card.answer;

        const total = activeCards.length;
        const progressText = `Carta ${currentIndex + 1} di ${total}`;
        cardProgress.textContent = progressText;

        // Reset flip state visually and update standard ARIA attributes.
        if (isFlipped) {
            flashcard.classList.add('is-flipped');
            flashcard.setAttribute('aria-label', `${progressText}. Lato Risposta. Premi Invio o Spazio per girare.`);
        } else {
            flashcard.classList.remove('is-flipped');
            flashcard.setAttribute('aria-label', `${progressText}. Lato Domanda. Premi Invio o Spazio per girare.`);
        }

        // Accessibility live region announcement.
        if (announceChange) {
            let message = announcePrefix ? announcePrefix + ' ' : '';
            if (isFlipped) {
                message += `Risposta: ${card.answer}`;
            } else {
                message += `${progressText}. Domanda: ${card.question}`;
            }
            announceAccessibility(message);
        }
    }

    function loadDeck(deckTitle) {
        const found = decks.find(d => d.title === deckTitle);
        if (found) {
            currentDeck = found;
            deckDescription.textContent = found.description || '';
            activeCards = [...found.cards];
            currentIndex = 0;
            isFlipped = false;
            updateCardUI(true, `Mazzo caricato: ${found.title}.`);
        }
    }

    function toggleFlip() {
        isFlipped = !isFlipped;
        updateCardUI(true);
    }

    function nextCard() {
        if (activeCards.length === 0) return;
        currentIndex = (currentIndex + 1) % activeCards.length;
        isFlipped = false;
        updateCardUI(true);
    }

    function prevCard() {
        if (activeCards.length === 0) return;
        currentIndex = (currentIndex - 1 + activeCards.length) % activeCards.length;
        isFlipped = false;
        updateCardUI(true);
    }

    function shuffleDeck() {
        if (activeCards.length <= 1) return;
        
        // Fisher-Yates shuffle algorithm.
        for (let i = activeCards.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [activeCards[i], activeCards[j]] = [activeCards[j], activeCards[i]];
        }

        currentIndex = 0;
        isFlipped = false;
        updateCardUI(true, 'Mazzo mescolato.');
    }

    // Event Listeners.
    if (deckSelector) {
        deckSelector.addEventListener('change', function(e) {
            loadDeck(e.target.value);
        });
        // Set initial description.
        deckDescription.textContent = currentDeck.description || '';
    }

    if (flashcard) {
        flashcard.addEventListener('click', toggleFlip);
        flashcard.addEventListener('keydown', function(e) {
            // Enter (13) or Space (32).
            if (e.key === ' ' || e.key === 'Spacebar' || e.keyCode === 32 || e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                toggleFlip();
            }
        });
    }

    if (btnFlip) btnFlip.addEventListener('click', toggleFlip);
    if (btnNext) btnNext.addEventListener('click', nextCard);
    if (btnPrev) btnPrev.addEventListener('click', prevCard);
    if (btnShuffle) btnShuffle.addEventListener('click', shuffleDeck);

    // Initial render.
    updateCardUI(false);
}
