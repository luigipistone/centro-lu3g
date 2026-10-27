export function parseHex(value) {
    const match = String(value).trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!match) return null;
    const digits = match[1].length === 3
        ? [...match[1]].map((digit) => digit + digit).join('')
        : match[1];
    return {
        r: parseInt(digits.slice(0, 2), 16),
        g: parseInt(digits.slice(2, 4), 16),
        b: parseInt(digits.slice(4, 6), 16),
    };
}

export function rgbToHex({ r, g, b }) {
    return `#${[r, g, b].map((value) => value.toString(16).padStart(2, '0')).join('').toUpperCase()}`;
}

export function parseRgb(values) {
    const channels = [values.r, values.g, values.b].map((value) => Number(value));
    if ([values.r, values.g, values.b].some((value) => String(value).trim() === '')
        || channels.some((value) => !Number.isInteger(value) || value < 0 || value > 255)) return null;
    return { r: channels[0], g: channels[1], b: channels[2] };
}

export function rgbToHsl({ r, g, b }) {
    const [red, green, blue] = [r, g, b].map((value) => value / 255);
    const max = Math.max(red, green, blue);
    const min = Math.min(red, green, blue);
    const delta = max - min;
    let hue = 0;
    if (delta) {
        if (max === red) hue = ((green - blue) / delta) % 6;
        else if (max === green) hue = (blue - red) / delta + 2;
        else hue = (red - green) / delta + 4;
    }
    const lightness = (max + min) / 2;
    const saturation = delta ? delta / (1 - Math.abs(2 * lightness - 1)) : 0;
    return {
        h: Math.round((hue * 60 + 360) % 360),
        s: Math.round(saturation * 1000) / 10,
        l: Math.round(lightness * 1000) / 10,
    };
}

export function parseHsl(values) {
    const raw = [values.h, values.s, values.l];
    const numbers = raw.map((value) => Number(value));
    if (raw.some((value) => String(value).trim() === '')
        || numbers.some((value) => !Number.isFinite(value))
        || numbers[0] < 0 || numbers[0] > 360
        || numbers[1] < 0 || numbers[1] > 100
        || numbers[2] < 0 || numbers[2] > 100) return null;
    return { h: numbers[0], s: numbers[1], l: numbers[2] };
}

export function hslToRgb({ h, s, l }) {
    const hue = (h % 360) / 60;
    const saturation = s / 100;
    const lightness = l / 100;
    const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
    const secondary = chroma * (1 - Math.abs((hue % 2) - 1));
    const offset = lightness - chroma / 2;
    const sectors = [
        [chroma, secondary, 0], [secondary, chroma, 0], [0, chroma, secondary],
        [0, secondary, chroma], [secondary, 0, chroma], [chroma, 0, secondary],
    ];
    const [r, g, b] = sectors[Math.floor(hue) % 6];
    return { r: Math.round((r + offset) * 255), g: Math.round((g + offset) * 255), b: Math.round((b + offset) * 255) };
}
