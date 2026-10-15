const entries = {
    Essenziali: [
        ['bold-serif', 'Grassetto con grazie'], ['bold-sans', 'Grassetto senza grazie'],
        ['bold-italic', 'Grassetto corsivo'], ['italic-serif', 'Corsivo con grazie'],
        ['italic-sans', 'Corsivo senza grazie'], ['sans', 'Senza grazie'],
        ['monospace', 'Monospazio'], ['blackboard', 'Doppio tratto'],
        ['script', 'Scrittura a mano'], ['bold-script', 'Scrittura a mano grassetto'],
        ['fraktur', 'Gotico'], ['bold-fraktur', 'Gotico grassetto'],
        ['wide', 'Largo'], ['japanese', 'Giapponese'],
    ],
    Forme: [
        ['bubble-outline', 'Cerchiato'], ['bubble-filled', 'Cerchiato pieno'],
        ['square-outline', 'Quadrato'], ['square-filled', 'Quadrato pieno'],
        ['square-alternating', 'Quadrati alternati'], ['parentheses', 'Tra parentesi'],
        ['bubble-square-a', 'Cerchi e quadrati A'], ['bubble-square-b', 'Cerchi e quadrati B'],
        ['square-mix-a', 'Quadrati misti A'], ['square-mix-b', 'Quadrati misti B'],
        ['mixed-a', 'Forme miste A'], ['mixed-b', 'Forme miste B'],
        ['superscript', 'Apice'], ['subscript', 'Pedice'],
        ['small-caps', 'Maiuscoletto'], ['mirror', 'Specchio'],
    ],
    Effetti: [
        ['overline', 'Sopralineato'], ['slash-light', 'Barrato obliquo'],
        ['slash-heavy', 'Barrato obliquo marcato'], ['strikethrough', 'Barrato'],
        ['strikethrough-heavy', 'Barrato marcato'], ['underline', 'Sottolineato'],
        ['underline-double', 'Sottolineato doppio'], ['arrow', 'Freccia'],
        ['asterisk', 'Stelle'], ['circle', 'Anelli'], ['triangle', 'Diamanti'],
        ['zigzag', 'Zigzag'],
    ],
    Creativi: [
        ['emoji', 'Emoji'], ['leet', 'Leet'], ['runic', 'Runico'],
        ['cherokee', 'Cherokee'], ['random-a', 'Misto A'],
        ['random-b', 'Misto B'], ['random-c', 'Misto C'],
    ],
    Altri: [
        ['extra-superscript', 'Apice alternativo'], ['extra-bold', 'Grassetto alternativo'],
        ['extra-italic', 'Corsivo alternativo'], ['extra-underline', 'Sottolineato alternativo'],
        ['extra-stroked', 'Barrato alternativo'], ['extra-brackets', 'Parentesi'],
        ['extra-bubble', 'Bolle'], ['extra-glitch', 'Glitch'],
        ['extra-weird', 'Strano'], ['extra-cursive', 'Corsivo creativo'],
        ['extra-cursed', 'Distorto'], ['extra-cool', 'Doppio tratto alternativo'],
        ['extra-tiny', 'Minuscolo creativo'], ['extra-discord', 'Discord'],
        ['extra-facebook', 'Facebook'], ['extra-twitter', 'Twitter'],
        ['extra-instagram', 'Instagram'], ['extra-snapchat', 'Snapchat'],
        ['extra-tiktok', 'TikTok'], ['extra-youtube', 'YouTube'],
        ['extra-whatsapp', 'WhatsApp'], ['extra-telegram', 'Telegram'],
        ['extra-pinterest', 'Pinterest'], ['extra-aesthetic', 'Estetico'],
    ],
};

export const styledTextCategories = ['Tutti', ...Object.keys(entries)];
export const styledTextStyles = Object.entries(entries).flatMap(([category, items]) =>
    items.map(([id, label]) => ({ id, label, category })));

