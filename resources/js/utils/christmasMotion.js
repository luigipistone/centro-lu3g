export const christmasEffectDuration = 8500;
export const christmasBulbColors = ['#ff454f', '#31b7ff', '#ffcf32', '#43d77d', '#ed65ed'];

export function drawChristmasBulb(c, x, y, index, time, scale = 1) {
    c.save(); c.translate(x, y); c.rotate(Math.sin(index * 2.3) * .35); c.scale(scale, scale);
    c.fillStyle = '#6c7471'; c.fillRect(-3, -2, 6, 5);
    c.globalAlpha *= .8 + .2 * Math.sin(time * .0012 + index * 1.7);
    c.fillStyle = christmasBulbColors[index % christmasBulbColors.length];
    c.shadowColor = c.fillStyle; c.shadowBlur = 11;
    c.beginPath(); c.moveTo(-3, 3); c.bezierCurveTo(-9, 8, -6, 15, 0, 19);
    c.bezierCurveTo(6, 15, 9, 8, 3, 3); c.closePath(); c.fill();
    c.shadowBlur = 0; c.strokeStyle = '#ffffffb0'; c.lineWidth = 1.2;
    c.beginPath(); c.moveTo(-3, 6); c.quadraticCurveTo(-5, 9, -2, 12); c.stroke(); c.restore();
}

// Sample once per click: frames and viewport changes keep continuous trajectories.
export function createChristmasStars(random = Math.random) {
    return Array.from({ length: 34 }, () => ({
        x: .03 + random() * .94, y: -.16 + random() * .35,
        delay: random() * .22, duration: .58 + random() * .2,
        drift: (random() - .5) * .15, phase: random() * Math.PI * 2,
        rotation: (random() - .5) * 3, size: 4 + random() * 6,
    }));
}

export function drawChristmasStars(c, progress, width, height, stars) {
    for (const star of stars) {
        const p = (progress - star.delay) / star.duration;
        if (p <= 0 || p >= 1) continue;
        c.save();
        c.translate(star.x * width + star.drift * width * p + Math.sin(p * 5 + star.phase) * 18,
            star.y * height + p * height * 1.18);
        c.rotate(star.phase + p * star.rotation);
        c.globalAlpha = Math.min(1, p * 10, (1 - p) * 7) * .85;
        c.fillStyle = '#ffe1a5'; c.shadowColor = '#ffcd67'; c.shadowBlur = 5;
        c.beginPath();
        for (let i = 0; i < 10; i++) {
            const angle = i * Math.PI / 5 - Math.PI / 2, radius = star.size * (i % 2 ? .45 : 1);
            c.lineTo(Math.cos(angle) * radius, Math.sin(angle) * radius);
        }
        c.closePath(); c.fill(); c.restore();
    }
}
