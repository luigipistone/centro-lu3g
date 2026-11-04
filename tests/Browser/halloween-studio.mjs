import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const output = process.env.SEASON_SCREENSHOTS || '/tmp/halloween-studio';
await mkdir(output, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1, ...(process.env.SEASON_VIDEO ? { recordVideo: { dir: output, size: { width: 1440, height: 1000 } } } : {}) });
const errors = []; page.on('pageerror', e => errors.push(e.message));
try {
    await page.goto('http://127.0.0.1:8017/login');
    await page.locator('#email').fill('season-qa@example.test');
    await page.locator('#password').fill(process.env.SEASON_QA_PASSWORD);
    await page.getByRole('button', { name: 'Accedi', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await page.evaluate(async () => {
        await window.axios.put('/settings/event-theme', { theme: 'halloween' }, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': JSON.parse(document.getElementById('app').dataset.page).version } });
        const types = ['clock_date', 'quick_links', 'stat_open_tasks', 'stat_clients', 'my_tasks', 'active_projects'];
        await window.axios.patch('/dashboard/widgets', { widgets: types.map((widget_type, position) => ({ widget_type, position, visible: true, col_span: position < 4 ? 1 : 2 })) });
        await window.axios.patch('/dashboard/widgets/quick_links/settings', { links: [{ label: 'Repository Web', url: 'https://github.com/lu3gofficial' }, { label: 'Drive', url: 'https://drive.google.com' }] });
        localStorage.setItem('centro:theme', 'dark');
    });
    await page.goto('http://127.0.0.1:8017/dashboard');
    await page.waitForSelector('.widget-card');
    await page.evaluate(() => {
        // Example records are browser-only; no clients, tasks or projects are written.
        const props = document.querySelector('#app').__vue_app__.config.globalProperties.$page.props;
        props.stats = { clients: 28, openTasks: 12, urgentTasks: 2 };
        props.myTasks = [
            { id: 'demo-1', title: 'Revisione homepage', priority: 'high', due_date: '2026-11-04' },
            { id: 'demo-2', title: 'Controllo responsive', priority: 'normal', due_date: '2026-11-05' },
            { id: 'demo-3', title: 'Consegna materiali campagna', priority: 'normal', due_date: '2026-11-06' },
        ];
        props.activeProjects = [
            { id: 'demo-1', name: 'Nuovo sito corporate', client_name: 'Progetto web', color: '#efa15e' },
            { id: 'demo-2', name: 'Identita di marca', client_name: 'Design', color: '#bdbdaf' },
            { id: 'demo-3', name: 'Campagna autunno', client_name: 'Digital marketing', color: '#92ba9e' },
        ];
    });
    await page.waitForTimeout(1600);
    await page.evaluate(() => document.fonts.ready);
    for (const width of [1440, 1920, 390]) {
        await page.setViewportSize({ width, height: width === 390 ? 900 : 1000 });
        await page.waitForTimeout(500);
        if (width >= 1024) await page.waitForFunction(() => parseFloat(getComputedStyle(document.querySelector('.desktop-shell')).paddingLeft) >= 256);
        await page.waitForTimeout(1000);
        if (width >= 1024) assert(await page.locator('.desktop-shell > header').evaluate(el => el.getBoundingClientRect().left >= 256), 'Header clears sidebar');
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width} overflow`);
        await page.screenshot({ path: `${output}/halloween-${width}.png`, fullPage: true, animations: 'disabled' });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.waitForTimeout(1000);
    await page.getByRole('button', { name: 'Aggiungi widget', exact: true }).click();
    await page.waitForTimeout(200);
    await page.screenshot({ path: `${output}/halloween-menu.png` });
    await page.getByRole('button', { name: 'Aggiungi widget', exact: true }).click();
    if (process.env.SEASON_VIDEO) {
        await page.locator('.season-object').hover();
        await page.waitForTimeout(4000);
        await page.locator('.season-object').click();
        await page.mouse.move(920, 135);
        await page.waitForTimeout(14000);
    }
    assert.deepEqual(errors, []);
    console.log('PASS: actual dashboard renders at 1440/1920/390, menu opens, no browser exceptions');
} finally {
    await page.context().close();
    if (process.env.SEASON_VIDEO) await page.video().saveAs(`${output}/halloween-session.webm`);
    await browser.close();
}
