import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const server = await createServer({
    configFile: false,
    plugins: [vue(), {
        name: 'client-test-entry',
        resolveId(id) { if (id === '/__entry.js') return '\0client-test-entry'; },
        load(id) { if (id === '\0client-test-entry') return `
            import {createApp, ref, h} from 'vue';
            import ClientSelect from '@/Components/ClientSelect.vue';
            createApp({setup(){const value=ref(''); const clients=[{id:'p',name:'Genitore'},{id:'c',name:'Filiale',parent_client_id:'p'}]; return ()=>h('main', [h(ClientSelect,{modelValue:value.value,clients,'onUpdate:modelValue':v=>value.value=v}),h('output',value.value)]);}}).mount('#app');
        `; },
    }],
    resolve: { alias: { '@': path.resolve('resources/js') } },
    server: { host: '127.0.0.1', port: 5199, strictPort: false },
});
const html = '<div id="app"></div><script type="module" src="/__entry.js"></script>';
await server.listen();
const browser = await chromium.launch();
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') console.error(message.text()); });
    await page.route('**/__client-test', (route) => route.fulfill({ contentType: 'text/html', body: html }));
    await page.goto(`${server.resolvedUrls.local[0]}__client-test`);
    await page.waitForTimeout(1500);
    assert.deepEqual(errors, []);
    await page.locator('#app main > div > button').click();
    await page.getByPlaceholder('Cerca...').fill('Genitore');
    await page.getByTitle('Comprimi', { exact: true }).click();
    assert.equal(await page.locator('.field-dropdown-menu').getByRole('button', { name: 'Filiale', exact: true }).count(), 0);
    await page.getByTitle('Espandi', { exact: true }).click();
    await page.getByRole('button', { name: 'Filiale', exact: true }).click();
    assert.equal(await page.locator('output').textContent(), 'c');
    await page.locator('#app main > div > button').click();
    assert.equal(await page.locator('.field-dropdown-menu').getByRole('button', { name: 'Filiale', exact: true }).count(), 1);
    await page.getByPlaceholder('Cerca...').fill('Filiale');
    assert.equal(await page.getByRole('button', { name: 'Genitore', exact: true }).count(), 1);
    await page.getByRole('button', { name: 'Genitore', exact: true }).click();
    assert.equal(await page.locator('output').textContent(), 'p');
    assert.deepEqual(errors, []);
    console.log('ClientSelect browser test passed: parent search, child selection, reopen, single selection.');
} finally {
    await browser.close();
    await server.close();
}
