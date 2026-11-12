export function normalizeSearch(value) {
    return String(value ?? '').normalize('NFKD').replace(/\p{M}/gu, '').toLowerCase()
        .replace(/ß/g, 'ss').replace(/æ/g, 'ae').replace(/œ/g, 'oe')
        .replace(/ø/g, 'o').replace(/ł/g, 'l');
}

export function matchesSearch(value, query) {
    const tokens = normalizeSearch(query).match(/[\p{L}\p{N}]+/gu) || [];
    const text = normalizeSearch(Array.isArray(value) ? value.join(' ') : value).replace(/[^\p{L}\p{N}]/gu, '');
    return tokens.every(token => text.includes(token));
}
