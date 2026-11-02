const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../plugins/aiskillnavigator/assets/aisn_flashcards.js'), 'utf8');
const context = {window: {}};
vm.createContext(context);
vm.runInContext(source, context);
const api = context.window.aisnFlashcards;

const items = ['a', 'b', 'c', 'd', 'e'];
const reversing = () => 0;
const shuffled = api.shuffle(items, reversing);
assert.deepEqual(items, ['a', 'b', 'c', 'd', 'e'], 'The source array is left untouched');
assert.equal(shuffled.length, items.length, 'No card is lost or repeated');
assert.deepEqual([...shuffled].sort(), [...items].sort(), 'The same cards come back in a new order');
for (let run = 0; run < 50; run++) {
    assert.deepEqual([...api.shuffle(items)].sort(), [...items].sort(), 'Random shuffles keep every card');
}
assert.deepEqual(api.shuffle([], reversing), [], 'An empty deck shuffles to an empty deck');
assert.deepEqual(api.shuffle(['only'], reversing), ['only'], 'A one-card deck survives shuffling');

assert.equal(api.progressText('Carta %position% di %total%', 2, 7), 'Carta 2 di 7', 'Both placeholders are resolved');

let selected = '';
const view = {getSelection: () => ({toString: () => selected})};
const doc = {activeElement: null, defaultView: view};
function classList() {
    const names = new Set();
    return {
        add(name) { names.add(name); },
        remove(name) { names.delete(name); },
        toggle(name, on) { return on ? names.add(name) : names.delete(name); },
        contains(name) { return names.has(name); }
    };
}
function element(attrs = {}) {
    return {
        attrs, hidden: false, disabled: false, textContent: '', id: attrs.id || '', ownerDocument: doc, listeners: {},
        classList: classList(),
        getAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null; },
        setAttribute(name, value) { this.attrs[name] = String(value); },
        addEventListener(type, handler) { (this.listeners[type] ??= []).push(handler); },
        focus() { doc.activeElement = this; },
        dispatch(type, data = {}) {
            const event = {defaultPrevented: false, ...data, preventDefault() { this.defaultPrevented = true; }};
            for (const handler of this.listeners[type] ?? []) { handler(event); }
            return event;
        },
        querySelector() { return null; }
    };
}
function card(name) {
    const front = element();
    const answer = element({id: 'answer-' + name});
    const pane = element();
    const faces = {'[data-flashcard-pane]': pane, '[data-flashcard-front]': front, '[data-flashcard-answer]': answer};
    const node = element({'data-flashcard': ''});
    node.name = name;
    node.answer = answer;
    node.front = front;
    node.pane = pane;
    node.flipped = () => pane.classList.contains('aisn-is-flipped');
    node.querySelector = (selector) => faces[selector] ?? null;
    return node;
}

const cards = [card('one'), card('two'), card('three')];
let order = cards.slice();
const list = element();
list.appendChild = (node) => { order = order.filter((item) => item !== node).concat([node]); };
const controls = element();
controls.hidden = true;
const progress = element({'data-progress-template': 'Card %position% of %total%'});
const fliplabel = element();
const flip = element({'data-label-show': 'Show answer', 'data-label-hide': 'Hide answer'});
flip.querySelector = (selector) => (selector === '[data-flashcards-flip-label]' ? fliplabel : null);
const previous = element();
const next = element();
const shuffler = element();
const nodes = {
    '[data-flashcards-list]': list,
    '[data-flashcards-controls]': controls,
    '[data-flashcards-progress]': progress,
    '[data-flashcards-flip]': flip,
    '[data-flashcards-previous]': previous,
    '[data-flashcards-next]': next,
    '[data-flashcards-shuffle]': shuffler
};
const root = element();
root.querySelector = (selector) => nodes[selector] ?? null;
root.querySelectorAll = (selector) => (selector === '[data-flashcard]' ? order.slice() : []);

api.init(root);

