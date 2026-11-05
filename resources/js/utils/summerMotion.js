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
    c.lineWidth = 1.5;
    wave(c, time, w, h - 10, 5, 0, '#6cdbdc65');
    wave(c, time, w, h - 22, 7, 1.8, '#fff0ba40');
    // Reflections stay at the edge, never across working text.
    for (let i = 0; i < 7; i++) {
        const x = w * (.12 + i * .13), y = h - 35 + Math.sin(time / 2000 + i) * 8;
        c.globalAlpha = .12 + .18 * (1 + Math.sin(time / 1600 + i * 2)) / 2;
        c.strokeStyle = '#fff0b3'; c.lineWidth = 2;
        c.beginPath(); c.moveTo(x - 6, y); c.lineTo(x + 6, y); c.stroke();
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
    wave(c, time, 180, 49, 4, 0, '#68d9da');
    wave(c, time, 180, 64, 5, 2, '#b0f0e7');
    c.restore();
}

export function drawSummerCorner(c, time) {
    c.save(); c.lineWidth = 2;
    wave(c, time, 240, 163, 7, 0, '#8ce4de');
    wave(c, time, 240, 180, 6, 2, '#ffe18b');
    wave(c, time, 240, 195, 5, 4, '#ffc2a9');
    c.restore();
}

export function drawSummerEffect(c, progress, w, h, ripples) {
    c.save();
    for (const ripple of ripples) {
        const u = (progress - ripple.delay) / .78;
        if (u <= 0 || u >= 1) continue;
        c.globalAlpha = Math.sin(u * Math.PI) * .35;
        c.strokeStyle = '#a6eee8'; c.lineWidth = 1.5;
        for (let i = 0; i < 3; i++) {
            const radius = (18 + u * ripple.radius + i * 20) * Math.min(1, w / 700);
            c.beginPath(); c.ellipse(ripple.x * w, ripple.y * h, radius, radius * .24, -.08, 0, Math.PI * 2); c.stroke();
        }
    }
    c.restore();
}
