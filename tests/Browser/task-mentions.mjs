import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const server = await createServer({
    configFile: false,
    plugins: [vue(), {
        name: 'mention-test',
        resolveId(id) { if (id === '/__entry.js') return '\0mention-test'; },
        load(id) { if (id === '\0mention-test') return `
            import {createApp,h} from 'vue';
            import TaskMentions from '@/Components/TaskMentions.vue';
            createApp({render:()=>h('main',[h(TaskMentions,{taskId:'test'}),h('div',{contenteditable:true,'data-task-comment-editor':'new'})])}).mount('#app');
        `; },
    }],
    resolve: { alias: { '@': path.resolve('resources/js') } },
    server: { host: '127.0.0.1', port: 5201, strictPort: false },
});
await server.listen();
const browser = await chromium.launch();
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/__test', route => route.fulfill({contentType:'text/html',body:'<div id="app"></div><script type="module" src="/__entry.js"></script>'}));
    await page.route('**/tasks/test/mention-users', route => route.fulfill({json:[{id:'one',name:'Giulia'},{id:'two',name:'Francesco'}]}));
    await page.goto(`${server.resolvedUrls.local[0]}__test`);
    const editor = page.locator('[contenteditable=true]');
    await editor.pressSequentially('Ciao @Giu');
    await page.getByRole('option', {name:'Giulia'}).click();
    assert.equal(await editor.locator('[data-mention-user="one"]').textContent(), '@Giulia');
    await editor.pressSequentially(' e @Fra');
    await page.getByRole('option', {name:'Francesco'}).waitFor();
    await page.keyboard.press('Enter');
    assert.equal(await editor.locator('[data-mention-user="two"]').count(), 1);
    assert.equal(await page.getByRole('listbox').count(), 0);
    assert.deepEqual(errors, []);
    console.log('Task mentions: mouse, keyboard and multiple mentions passed.');
} finally {
    await browser.close();
    await server.close();
}
