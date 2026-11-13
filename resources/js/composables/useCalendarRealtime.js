import Pusher from 'pusher-js';
import axios from 'axios';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useCalendarRealtime(props, busy, apply) {
    const state = ref('disabled');
    let socket, channel, timer, controller, stopped = false;
    let dirty = false, navigating = false, epoch = 0;
    const disposers = [];
    const blocked = () => navigating || busy() || document.hidden;
    const invalidate = () => {
        epoch++;
        controller?.abort();
        dirty = true;
        schedule();
    };
    function schedule(delay = 150) {
        clearTimeout(timer);
        if (!stopped && dirty && !blocked()) timer = setTimeout(refresh, delay);
    }
    async function refresh() {
        if (stopped || blocked() || !dirty) return;
        dirty = false;
        const revision = epoch;
        controller?.abort();
        controller = new AbortController();
        try {
            const { data } = await axios.get(route('calendar.live-data'), {
                signal: controller.signal, headers: { Accept: 'application/json' },
            });
            if (stopped) return;
            if (revision !== epoch || blocked()) { dirty = true; schedule(); return; }
            if (!Array.isArray(data.rows)) throw new Error('Invalid calendar snapshot');
            apply(data.rows);
            state.value = socket?.connection.state === 'connected' ? 'connected' : 'reconnecting';
        } catch (error) {
            if (stopped || axios.isCancel(error)) return;
            if ([401, 403, 419, 503].includes(error.response?.status)) {
                state.value = 'unavailable';
                socket?.disconnect();
                return;
            }
            state.value = 'reconnecting';
            dirty = true;
            schedule(5000);
        }
    }
    const resume = () => { if (!document.hidden) invalidate(); };
    watch(busy, () => schedule());
    watch(() => props.rows, () => { if (socket) invalidate(); });
    onMounted(() => {
        const config = props.calendarRealtime;
        if (props.section !== 'calendar' || !config?.enabled || !config.key) return;
        state.value = 'connecting';
        socket = new Pusher(config.key, {
            cluster: 'mt1', wsHost: config.host, wsPort: config.port, wssPort: config.port,
            forceTLS: config.tls, enabledTransports: ['ws', 'wss'], disableStats: true,
            channelAuthorization: {
                customHandler: async ({ socketId, channelName }, callback) => {
                    try {
                        const { data } = await axios.post(route('calendar.realtime.auth'), {
                            socket_id: socketId, channel_name: channelName,
                        });
                        callback(null, data);
                    } catch (error) { callback(error, null); }
                },
            },
        });
        channel = socket.subscribe('private-calendar');
        channel.bind('calendar.changed', invalidate);
        channel.bind('pusher:subscription_succeeded', invalidate);
        channel.bind('pusher:subscription_error', () => { state.value = 'unavailable'; });
        socket.connection.bind('state_change', ({ current }) => {
            state.value = current === 'connected' ? 'connected' : 'reconnecting';
        });
        disposers.push(router.on('start', () => {
            navigating = true; epoch++; controller?.abort(); dirty = true;
        }));
        disposers.push(router.on('finish', () => { navigating = false; schedule(); }));
        document.addEventListener('visibilitychange', resume);
        window.addEventListener('online', resume);
    });
    onUnmounted(() => {
        stopped = true;
        clearTimeout(timer);
        controller?.abort();
        channel?.unbind_all();
        socket?.disconnect();
        disposers.forEach(dispose => dispose());
        document.removeEventListener('visibilitychange', resume);
        window.removeEventListener('online', resume);
    });
    return state;
}
