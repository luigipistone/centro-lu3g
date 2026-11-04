import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8017';
const output = process.env.SEASON_SCREENSHOTS || '/tmp/centro-season-real';
await mkdir(output, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = []; page.on('pageerror', e => errors.push(e.message));
try {
    await page.goto(`${base}/login`);
    await page.locator('#email').fill('season-qa@example.test');
    await page.locator('#password').fill(process.env.SEASON_QA_PASSWORD);
    await page.getByRole('button', { name: 'Accedi', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await page.evaluate(async () => {
        const types = ['clock_date', 'quick_links', 'stat_open_tasks', 'stat_clients', 'my_tasks', 'active_projects'];
        await window.axios.patch('/dashboard/widgets', { widgets: types.map((widget_type, position) => ({ widget_type, position, visible: true, col_span: 1 })) });
        await window.axios.patch('/dashboard/widgets/quick_links/settings', { links: [{ label: 'Repository Web', url: 'https://github.com/lu3gofficial' }] });
    });
    for (const theme of ['halloween', 'natale', 'pasqua', 'estate']) {
        await page.evaluate(async theme => { await window.axios.put('/settings/event-theme', { theme }, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': JSON.parse(document.getElementById('app').dataset.page).version } }); }, theme);
        for (const dark of [false, true]) {
            await page.evaluate(dark => localStorage.setItem('centro:theme', dark ? 'dark' : 'light'), dark);
            for (const route of ['/dashboard', '/settings?tab=personalizzazione', '/clients']) {
                await page.goto(base + route);
                await page.waitForFunction(() => {
                    const shell = document.querySelector('.desktop-shell');
                    return shell && parseFloat(getComputedStyle(shell).paddingLeft) >= 256;
                });
                await page.waitForTimeout(1000);
                assert.equal(await page.locator('html').getAttribute('data-event-theme'), theme);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${theme} ${route} overflow`);
                const name = route.split('?')[0].slice(1);
                assert(await page.locator('.desktop-shell > header').evaluate(e => e.getBoundingClientRect().left >= 256), 'Header clears sidebar');
                await page.screenshot({ path: `${output}/${theme}-${dark ? 'dark' : 'light'}-${name}.png`, animations: 'disabled' });
            }
        }
        console.log(`PASS pages: ${theme}`);
    }
    await page.goto(`${base}/dashboard`);
    await page.locator('.season-fullscreen').waitFor({ state: 'attached' });
    await page.waitForTimeout(1000);
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('centro:task-completed')));
    await page.waitForTimeout(300);
    const ink = () => page.locator('.season-fullscreen').evaluate(c => c.getContext('2d').getImageData(0, 0, c.width, c.height).data.some(v => v));
    assert(await ink(), 'Real completion event triggers effect');
    await page.waitForTimeout(1600); assert(!(await ink()), 'Effect clears');
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        for (const route of ['/dashboard', '/settings?tab=personalizzazione', '/clients']) {
            await page.goto(base + route); await page.waitForTimeout(400);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `mobile ${width} ${route}`);
            await page.screenshot({ path: `${output}/mobile-${width}-${route.split('?')[0].slice(1)}.png`, fullPage: true });
        }
    }
    await page.evaluate(async () => { await window.axios.put('/settings/event-theme', { theme: 'standard' }, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': JSON.parse(document.getElementById('app').dataset.page).version } }); });
    await page.goto(`${base}/dashboard`); assert.equal(await page.locator('.season-object,.season-fullscreen').count(), 0);
    assert.deepEqual(errors, []);
    console.log('PASS real pages, mobile, completion event, standard restoration; no browser exceptions');
} finally { await browser.close(); }
