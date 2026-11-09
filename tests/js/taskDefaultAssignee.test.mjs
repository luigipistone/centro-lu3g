import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const index = fs.readFileSync(new URL('../../resources/js/Pages/Centro/Index.vue', import.meta.url), 'utf8');
const show = fs.readFileSync(new URL('../../resources/js/Pages/Centro/Show.vue', import.meta.url), 'utf8');
const part = (text, from, to) => text.slice(text.indexOf(from), text.indexOf(to));

test('new task form defaults to the authenticated user, not the user filter', () => {
    const state = { page: { props: { auth: { user: { id: 'me' } } } }, props: { section: 'tasks', fields: [] }, computed: fn => ({ value: fn() }) };
    vm.createContext(state);
    vm.runInContext(part(index, 'const defaults = computed(', 'const form = useForm(') + '\nglobalThis.result = defaults.value;', state);
    assert.equal(state.result.assignee_ids.join(), 'me');
    state.result.assignee_ids = [];
    assert.equal(state.result.assignee_ids.length, 0);
});

test('calendar creation resets assignee to self for each task type', () => {
    const state = {
        page: { props: { auth: { user: { id: 'me' } } } }, calendarHolidays: { value: {} },
        window: { clearTimeout() {} }, calendarTaskAutosaveTimer: null,
        calendarTaskForm: { defaults() {}, reset() {}, clearErrors() {} },
        calendarCommentForm: { reset() {} }, calendarSubtaskForm: { reset() {} },
        hydrateCalendarTaskRelated() {}, refreshCalendarTaskDescriptionEditor() {}, refreshCalendarCommentEditor() {},
    };
    for (const key of ['calendarCreateDate', 'calendarTaskParentStack', 'calendarShowAllComments', 'calendarShowAllActivity', 'calendarTaskActionMenuOpen', 'calendarTaskPanelMode', 'calendarTaskPanelClosedByUser', 'calendarTaskPanel', 'calendarTaskPanelOpen', 'calendarTaskAutosaveState', 'calendarTaskAutosaveError']) state[key] = { value: null };
    vm.createContext(state);
    vm.runInContext(part(index, 'function openCalendarTaskCreate(', 'function closeCalendarTaskPanel('), state);
    for (const type of ['task', 'ongoing', 'meeting']) {
        state.openCalendarTaskCreate(type, '2026-11-09');
        assert.equal(state.calendarTaskPanel.value.assignee_ids.join(), 'me');
        state.calendarTaskPanel.value.assignee_ids = ['someone-else'];
    }
});

test('quick creation inside project sends creator as assignee', () => {
    const state = {
        page: { props: { auth: { user: { id: 'me' } } } }, props: { record: { id: 'project' } },
        isGuest: { value: false }, projectTaskDrafts: { value: { section: 'New task' } },
        projectForm: { client_id: 'client' }, route: name => name,
        router: { post: (url, payload) => { state.sent = payload; } },
    };
    vm.createContext(state);
    vm.runInContext(part(show, 'function addProjectTask(', 'function startProjectTaskDrag('), state);
    state.addProjectTask({ id: 'section' });
    assert.equal(state.sent.assignee_ids.join(), 'me');
});
