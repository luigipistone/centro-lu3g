import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { route } from '../../vendor/tightenco/ziggy';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

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

document.addEventListener('click', openNativeDatePicker, true);
document.addEventListener('focusin', openNativeDatePicker, true);

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
