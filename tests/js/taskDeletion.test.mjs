import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const index = fs.readFileSync(new URL('../../resources/js/Pages/Centro/Index.vue', import.meta.url), 'utf8');
const show = fs.readFileSync(new URL('../../resources/js/Pages/Centro/Show.vue', import.meta.url), 'utf8');
const between = (source, start, end) => source.slice(source.indexOf(start), source.indexOf(end));

test('calendar deletion preserves creator and waits for typed confirmation for employees and superadmin', () => {
    for (const superadmin of [false, true]) {
        const state = {
            props: { section: 'calendar' }, isSuperadmin: { value: superadmin }, isEditor: { value: !superadmin },
            page: { props: { auth: { user: { id: 'owner' } } } },
            sectionPermission: () => 'tasks.delete', hasPermission: () => true,
            deleteTarget: { value: null }, deleteTargetAction: { value: null }, deleteConfirmText: { value: '' }, archiveReason: { value: '' },
            calendarTaskForm: { id: 'task', title: 'Example' }, calendarTaskPanel: { value: { id: 'task', created_by: 'owner' } },
            calendarTaskActionMenuOpen: { value: true }, closeCalendarTaskPanel() {},
            route: (name, id) => id, routeBase: { value: 'tasks' },
            router: { delete: (id, options) => { state.sent = { id, options }; } },
        };
        vm.createContext(state);
        vm.runInContext(between(index, 'function remove(row,', 'function reviewArchiveRequest(')
            + between(index, 'function executeDelete(', 'function showRoute(')
            + between(index, 'function removeCalendarTask()', 'async function copyCalendarTaskLink('), state);
        state.removeCalendarTask();
        assert.equal(state.deleteTarget.value.created_by, 'owner');
        assert.equal(state.sent, undefined);
        state.confirmDelete();
        assert.equal(state.sent, undefined);
        state.deleteConfirmText.value = 'ELIMINA';
        state.confirmDelete();
        assert.equal(state.sent.options.data.confirmation, 'ELIMINA');
    }
});

test('task detail confirmation cannot be bypassed by superadmin', () => {
    const state = {
        isSuperadmin: { value: true }, confirmAction: { value: null }, confirmText: { value: '' },
        canDeleteCurrentTask: { value: true }, taskActionMenuOpen: { value: true },
        props: { record: { id: 'task', title: 'Example' } }, route: (name, id) => id,
        router: { delete: (id, options) => { state.sent = { id, options }; } },
    };
    vm.createContext(state);
    vm.runInContext(between(show, 'function deleteTaskFromDetail()', 'function toggleTaskActionMenu(')
        + between(show, 'function openConfirm(', 'function clientHasService('), state);
    state.deleteTaskFromDetail();
    assert.ok(state.confirmAction.value);
    state.runConfirmAction();
    assert.equal(state.sent, undefined);
    state.confirmText.value = 'ELIMINA';
    state.runConfirmAction();
    assert.equal(state.sent.options.data.confirmation, 'ELIMINA');
});
