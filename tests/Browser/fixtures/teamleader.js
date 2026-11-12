import { createApp } from 'vue';
import axios from 'axios';
import TeamleaderPanel from '../../../resources/js/Components/TeamleaderPanel.vue';
import '../../../resources/css/app.css';
const state = { client_id: 'client', has_secret: true, connected: true, connection_id: '00000000-0000-4000-8000-000000000001', redirect_uri: 'https://centro.lu3g.com/settings/teamleader/callback' };
window.previewCalls = [];
axios.defaults.adapter = async config => {
    let data = { ...state };
    if (config.method === 'post') {
        const input = JSON.parse(config.data); window.previewCalls.push(input);
        data = { rows: Array.from({ length: input.page === 1 ? 20 : 1 }, (_, i) => ({ id: input.page + '-' + i, title: 'Meeting di verifica con partecipante interno e cliente aziendale', scheduled_at: '2026-11-12T10:00:00+01:00', duration: 30, status: 'open', assignees: ['Collega Test'], unmatched: [], client: 'CLIENTE TEST' })), has_more: input.page === 1, read_only: true };
    }
    if (config.method === 'delete') { state.connected = false; data = { ...state }; }
    return { status: 200, statusText: 'OK', headers: {}, config, data };
};
createApp(TeamleaderPanel).mount('#app');
