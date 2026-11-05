import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const theme = process.env.SEASON_QA_THEME || 'halloween';
const browser = await chromium.launch();
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await mkdir(`/tmp/${theme}-content`, { recursive: true });
async function contrast(locator) {
    for (const result of await locator.evaluateAll(elements => elements.filter(el => el.getClientRects().length).map(el => {
        const lum = color => color.match(/[\d.]+/g).slice(0,3).map(Number).map(v => v / 255 <= .04045 ? v / 3294.6 : ((v / 255 + .055) / 1.055) ** 2.4).reduce((a,v,i) => a + v * [.2126,.7152,.0722][i],0);
        let parent = el;
        while (parent && getComputedStyle(parent).backgroundColor === 'rgba(0, 0, 0, 0)') parent = parent.parentElement;
        const a = lum(getComputedStyle(el).color), b = lum(getComputedStyle(parent).backgroundColor);
        return { text: el.textContent.trim().slice(0,60), ratio: (Math.max(a,b)+.05)/(Math.min(a,b)+.05) };
    }))) assert(result.ratio >= 4.5, JSON.stringify(result));
}
try {
    await page.goto('http://127.0.0.1:8017/login');
    await page.locator('#email').fill('season-qa@example.test');
    await page.locator('#password').fill(process.env.SEASON_QA_PASSWORD);
    await page.getByRole('button', { name: 'Accedi', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await page.evaluate(async theme => {
        await window.axios.put('/settings/event-theme', { theme, scope: 'personal' }, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': JSON.parse(document.getElementById('app').dataset.page).version } });
        await window.axios.patch('/dashboard/widgets', { widgets: ['notes','password_search','my_tasks','clock_date'].map((widget_type,position) => ({ widget_type, position, visible: true, col_span: 1 })) });
    }, theme);
    for (const width of [1920,1440,390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('http://127.0.0.1:8017/dashboard');
        await page.waitForTimeout(1200);
        await page.evaluate(() => document.fonts.ready);
        await contrast(page.locator('[data-widget-type="notes"] .season-widget-title'));
        const password = page.locator('[data-widget-type="password_search"] .season-widget-title');
        assert(await password.evaluate(el => el.getBoundingClientRect().height < 30 && el.scrollWidth <= el.clientWidth + 1), `Password heading wraps at ${width}`);
        await page.screenshot({ path: `/tmp/${theme}-content/dashboard-${width}.png` });
        for (const route of ['tasks','calendar']) {
            await page.goto(`http://127.0.0.1:8017/${route}`);
            await page.waitForTimeout(1200);
            await page.locator('.app-shell').evaluate(el => {
                let c = el.__vueParentComponent;
                while (c && !c.setupState?.taskTypeClass) c = c.parent;
                const date = new Date().toLocaleDateString('en-CA');
                c.setupState.expandedCalendarDays = [date];
                c.setupState.compactWeekend = false;
                c.props.rows = ['project','ongoing','meeting'].map((task_type,i) => ({ id: `qa-${i}`, title: `${task_type} - Titolo leggibile`, task_type, status: 'todo', priority: 'medium', due_date: date, start_date: date, client_name: 'Cliente di prova', parent_task_id: null }));
            });
            await page.locator('.task-tone').first().waitFor();
            await page.waitForTimeout(1200);
            assert.equal(await page.locator('.task-tone:not(button)').count(),3);
            await contrast(page.locator('.task-tone h4'));
            await contrast(page.locator('.task-tone .text-gray-500'));
            const colors = await page.locator('.task-tone:not(button)').evaluateAll(els => els.map(el => getComputedStyle(el).backgroundColor));
            assert.equal(new Set(colors).size,3);
            await page.screenshot({ path: `/tmp/${theme}-content/${route}-${width}.png` });
        }
        await page.goto('http://127.0.0.1:8017/documents');
        await page.waitForTimeout(1200);
        await contrast(page.locator('.document-tab-count'));
        await page.evaluate(() => {
            const fixture = document.createElement('section');
            fixture.className = 'surface'; fixture.id = 'content-regression';
            fixture.style.cssText = 'position:fixed;inset:16px;z-index:20000;padding:24px;overflow:auto;display:grid;gap:20px;align-content:start';
            fixture.innerHTML = `<a class="related-project-card bg-gray-50" style="background-color:#fff;color:#111827;padding:16px"><span>Titolo progetto del cliente</span><span class="text-xs" style="color:#111827">Informazioni progetto</span></a>
              <div class="wysiwyg-content form-control" contenteditable="true"><p style="color:#111827;background:#fff"><span style="color:#000;background-color:white">Descrizione incollata da un documento</span></p></div>
              <button class="btn btn-primary">Pulsante principale</button>
              <button class="bg-blue-600 border-blue-600 text-white" style="padding:12px">Giorno lavorativo</button>
              <button class="bg-indigo-50 ring-2 ring-indigo-500 ring-offset-2 ring-offset-white" style="padding:12px">Persona selezionata</button>`;
            document.getElementById('app').append(fixture);
        });
        const fixture = page.locator('#content-regression');
        await contrast(fixture.locator('a > span,.wysiwyg-content span,button'));
        assert.equal(await fixture.locator('.btn-primary').evaluate(el => getComputedStyle(el).backgroundImage),'none');
        await fixture.locator('.btn-primary').hover();
        await contrast(fixture.locator('.btn-primary'));
        assert.equal(await fixture.locator('.ring-2').evaluate(el => getComputedStyle(el).getPropertyValue('--tw-ring-color').trim()),{ natale: '#b61636', pasqua: '#a83265', estate: '#b63e37', halloween: '#ff975a' }[theme]);
        await page.screenshot({ path: `/tmp/${theme}-content/shared-${width}.png` });
    }
    assert.deepEqual(errors,[]);
    console.log('PASS: populated tasks/calendar, notes/password widgets, document counters, project/editor/button/selection contrast at 1920/1440/390');
} finally { await browser.close(); }
