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

export async function showCentroBrowserNotification(title, options = {}) {
    if (browserNotificationSupport() !== 'granted') {
        return false;
    }

    const payload = {
        icon: '/icons/icon-192.svg',
        badge: '/icons/icon-192.svg',
        ...options,
    };

    if ('serviceWorker' in navigator) {
        const registration = await navigator.serviceWorker.ready;
        await registration.showNotification(title, payload);
        return true;
    }

    const notification = new window.Notification(title, payload);
    if (payload.data?.url) {
        notification.onclick = () => {
            window.focus();
            window.location.href = payload.data.url;
            notification.close();
        };
    }

    return true;
}

export async function enableCentroBrowserNotifications() {
    if (browserNotificationSupport() === 'unsupported') {
        return { permission: 'unsupported', message: 'Questo browser non supporta le notifiche.' };
    }

    await registerCentroServiceWorker();
    const permission = await window.Notification.requestPermission();

    if (permission === 'granted') {
        await showCentroBrowserNotification('Il Centro', {
            body: 'Notifiche browser attivate.',
            tag: 'centro-notifications-enabled',
            renotify: false,
            data: { url: '/notifications' },
        });

        return { permission, message: 'Notifiche browser attivate.' };
    }

    if (permission === 'denied') {
        return { permission, message: 'Notifiche bloccate dal browser. Riattivale dalle impostazioni del sito.' };
    }

    return { permission, message: 'Attivazione notifiche non completata.' };
}
