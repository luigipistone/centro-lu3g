export function isTelevision(item) {
    return /\b(?:tv|televisor[ei]|television[ei])\b/i.test(`${item.name || ''} ${item.category || ''}`);
}

export function placePhysicalItems(items, columns = 3) {
    const placements = [];
    let slot = 0;
    for (const item of items) {
        const span = isTelevision(item) ? 2 : 1;
        for (let unit = 0; unit < Math.min(Number(item.quantity || 0), 100); unit++) {
            if (span > columns - (slot % columns)) slot += columns - (slot % columns);
            placements.push({ item, slot, span });
            slot += span;
        }
    }
    return placements;
}
