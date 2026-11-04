import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const output = '/tmp/halloween-global';
await mkdir(output, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.context().grantPermissions(['clipboard-read', 'clipboard-write'], { origin: 'http://127.0.0.1:8017' });
const errors = []; page.on('pageerror', error => errors.push(error.message));
try {
    await page.goto('http://127.0.0.1:8017/login');
    await page.locator('#email').fill('season-qa@example.test');
    await page.locator('#password').fill(process.env.SEASON_QA_PASSWORD);
    await page.getByRole('button', { name: 'Accedi', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await page.evaluate(async () => {
        await window.axios.put('/settings/event-theme', { theme: 'halloween' }, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': JSON.parse(document.getElementById('app').dataset.page).version } });
        // Regression: no "my_tasks" widget in this user's configuration.
        await window.axios.patch('/dashboard/widgets', { widgets: ['clock_date','stat_clients','quick_links'].map((widget_type, position) => ({ widget_type, position, visible: true, col_span: 1 })) });
    });
    for (const width of [1440, 390]) {
        await page.evaluate(width => localStorage.setItem('centro:theme', width === 1440 ? 'light' : 'dark'), width);
        await page.setViewportSize({ width, height: 1000 });
        for (const route of ['/dashboard','/clients','/projects','/tools','/settings','/documents','/passwords','/favorites','/tasks']) {
            await page.goto(`http://127.0.0.1:8017${route}`);
            await page.waitForTimeout(1100);
            await page.evaluate(() => document.fonts.ready);
            assert(await page.evaluate(() => document.fonts.check('400 24px "Centro Halloween"')), 'Seasonal heading font loaded');
            assert(await page.locator('.desktop-shell > header h2').first().evaluate(el => getComputedStyle(el).fontFamily.includes('Centro Halloween')), 'Page title uses themed typography');
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route} overflow at ${width}`);
            assert.equal(await page.locator('.season-atmosphere').evaluate(el => getComputedStyle(el).pointerEvents), 'none');
            assert.equal(await page.locator('.season-atmosphere').evaluate(el => getComputedStyle(el).zIndex), '7900');
            if (route === '/dashboard') {
                assert.equal(await page.locator('[data-season-accent]').count(), 1);
                assert.equal(await page.locator('[data-season-accent]').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(255, 123, 50)');
            }
            if (route === '/tools') assert.equal(await page.locator('.surface:not(.web-repositories-card)').first().evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(37, 40, 44)');
            if (route === '/settings') assert(['rgb(245, 241, 233)', 'rgb(235, 239, 244)'].includes(await page.locator('.section-title').first().evaluate(el => getComputedStyle(el).color)), 'Heading stays light in both appearance modes');
            await page.screenshot({ path: `${output}/${route.slice(1)}-${width}.png`, fullPage: true, animations: 'disabled' });
            if (route === '/passwords') {
                await page.route('**/*/reveal', route => route.fulfill({ json: { password: 'Demo-Only-2026!', username: 'demo@example.test' } }));
                await page.locator('.app-shell').evaluate(el => {
                    let component = el.__vueParentComponent;
                    while (component && !component.setupState?.openReveal) component = component.parent;
                    if (!component) throw new Error('Password component not found');
                    component.setupState.openReveal({ id: 'demo-only', title: 'Credenziale dimostrativa', vault_name: 'Test', url: 'https://example.test' });
                });
                await page.locator('.password-reveal-row').first().waitFor();
                const row = page.locator('.password-reveal-row').first();
                assert.equal(await row.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(30, 32, 35)');
                assert(['rgb(245, 241, 233)', 'rgb(235, 239, 244)'].includes(await row.locator('p').evaluate(el => getComputedStyle(el).color)), 'Credential stays light on the dark row');
                await row.click();
                await page.getByText('Nome utente copiato.', { exact: true }).waitFor();
                await page.screenshot({ path: `${output}/password-dialog-${width}.png`, animations: 'disabled' });
                await page.getByRole('button', { name: 'Chiudi dettagli password' }).click();
            }
        }
    }
    assert.deepEqual(errors, []);
    console.log('PASS: nine sections desktop/mobile, accent without my_tasks, surface/title contrast, foreground nonblocking atmosphere');
} finally { await browser.close(); }
