// Exercise the emitted selector script without a Moodle/browser dependency.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function element(extra = {}) {
    return Object.assign({
        dataset: {}, style: {}, value: '', textContent: '', listeners: {},
        addEventListener(type, handler) { (this.listeners[type] ??= []).push(handler); },
        dispatch(type, data = {}) {
            const event = { defaultPrevented: false, ...data,
                preventDefault() { this.defaultPrevented = true; }, stopPropagation() {} };
            for (const handler of this.listeners[type] ?? []) handler(event);
            return event;
        }
    }, extra);
}
const search = element();
const summary = element();
const form = element();
const rows = [element({dataset: {search: 'database & sql'}}), element({dataset: {search: 'calculus'}})];
const boxes = [element({checked: false})];
const root = element({
    closest() { return form; },
    querySelector(selector) { return selector.includes('search') ? search : summary; },
    querySelectorAll(selector) { return selector.includes('row') ? rows : boxes; }
});
const php = fs.readFileSync(path.join(__dirname, '../plugins/aiskillnavigator/includes/simulator_materials_helper.php'), 'utf8');
const match = php.match(/\$html \.= html_writer::tag\('script', '([\s\S]*?)'\);/);
assert.ok(match, 'Material selector script exists');
let alerts = 0;
vm.runInNewContext(match[1], {
    document: { readyState: 'complete', getElementById() { return root; } },
    setTimeout() {}, alert() { alerts++; }
});
search.value = 'Database & SQL';
search.dispatch('input');
assert.equal(rows[0].style.display, '');
assert.equal(rows[1].style.display, 'none');
assert.equal(search.dispatch('keydown', {key: 'Enter'}).defaultPrevented, true, 'Enter must not submit the generation form');
search.value = '';
search.dispatch('input');
assert.ok(rows.every(row => row.style.display === ''), 'Clearing the filter restores all rows');
assert.equal(form.dispatch('submit').defaultPrevented, true, 'Generation still requires selected materials');
assert.equal(alerts, 1);
boxes[0].checked = true;
boxes[0].dispatch('change');
assert.equal(summary.textContent, '1 material(s) selected');
assert.equal(form.dispatch('submit').defaultPrevented, false, 'Selecting material still permits generation');
console.log('simulator_material_filter_test: OK');
