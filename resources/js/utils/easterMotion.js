export const easterEffectDuration = 7000;
const petalColors = ['#f4afcd', '#f7dd8a', '#e9c8e9'];
const breezes = new WeakMap();

export function createEasterPetals(count = 30, random = Math.random) {
    return Array.from({length: count}, () => ({
        x: random(), y: random(), phase: random() * Math.PI * 2,
        speed: .018 + random() * .022, size: 3 + random() * 4,
        delay: random() * .2, duration: .65 + random() * .15,
    }));
}

function petal(c, x, y, size, angle, color, alpha) {
    c.save(); c.translate(x,y); c.rotate(angle); c.globalAlpha = alpha;
    c.fillStyle = color;
    c.beginPath(); c.moveTo(0,-size); c.bezierCurveTo(size*1.5,-size*.8,size,size,0,size);
    c.bezierCurveTo(-size*.8,size*.4,-size*.7,-size*.4,0,-size); c.fill();
    c.strokeStyle = '#ffffff45'; c.lineWidth = .6;
    c.beginPath(); c.moveTo(0,-size*.7); c.quadraticCurveTo(size*.3,0,0,size*.7); c.stroke(); c.restore();
}

export function drawEasterBreeze(c, time, w, h) {
    let particles = breezes.get(c);
    if (!particles) { particles = createEasterPetals(22); breezes.set(c,particles); }
    const t = time / 1000;
    particles.slice(0,w < 600 ? 10 : 22).forEach((p,i) => {
        const x = ((p.x + t*p.speed*.5) % 1.1)*w-20;
        const y = ((p.y + t*p.speed*.23) % 1.1)*h-20 + Math.sin(t*.65+p.phase)*24;
        petal(c,x,y,p.size,Math.sin(t*.8+p.phase)*.9+t*.15,petalColors[i%3],.35+.15*Math.sin(t+p.phase));
    });
}

export function drawEasterEffect(c, progress, w, h, petals) {
    petals.forEach((p,i) => {
        const u = (progress-p.delay)/p.duration;
        if (u<=0 || u>=1) return;
        petal(c, p.x*w + Math.sin(u*6+p.phase)*45, (-.15+p.y*.35+u)*h,
            p.size*1.5,p.phase+u*3,petalColors[i%3],Math.min(1,u*8,(1-u)*6)*.85);
    });
}

export function drawEasterBranch(c,time) {
    c.save(); c.translate(235,198); c.rotate(Math.sin(time/2500)*.025);
    c.strokeStyle='#8fb29a'; c.lineWidth=1.8;
    c.beginPath(); c.moveTo(0,0); c.bezierCurveTo(-90,-5,-105,-75,-165,-112); c.stroke();
    for(let i=0;i<5;i++) {
        const x=-24-i*30,y=-5-i*i*5;
        c.save(); c.translate(x,y); c.rotate(-.5+i*.12);
        c.fillStyle='#8fb29a'; c.beginPath(); c.ellipse(-10,5,12,4,-.5,0,Math.PI*2); c.fill();
        c.translate(i%2?-5:7,-12);
        c.rotate(Math.sin(time/1800+i)*.06);
        for(let j=0;j<5;j++) { c.rotate(Math.PI*2/5); c.fillStyle=i%2?'#f4afcd':'#f7e8bd'; c.beginPath(); c.ellipse(0,-6,4,7,0,0,Math.PI*2); c.fill(); }
        c.fillStyle='#e4b64e';c.beginPath();c.arc(0,0,3,0,Math.PI*2);c.fill();c.restore();
    }
    c.restore();
}
