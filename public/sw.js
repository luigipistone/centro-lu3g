self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    const payload = event.data?.json?.() || {};
    const title = payload.title || 'Il Centro';
    const options = {
        body: payload.body || payload.message || 'Hai una nuova notifica.',
        icon: payload.icon || '/icons/icon-192.svg',
        badge: payload.badge || '/icons/icon-192.svg',
        tag: payload.tag || payload.id || 'centro-notification',
        renotify: Boolean(payload.renotify),
        data: {
            url: payload.url || '/notifications',
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/notifications';

    event.waitUntil((async () => {
        const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existingClient = allClients.find((client) => client.url.includes(self.location.origin));

        if (existingClient) {
            await existingClient.focus();
            existingClient.navigate(url);
            return;
        }

        await self.clients.openWindow(url);
    })());
});
