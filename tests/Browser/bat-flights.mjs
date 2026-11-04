import assert from 'node:assert/strict';
import { createBatFlights, drawSeasonAtmosphere } from '../../resources/js/utils/seasonalAtmosphere.js';

let seed = 23;
const random = () => ((seed = (seed * 1664525 + 1013904223) >>> 0) / 2 ** 32);
const paths = new Set(), counts = new Set();
for (let i = 0; i < 100; i++) {
    const flights = createBatFlights(random);
    counts.add(flights.length);
    assert(flights.length === 2 || flights.length === 3);
    for (const flight of flights) {
        assert(flight.duration >= 9 && flight.duration <= 13);
        for (const point of [flight.points[0], flight.points[3]]) assert(point.some(v => v < 0 || v > 1));
        const path = JSON.stringify(flight.points);
        assert(!paths.has(path), 'Each flight gets a new route'); paths.add(path);
    }
}
assert.deepEqual([...counts].sort(), [2, 3]);
const context = new Proxy({}, { get: () => () => {} });
const originalRandom = Math.random;
let samples = 0;
try {
    Math.random = () => { samples++; return random(); };
    drawSeasonAtmosphere(context, 'halloween', 0, 1440, 1000);
    const initial = samples;
    for (const t of [16, 1000, 2000, 2000, 3000]) drawSeasonAtmosphere(context, 'halloween', t, 1440, 1000);
    assert.equal(samples, initial, 'Frames and pause/resume retain continuous paths');
    drawSeasonAtmosphere(context, 'halloween', 20000, 1440, 1000);
    assert(samples > initial, 'Next flock regenerates routes');
} finally { Math.random = originalRandom; }
console.log('PASS: 2-3 bats, unique random paths, continuous frames and new flocks');
