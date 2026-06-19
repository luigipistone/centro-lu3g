export function browserNotificationSupport() {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return 'unsupported';
    }

    return window.Notification.permission;
}

export async function registerCentroServiceWorker() {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return null;
    }

    try {
        return await navigator.serviceWorker.register('/sw.js');
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

    const registration = await Promise.race([
        navigator.serviceWorker.ready,
        new Promise((resolve) => window.setTimeout(() => resolve(null), 1800)),
    ]);
    if (!registration) return false;

    const existingSubscription = await registration.pushManager.getSubscription();
    const subscription = existingSubscription || await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    await window.axios.post('/push-subscriptions', subscription.toJSON());
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
        const notification = new window.Notification(title, payload);
        if (payload.data?.url) {
            notification.onclick = () => {
                window.focus();
                window.location.href = payload.data.url;
                notification.close();
            };
        }

        return true;
    };

    if ('serviceWorker' in navigator) {
        const registration = await Promise.race([
            navigator.serviceWorker.ready,
            new Promise((resolve) => window.setTimeout(() => resolve(null), 1200)),
        ]);
        if (!registration) {
            return showNativeNotification();
        }

        await registration.showNotification(title, payload);
        return true;
    }

    return showNativeNotification();
}

export async function enableCentroBrowserNotifications(vapidPublicKey = null) {
    const support = browserNotificationSupport();
    if (support === 'unsupported') {
        return { permission: 'unsupported', message: 'Questo browser non supporta le notifiche.' };
    }

    try {
        await registerCentroServiceWorker();
    } catch (error) {
        console.warn('Service worker non registrato', error);
    }

    let permission = support;
    try {
        permission = await window.Notification.requestPermission();
    } catch (error) {
        return { permission, message: 'Il browser non ha completato la richiesta di autorizzazione.' };
    }

    if (permission === 'granted') {
        let pushSubscribed = false;
        try {
            pushSubscribed = await subscribeCentroPush(vapidPublicKey);
        } catch (error) {
            console.warn('Sottoscrizione push non completata', error);
        }

        try {
            await showCentroBrowserNotification('Il Centro', {
                body: 'Notifiche browser attivate.',
                tag: 'centro-notifications-enabled',
                renotify: false,
                data: { url: '/notifications' },
            });
        } catch (error) {
            console.warn('Notifica di test non mostrata', error);
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
