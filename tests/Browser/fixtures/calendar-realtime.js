import { createApp, ref } from 'vue';
import { useCalendarRealtime } from '../../../resources/js/composables/useCalendarRealtime';
window.route = name => '/fixture/' + name;
createApp({
    setup() {
        const rows = ref([]), busy = ref(false), draft = ref('Bozza non salvata');
        window.setBusy = value => { busy.value = value; };
        const state = useCalendarRealtime({
            section: 'calendar', rows: [],
            calendarRealtime: { enabled: true, key: 'test-key', host: '127.0.0.1',
                port: Number(new URLSearchParams(location.search).get('port')), tls: false },
        }, () => busy.value, value => { rows.value = value; });
        return { rows, state, draft };
    },
    template: '<main><p id="state">{{state}}</p><input v-model="draft"><div id="rows">{{JSON.stringify(rows)}}</div></main>',
}).mount('#app');
