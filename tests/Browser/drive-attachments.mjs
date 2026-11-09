import assert from 'node:assert/strict';
import path from 'node:path';
import fs from 'node:fs';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const css = fs.readdirSync('/tmp/centro-drive-build/assets').find(name => /^app-.*\.css$/.test(name));
const server = await createServer({
    configFile: false, plugins: [vue(), {
        name: 'drive-test',
        resolveId(id) { if (id === '/__entry.js') return '\0drive-test'; },
        load(id) { if (id === '\0drive-test') return `import {createApp,h} from 'vue'; import DriveAttachments from '@/Components/DriveAttachments.vue'; createApp({render:()=>h('main',{class:'surface p-6 mx-auto',style:'max-width:850px'},[h(DriveAttachments,{scope:'tasks',entityId:'test'})])}).mount('#app');`; },
    }], resolve: { alias: { '@': path.resolve('resources/js') } }, server: { host: '127.0.0.1', port: 5212, strictPort: false },
});
await server.listen();
const browser = await chromium.launch();
try {
    const page = await browser.newPage(); const errors = []; let files = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('**/__test', route => route.fulfill({contentType:'text/html',body:`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${fs.readFileSync('/tmp/centro-drive-build/assets/'+css,'utf8')}</style><div id="app"></div><script type="module" src="/__entry.js"></script>`}));
    await page.route('**/tasks/test/drive-attachments', async route => {
        if (route.request().method() === 'POST') {
            assert.ok(route.request().postDataBuffer().includes(Buffer.from('folder-child')));
            files = [{id:'one',name:'brief.txt',size:5,folder_name:'Progetto test'}];
            return route.fulfill({status:201,json:{message:'ok'}});
        }
        return route.fulfill({json:{configured:true,root:'root',can_upload:true,can_remove:true,files}});
    });
    await page.route('**/tasks/test/drive-folders?*', route => {
        const child = new URL(route.request().url()).searchParams.get('parent') === 'folder-child';
        return route.fulfill({json:{folder:child?{id:'folder-child',name:'Progetto test'}:{id:'root',name:'Drive aziendale'},folders:child?[]:[{id:'folder-child',name:'Progetto test'}],cursor:null}});
    });
    await page.route('**/tasks/test/drive-attachments/one', route => { files=[]; return route.fulfill({json:{message:'ok'}}); });
    await page.goto(`${server.resolvedUrls.local[0]}__test`);
    await page.getByRole('button',{name:'Drive aziendale',exact:true}).click();
    await page.getByRole('button',{name:'Progetto test',exact:true}).click();
    await page.getByRole('button',{name:'Seleziona cartella',exact:true}).click();
    await page.locator('dialog[open]').waitFor({state:'hidden'});
    await page.locator('input[type=file]').setInputFiles({name:'brief.txt',mimeType:'text/plain',buffer:Buffer.from('hello')});
    await page.getByText('File salvato su Drive.',{exact:true}).waitFor();
    await page.getByRole('link',{name:'Scarica allegato'}).waitFor();
    for (const width of [1440,390]) {
        await page.setViewportSize({width,height:900});
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
        await page.screenshot({path:`/tmp/drive-attachments-${width}.png`,fullPage:true});
    }
    await page.getByRole('button',{name:'Elimina file',exact:true}).click();
    await page.getByText('spostato nel cestino di Google Drive.',{exact:false}).waitFor();
    await page.getByRole('button',{name:'Elimina',exact:true}).click();
    await page.getByRole('link',{name:'Scarica allegato'}).waitFor({state:'detached'});
    await page.route('**/tasks/test/drive-folders', route => route.fulfill({status:201,json:{id:'created-folder',name:'Cartella nuova'}}));
    await page.getByRole('button',{name:'Progetto test',exact:true}).click();
    await page.getByRole('button',{name:'Nuova cartella',exact:true}).click();
    await page.getByLabel('Nome cartella').fill('Cartella nuova');
    await page.getByRole('button',{name:'Crea e seleziona',exact:true}).click();
    await page.locator('dialog[open]').waitFor({state:'hidden'});
    await page.getByRole('button',{name:'Cartella nuova',exact:true}).waitFor();
    assert.deepEqual(errors,[]);
    console.log('Drive attachments: folder creation/navigation, upload, delete, desktop/mobile checks passed.');
} finally { await browser.close(); await server.close(); }
