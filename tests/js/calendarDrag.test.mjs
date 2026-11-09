import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { movedTaskDates } from '../../resources/js/utils/calendarTaskDates.js';

const source = fs.readFileSync(new URL('../../resources/js/Pages/Centro/Index.vue', import.meta.url), 'utf8');
const handlers = source.slice(source.indexOf('function startCalendarDrag('), source.indexOf('function taskSpansDate('));

function harness() {
    class Cell {
        dataset = { calendarDate: '2026-11-12' };
        hasAttribute(name) { return name === 'data-calendar-date'; }
    }
    const state = {
        calendarDraggedTask: { value: null }, calendarMoveError: { value: '' },
        calendarDropDate: { value: null }, calendarHolidays: { value: {} },
        calendarRowOverrides: { value: { one: { due_date: '2026-11-09' }, other: {} } },
        calendarRowsWithOverrides: () => [], refreshCalendarTaskPanelFromRows: () => {},
        HTMLElement: Cell, document: { elementsFromPoint: () => [new Cell()] },
        movedTaskDates, route: (name, id) => id,
        router: { patch: (id, payload, callbacks) => { state.sent = { id, payload, callbacks }; } },
    };
    vm.createContext(state); vm.runInContext(handlers, state);
    return state;
}

test('drop keeps full duration and clears only the moved drawer snapshot after success', () => {
    const state = harness();
    const transfer = { setData(type, id) { assert.equal(type, 'text/plain'); assert.equal(id, 'one'); } };
    state.startCalendarDrag({ id: 'one', start_date: '2026-11-09', due_date: '2026-11-11' }, { dataTransfer: transfer });
    state.moveCalendarTask({ clientX: 10, clientY: 20 });
    assert.deepEqual(state.sent.payload, { start_date: '2026-11-12', due_date: '2026-11-14' });
    assert.equal(transfer.effectAllowed, 'move');
    state.sent.callbacks.onSuccess();
    assert.equal(state.calendarRowOverrides.value.one, undefined);
    assert.ok(state.calendarRowOverrides.value.other);
    state.sent.callbacks.onFinish();
    assert.equal(state.calendarDraggedTask.value, null);
});

test('rejected moves show feedback and retain the current snapshot', () => {
    const state = harness();
    state.startCalendarDrag({ id: 'one', start_date: '2026-11-09', due_date: '2026-11-09' });
    state.moveCalendarTask({ clientX: 10, clientY: 20 });
    state.sent.callbacks.onError({ due_date: 'Spostamento bloccato' });
    assert.equal(state.calendarMoveError.value, 'Spostamento bloccato');
    assert.ok(state.calendarRowOverrides.value.one);
});

test('holiday drops give feedback without sending a request', () => {
    const state = harness();
    state.calendarHolidays.value['2026-11-12'] = 'Chiusura';
    state.startCalendarDrag({ id: 'one', due_date: '2026-11-09' });
    state.moveCalendarTask({ clientX: 10, clientY: 20 });
    assert.equal(state.sent, undefined);
    assert.ok(state.calendarMoveError.value);
});
