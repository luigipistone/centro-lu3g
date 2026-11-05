function curve(points, u) {
    const v = 1 - u;
    return [0, 1].map(k => v ** 3 * points[0][k] + 3 * v * v * u * points[1][k] + 3 * v * u * u * points[2][k] + u ** 3 * points[3][k]);
}

const flightsByCanvas = new WeakMap();

export function createBatFlights(random = Math.random) {
    const between = (min, max) => min + random() * (max - min);
    const edgePoint = edge => {
        const position = between(.12, .88);
        return [[-.1, position], [position, -.1], [1.1, position], [position, 1.1]][edge];
    };
    return Array.from({ length: random() < .5 ? 2 : 3 }, () => {
        const entry = Math.floor(random() * 4);
        const exit = (entry + (random() < .7 ? 2 : random() < .5 ? 1 : 3)) % 4;
        return {
            points: [edgePoint(entry), [between(.15, .85), between(.1, .9)], [between(.15, .85), between(.1, .9)], edgePoint(exit)],
            delay: between(0, .65), duration: between(9, 13),
            size: between(.55, .8), phase: between(0, Math.PI * 2), wingSpeed: between(29, 38),
        };
    });
}

function bat(c, x, y, phase, angle, size, bank, glide) {
    c.save(); c.translate(x, y); c.rotate(angle + Math.PI / 2); c.scale(size, size);
    const stroke = glide ? .12 : Math.sin(phase), fold = glide ? 0 : Math.max(0, Math.cos(phase + .6));
    for (const side of [-1, 1]) {
        c.save(); c.scale(side * (1 + side * bank * .25), 1);
        const elbow = 10 - fold * 2, wrist = 21 - fold * 7, tip = 35 - fold * 11, lift = stroke * 14;
        c.fillStyle = side * bank > 0 ? '#202224' : '#101719';
        c.beginPath(); c.moveTo(2, -4); c.lineTo(elbow, -10 + lift * .3); c.lineTo(wrist, -17 + lift * .8); c.lineTo(tip, -23 + lift);
        c.quadraticCurveTo(tip - 9, -8 + lift * .9, tip - 8, 4 + lift * .6);
        c.quadraticCurveTo(16, -1 + lift * .6, 14, 12 + lift * .35); c.quadraticCurveTo(7, 5, 3, 13); c.closePath(); c.fill();
        c.strokeStyle = '#d3a07440'; c.lineWidth = .65; c.beginPath(); c.moveTo(3, -3); c.lineTo(wrist, -17 + lift * .8); c.lineTo(tip, -23 + lift); c.stroke(); c.restore();
    }
    c.fillStyle = '#111819'; c.beginPath(); c.ellipse(0, 1, 3.4, 9, 0, 0, Math.PI * 2); c.fill();
    c.beginPath(); c.moveTo(-3, -5); c.lineTo(-4, -12); c.lineTo(0, -8); c.lineTo(4, -12); c.lineTo(3, -5); c.fill(); c.restore();
}

// The clock belongs to the component, so pause and hidden-tab time cannot advance a flight.
export function drawSeasonAtmosphere(c, theme, milliseconds, w, h) {
    const t = milliseconds / 1000;
    c.save();
    if (theme === 'halloween') {
        let flock = flightsByCanvas.get(c);
        // Randomness is sampled only between flights, never during a frame or resume.
        if (!flock || t < flock.lastTime || t > flock.ends) {
            const flights = createBatFlights();
            flock = { flights, start: t, ends: t + Math.max(...flights.map(f => f.delay + f.duration)) + 1 + Math.random() * 2 };
            flightsByCanvas.set(c, flock);
        }
        flock.lastTime = t;
        for (const flight of flock.flights) {
            const age = t - flock.start - flight.delay, u = age / flight.duration;
            if (u < 0 || u > 1) continue;
            const points = flight.points.map(([x, y]) => [x * w, y * h]);
            const p = curve(points, u), a = curve(points, Math.max(0, u - .008)), b = curve(points, Math.min(1, u + .008));
            bat(c, ...p, age * flight.wingSpeed + flight.phase, Math.atan2(b[1] - a[1], b[0] - a[0]), flight.size + Math.sin(u * 6) * .06, Math.sin(age * 1.2 + flight.phase) * .65, Math.sin(age * 1.8 + flight.phase) > .4);
        }
    } else if (theme === 'natale') {
        c.strokeStyle = '#b58a55'; c.lineWidth = 1.2;
        c.beginPath(); c.moveTo(0, 8);
        for (let x = 0; x <= w; x += 4) c.lineTo(x, 8 + 13 * Math.sin(Math.PI * (x % 180) / 180));
        c.stroke();
        for (let x = 25, i = 0; x < w; x += 60, i++) {
            const y = 8 + 13 * Math.sin(Math.PI * (x % 180) / 180);
            c.globalAlpha = .75 + .2 * Math.sin(t * 1.4 + i * .7);
            c.fillStyle = i % 3 ? '#ffe2a0' : '#eb6d60'; c.shadowColor = c.fillStyle; c.shadowBlur = 9;
            c.beginPath(); c.ellipse(x, y + 5, 2.5, 4, -.2, 0, Math.PI * 2); c.fill();
        }
        c.shadowBlur = 0;
        for (let i = 0; i < (w < 600 ? 20 : 54); i++) {
            const depth = (i % 5) / 4;
            const x = ((i * 131.7 + Math.sin(t * (.17 + depth * .12) + i) * (18 + depth * 30) + t * 5) % w + w) % w;
            const y = (i * 73 + t * (10 + depth * 18)) % (h + 20) - 10;
            c.globalAlpha = .2 + depth * .45; c.fillStyle = '#fff8eb';
            c.beginPath(); c.arc(x, y, .8 + depth * 1.8, 0, Math.PI * 2); c.fill();
        }
    } else if (theme === 'pasqua') {
        for (let i = 0; i < 12; i++) {
            const x = (i * 173 + Math.sin(t * .4 + i) * 35 + w) % w, y = (i * 97 + t * (9 + i % 4)) % (h + 40) - 20;
            c.save(); c.translate(x, y); c.rotate(t * .5 + i); c.globalAlpha = .55;
            c.fillStyle = i % 2 ? '#ce6989' : '#f2bf56'; c.beginPath(); c.ellipse(0, 0, 3, 7, 0, 0, Math.PI * 2); c.fill(); c.restore();
        }
    } else if (theme === 'estate') {
        c.translate(w - 58, 58); c.rotate(t / 25); c.strokeStyle = '#edae21'; c.lineWidth = 2; c.globalAlpha = .65;
        for (let i = 0; i < 12; i++) { c.rotate(Math.PI / 6); c.beginPath(); c.moveTo(0, 28); c.lineTo(0, 36); c.stroke(); }
        c.fillStyle = '#ffcc49'; c.beginPath(); c.arc(0, 0, 20, 0, Math.PI * 2); c.fill();
    }
    c.restore();
}