assert.equal(controls.hidden, false, 'Controls are revealed only once the script runs');
assert.equal(order[0].hidden, false, 'The first card is visible');
assert.equal(order[1].hidden, true, 'Other cards stay hidden');
assert.equal(order[0].flipped(), false, 'The card starts on its question');
assert.equal(order[0].answer.getAttribute('aria-hidden'), 'true', 'The answer is hidden from assistive technology');
assert.equal(order[0].front.getAttribute('aria-hidden'), 'false', 'The question is readable');
assert.equal(root.getAttribute('data-flashcards-ready'), '', 'The deck is marked as script driven');
assert.equal(flip.getAttribute('aria-expanded'), 'false', 'The toggle reports a hidden answer');
assert.equal(flip.getAttribute('aria-controls'), 'answer-one', 'The toggle points at the visible answer');
assert.equal(progress.textContent, 'Card 1 of 3', 'Progress starts at the first card');
assert.equal(previous.disabled, true, 'There is no card before the first one');
assert.equal(next.disabled, false, 'A next card is available');

flip.dispatch('click');
assert.equal(order[0].flipped(), true, 'Flipping turns the card over');
assert.equal(order[0].answer.getAttribute('aria-hidden'), 'false', 'The revealed answer is readable');
assert.equal(order[0].front.getAttribute('aria-hidden'), 'true', 'The turned-away question is hidden');
assert.equal(flip.getAttribute('aria-expanded'), 'true', 'The toggle reports a revealed answer');
assert.equal(fliplabel.textContent, 'Hide answer', 'The toggle is renamed when the answer is visible');
flip.dispatch('click');
assert.equal(order[0].flipped(), false, 'Flipping again turns the card back');
assert.equal(order[0].answer.getAttribute('aria-hidden'), 'true', 'The answer is hidden again');
assert.equal(fliplabel.textContent, 'Show answer', 'The toggle name is restored');

list.dispatch('click', {target: order[0].front});
assert.equal(order[0].flipped(), true, 'Clicking the card turns it over');
list.dispatch('click', {target: order[0].answer});
assert.equal(order[0].flipped(), false, 'Clicking the turned card turns it back');
selected = 'An open-source';
list.dispatch('click', {target: order[0].front});
assert.equal(order[0].flipped(), false, 'Selecting the card text does not turn it over');
selected = '';
list.dispatch('click', {target: {closest: () => ({})}});
assert.equal(order[0].flipped(), false, 'A click on a control inside the card is left alone');

flip.dispatch('click');
next.dispatch('click');
assert.equal(progress.textContent, 'Card 2 of 3', 'The counter follows the visible card');
assert.equal(order[1].hidden, false, 'The next card is visible');
assert.equal(order[1].flipped(), false, 'A new card starts on its question');
assert.equal(order[0].flipped(), false, 'The card left behind is turned back');
assert.equal(previous.disabled, false, 'Going back is possible from the second card');
next.dispatch('click');
assert.equal(progress.textContent, 'Card 3 of 3', 'The last card is reported');
assert.equal(next.disabled, true, 'There is no card after the last one');
next.dispatch('click');
assert.equal(progress.textContent, 'Card 3 of 3', 'Moving past the last card does nothing');
previous.dispatch('click');
assert.equal(progress.textContent, 'Card 2 of 3', 'Going back works');

assert.equal(root.dispatch('keydown', {key: 'ArrowRight'}).defaultPrevented, true, 'The right arrow is handled');
assert.equal(progress.textContent, 'Card 3 of 3', 'The right arrow moves forward');
assert.equal(root.dispatch('keydown', {key: 'ArrowLeft'}).defaultPrevented, true, 'The left arrow is handled');
assert.equal(progress.textContent, 'Card 2 of 3', 'The left arrow moves back');
assert.equal(root.dispatch('keydown', {key: 'ArrowRight', ctrlKey: true}).defaultPrevented, false,
    'A browser shortcut is left alone');
assert.equal(root.dispatch('keydown', {key: 'a'}).defaultPrevented, false, 'Typing is left alone');

shuffler.dispatch('click');
assert.equal(order.length, cards.length, 'Shuffling keeps the deck size');
assert.deepEqual(order.map((item) => item.name).sort(), cards.map((item) => item.name).sort(),
    'Shuffling keeps exactly the same cards');
assert.equal(progress.textContent, 'Card 1 of 3', 'Shuffling restarts the deck');
assert.equal(order[0].hidden, false, 'The first card of the shuffled deck is visible');

doc.activeElement = next;
next.dispatch('click');
next.dispatch('click');
assert.equal(doc.activeElement, previous, 'Focus moves off the control that became disabled');

console.log('flashcards_controls_test: OK');
