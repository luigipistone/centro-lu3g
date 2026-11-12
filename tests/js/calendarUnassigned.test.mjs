import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/Pages/Centro/Index.vue', import.meta.url), 'utf8');
function setup() {
    const context = vm.createContext({
        calendarUnassignedOnly: { value: false },
        calendarUserIds: { value: ['me'] },
        calendarType: { value: 'all' },
        calendarRowsWithOverrides: () => [
            { id: 'assigned', title: 'A', assignee_ids: ['me'], task_type: 'task' },
            { id: 'empty', title: 'B', assignee_ids: [], task_type: 'task' },
            { id: 'followers-only', title: 'C', follower_ids: ['me'], task_type: 'meeting' },
        ],
        taskSpansDate: () => true, taskSpanRole: () => 'single', normalizeCalendarTask: row => row,
    });
    for (const name of ['tasksForDay', 'toggleCalendarUserFilter']) {
        vm.runInContext(source.match(new RegExp('function ' + name + '\\([^]*?\\n}'))[0], context);
    }
    return context;
}
const ids = c => Array.from(c.tasksForDay('2026-11-12'), row => row.id);
test('default user filter includes assigned and followed tasks', () => {
    assert.deepEqual(ids(setup()), ['assigned', 'followers-only']);
});
test('unassigned means no assignees, even with followers, and respects task type', () => {
    const c = setup(); c.calendarUnassignedOnly.value = true; c.calendarUserIds.value = [];
    assert.deepEqual(ids(c), ['empty', 'followers-only']);
    c.calendarType.value = 'meeting';
    assert.deepEqual(ids(c), ['followers-only']);
});
test('selecting an avatar exits unassigned mode', () => {
    const c = setup(); c.calendarUnassignedOnly.value = true; c.calendarUserIds.value = [];
    c.toggleCalendarUserFilter('me');
    assert.equal(c.calendarUnassignedOnly.value, false);
    assert.deepEqual(ids(c), ['assigned', 'followers-only']);
});
test('clearing filters restores all tasks', () => {
    const c = setup(); c.calendarUserIds.value = [];
    assert.deepEqual(ids(c), ['assigned', 'empty', 'followers-only']);
});
