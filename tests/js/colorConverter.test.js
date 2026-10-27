import assert from 'node:assert/strict';
import test from 'node:test';
import { hslToRgb, hsvToRgb, parseHex, parseHsl, parseRgb, rgbToHex, rgbToHsl, rgbToHsv } from '../../resources/js/utils/colorConverter.js';

test('HEX accepts short and long notation and rejects incomplete values', () => {
    assert.deepEqual(parseHex('#0f8'), { r: 0, g: 255, b: 136 });
    assert.deepEqual(parseHex('FF0088'), { r: 255, g: 0, b: 136 });
    assert.equal(parseHex('#12'), null);
    assert.equal(parseHex('#12345678'), null);
});

test('RGB and HSL inputs are bounded', () => {
    assert.equal(parseRgb({ r: 256, g: 0, b: 0 }), null);
    assert.equal(parseRgb({ r: 1.5, g: 0, b: 0 }), null);
    assert.equal(parseHsl({ h: 361, s: 50, l: 50 }), null);
    assert.equal(parseHsl({ h: 0, s: '', l: 50 }), null);
});

test('conversions preserve primary colors and achromatic values', () => {
    assert.equal(rgbToHex(hslToRgb({ h: 0, s: 100, l: 50 })), '#FF0000');
    assert.equal(rgbToHex(hslToRgb({ h: 120, s: 100, l: 50 })), '#00FF00');
    assert.equal(rgbToHex(hslToRgb({ h: 240, s: 100, l: 50 })), '#0000FF');
    assert.deepEqual(rgbToHsl({ r: 128, g: 128, b: 128 }), { h: 0, s: 0, l: 50.2 });
});

test('RGB to HSL and back is stable for ordinary colors', () => {
    for (const rgb of [{ r: 31, g: 99, b: 214 }, { r: 250, g: 184, b: 67 }, { r: 0, g: 0, b: 0 }]) {
        const restored = hslToRgb(rgbToHsl(rgb));
        for (const channel of ['r', 'g', 'b']) assert.ok(Math.abs(restored[channel] - rgb[channel]) <= 1);
    }
});

test('inline picker HSV coordinates match RGB colors', () => {
    assert.equal(rgbToHex(hsvToRgb({ h: 0, s: 100, v: 100 })), '#FF0000');
    assert.equal(rgbToHex(hsvToRgb({ h: 120, s: 100, v: 100 })), '#00FF00');
    assert.equal(rgbToHex(hsvToRgb({ h: 240, s: 100, v: 100 })), '#0000FF');
    assert.equal(rgbToHex(hsvToRgb({ h: 240, s: 0, v: 0 })), '#000000');
    for (const rgb of [{ r: 31, g: 99, b: 214 }, { r: 250, g: 184, b: 67 }, { r: 128, g: 128, b: 128 }]) {
        assert.deepEqual(hsvToRgb(rgbToHsv(rgb)), rgb);
    }
});
