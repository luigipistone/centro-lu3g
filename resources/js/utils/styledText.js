export const styledTextStyles = [
    { id: 'bold-serif', label: 'Grassetto', detail: 'Con grazie', upper: 0x1d400, lower: 0x1d41a, digit: 0x1d7ce },
    { id: 'bold-sans', label: 'Grassetto', detail: 'Senza grazie', upper: 0x1d5d4, lower: 0x1d5ee, digit: 0x1d7ec },
    { id: 'italic-serif', label: 'Corsivo', detail: 'Con grazie', upper: 0x1d434, lower: 0x1d44e },
    { id: 'bold-italic', label: 'Grassetto corsivo', detail: 'Con grazie', upper: 0x1d468, lower: 0x1d482 },
    { id: 'monospace', label: 'Monospazio', detail: 'Larghezza fissa', upper: 0x1d670, lower: 0x1d68a, digit: 0x1d7f6 },
];

export function styleText(input, styleId) {
    const style = styledTextStyles.find(item => item.id === styleId);
    if (!style) return input;

    return Array.from(input, character => {
        const code = character.codePointAt(0);
        if (code >= 65 && code <= 90) return String.fromCodePoint(style.upper + code - 65);
        if (code >= 97 && code <= 122) {
            if (styleId === 'italic-serif' && code === 104) return 'ℎ';
            return String.fromCodePoint(style.lower + code - 97);
        }
        if (style.digit && code >= 48 && code <= 57) return String.fromCodePoint(style.digit + code - 48);
        return character;
    }).join('');
}
