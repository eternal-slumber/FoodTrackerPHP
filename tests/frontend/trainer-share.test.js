const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const unavailable = { hidden: true };
global.document = {
    body: { dataset: { shareState: 'unavailable' } },
    getElementById(id) {
        return id === 'trainer-share-unavailable' ? unavailable : null;
    }
};

const sourcePath = path.resolve(__dirname, '../../resources/frontend/trainer/js/share.js');
const source = fs.readFileSync(sourcePath, 'utf8');
vm.runInThisContext(
    `${source}\nglobalThis.__trainerShare = { trainerShareGetDayRingVisual };`,
    { filename: sourcePath }
);

const { trainerShareGetDayRingVisual } = global.__trainerShare;

test('calculates the visible percentage and caps the ring at one turn', () => {
    const visual = trainerShareGetDayRingVisual({ calories: 2400 }, 2000);

    assert.equal(visual.percentage, 120);
    assert.equal(visual.progress, 100);
    assert.equal(visual.color, 'over');
    assert.equal(visual.hasOver, true);
});

test('uses the same nutrition color thresholds as the main history calendar', () => {
    assert.equal(trainerShareGetDayRingVisual({ calories: 1000 }, 2000).color, 'low');
    assert.equal(trainerShareGetDayRingVisual({ calories: 1600 }, 2000).color, 'warning');
    assert.equal(trainerShareGetDayRingVisual({ calories: 2000 }, 2000).color, 'good');
    assert.equal(trainerShareGetDayRingVisual({ calories: 2200 }, 2000).color, 'over');
    assert.equal(trainerShareGetDayRingVisual({ calories: 2500 }, 2000).color, 'danger');
});

test('does not invent a percentage when the daily goal is absent', () => {
    const visual = trainerShareGetDayRingVisual({ calories: 500 }, 0);

    assert.equal(visual.hasGoal, false);
    assert.equal(visual.percentage, 0);
    assert.equal(visual.progress, 0);
});
