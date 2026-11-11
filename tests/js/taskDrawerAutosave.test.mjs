import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

for (const [file, start, end, name] of [
    ['Index.vue', 'function saveCalendarTaskInline(', 'function setCalendarTaskType(', 'saveCalendarTaskInline'],
    ['Show.vue', 'function saveProjectTaskDrawer(', 'function projectDrawerComments(', 'saveProjectTaskDrawer'],
]) {
    const source = readFileSync(new URL(`../../resources/js/Pages/Centro/${file}`, import.meta.url), 'utf8');
    const code = source.slice(source.indexOf(start), source.indexOf(end));
    for (const immediate of [true, false]) {
        test(`${name}: ${immediate ? 'selection saves immediately' : 'typing waits only 300ms'}`, () => {
            let requests = 0;
            let scheduled;
            const form = { id: 'task', title: 'Test', transform() { return this; }, put() { requests++; } };
            const context = {
                window: { clearTimeout() {}, setTimeout(fn, delay) { scheduled = { fn, delay }; return 1; } },
                route: () => '/tasks/task',
                calendarTaskPanel: { value: {} }, calendarTaskForm: form,
                calendarTaskAutosaveTimer: null, calendarTaskAutosaveSequence: 0,
                calendarTaskAutosaveState: { value: 'idle' }, calendarTaskAutosaveError: { value: '' },
                calendarTaskPayload: () => ({}),
                projectTaskDrawerTask: { value: { id: 'task' } }, projectTaskDrawerForm: form,
                projectTaskDrawerAutosaveTimer: null, projectTaskDrawerPayload: () => ({}),
            };
            vm.createContext(context);
            vm.runInContext(code, context);
            if (immediate) {
                context[name](0);
                assert.equal(requests, 1);
                assert.equal(scheduled, undefined);
            } else {
                context[name]();
                assert.equal(requests, 0);
                assert.equal(scheduled.delay, 300);
                scheduled.fn();
                assert.equal(requests, 1);
            }
        });
    }
}
