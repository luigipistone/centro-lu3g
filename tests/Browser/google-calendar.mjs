import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = fileURLToPath(new URL('../../', import.meta.url));
const server = await createServer({ configFile: false, root, plugins: [vue()], resolve: { alias: { '@': `${root}resources/js` } }, server: { host: '127.0.0.1', port: 0 } });
let browser;
try {
    await server.listen(); browser = await chromium.launch({ headless: true });
    for (const width of [1440, 390]) {
        const page = await browser.newPage({ viewport: { width, height: 1000 } });
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await page.goto(`http://127.0.0.1:${server.httpServer.address().port}/tests/Browser/fixtures/google-calendar.html`);
        await page.getByRole('button', { name: 'Meeting aziendali', exact: true }).click();
        await page.getByRole('button', { name: 'Secondo calendario', exact: true }).click();
        await page.getByRole('button', { name: '12 mesi', exact: true }).click();
        await page.getByRole('button', { name: '6 mesi', exact: true }).click();
        await page.getByRole('button', { name: 'Salva', exact: true }).click();
        await page.waitForFunction(() => window.savedCalendar);
        assert.equal(await page.evaluate(() => window.savedCalendar.calendar_id), 'other');
        assert.equal(Number(await page.evaluate(() => window.savedCalendar.months_ahead)), 6);
        await page.getByText('Email non associate (1)').click();
        await page.screenshot({ path: `/tmp/centro-google-calendar-${width}.png` });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        await page.getByRole('button', { name: 'Sincronizza ora' }).click();
        await page.getByRole('button', { name: 'In attesa...' }).waitFor();
        await page.getByRole('button', { name: 'Scollega Google', exact: true }).click();
        await page.locator('dialog[open]').getByRole('button', { name: 'Scollega', exact: true }).click();
        await page.getByRole('link', { name: 'Collega Google' }).waitFor();
        assert.equal(await page.evaluate(() => window.disconnected), true);
        assert.deepEqual(errors, []);
        console.log(`PASS ${width}px: calendar selection, save, sync feedback, disconnect`);
        await page.close();
    }
} finally { await browser?.close(); await server.close(); }
