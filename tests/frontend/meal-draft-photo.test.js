const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

global.nextDraftProductId = 1;

const sourcePath = path.resolve(
    __dirname,
    '../../resources/frontend/app/js/features/meal-draft/photo.js'
);
const source = fs.readFileSync(sourcePath, 'utf8');
vm.runInThisContext(
    `${source}\nglobalThis.__mealDraftPhoto = { normalizeDraft };`,
    { filename: sourcePath }
);

const { normalizeDraft } = global.__mealDraftPhoto;

test('preserves real zero nutrition values in an analyzed draft', () => {
    const draft = normalizeDraft({
        meal_name: 'Не определено',
        products: [{
            name: 'Не определено',
            weight: 0,
            calories: 0,
            proteins: 0,
            fats: 0,
            carbs: 0
        }]
    }, 'photo');

    assert.equal(draft.products[0].weight, 0);
    assert.equal(draft.products[0].calories, 0);
    assert.equal(draft.products[0].proteins, 0);
    assert.equal(draft.products[0].fats, 0);
    assert.equal(draft.products[0].carbs, 0);
});

test('uses empty fields only for null or missing nutrition values', () => {
    const draft = normalizeDraft({
        products: [{
            name: 'Продукт',
            weight: null,
            calories: null
        }]
    }, 'manual');

    assert.equal(draft.products[0].weight, '');
    assert.equal(draft.products[0].calories, '');
    assert.equal(draft.products[0].proteins, '');
});