const ranges = {
    'bold-serif': [0x1d400, 0x1d41a, 0x1d7ce], 'bold-sans': [0x1d5d4, 0x1d5ee, 0x1d7ec],
    'bold-italic': [0x1d468, 0x1d482], 'italic-serif': [0x1d434, 0x1d44e],
    'italic-sans': [0x1d608, 0x1d622], sans: [0x1d5a0, 0x1d5ba, 0x1d7e2],
    monospace: [0x1d670, 0x1d68a, 0x1d7f6],
    'bold-script': [0x1d4d0, 0x1d4ea], 'bold-fraktur': [0x1d56c, 0x1d586],
};
const maps = {
    blackboard: ['𝔸𝔹ℂ𝔻𝔼𝔽𝔾ℍ𝕀𝕁𝕂𝕃𝕄ℕ𝕆ℙℚℝ𝕊𝕋𝕌𝕍𝕎𝕏𝕐ℤ', '𝕒𝕓𝕔𝕕𝕖𝕗𝕘𝕙𝕚𝕛𝕜𝕝𝕞𝕟𝕠𝕡𝕢𝕣𝕤𝕥𝕦𝕧𝕨𝕩𝕪𝕫', '𝟘𝟙𝟚𝟛𝟜𝟝𝟞𝟟𝟠𝟡'],
    script: ['𝒜ℬ𝒞𝒟ℰℱ𝒢ℋℐ𝒥𝒦ℒℳ𝒩𝒪𝒫𝒬ℛ𝒮𝒯𝒰𝒱𝒲𝒳𝒴𝒵', '𝒶𝒷𝒸𝒹ℯ𝒻ℊ𝒽𝒾𝒿𝓀𝓁𝓂𝓃ℴ𝓅𝓆𝓇𝓈𝓉𝓊𝓋𝓌𝓍𝓎𝓏'],
    fraktur: ['𝔄𝔅ℭ𝔇𝔈𝔉𝔊ℌℑ𝔍𝔎𝔏𝔐𝔑𝔒𝔓𝔔ℜ𝔖𝔗𝔘𝔙𝔚𝔛𝔜ℨ', '𝔞𝔟𝔠𝔡𝔢𝔣𝔤𝔥𝔦𝔧𝔨𝔩𝔪𝔫𝔬𝔭𝔮𝔯𝔰𝔱𝔲𝔳𝔴𝔵𝔶𝔷'],
    'small-caps': ['', 'ᴀʙᴄᴅᴇꜰɢʜɪᴊᴋʟᴍɴᴏᴘǫʀꜱᴛᴜᴠᴡxʏᴢ'],
    leet: ['', '48cd3f6h1jk1mn0p9r57uvwxy2'],
    runic: ['', 'ᚨᛒᚲᛞᛖᚠᚷᚺᛁᛃᚲᛚᛗᚾᛟᛈᛩᚱᛊᛏᚢᚹᚹᛪᛦᛉ'],
    cherokee: ['', 'ᎠᏴᏟᎠᎬᎰᏳᎻᎥᎫᏦᏞᎷᏁᎾᏢᎤᏒᏚᎢᏌᏙᏔᏆᎩᏃ'],
};

function byRange(text, [upper, lower, digits]) {
    return Array.from(text, char => {
        const code = char.codePointAt(0);
        if (code >= 65 && code <= 90) return String.fromCodePoint(upper + code - 65);
        if (code >= 97 && code <= 122) return String.fromCodePoint(lower + code - 97);
        if (digits && code >= 48 && code <= 57) return String.fromCodePoint(digits + code - 48);
        return char;
    }).join('');
}

function byMap(text, [upper, lower, digits = '']) {
    const capitals = Array.from(upper);
    const small = Array.from(lower);
    const numbers = Array.from(digits);
    return Array.from(text, char => {
        const code = char.codePointAt(0);
        if (code >= 65 && code <= 90) return capitals[code - 65] || small[code - 65] || char;
        if (code >= 97 && code <= 122) return small[code - 97] || char;
        if (code >= 48 && code <= 57) return numbers[code - 48] || char;
        return char;
    }).join('');
}

function mark(text, character, repeat = 1) {
    return Array.from(text, char => /\s/u.test(char) ? char : char + character.repeat(repeat)).join('');
}

function alternate(text, ids) {
    let index = 0;
    return Array.from(text, char => {
        if (!/[a-z]/i.test(char)) return char;
        return styleText(char, ids[index++ % ids.length]);
    }).join('');
}

const superscript = 'ᵃᵇᶜᵈᵉᶠᵍʰⁱʲᵏˡᵐⁿᵒᵖᑫʳˢᵗᵘᵛʷˣʸᶻ';
const subscript = 'ₐᵦ𝒸𝒹ₑ𝒻𝓰ₕᵢⱼₖₗₘₙₒₚ𝓆ᵣₛₜᵤᵥ𝓌ₓᵧ𝓏';
const upsideDown = 'ɐqɔpǝɟƃɥᴉɾʞןɯuodbɹsʇnʌʍxʎz';

