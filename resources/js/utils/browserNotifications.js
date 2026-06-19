export function browserNotificationSupport() {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return 'unsupported';
    }

    return window.Notification.permission;
}

function withTimeout(promise, milliseconds, fallback = null) {
    return Promise.race([
        promise,
        new Promise((resolve) => window.setTimeout(() => resolve(fallback), milliseconds)),
    ]);
}

function requestBrowserPermission() {
    if (typeof window !== 'undefined' && !window.isSecureContext) {
        return Promise.resolve('denied');
    }

    try {
        const result = window.Notification.requestPermission();
        if (result?.then) {
            return result;
        }

        if (typeof result === 'string') {
            return Promise.resolve(result);
        }
    } catch (error) {
        console.warn('Richiesta permesso notifiche moderna non disponibile', error);
    }

    return new Promise((resolve, reject) => {
        try {
            window.Notification.requestPermission(resolve);
        } catch (error) {
            reject(error);
        }
    });
}

export async function registerCentroServiceWorker() {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return null;
    }

    try {
        return await withTimeout(navigator.serviceWorker.register('/sw.js'), 10000, null);
    } catch (error) {
        console.warn('Service worker non registrato', error);
        return null;
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((character) => character.charCodeAt(0)));
}

async function subscribeCentroPush(vapidPublicKey) {
    if (!vapidPublicKey || !('PushManager' in window) || !('serviceWorker' in navigator)) {
        return false;
    }

    await registerCentroServiceWorker();

    const registration = await Promise.race([
        navigator.serviceWorker.ready,
        new Promise((resolve) => window.setTimeout(() => resolve(null), 10000)),
    ]);
    if (!registration) return false;

    const existingSubscription = await withTimeout(registration.pushManager.getSubscription(), 8000, null);
    const subscription = existingSubscription || await withTimeout(registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    }), 15000, null);

    if (!subscription) return false;

    const response = await withTimeout(window.axios.post('/push-subscriptions', subscription.toJSON()), 10000, null);
    if (!response) return false;

    return true;
}

export async function ensureCentroPushSubscription(vapidPublicKey = null) {
    if (browserNotificationSupport() !== 'granted') {
        return false;
    }

    try {
        await registerCentroServiceWorker();
        return await subscribeCentroPush(vapidPublicKey);
    } catch (error) {
        console.warn('Sottoscrizione push non completata', error);
        return false;
    }
}

export async function showCentroBrowserNotification(title, options = {}) {
    if (browserNotificationSupport() !== 'granted') {
        return false;
    }

    const payload = {
        icon: '/icons/icon-192.svg',
        badge: '/icons/icon-192.svg',
        ...options,
    };

    const showNativeNotification = () => {
        try {
            const notification = new window.Notification(title, payload);
            if (payload.data?.url) {
                notification.onclick = () => {
                    window.focus();
                    window.location.href = payload.data.url;
                    notification.close();
                };
            }

            return true;
        } catch (error) {
            console.warn('Notifica browser non mostrata', error);
            return false;
        }
    };

    if ('serviceWorker' in navigator) {
        const registration = await Promise.race([
            navigator.serviceWorker.ready,
            new Promise((resolve) => window.setTimeout(() => resolve(null), 1200)),
        ]);
        if (!registration) {
            return showNativeNotification();
        }

        const shown = await withTimeout(registration.showNotification(title, payload).then(() => true), 1600, false);
        return shown || showNativeNotification();
    }

    return showNativeNotification();
}

export async function enableCentroBrowserNotifications(vapidPublicKey = null, options = {}) {
    const { showTestNotification = true } = options;
    const support = browserNotificationSupport();
    if (support === 'unsupported') {
        return { permission: 'unsupported', message: 'Questo browser non supporta le notifiche.' };
    }

    if (typeof window !== 'undefined' && !window.isSecureContext) {
        return { permission: 'denied', message: 'Le notifiche browser richiedono una connessione HTTPS sicura.' };
    }

    let permission = support;
    if (permission !== 'granted') {
        try {
            permission = await withTimeout(requestBrowserPermission(), 12000, browserNotificationSupport());
        } catch (error) {
            return { permission, message: 'Il browser non ha completato la richiesta di autorizzazione.' };
        }
    }

    if (permission === 'default') {
        return {
            permission,
            message: 'Il browser ha chiuso o bloccato la richiesta. Clicca di nuovo su Attiva browser e scegli Consenti nella finestra del browser.',
        };
    }

    if (permission === 'granted') {
        try {
            await registerCentroServiceWorker();
        } catch (error) {
            console.warn('Service worker non registrato', error);
        }

        let pushSubscribed = false;
        try {
            pushSubscribed = await withTimeout(subscribeCentroPush(vapidPublicKey), 30000, false);
        } catch (error) {
            console.warn('Sottoscrizione push non completata', error);
        }

        if (showTestNotification) {
            try {
                await withTimeout(showCentroBrowserNotification('Il Centro', {
                    body: 'Notifiche browser attivate.',
                    tag: 'centro-notifications-enabled',
                    renotify: false,
                    data: { url: '/notifications' },
                }), 2500, false);
            } catch (error) {
                console.warn('Notifica di test non mostrata', error);
            }
        }

        return {
            permission,
            message: pushSubscribed
                ? 'Notifiche browser e push attivate.'
                : 'Notifiche browser attivate. Push non disponibile su questo dispositivo.',
        };
    }

    if (permission === 'denied') {
        return { permission, message: 'Notifiche bloccate dal browser. Riattivale dalle impostazioni del sito.' };
    }

    return { permission, message: 'Attivazione notifiche non completata.' };
}
