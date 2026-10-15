export function cleanText(input, options = {}, documentParser = null) {
    let text = String(input).replace(/\r\n?/g, '\n');

    if (options.stripHtml && documentParser) {
        const doc = documentParser.parseFromString(text, 'text/html');
        doc.querySelectorAll('script, style, noscript, template').forEach(node => node.remove());
        doc.querySelectorAll('br').forEach(node => node.replaceWith('\n'));
        doc.querySelectorAll('p, div, li, h1, h2, h3, h4, h5, h6').forEach(node => node.append('\n'));
        text = doc.body.textContent || '';
    }
    if (options.decodeEntities && documentParser) {
        text = documentParser.parseFromString(text, 'text/html').documentElement.textContent || '';
    }
    if (options.removeUrls) text = text.replace(/https?:\/\/[^\s<>]+|www\.[^\s<>]+/giu, '');
    if (options.removeEmails) text = text.replace(/[\w.+-]+@[\w.-]+\.[a-z]{2,}/giu, '');
    if (options.removeEmoji) text = text.replace(/[\p{Extended_Pictographic}\uFE0F\u200D]/gu, '');
    if (options.removeDiacritics) text = text.normalize('NFD').replace(/\p{M}/gu, '').normalize('NFC');
    if (options.removePunctuation) text = text.replace(/\p{P}/gu, '');
    if (options.tabsToSpaces) text = text.replace(/\t/g, ' ');
    if (options.joinLines) text = text.replace(/[\t ]*\n[\t ]*/g, ' ');
    if (options.trimLines || options.collapseSpaces) {
        text = text.split('\n').map(line => {
            let result = options.collapseSpaces ? line.replace(/[\t ]{2,}/g, ' ') : line;
            return options.trimLines ? result.trim() : result;
        }).join('\n');
    }
    if (options.removeDuplicateLines) {
        const seen = new Set();
        text = text.split('\n').filter(line => {
            if (!line.trim()) return true;
            if (seen.has(line)) return false;
            seen.add(line);
            return true;
        }).join('\n');
    }
    if (options.removeBlankLines) text = text.split('\n').filter(line => line.trim()).join('\n');
    else if (options.collapseBlankLines) text = text.replace(/\n(?:[\t ]*\n){2,}/g, '\n\n');
    if (options.case === 'upper') text = text.toLocaleUpperCase('it');
    if (options.case === 'lower') text = text.toLocaleLowerCase('it');
    if (options.case === 'title') text = text.toLocaleLowerCase('it').replace(/(^|[\s\p{P}])\p{L}/gu, match => match.toLocaleUpperCase('it'));
    if (options.case === 'sentence') text = text.toLocaleLowerCase('it').replace(/(^|[.!?]\s+|\n+)\p{L}/gu, match => match.replace(/\p{L}/u, letter => letter.toLocaleUpperCase('it')));
    if (options.find) text = text.split(options.find).join(options.replace ?? '');
    if (options.trimText) text = text.trim();
    return text;
}
