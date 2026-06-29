const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const element = { textContent: '' };
global.document = {
    getElementById(id) {
        return id === 'target' ? element : null;
    }
};

const sourcePath = path.resolve(__dirname, '../../resources/frontend/shared/js/helpers.js');
const source = fs.readFileSync(sourcePath, 'utf8');
vm.runInThisContext(
    `${source}\nglobalThis.__helpers = { escapeHtml, setElementText, formatActivityLabel, formatGoalLabel };`,
    { filename: sourcePath }
);

const {
    escapeHtml,
    setElementText,
    formatActivityLabel,
    formatGoalLabel
} = global.__helpers;

test('escapes values inserted into generated markup', () => {
    assert.equal(escapeHtml('<b title="x">'), '&lt;b title=&quot;x&quot;&gt;');
});

test('sets text without interpreting it as HTML', () => {
    setElementText('target', '<b>Текст</b>');

    assert.equal(element.textContent, '<b>Текст</b>');
});

test('formats shared profile labels', () => {
    assert.equal(formatActivityLabel('high'), 'Высокая');
    assert.equal(formatGoalLabel('deficit'), 'Похудение');
});
