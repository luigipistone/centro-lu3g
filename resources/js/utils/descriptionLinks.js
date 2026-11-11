const states = new WeakMap();

export function linkDescription(root) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
        if (!walker.currentNode.parentElement.closest('a, script, style, code, pre')) nodes.push(walker.currentNode);
    }
    for (const node of nodes) {
        const text = node.textContent;
        const matches = [...text.matchAll(/\b(?:https?:\/\/|www\.)[^\s<>"']+/gi)];
        if (!matches.length) continue;
        const fragment = document.createDocumentFragment();
        let offset = 0;
        for (const match of matches) {
            let label = match[0].replace(/[.,;:!?]+$/, '');
            while (label.endsWith(')') && (label.match(/\)/g) || []).length > (label.match(/\(/g) || []).length) label = label.slice(0, -1);
            let url;
            try { url = new URL(/^www\./i.test(label) ? `https://${label}` : label); } catch { continue; }
            if (!['http:', 'https:'].includes(url.protocol)) continue;
            fragment.append(document.createTextNode(text.slice(offset, match.index)));
            const anchor = document.createElement('a');
            anchor.href = url.href;
            anchor.textContent = label;
            fragment.append(anchor);
            offset = match.index + label.length;
        }
        if (!offset) continue;
        fragment.append(document.createTextNode(text.slice(offset)));
        node.replaceWith(fragment);
    }
    for (const anchor of root.querySelectorAll('a[href]')) {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
        anchor.style.textDecoration = 'underline';
        anchor.style.overflowWrap = 'anywhere';
    }
}

export const descriptionLinks = {
    mounted(el) {
        const observer = new MutationObserver(() => {
            if (document.activeElement !== el) refresh();
        });
        function refresh() {
            observer.disconnect();
            linkDescription(el);
            observer.observe(el, { childList: true, subtree: true, characterData: true });
        }
        const blur = () => {
            const before = el.innerHTML;
            refresh();
            if (before !== el.innerHTML) el.dispatchEvent(new Event('input', { bubbles: true }));
        };
        const click = (event) => {
            const anchor = event.target.closest?.('a[href]');
            if (!anchor || !el.contains(anchor) || !el.isContentEditable) return;
            const url = new URL(anchor.href, location.href);
            if (!['http:', 'https:', 'mailto:'].includes(url.protocol)) return;
            event.preventDefault();
            window.open(url.href, '_blank', 'noopener,noreferrer');
        };
        el.addEventListener('blur', blur, true);
        el.addEventListener('click', click);
        refresh();
        states.set(el, { observer, blur, click });
    },
    beforeUnmount(el) {
        const state = states.get(el);
        state?.observer.disconnect();
        el.removeEventListener('blur', state?.blur, true);
        el.removeEventListener('click', state?.click);
        states.delete(el);
    },
};
