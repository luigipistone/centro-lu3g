import assert from 'node:assert/strict';
import test from 'node:test';
import { daysBetween, movedTaskDates } from '../../resources/js/utils/calendarTaskDates.js';

test('moving a multi-day task anchors its start to the drop date and keeps its length', () => {
    const task = { start_date: '2026-10-05', due_date: '2026-10-09' };
    assert.deepEqual(movedTaskDates(task, '2026-10-15'), {
        start_date: '2026-10-15', due_date: '2026-10-19',
    });
    assert.deepEqual(movedTaskDates(task, '2026-10-30'), {
        start_date: '2026-10-30', due_date: '2026-11-03',
    });
});

test('duration stays stable across daylight-saving changes', () => {
    const task = { start_date: '2026-10-24', due_date: '2026-10-28' };
    const moved = movedTaskDates(task, '2026-03-27');
    assert.deepEqual(moved, { start_date: '2026-03-27', due_date: '2026-03-31' });
    assert.equal(daysBetween(moved.start_date, moved.due_date), 4);
});

test('single-day tasks stay single-day', () => {
    assert.deepEqual(movedTaskDates({ start_date: null, due_date: '2026-10-05' }, '2026-10-20'), {
        start_date: null, due_date: '2026-10-20',
    });
});
