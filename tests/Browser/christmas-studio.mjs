import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await chromium.launch();
const page = await browser.newPage();
const errors = [];
const theme = process.env.SEASON_QA_THEME || 'natale';
const output = theme === 'pasqua' ? '/tmp/easter-review' : '/tmp/christmas-review';
page.on('pageerror', error => errors.push(error.message));
await mkdir(output, { recursive: true });
try {
    await page.goto('http://127.0.0.1:8017/login');
    await page.locator('#email').fill('season-qa@example.test');
    await page.locator('#password').fill(process.env.SEASON_QA_PASSWORD);
    await page.getByRole('button', { name: 'Accedi', exact: true }).click();
    await page.waitForURL('**/dashboard');
    await page.evaluate(async theme => {
        await window.axios.put('/settings/event-theme', { theme, scope: 'personal' }, { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': JSON.parse(document.getElementById('app').dataset.page).version } });
        localStorage.setItem('centro:theme','dark');
    }, theme);
    for (const width of [1920,1440,390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const route of ['dashboard','clients','projects','tools','settings','documents','passwords','profile']) {
            await page.goto(`http://127.0.0.1:8017/${route}`);
            await page.waitForTimeout(1100);
            await page.evaluate(() => document.fonts.ready);
            if (width >= 1024) {
                await page.waitForFunction(() => {
                    const shell = document.querySelector('.desktop-shell');
                    return Math.abs(parseFloat(getComputedStyle(shell).paddingLeft) - parseFloat(shell.style.getPropertyValue('--sidebar-width'))) < 1;
                });
            }
            assert.equal(await page.evaluate(() => document.documentElement.dataset.eventTheme),theme);
            assert.equal(await page.evaluate(() => document.documentElement.classList.contains('dark')),false);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route}: ${width} overflow`);
            const headings = await page.locator('.season-widget-title,.tool-card-title,.desktop-shell > header h2').evaluateAll(els => els.filter(el => el.getClientRects().length && el.scrollWidth > el.clientWidth + 1).map(el=>el.textContent));
            assert.deepEqual(headings,[],`${route} clipped headings at ${width}`);
            const unreadableTitles = await page.locator('h1,h2,h3,h4,h5,h6,.section-title,.tool-card-title,.season-widget-title').evaluateAll(els => {
                const rgb = value => (value.match(/[\d.]+/g) || []).map(Number);
                const luminance = color => color.slice(0,3).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
                return els.filter(el=>el.getClientRects().length && el.textContent.trim()).flatMap(el=>{
                    let bg=[255,255,255]; const parents=[];
                    for(let p=el;p;p=p.parentElement) parents.unshift(p);
                    for(const p of parents) { const c=rgb(getComputedStyle(p).backgroundColor); const a=c[3]??1; bg=bg.map((v,i)=>v*(1-a)+c[i]*a); }
                    const fg=rgb(getComputedStyle(el).color), a=luminance(fg), b=luminance(bg);
                    const contrast=(Math.max(a,b)+.05)/(Math.min(a,b)+.05);
                    return contrast<4.5?[{title:el.textContent.trim(),contrast,bg,fg}]:[];
                });
            });
            assert.deepEqual(unreadableTitles,[],`${route}: low-contrast titles at ${width}`);
            assert.equal(await page.locator('.brand-mark img').first().evaluate(el=>getComputedStyle(el).filter),'brightness(0) invert(1)');
            if (route === 'dashboard') {
                const atmosphere = page.locator('.season-atmosphere');
                const pixels = () => atmosphere.evaluate(el => {
                    const data = el.getContext('2d').getImageData(0,0,el.width,el.height).data;
                    let count = 0, hash = 0;
                    for (let i=3;i<data.length;i+=4) if(data[i]) { count++; hash=(hash+i*data[i])%1000000007; }
                    return {count,hash};
                });
                const before = await pixels(); await page.waitForTimeout(300); const after = await pixels();
                assert(before.count>100 && before.hash!==after.hash,'Snow/lights rendered and moving');
                assert.equal(await atmosphere.evaluate(el=>getComputedStyle(el).pointerEvents),'none');
                await page.getByRole('button',{name:'Pausa animazioni stagionali'}).click();
                const paused = await pixels(); await page.waitForTimeout(200); assert.deepEqual(await pixels(),paused);
                await page.getByRole('button',{name:'Attiva animazioni stagionali'}).click();
                if (width === 1920) {
                    await page.getByRole('button',{name:'Anima il tema stagionale'}).click();
                    await page.waitForTimeout(2500);
                    assert(await page.locator('.season-fullscreen').evaluate(el=>el.getContext('2d').getImageData(0,0,el.width,el.height).data.some(v=>v!==0)), 'Stars must still be falling after 2.5 seconds');
                    await page.screenshot({path:`${output}/effect.png`,animations:'disabled'});
                    await page.waitForFunction(()=>{const el=document.querySelector('.season-fullscreen');return !el.getContext('2d').getImageData(0,0,el.width,el.height).data.some(v=>v!==0);},null,{timeout:15000});
                }
            }
            if (route === 'profile') {
                await page.getByRole('button',{name:'Notifiche',exact:true}).click();
                assert(await page.locator('.notification-switch-track').count()>0);
                const colors = await page.locator('.notification-switch-track').evaluateAll(els=>els.map(el=>getComputedStyle(el).backgroundColor));
                const switchColors = theme === 'pasqua' ? ['rgb(168, 50, 101)','rgb(83, 109, 96)'] : ['rgb(182, 22, 54)','rgb(125, 87, 99)'];
                assert(colors.every(c=>switchColors.includes(c)),JSON.stringify(colors));
            }
            if (route === 'passwords') {
                await page.locator('.app-shell').evaluate(el=>{
                    let c=el.__vueParentComponent; while(c&&!c.setupState?.openCreateItem)c=c.parent;
                    c.setupState.openCreateItem();
                });
                await page.locator('.password-drawer-panel').waitFor();
            }
            const lightPanels = await page.locator('.surface,.app-card,.toolbar,.password-drawer-panel,.form-control,[contenteditable="true"]').evaluateAll(els=>els.filter(el=>{
                if(!el.getClientRects().length) return false;
                const c=getComputedStyle(el).backgroundColor.match(/[\d.]+/g)?.map(Number);
                return c && (c[3]??1)>.5 && c.slice(0,3).every(v=>v>180);
            }).map(el=>el.className));
            assert.deepEqual(lightPanels,[],`${route}: light panels in ${theme} theme`);
            await page.screenshot({ path: `${output}/${route}-${width}.png`, fullPage: true, animations: 'disabled' });
            if (width >= 1024) {
                const geometry = await page.evaluate(() => ['.desktop-shell','.desktop-shell > header','.desktop-sidebar','.app-shell'].map(s=>{const e=document.querySelector(s),c=getComputedStyle(e);return {s,rect:e.getBoundingClientRect().toJSON(),padding:c.paddingLeft,margin:c.marginLeft,transform:c.transform,scroll:e.scrollLeft};}));
                assert(geometry[1].rect.left >= geometry[2].rect.right - 1, `${route}: sidebar overlaps header ${JSON.stringify(geometry)}`);
            }
        }
    }
    await page.emulateMedia({reducedMotion:'reduce'});
    await page.goto('http://127.0.0.1:8017/dashboard');
    await page.waitForTimeout(800);
    assert.equal(await page.locator('.season-atmosphere').evaluate(el=>el.getContext('2d').getImageData(0,0,el.width,el.height).data.some(v=>v!==0)),false);
    assert.deepEqual(errors,[]);
    console.log(`PASS: ${theme} 8 sections x 3 viewports, drawer, notification switches, logo, animated pixels, pause, reduced motion`);
} finally {await browser.close();}
