import { createApp } from 'vue';
import axios from 'axios';
import GoogleCalendarPanel from '../../../resources/js/Components/GoogleCalendarPanel.vue';
import '../../../resources/css/app.css';
const state = { client_id: 'client', has_secret: true, connected: true, enabled: true, calendar_id: 'team', calendar_name: 'Meeting aziendali', import_from: '2026-11-01', months_ahead: 12, redirect_uri: 'https://centro.lu3g.com/settings/google-calendar/callback', last_synced_at: '2026-11-10 09:00:00', summary: { created: 12, updated: 3, unchanged: 44, cancelled: 1, unmatched: ['esterno@example.com'] } };
axios.defaults.adapter = async config => {
    if (config.method === 'put') { window.savedCalendar = JSON.parse(config.data); Object.assign(state, window.savedCalendar); }
    if (config.method === 'post') state.pending = true;
    if (config.method === 'delete') { state.connected = false; state.enabled = false; window.disconnected = true; }
    return { status: 200, statusText: 'OK', headers: {}, config, data: config.url.endsWith('/calendars') ? { calendars: [{ value: 'team', label: 'Meeting aziendali' }, { value: 'other', label: 'Secondo calendario' }] } : { ...state } };
};
createApp(GoogleCalendarPanel).mount('#app');
