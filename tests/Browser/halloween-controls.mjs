import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await chromium.launch();
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await mkdir('/tmp/halloween-controls', { recursive: true });
async function state(action) {
    await page.locator('.app-shell').evaluate((el, action) => {
        let component = el.__vueParentComponent;
        while (component && !component.setupState?.openCreateItem) component = component.parent;
        const state = component.setupState;
        if (action === 'create') state.openCreateItem();
        if (action === 'delete') { state.drawerOpen = false; state.deleteTarget = { target: { id: 'demo-only' }, type: 'item' }; }
    }, action);
}
async function contrast(locator, minimum = 4.5) {
    const results = await locator.evaluateAll(elements => elements.filter(el => el.getClientRects().length).map(el => {
        const rgb = value => value.match(/[\d.]+/g).slice(0, 3).map(Number);
        const luminance = color => rgb(color).map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((a, v, i) => a + v * [.2126,.7152,.0722][i], 0);
        let parent = el;
        while (parent && getComputedStyle(parent).backgroundColor === 'rgba(0, 0, 0, 0)') parent = parent.parentElement;
        const fg = luminance(getComputedStyle(el).color);
        const bg = luminance(getComputedStyle(parent || el).backgroundColor);
        return { text: el.textContent.slice(0, 60), ratio: (Math.max(fg, bg) + .05) / (Math.min(fg, bg) + .05) };
    }));
    for (const result of results) assert(result.ratio >= minimum, JSON.stringify(result));
}
try {
    await page.goto('http://127.0.0.1:8017/login');
    await page.locator('#email').fill('season-qa@example.test');
    await page.locator('#password').fill(process.env.SEASON_QA_PASSWORD);
    await page.getByRole('button', { name: 'Accedi', exact: true }).click();
    await page.waitForURL('**/dashboard');
    for (const mode of ['light','dark']) for (const width of [1440,390]) {
        await page.evaluate(mode => localStorage.setItem('centro:theme', mode), mode);
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('http://127.0.0.1:8017/passwords');
        await state('create');
        const drawer = page.locator('.password-drawer-panel');
        await drawer.getByRole('button', { name: 'Genera', exact: true }).click();
        const checkbox = drawer.locator('input[type="checkbox"]').first();
        assert.equal(await checkbox.evaluate(el => getComputedStyle(el).appearance), 'auto');
        assert(await checkbox.isChecked());
        await checkbox.uncheck(); assert(!(await checkbox.isChecked())); await checkbox.check();
        await contrast(drawer.locator('code'));
        await contrast(drawer.locator('label > span').filter({ hasText: /Titolo|Nome utente|Categoria/ }));
        await drawer.getByRole('button', { name: 'Campi personalizzati' }).click();
        await page.screenshot({ path: `/tmp/halloween-controls/drawer-${mode}-${width}.png` });
        const select = drawer.locator('button.form-control').first();
        await select.click();
        const options = page.locator('.field-dropdown-option');
        await options.first().waitFor();
        await contrast(options);
        await options.last().hover();
        await contrast(options.last());
        await page.waitForTimeout(400);
        await page.screenshot({ path: `/tmp/halloween-controls/select-${mode}-${width}.png` });
        await select.click();
        await state('delete');
        await page.getByRole('button', { name: 'Elimina', exact: true }).waitFor();
        await page.screenshot({ path: `/tmp/halloween-controls/delete-${mode}-${width}.png` });

        // Shared utility/state matrix uses real application styles, without changing stored data.
        await page.evaluate(() => {
            const fixture = document.createElement('section');
            fixture.id = 'theme-matrix'; fixture.className = 'surface';
            fixture.style.cssText = 'position:fixed;inset:10px;z-index:20000;overflow:auto;padding:20px;display:grid;gap:12px';
            const rows = [
                ['bg-gray-50/80 text-gray-800','Pannello traslucido'],
                ['bg-white/90 text-gray-500','Testo secondario'],
                ['bg-red-50/80 text-red-600','Errore di caricamento'],
                ['bg-amber-50 text-amber-800','Operazione da verificare'],
                ['bg-emerald-50 text-emerald-700','Salvataggio completato'],
                ['bg-blue-50 text-blue-700','Informazione'],
                ['bg-indigo-50 text-indigo-700','Selezione'],
                ['btn btn-danger','Elimina'], ['btn btn-primary','Salva'], ['btn btn-outline','Annulla'],
            ];
            for (const [classes, label] of rows) {
                const el = document.createElement('button'); el.className = classes; el.textContent = label; fixture.append(el);
            }
            document.body.append(fixture);
        });
        await contrast(page.locator('#theme-matrix > button'));
        for (const button of await page.locator('#theme-matrix .btn').all()) {
            await button.hover(); await contrast(button); await button.focus(); await contrast(button);
        }
        await page.screenshot({ path: `/tmp/halloween-controls/states-${mode}-${width}.png` });
        await page.evaluate(() => document.getElementById('theme-matrix').remove());
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    }
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('http://127.0.0.1:8017/tools');
    const repoIcon = page.locator('.web-repositories-card .tool-card-icon svg');
    await contrast(repoIcon, 3);
    await page.screenshot({ path: '/tmp/halloween-controls/tools-final-1920.png' });
    assert.deepEqual(errors, []);
    console.log('PASS: real password drawer/generator/select/delete; semantic color matrix >=4.5 contrast; light/dark, desktop/mobile. No records saved.');
} finally { await browser.close(); }
