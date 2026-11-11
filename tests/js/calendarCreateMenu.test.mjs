import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/Pages/Centro/Index.vue', import.meta.url), 'utf8');
const functions = source.slice(source.indexOf('function openCalendarCreateMenu('), source.indexOf('function closeCalendarCreateMenuOnOutside('));

for (const width of [390, 1440]) {
    test(`calendar create menu stays inside ${width}px viewport`, () => {
        for (const rect of [{ right: 80, top: 100, bottom: 125 }, { right: width, top: 760, bottom: 790 }]) {
            const context = { calendarHolidays: { value: {} }, calendarCreateDate: { value: null }, calendarCreateMenuStyle: { value: {} }, requestFloatingUiClose() {}, window: { innerWidth: width, innerHeight: 800 } };
            vm.createContext(context);
            vm.runInContext(functions, context);
            context.openCalendarCreateMenu('2026-11-11', { currentTarget: { getBoundingClientRect: () => rect } });
            const { left, top } = context.calendarCreateMenuStyle.value;
            assert.ok(parseFloat(left) >= 12);
            assert.ok(parseFloat(left) + 176 <= width - 12);
            assert.ok(parseFloat(top) >= 12);
            assert.ok(parseFloat(top) + 116 <= 788);
            context.dismissCalendarCreateMenu({ type: 'keydown', key: 'Escape' });
            assert.equal(context.calendarCreateDate.value, null);
        }
    });
}

test('calendar create menu is teleported outside the clipping calendar', () => {
    assert.match(source, /<Teleport to="body">\s*<div\s+v-if="calendarCreateDate === cell.date"/);
    assert.match(source, /class="app-popover fixed z-\[7600\]/);
});
