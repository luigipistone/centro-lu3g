import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = fileURLToPath(new URL('../../', import.meta.url));
const server = await createServer({ configFile: false, root, plugins: [vue()], resolve: { alias: { '@': root + 'resources/js' } }, server: { host: '127.0.0.1', port: 0 } });
let browser;
try {
    await server.listen(); browser = await chromium.launch({ headless: true });
    for (const width of [1440, 390]) {
        const page = await browser.newPage({ viewport: { width, height: 1000 } });
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await page.goto('http://127.0.0.1:' + server.httpServer.address().port + '/tests/Browser/fixtures/teamleader.html');
        await page.getByRole('button', { name: 'Esegui simulazione' }).click();
        await page.getByText('Simulazione completata').waitFor();
        assert.equal(await page.evaluate(() => window.previewCalls.length), 2);
        await page.getByText('21 meeting', { exact: true }).waitFor();
        await page.getByRole('button', { name: 'Pagina successiva' }).click();
        await page.getByText('2 / 2', { exact: true }).waitFor();
        await page.getByRole('checkbox', { name: 'Sincronizzazione automatica ogni 5 minuti' }).check();
        await page.getByRole('button', { name: 'Salva', exact: true }).click();
        await page.getByText('Impostazioni salvate.').waitFor();
        await page.getByRole('button', { name: 'Sincronizza ora', exact: true }).click();
        await page.getByRole('button', { name: 'In attesa...', exact: true }).waitFor();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        await page.screenshot({ path: '/tmp/centro-teamleader-' + width + '.png', fullPage: true });
        await page.getByRole('button', { name: 'Scollega Teamleader', exact: true }).click();
        await page.locator('dialog[open]').getByRole('button', { name: 'Scollega', exact: true }).click();
        await page.getByRole('link', { name: 'Collega Teamleader' }).waitFor();
        assert.deepEqual(errors, []);
        console.log('PASS ' + width + 'px: paginated read-only simulation, layout, disconnect');
        await page.close();
    }
} finally { await browser?.close(); await server.close(); }
