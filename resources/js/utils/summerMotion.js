export const summerEffectDuration = 6500;

export function createSummerRipples(count = 6, random = Math.random) {
    return Array.from({ length: count }, () => ({
        x: random(), y: .72 + random() * .25,
        delay: random() * .22, radius: 100 + random() * 180,
    }));
}

function wave(c, time, width, y, amplitude, phase, color) {
    c.strokeStyle = color;
    c.beginPath();
    for (let x = 0; x <= width + 8; x += 8) {
        const offset = Math.sin(x / 90 + time / 2400 + phase) * amplitude
            + Math.sin(x / 163 - time / 3400) * amplitude * .4;
        if (x === 0) c.moveTo(x, y + offset);
        else c.lineTo(x, y + offset);
    }
    c.stroke();
}

export function drawSummerAtmosphere(c, time, w, h) {
    c.save();
    const t = time / 1000;
    const depth = w < 600 ? 23 : 31;
    // Different frequencies move continuously: no repeated rows or per-frame randomness.
    const shoreline = (x, layer) => h - depth + layer * 8
        + Math.sin(x * .009 - t * .48 + layer * 1.7) * 3.5
        + Math.sin(x * .023 + t * .31 + layer) * 1.8
        + Math.sin(x * .051 - t * .19) * .7
        + Math.sin(t * .67 + layer * .9) * 3;
    for (let layer = 0; layer < 3; layer++) {
        const water = c.createLinearGradient(0, h - depth - 9, 0, h);
        water.addColorStop(0, '#a5e9de08');
        water.addColorStop(.35, ['#72d9cc45', '#32b8bc40', '#198eaa32'][layer]);
        water.addColorStop(1, ['#2ba9b561', '#159bb25c', '#087d9b4a'][layer]);
        c.beginPath();
        c.moveTo(0, shoreline(0, layer));
        for (let x = 4; x <= w + 4; x += 4) c.lineTo(x, shoreline(x, layer));
        c.lineTo(w, h); c.lineTo(0, h); c.closePath();
        c.fillStyle = water; c.fill();

        // Soft foam follows the advancing crest, with uneven gaps and a fading wake.
        c.lineCap = 'round';
        for (let x = 0; x < w; x += 9) {
            const strength = .5 + .5 * Math.sin(x * .043 + layer * 3 + t * .22);
            if (strength < .25) continue;
            c.strokeStyle = `rgba(246,255,250,${(.18 + strength * .48) * (1 - layer * .18)})`;
            c.lineWidth = .6 + strength * 1.4;
            c.beginPath(); c.moveTo(x, shoreline(x, layer) + .7);
            c.quadraticCurveTo(x + 4, shoreline(x + 4, layer), x + 5 + strength * 5, shoreline(x + 8, layer) + .5);
            c.stroke();
            if (layer === 0 && strength > .65) {
                c.fillStyle = `rgba(255,255,250,${strength * .35})`;
                c.beginPath();
                c.ellipse(x + 3, shoreline(x, layer) + 3 + strength * 3, 1.6, .65, 0, 0, Math.PI * 2);
                c.fill();
            }
        }
    }
    // Short, irregular glints suggest moving water rather than a geometric outline.
    c.lineWidth = .7;
    for (let i = 0; i < Math.ceil(w / 22); i++) {
        const x = (i * 47.3 + Math.sin(t * .4 + i) * 5) % w;
        const y = h - 2 - (i % 4) * 3;
        const shimmer = Math.max(0, Math.sin(t * .8 + i * 2.4));
        c.strokeStyle = `rgba(233,255,242,${shimmer * .25})`;
        c.beginPath(); c.moveTo(x, y); c.lineTo(x + 3 + shimmer * 5, y - .5); c.stroke();
    }
    c.restore();
}

export function drawSummerSun(c, time) {
    c.save();
    c.save(); c.rotate(time / 22000);
    c.strokeStyle = '#ffdc79'; c.lineWidth = 4; c.lineCap = 'round';
    for (let i = 0; i < 12; i++) {
        c.rotate(Math.PI / 6); c.beginPath(); c.moveTo(0, -48);
        c.lineTo(0, -60 - Math.sin(time / 2000 + i) * 3); c.stroke();
    }
    c.restore();
    const fill = c.createRadialGradient(-12, -15, 4, 0, 0, 40);
    fill.addColorStop(0, '#fff0a3'); fill.addColorStop(.6, '#ffcc50'); fill.addColorStop(1, '#f69a35');
    c.fillStyle = fill; c.beginPath(); c.arc(0, 0, 36, 0, Math.PI * 2); c.fill();
    c.translate(-90, 0); c.lineWidth = 4;
    wave(c, time, 180, 49, 4, 0, '#008b95');
    wave(c, time, 180, 64, 5, 2, '#37b6ad');
    c.restore();
}

export function drawSummerCorner(c, time) {
    c.save(); c.lineWidth = 2;
    wave(c, time, 240, 163, 7, 0, '#087780');
    wave(c, time, 240, 180, 6, 2, '#bd8d00');
    wave(c, time, 240, 195, 5, 4, '#26998c');
    c.restore();
}

export function drawSummerEffect(c, progress, w, h, ripples) {
    c.save();
    for (const ripple of ripples) {
        const u = (progress - ripple.delay) / .78;
        if (u <= 0 || u >= 1) continue;
        c.globalAlpha = Math.sin(u * Math.PI) * .35;
        c.strokeStyle = '#087780'; c.lineWidth = 1.5;
        for (let i = 0; i < 3; i++) {
            const radius = (18 + u * ripple.radius + i * 20) * Math.min(1, w / 700);
            c.beginPath(); c.ellipse(ripple.x * w, ripple.y * h, radius, radius * .24, -.08, 0, Math.PI * 2); c.stroke();
        }
    }
    c.restore();
}
