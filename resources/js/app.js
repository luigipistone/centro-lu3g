import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { route } from '../../vendor/tightenco/ziggy';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { ensureCentroPushSubscription, registerCentroServiceWorker, requestCentroBrowserPermission } from './utils/browserNotifications';

const appName = import.meta.env.VITE_APP_NAME || 'Il Centro';

window.route = route;

function openNativeDatePicker(event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;
    if (!['date', 'time', 'datetime-local', 'month', 'week'].includes(input.type)) return;

    try {
        input.showPicker?.();
    } catch (error) {
        input.focus?.();
    }
}

function enableBrowserNotificationsFromNativeClick(event) {
    const trigger = event.target?.closest?.('[data-enable-browser-notifications]');
    if (!trigger) return;
    if (typeof window === 'undefined' || !('Notification' in window)) return;
    if (window.Notification.permission !== 'default') return;

    event.preventDefault();
    event.stopImmediatePropagation();

    const vapidPublicKey = window.CentroPush?.vapidPublicKey;
    trigger.dataset.notificationActivation = 'pending';

    requestCentroBrowserPermission()
        .then((permission) => {
            trigger.dataset.notificationActivation = permission;
            window.dispatchEvent(new CustomEvent('centro:browser-notification-permission', {
                detail: { permission },
            }));

            if (permission === 'granted') {
                return ensureCentroPushSubscription(vapidPublicKey);
            }

            return false;
        })
        .then((subscribed) => {
            if (subscribed) {
                trigger.dataset.notificationActivation = 'subscribed';
            }
        })
        .catch((error) => {
            trigger.dataset.notificationActivation = 'failed';
            console.warn('Attivazione notifiche browser non riuscita', error);
        });
}

document.addEventListener('click', enableBrowserNotificationsFromNativeClick, true);
document.addEventListener('click', openNativeDatePicker, true);
document.addEventListener('focusin', openNativeDatePicker, true);
registerCentroServiceWorker();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0B6EF3',
    },
});
