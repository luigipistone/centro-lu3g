import { test } from 'node:test';
import assert from 'node:assert/strict';
import { clientOptions } from '../../resources/js/utils/clientOptions.js';

test('parents and children remain individually selectable in one tree', () => {
    const rows = [{ id: 'c', name: 'Child', parent_client_id: 'p' }, { id: 'p', name: 'Parent' }, { id: 'q', name: 'Other' }];
    const options = clientOptions(rows, { value: '', label: 'None' });
    assert.equal(options.length, 3);
    assert.equal(options[1].value, 'p');
    assert.deepEqual(options[1].children, [{ value: 'c', label: 'Child' }]);
    assert.equal(rows.length, 3);
});

test('permission-filtered child stays selectable without exposing its parent', () => {
    assert.deepEqual(clientOptions([{ id: 'c', name: 'Child', parent_client_id: 'hidden' }]), [{ value: 'c', label: 'Child', children: [] }]);
});

test('flat user options retain their labels and single values', () => {
    assert.deepEqual(clientOptions([{ id: 'u', email: 'test@example.com' }]), [{ value: 'u', label: 'test@example.com', children: [] }]);
});
