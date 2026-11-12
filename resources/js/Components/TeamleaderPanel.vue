<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import AppDateInput from '@/Components/AppDateInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import Modal from '@/Components/Modal.vue';
import { CalendarDays, Link2, RefreshCw, Save, Unplug, Copy, Check, ChevronLeft, ChevronRight } from '@lucide/vue';

const base = '/settings/teamleader';
const data = ref(null), secret = ref(''), busy = ref(false), error = ref(''), message = ref(''), copied = ref(false), confirmDisconnect = ref(false);
const today = new Date();
const localDate = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const from = ref(localDate(today)), to = ref(localDate(new Date(today.getFullYear(), today.getMonth() + 1, today.getDate())));
const rows = ref([]), complete = ref(false), page = ref(1), period = ref('');
const draft = ref({ enabled: false, import_from: '', months_ahead: 12 });
const visible = computed(() => rows.value.slice((page.value - 1) * 20, page.value * 20));
const matched = computed(() => rows.value.filter(r => r.assignees.length && !r.unmatched.length).length);
let disposed = false, timer, poll;
async function load(reset = false) {
    data.value = (await axios.get(base)).data;
    if (reset) draft.value = { enabled: data.value.enabled, import_from: data.value.import_from, months_ahead: data.value.months_ahead };
}
async function saveSync() { await axios.put(base, draft.value); await load(true); message.value = 'Impostazioni salvate.'; }
async function requestSync() { await axios.post(base + '/sync'); await load(); }
async function action(fn) {
    busy.value = true; error.value = ''; message.value = '';
    try { await fn(); } catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || e.message || 'Operazione non riuscita.'; }
    finally { busy.value = false; }
}
async function save() {
    await axios.put(`${base}/credentials`, { client_id: data.value.client_id, client_secret: secret.value || null });
    secret.value = ''; await load(); message.value = 'Credenziali salvate.';
}
async function simulate() {
    rows.value = []; complete.value = false; page.value = 1;
    const input = { from: from.value, to: to.value, connection_id: data.value.connection_id };
    period.value = `${input.from} / ${input.to}`;
    let next = true, current = 1; const ids = new Set();
    while (next && !disposed) {
        const response = (await axios.post(`${base}/simulate`, { ...input, page: current++ })).data;
        if (response.has_more && !response.rows.length) throw new Error('Paginazione non valida. Ripeti la simulazione.');
        for (const row of response.rows) {
            if (ids.has(row.id)) throw new Error('Elenco cambiato durante la lettura. Ripeti la simulazione.');
            ids.add(row.id); rows.value.push(row);
        }
        next = response.has_more;
    }
    complete.value = !disposed;
}
async function disconnect() { await axios.delete(base); confirmDisconnect.value = false; rows.value = []; complete.value = false; period.value = ''; await load(); }
async function copy() { await navigator.clipboard.writeText(data.value.redirect_uri); copied.value = true; timer = setTimeout(() => copied.value = false, 2000); }
function date(value) { return new Date(value).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Rome' }); }
onMounted(() => action(async () => { await load(true); poll = setInterval(() => { if (!busy.value && data.value?.connected) load().catch(() => {}); }, 10000); }));
onUnmounted(() => { disposed = true; clearTimeout(timer); clearInterval(poll); });
</script>

<template>
    <section class="app-card p-5 sm:p-6 space-y-5 min-w-0">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900"><CalendarDays class="h-5 w-5 text-primary" />Teamleader</h3>
            <span class="text-sm text-gray-500">{{ data?.connected ? (data.enabled ? 'Sincronizzazione attiva' : 'Collegato · In pausa') : 'Non collegato' }}</span>
        </header>
        <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
        <p v-if="message" role="status" class="text-sm text-emerald-600">{{ message }}</p>
        <p v-if="!data" class="text-sm text-gray-500">Caricamento...</p>
        <form v-else-if="!data.connected" class="space-y-4" @submit.prevent="action(save)">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block text-sm font-medium text-gray-700">Client ID<input v-model="data.client_id" class="form-control" autocomplete="off" required :disabled="busy" /></label>
                <label class="block text-sm font-medium text-gray-700">Client secret<input v-model="secret" class="form-control" type="password" autocomplete="new-password" :placeholder="data.has_secret ? 'Già configurato' : ''" :required="!data.has_secret" :disabled="busy" /></label>
            </div>
            <div><label class="block text-sm font-medium text-gray-700">URI di reindirizzamento</label><div class="mt-1 flex items-center gap-2"><code class="min-w-0 break-all text-xs text-gray-600">{{ data.redirect_uri }}</code><button type="button" class="icon-btn shrink-0" title="Copia URI" @click="action(copy)"><component :is="copied ? Check : Copy" class="h-4 w-4" /></button></div></div>
            <div class="flex flex-wrap gap-3"><button class="btn btn-outline" :disabled="busy"><Save class="h-4 w-4" />Salva credenziali</button><a v-if="data.has_secret && !busy" :href="`${base}/connect`" class="btn btn-primary"><Link2 class="h-4 w-4" />Collega Teamleader</a></div>
        </form>
        <template v-else>
            <form class="space-y-4 border-b border-gray-200 pb-5" @submit.prevent="action(saveSync)">
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="block text-sm font-medium text-gray-700">Importa dal</label><AppDateInput v-model="draft.import_from" /></div><div><label class="block text-sm font-medium text-gray-700">Orizzonte futuro</label><AppSelect v-model="draft.months_ahead" :options="[{value:3,label:'3 mesi'},{value:6,label:'6 mesi'},{value:12,label:'12 mesi'},{value:24,label:'24 mesi'}]" /></div></div>
                <label class="flex items-center gap-3 text-sm font-medium text-gray-700"><input v-model="draft.enabled" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />Sincronizzazione automatica ogni 5 minuti</label>
                <div class="flex flex-wrap gap-3"><button class="btn btn-primary" :disabled="busy"><Save class="h-4 w-4" />Salva</button><button type="button" class="btn btn-outline" :disabled="busy || !data.enabled || data.pending" @click="action(requestSync)"><RefreshCw :class="['h-4 w-4', data.pending ? 'animate-spin' : '']" />{{ data.pending ? 'In attesa...' : 'Sincronizza ora' }}</button></div>
                <p v-if="data.last_error" role="alert" class="text-sm text-red-600">{{ data.last_error }}</p>
                <div class="text-sm text-gray-500 space-y-2"><p>Ultimo controllo: {{ data.last_synced_at ? date(data.last_synced_at) : 'Non ancora eseguito' }}</p><p v-if="data.summary">{{ data.summary.created }} creati · {{ data.summary.updated }} aggiornati · {{ data.summary.unchanged }} invariati · {{ data.summary.cancelled }} rimossi dal calendario</p><details v-if="data.summary?.unmatched?.length"><summary>Email non associate ({{ data.summary.unmatched.length }})</summary><p v-for="email in data.summary.unmatched" :key="email" class="break-all">{{ email }}</p></details></div>
            </form>
            <h4 class="font-semibold text-gray-900">Simulazione senza modifiche</h4>
            <form class="space-y-4" @submit.prevent="action(simulate)">
                <fieldset :disabled="busy" class="grid gap-4 sm:grid-cols-2">
                    <div><label class="block text-sm font-medium text-gray-700">Dal</label><AppDateInput v-model="from" /></div>
                    <div><label class="block text-sm font-medium text-gray-700">Al</label><AppDateInput v-model="to" /></div>
                </fieldset>
                <div class="flex flex-wrap items-center gap-3"><button class="btn btn-primary" :disabled="busy || !from || !to"><RefreshCw :class="['h-4 w-4', busy ? 'animate-spin' : '']" />{{ busy ? 'Lettura in corso...' : 'Esegui simulazione' }}</button><button type="button" class="icon-btn text-red-600" title="Scollega Teamleader" :disabled="busy" @click="confirmDisconnect = true"><Unplug class="h-4 w-4" /></button><span class="text-sm text-gray-500">Nessuna task verrà creata o modificata.</span></div>
            </form>
            <div v-if="period" class="border-t border-gray-200 pt-4 space-y-3 min-w-0">
                <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600" role="status"><strong>{{ complete ? 'Simulazione completata' : 'Risultati parziali' }}</strong><span>{{ rows.length }} meeting</span><span>{{ matched }} con partecipanti associati</span><span>{{ rows.length - matched }} da verificare</span><span>{{ period }}</span></div>
                <p v-if="complete && !rows.length" class="text-sm text-gray-500">Nessun meeting nel periodo selezionato.</p>
                <div v-for="row in visible" :key="row.id" class="grid gap-2 border-b border-gray-200 py-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div class="min-w-0"><p class="font-semibold text-gray-900 break-words">{{ row.title || 'Senza titolo' }}</p><p class="text-gray-500">{{ date(row.scheduled_at) }} · {{ row.duration ?? '?' }} min</p></div>
                    <div class="min-w-0 text-gray-700 break-words">{{ row.assignees.join(', ') || 'Nessun assegnatario associato' }}<p v-if="row.unmatched.length" class="text-amber-700 break-all">Da associare: {{ row.unmatched.join(', ') }}</p></div>
                    <p class="text-gray-600 break-words">{{ row.client || (row.has_customer ? 'Cliente non associato' : 'Nessun cliente') }}</p>
                    <p class="text-gray-600">{{ row.status === 'done' ? 'Completato su Teamleader' : row.status === 'open' ? 'Da fare' : row.status }}</p>
                </div>
                <div v-if="rows.length > 20" class="flex justify-end items-center gap-3"><button class="icon-btn" title="Pagina precedente" :disabled="page === 1" @click="page--"><ChevronLeft class="h-4 w-4" /></button><span class="text-sm text-gray-600">{{ page }} / {{ Math.ceil(rows.length / 20) }}</span><button class="icon-btn" title="Pagina successiva" :disabled="page * 20 >= rows.length" @click="page++"><ChevronRight class="h-4 w-4" /></button></div>
            </div>
        </template>
        <Modal :show="confirmDisconnect" @close="confirmDisconnect = false"><div class="p-6 space-y-4"><h3 class="text-lg font-semibold text-gray-900">Scollega Teamleader</h3><p class="text-sm text-gray-600">Le credenziali di accesso verranno rimosse dal Centro. Nessuna task verrà modificata.</p><div class="flex justify-end gap-3"><button class="btn btn-outline" :disabled="busy" @click="confirmDisconnect = false">Annulla</button><button class="btn btn-primary" :disabled="busy" @click="action(disconnect)">Scollega</button></div></div></Modal>
    </section>
</template>
