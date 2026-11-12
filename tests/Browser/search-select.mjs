import assert from 'node:assert/strict';
import path from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const server = await createServer({ configFile: false, plugins: [vue(), {
    name: 'search-test',
    resolveId(id) { if (id === '/__search.js') return '\0search-test'; },
    load(id) { if (id === '\0search-test') return `
        import {createApp,h,ref} from 'vue';
        import AppSelect from '@/Components/AppSelect.vue';
        createApp({setup(){const value=ref('');return()=>h('main',[
            h(AppSelect,{modelValue:value.value,searchable:true,options:[
                {value:'parent',label:'ONE-TEAM',children:[{value:'child',label:'Nicolò D’Angelo'}]},
                {value:'other',label:'Giulia Grandi'}
            ],'onUpdate:modelValue':v=>value.value=v}),h('output',value.value)
        ])}}).mount('#app');`; },
}], resolve: {alias:{'@':path.resolve('resources/js')}}, server:{host:'127.0.0.1',port:0} });
await server.listen();
const browser = await chromium.launch();
try {
    for (const width of [1440, 390]) {
        const page = await browser.newPage({viewport:{width,height:900}});
        const errors=[];
        page.on('pageerror', error=>errors.push(error.message));
        await page.route('**/__search-test', route=>route.fulfill({contentType:'text/html',body:'<div id="app"></div><script type="module" src="/__search.js"></script>'}));
        await page.goto(`${server.resolvedUrls.local[0]}__search-test`);
        await page.locator('#app main > div > button').click();
        await page.getByPlaceholder('Cerca...').fill('dangelo nicolo');
        await page.getByRole('button',{name:'Nicolò D’Angelo',exact:true}).click();
        assert.equal(await page.locator('output').textContent(),'child');
        await page.locator('#app main > div > button').click();
        await page.getByPlaceholder('Cerca...').fill('oneteam');
        await page.getByRole('button',{name:'ONE-TEAM',exact:true}).click();
        assert.equal(await page.locator('output').textContent(),'parent');
        await page.locator('#app main > div > button').click();
        await page.getByPlaceholder('Cerca...').fill('grandi giulia');
        await page.getByRole('button',{name:'Giulia Grandi',exact:true}).click();
        assert.equal(await page.locator('output').textContent(),'other');
        assert.deepEqual(errors,[]);
        await page.close();
    }
    console.log('Search selector passed on desktop/mobile: accents, punctuation, reversed names, hierarchy.');
} finally { await browser.close(); await server.close(); }