export function styleText(input, styleId) {
    const text = String(input);
    if (ranges[styleId]) {
        const output = byRange(text, ranges[styleId]);
        return styleId === 'italic-serif' ? output.replaceAll(String.fromCodePoint(0x1d455), 'ℎ') : output;
    }
    if (maps[styleId]) return byMap(text, maps[styleId]);
    if (styleId === 'bubble-outline') return byRange(text, [0x24b6, 0x24d0]);
    if (styleId === 'bubble-filled') return byRange(text.toUpperCase(), [0x1f150, 0x1f150]);
    if (styleId === 'square-outline') return byRange(text.toUpperCase(), [0x1f130, 0x1f130]);
    if (styleId === 'square-filled') return byRange(text.toUpperCase(), [0x1f170, 0x1f170]);
    if (styleId === 'square-alternating') return alternate(text.toUpperCase(), ['square-outline', 'square-filled']);
    if (styleId === 'parentheses') return byRange(text.toLowerCase(), [0x249c, 0x249c]);
    if (styleId === 'extra-brackets') return text.replace(/\S+/gu, word => `[${word}]`);
    if (styleId === 'bubble-square-a') return alternate(text, ['bubble-outline', 'square-filled']);
    if (styleId === 'mixed-a') return alternate(text, ['square-filled', 'bubble-outline']);
    if (styleId === 'bubble-square-b') return alternate(text, ['square-outline', 'bubble-filled']);
    if (styleId === 'square-mix-b') return alternate(text, ['bubble-filled', 'square-outline']);
    if (styleId === 'square-mix-a') return alternate(text, ['square-filled', 'square-outline']);
    if (styleId === 'mixed-b') return alternate(text, ['square-outline', 'square-filled', 'bubble-filled']);
    if (styleId === 'superscript') return byMap(text.toLowerCase(), ['', superscript, '⁰¹²³⁴⁵⁶⁷⁸⁹']);
    if (styleId === 'extra-superscript') return alternate(text, ['superscript', 'small-caps']);
    if (styleId === 'subscript') return byMap(text.toLowerCase(), ['', subscript, '₀₁₂₃₄₅₆₇₈₉']);
    if (styleId === 'mirror') return byMap(Array.from(text).reverse().join('').toLowerCase(), ['', upsideDown]);
    if (styleId === 'extra-twitter') return byMap(text.toLowerCase(), ['', upsideDown]);
    if (styleId === 'extra-instagram') return alternate(Array.from(text).reverse().join(''), ['italic-serif', 'bold-serif']);
    if (styleId === 'wide') return byRange(text, [0xff21, 0xff41, 0xff10]);
    if (styleId === 'japanese') return alternate(text, ['wide', 'sans']);
    if (styleId === 'extra-weird') return alternate(text, ['wide', 'italic-serif']);
    if (styleId === 'overline') return mark(text, '\u0305');
    if (styleId === 'slash-light') return mark(text, '\u0337');
    if (styleId === 'slash-heavy') return mark(text, '\u0338');
    if (styleId === 'strikethrough') return mark(text, '\u0336');
    if (styleId === 'strikethrough-heavy') return mark(text, '\u20e0');
    if (styleId === 'underline') return mark(text, '\u0332');
    if (styleId === 'extra-underline') return mark(text, '\u0332', 2);
    if (styleId === 'underline-double') return mark(text, '\u0333');
    if (styleId === 'arrow') return mark(text, '\u0362');
    if (styleId === 'asterisk') return mark(text, '\u0359');
    if (styleId === 'circle') return mark(text, '\u035b');
    if (styleId === 'triangle') return mark(text, '\u20df');
    if (styleId === 'zigzag') return mark(text, '\u035b', 2);
    if (styleId === 'emoji') return Array.from(text, char => ({ A: '🅰️', B: '🅱️', E: '📧', I: 'ℹ️', M: 'Ⓜ️', O: '🅾️', P: '🅿️', S: '💲', X: '❌' }[char.toUpperCase()] || styleText(char, 'bubble-outline'))).join('');
    if (styleId === 'random-a') return alternate(text, ['bold-serif', 'italic-serif', 'bold-sans']);
    if (styleId === 'random-b') return alternate(text, ['bold-italic', 'sans', 'blackboard']);
    if (styleId === 'random-c') return alternate(text, ['italic-sans', 'bold-script', 'monospace']);
    if (styleId === 'extra-bold') return alternate(text, ['bold-serif', 'bold-sans']);
    if (styleId === 'extra-italic') return alternate(text, ['italic-serif', 'italic-sans']);
    if (styleId === 'extra-aesthetic') return alternate(text, ['script', 'italic-serif']);
    if (styleId === 'extra-stroked') return mark(text, '\u0336', 2);
    if (styleId === 'extra-bubble') return alternate(text, ['bubble-outline', 'bubble-filled']);
    if (styleId === 'extra-glitch') return alternate(text, ['superscript', 'italic-sans']);
    if (styleId === 'extra-cursive') return alternate(text, ['script', 'bold-script']);
    if (styleId === 'extra-cursed') return alternate(text, ['fraktur', 'italic-serif']);
    if (styleId === 'extra-cool') return alternate(text, ['blackboard', 'bold-serif']);
    if (styleId === 'extra-tiny') return alternate(text, ['small-caps', 'superscript']);
    if (styleId === 'extra-discord') return alternate(text, ['wide', 'runic']);
    if (styleId === 'extra-facebook') return alternate(text, ['fraktur', 'script']);
    if (styleId === 'extra-snapchat') return alternate(text, ['bubble-outline', 'wide']);
    if (styleId === 'extra-tiktok') return alternate(text, ['bold-serif', 'runic']);
    if (styleId === 'extra-youtube') return alternate(text, ['script', 'bold-italic']);
    if (styleId === 'extra-whatsapp') return mark(text, '\u0301');
    if (styleId === 'extra-telegram') return alternate(text, ['runic', 'wide']);
    if (styleId === 'extra-pinterest') return alternate(text, ['bubble-outline', 'script']);
    return text;
}
