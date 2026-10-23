import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isTelevision, placePhysicalItems } from '../../resources/js/Components/inventory/inventoryLayout.js';

test('recognizes televisions by name or category', () => {
    assert.equal(isTelevision({ name: 'TV Sony Bravia' }), true);
    assert.equal(isTelevision({ name: 'Schermo', category: 'Televisore' }), true);
    assert.equal(isTelevision({ name: 'Monitor HP' }), false);
});

test('a television occupies two adjacent places and moves following devices', () => {
    const placements = placePhysicalItems([
        { name: 'iPhone', quantity: 1 },
        { name: 'TV Sony Bravia', quantity: 1 },
        { name: 'MacBook', quantity: 1 },
    ]);
    assert.deepEqual(placements.map(({ slot, span }) => [slot, span]), [[0, 1], [1, 2], [3, 1]]);
});

test('a television skips the final place in a row', () => {
    const placements = placePhysicalItems([
        { name: 'iMac', quantity: 2 },
        { name: 'TV Sony Bravia', quantity: 1 },
        { name: 'iPad', quantity: 1 },
    ]);
    assert.deepEqual(placements.map(({ slot, span }) => [slot, span]), [[0, 1], [1, 1], [3, 2], [5, 1]]);
});
