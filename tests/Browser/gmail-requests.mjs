import assert from 'node:assert/strict';
import path from 'node:path';
import {createServer} from 'vite';
import vue from '@vitejs/plugin-vue';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const server=await createServer({configFile:false,plugins:[vue(),{
    name:'gmail-test',
    resolveId(id){if(id==='/__gmail.js')return '\0gmail-test';},
    load(id){if(id==='\0gmail-test')return `
        import {createApp,h} from 'vue';
        import {createInertiaApp} from '@inertiajs/vue3';
        import Review from '@/Components/MailRequestReview.vue';
        import Panel from '@/Components/GmailRequestsPanel.vue';
        import '@/../css/app.css';
        const data={record:{id:'r',subject:'Aggiornamento pagina contatti',reviewer_id:'u',version:1,status:'pending'},messages:[{id:'m',subject:'Aggiornamento pagina contatti',sender:'cliente@example.com',body:'Buongiorno, aggiornate i riferimenti nella pagina contatti. Grazie!',received_at:'12/11/2026',attachments:[{name:'brief.pdf'}]}],clients:[{id:'c',name:'CLIENTE'}],projects:[{id:'p',name:'Sito web',client_id:'c'}],services:[],users:[{id:'u',name:'Manager'}],tasks:[]};
        data.ai_configured=true;
        const Demo={render(){return h('main',{class:'mx-auto max-w-[1440px] space-y-6 p-5'},[h(Panel),h(Review,{data})])}};
        createInertiaApp({page:{component:'Demo',props:{auth:{user:{id:'u'}}},url:'/',version:'1'},resolve:()=>Demo,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).mount(el)}});
    `;}
}],resolve:{alias:{'@':path.resolve('resources/js')}},server:{host:'127.0.0.1',port:0}});
await server.listen(); const browser=await chromium.launch();
try {
    for(const width of [1440,390]){
        const page=await browser.newPage({viewport:{width,height:1000}});const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        await page.route('**/requests/gmail',r=>r.fulfill({json:{admin:true,configured:true,connected:true,email:'manager@example.com',label_id:'Label_1',enabled:true,redirect_uri:'https://centro.lu3g.com/requests/gmail/callback'}}));
        await page.route('**/requests/gmail/labels',r=>r.fulfill({json:{labels:[{value:'Label_1',label:'Invia al Centro'}]}}));
        await page.route('**/requests/r/prepare',r=>r.fulfill({json:{ai_generated:r.request().postDataJSON().ai,warnings:['Verificare i nuovi riferimenti.'],subtasks:['Cambiare telefono','Aggiornare indirizzo'],title:'Aggiornamento pagina contatti',description:'Aggiornare i riferimenti nella pagina contatti.',client_id:'c',project_id:'p',candidates:[{id:'c',name:'CLIENTE',reason:'Email del cliente o referente'}]}}));
        await page.route('**/__gmail-test',r=>r.fulfill({contentType:'text/html',body:'<div id="app"></div><script type="module" src="/__gmail.js"></script>'}));
        await page.goto(server.resolvedUrls.local[0]+'__gmail-test');
        await page.getByText('Proposta da verificare').waitFor();
        await page.getByRole('button',{name:'Prepara proposta (1)',exact:true}).click();
        await page.getByRole('button',{name:'Approva e crea task'}).waitFor();
        await page.getByRole('button',{name:'Prepara con AI',exact:true}).click();
        await page.getByText('Sostituire titolo, descrizione e sottoattività attuali?',{exact:false}).waitFor();
        await page.getByRole('button',{name:'Conferma',exact:true}).click();
        await page.getByText('Bozza AI · Da verificare').waitFor();
        await page.getByText('Continuativa',{exact:true}).waitFor();
        assert.equal(await page.getByText('Progetto',{exact:true}).count(),0);
        await page.getByRole('button',{name:'Assegnatari',exact:true}).click();
        await page.getByRole('button',{name:'Manager',exact:true}).waitFor();
        await page.keyboard.press('Escape');
        await page.getByRole('textbox',{name:'Sottoattività 1',exact:true}).fill('Cambiare telefono corretto');
        await page.getByRole('button',{name:'Rimuovi sottoattività 2',exact:true}).click();
        assert.equal(await page.getByRole('textbox',{name:'Sottoattività 2',exact:true}).count(),0);
        await page.waitForTimeout(1000);
        await page.screenshot({path:'/tmp/gmail-requests-'+width+'.png',fullPage:true});
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
        assert.deepEqual(errors,[]);
        await page.close();
    }
    console.log('Gmail review/panel: desktop and mobile render without overflow or runtime errors.');
}finally{await browser.close();await server.close();}
