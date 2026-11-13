import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { createHmac } from 'node:crypto';
import { spawn, execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = fileURLToPath(new URL('../../', import.meta.url));
const php = process.env.PHP_BIN || '/opt/homebrew/bin/php';
const port = 18085;
const env = { ...process.env, APP_URL: 'http://127.0.0.1', REVERB_APP_ID: 'test',
    REVERB_APP_KEY: 'test-key', REVERB_APP_SECRET: 'test-secret', REVERB_SERVER_PORT: String(port) };
const processReverb = spawn(php, ['artisan', 'reverb:start', '--host=127.0.0.1', '--port=' + port], { cwd: root, env, stdio: 'pipe' });
let browser, server;
let rows = [];
try {
    await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Reverb startup timeout')), 10000);
        processReverb.stdout.on('data', data => { if (data.toString().includes('Starting server')) { clearTimeout(timer); resolve(); } });
        processReverb.once('exit', code => { clearTimeout(timer); reject(new Error('Reverb exit ' + code)); });
    });
    const handler = (req, res) => {
        res.setHeader('Content-Type', 'application/json');
        if (req.url.includes('live-data')) { res.end(JSON.stringify({ rows })); return; }
        let body = '';
        req.on('data', chunk => { body += chunk; });
        req.on('end', () => {
            const data = JSON.parse(body);
            const signature = createHmac('sha256', 'test-secret').update(data.socket_id + ':' + data.channel_name).digest('hex');
            res.end(JSON.stringify({ auth: 'test-key:' + signature }));
        });
    };
    server = await createServer({ configFile: false, root, plugins: [vue(), {
        name: 'realtime-fixture', configureServer(server) { server.middlewares.use('/fixture/', handler); },
    }],
        resolve: { alias: { '@': root + 'resources/js', vue: 'vue/dist/vue.esm-bundler.js' } },
        server: { host: '127.0.0.1', port: 0 } });
    await server.listen();
    browser = await chromium.launch({ headless: true });
    const pages = await Promise.all([browser.newPage(), browser.newPage()]);
    const errors = [];
    for (const [index, page] of pages.entries()) {
        page.on('console', message => { if (message.type() === 'error') console.error(message.text()); });
        page.on('response', response => { if (response.status() >= 400) console.error(response.status(), response.url()); });
        page.on('websocket', ws => { ws.on('socketerror', error => console.error(error)); });
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('http://127.0.0.1:' + server.httpServer.address().port + '/tests/Browser/fixtures/calendar-realtime.html?port=' + port + '&section=' + (index ? 'tasks' : 'calendar'));
        await page.waitForFunction(() => document.querySelector('#state').textContent === 'connected').catch(async error => {
            console.error(await page.locator('body').innerText()); throw error;
        });
    }
    const emit = () => execFileSync(php, ['-r', 'require "vendor/autoload.php"; $p=new Pusher\\Pusher("test-key","test-secret","test",["host"=>"127.0.0.1","port"=>18085,"scheme"=>"http","useTLS"=>false]); $p->trigger("private-calendar","calendar.changed",[]);'], { cwd: root, env });
    for (const title of ['Creata', 'Spostata', 'Modificata', 'Completata']) {
        rows = [{ id: 1, title }]; emit();
        for (const page of pages) await page.waitForFunction(title => document.querySelector('#rows').textContent.includes(title), title);
    }
    await pages[1].evaluate(() => window.setBusy(true));
    rows = [{ id: 1, title: 'Differita' }]; emit();
    await pages[0].waitForFunction(() => document.querySelector('#rows').textContent.includes('Differita'));
    assert.equal(await pages[1].locator('#rows').textContent(), '[{"id":1,"title":"Completata"}]');
    await pages[1].evaluate(() => window.setBusy(false));
    await pages[1].waitForFunction(() => document.querySelector('#rows').textContent.includes('Differita'));
    rows = []; emit();
    for (const page of pages) {
        await page.waitForFunction(() => document.querySelector('#rows').textContent === '[]');
        assert.equal(await page.locator('input').inputValue(), 'Bozza non salvata');
    }
    assert.deepEqual(errors, []);
    console.log('PASS: two real WebSocket subscribers, updates, deletion, busy deferral and draft preservation.');
} finally {
    await browser?.close();
    await server?.close();
    processReverb.kill('SIGTERM');
    if (processReverb.exitCode === null) await new Promise(resolve => processReverb.once('exit', resolve));
}
