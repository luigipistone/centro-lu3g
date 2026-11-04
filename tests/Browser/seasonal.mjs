// Run with PLAYWRIGHT_MODULE pointing to the installed Playwright module.
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = process.cwd();
const server = await createServer({ configFile: false, root, plugins: [vue()], resolve: { alias: { '@': resolve(root, 'resources/js'), '@inertiajs/vue3': resolve(root, 'tests/Browser/seasonal-page.js') } }, server: { port: 5187, strictPort: false } });
let browser;
try {
    await server.listen(); browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1024, height: 800 } });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${server.resolvedUrls.local[0]}tests/Browser/seasonal.html`);
    await page.waitForSelector('.season-object');
    const nonblank = selector => page.locator(selector).evaluate(c => c.getContext('2d').getImageData(0, 0, c.width, c.height).data.some(v => v !== 0));
    for (const theme of ['halloween', 'natale', 'pasqua', 'estate']) {
        await page.evaluate(theme => window.setSeason(theme), theme);
        await page.waitForTimeout(150);
        assert(await nonblank('.season-object canvas'), `${theme} artwork`);
        assert(await nonblank('.season-corner'), `${theme} corner`);
        await page.locator('.season-object').click(); await page.waitForTimeout(300);
        assert(await nonblank('.season-fullscreen'), `${theme} fullscreen`);
        await page.waitForTimeout(1600); assert(!(await nonblank('.season-fullscreen')), 'Effect clears');
        await page.screenshot({ path: `/tmp/centro-season-${theme}.png` });
        await page.evaluate(() => document.documentElement.classList.add('dark'));
        await page.waitForTimeout(250);
        assert.equal(await page.locator('.surface').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(34, 38, 41)', `${theme} dark surface`);
        await page.screenshot({ path: `/tmp/centro-season-${theme}-dark.png` });
        await page.evaluate(() => document.documentElement.classList.remove('dark'));
    }
    await page.getByRole('button', { name: 'Pausa animazioni stagionali' }).click();
    await page.locator('.season-object').click(); await page.waitForTimeout(100);
    assert(!(await nonblank('.season-fullscreen')), 'Pause suppresses effects');
    await page.reload(); await page.waitForSelector('.season-object');
    assert.equal(await page.getByRole('button', { name: 'Attiva animazioni stagionali' }).count(), 1);
    await page.getByRole('button', { name: 'Attiva animazioni stagionali' }).click();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.locator('.season-object').click(); await page.waitForTimeout(100);
    assert(!(await nonblank('.season-fullscreen')), 'Reduced motion suppresses effects');
    await page.setViewportSize({ width: 320, height: 700 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Mobile fits');
    await page.evaluate(() => window.setSeason('standard')); await page.waitForTimeout(100);
    assert.equal(await page.locator('canvas').count(), 0, 'Standard removes artwork');
    assert.deepEqual(errors, []);
    console.log('PASS: all themes, effects, cleanup, pause persistence, reduced motion, mobile, standard');
} finally { await browser?.close(); await server.close(); }
