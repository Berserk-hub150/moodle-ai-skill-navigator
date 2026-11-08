// Exercise the production script without Moodle or a browser dependency.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname,
    '../plugins/aiskillnavigator/assets/js/quiz_generator.js'), 'utf8');
function element(extra = {}) {
    const classes = new Set();
    return Object.assign({listeners: {}, attrs: {}, dataset: {}, textContent: '',
        classList: {add(...cs) {cs.forEach(c => classes.add(c));}, remove(...cs) {cs.forEach(c => classes.delete(c));}, contains(c) {return classes.has(c);}},
        addEventListener(type, fn) {(this.listeners[type] ??= []).push(fn);},
        setAttribute(k, v) {this.attrs[k] = v;}, removeAttribute(k) {delete this.attrs[k];},
        dispatch(type, options = {}) {
            const event = {defaultPrevented: false, preventDefault() {this.defaultPrevented = true;}, ...options};
            for (const fn of this.listeners[type] ?? []) {fn(event);}
            return event;
        }
    }, extra);
}
function setup(loading = false, missing = false) {
    const form = element();
    const submit = element({value: 'Generate test', disabled: false, dataset: {loadingLabel: 'Generating quiz...'}});
    const status = element({dataset: {message: 'Please wait for your quiz.'}});
    const window = element();
    const document = element({readyState: loading ? 'loading' : 'complete',
        getElementById(id) {return missing ? null : ({
            'aisn-quiz-generator-form': form, 'aisn-quiz-generator-submit': submit,
            'aisn-quiz-generator-status': status
        })[id];}});
    vm.runInNewContext(script, {document, window, Promise});
    return {form, submit, status, window, document};
}
(async function() {
    const ui = setup(true);
    assert.equal(ui.form.listeners.submit, undefined);
    ui.document.dispatch('DOMContentLoaded');
    assert.equal(ui.submit.disabled, false);
    assert.equal(ui.status.textContent, '');
    assert.equal(ui.form.dispatch('submit', {defaultPrevented: true}).defaultPrevented, true);
    assert.equal(ui.submit.disabled, false, 'Canceled validation must not lock the form');
    assert.equal(ui.form.dispatch('submit').defaultPrevented, false, 'Allow native navigation');
    assert.equal(ui.submit.disabled, true);
    assert.equal(ui.submit.value, 'Generating quiz...');
    assert.equal(ui.status.textContent, 'Please wait for your quiz.');
    assert.equal(ui.status.classList.contains('alert'), true);
    assert.equal(ui.form.attrs['aria-busy'], 'true');
    assert.equal(ui.form.dispatch('submit').defaultPrevented, true, 'Reject repeated keyboard/requestSubmit events');
    await Promise.resolve();
    assert.equal(ui.submit.disabled, true, 'A rejected duplicate must not unlock an in-flight submission');
    ui.window.dispatch('pageshow', {persisted: true});
    assert.equal(ui.submit.disabled, false, 'History restoration permits retry');
    assert.equal(ui.submit.value, 'Generate test');
    assert.equal(ui.status.textContent, '');
    assert.equal(ui.form.attrs['aria-busy'], undefined);
    assert.equal(ui.form.dispatch('submit').defaultPrevented, false, 'Retry after history restoration');
    const late = setup();
    late.form.addEventListener('submit', event => event.preventDefault());
    late.form.dispatch('submit');
    await Promise.resolve();
    assert.equal(late.submit.disabled, false, 'Later validation cancellation permits retry');
    const fresh = setup();
    assert.equal(fresh.submit.disabled, false, 'A server error response renders a fresh enabled form');
    assert.equal(fresh.status.textContent, '');
    setup(false, true);
    console.log('quiz_generator_test: OK');
})().catch(error => {console.error(error); process.exitCode = 1;});
