export function faviconUrls(link) {
    try {
        const url = new URL(link);
        if (!['http:', 'https:'].includes(url.protocol)) return [];
        url.protocol = 'https:';
        return [`${url.origin}/favicon.ico`, `${url.origin}/apple-touch-icon.png`];
    } catch {
        return [];
    }
}
