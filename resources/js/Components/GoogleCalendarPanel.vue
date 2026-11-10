<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import AppSelect from '@/Components/AppSelect.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import Modal from '@/Components/Modal.vue';
import { CalendarDays, Link2, RefreshCw, Save, Unplug, Copy, Check } from '@lucide/vue';

const data = ref(null), calendars = ref([]), busy = ref(false), error = ref(''), message = ref(''), secret = ref(''), confirmDisconnect = ref(false), copied = ref(false);
const draft = ref({ calendar_id: '', import_from: '', months_ahead: 12, enabled: true });
const base = '/settings/google-calendar';
let timer;
async function load(reset = false) {
    const response = await axios.get(base);
    data.value = response.data;
    if (reset) draft.value = { calendar_id: data.value.calendar_id, import_from: data.value.import_from, months_ahead: data.value.months_ahead, enabled: data.value.calendar_id ? data.value.enabled : true };
}
async function action(fn, success = '') {
    busy.value = true; error.value = ''; message.value = '';
    try { await fn(); message.value = success; }
    catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'Operazione non riuscita. Riprova.'; }
    finally { busy.value = false; }
}
async function fetchCalendars() { calendars.value = (await axios.get(`${base}/calendars`)).data.calendars; }
async function saveCredentials() { await axios.put(`${base}/credentials`, { client_id: data.value.client_id, client_secret: secret.value || null }); secret.value = ''; await load(); }
async function save() { await axios.put(base, draft.value); await load(true); }
async function disconnect() { await axios.delete(base); confirmDisconnect.value = false; calendars.value = []; await load(true); }
async function copy() { try { await navigator.clipboard.writeText(data.value.redirect_uri); copied.value = true; setTimeout(() => copied.value = false, 2000); } catch { error.value = 'Copia non disponibile nel browser.'; } }
function date(value) { return value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('it-IT') : 'Non ancora eseguita'; }
onMounted(() => action(async () => { await load(true); if (data.value.connected) await fetchCalendars(); timer = setInterval(() => { if (!busy.value && data.value?.connected) load().catch(() => {}); }, 10000); }));
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <section class="app-card p-5 sm:p-6 space-y-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900"><CalendarDays class="h-5 w-5 text-primary" />Google Calendar</h3>
            <span v-if="data" class="text-sm text-gray-500">{{ data.connected ? (data.enabled ? 'Sincronizzazione attiva' : 'Collegato · In pausa') : 'Non collegato' }}</span>
        </header>
        <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
        <p v-if="message" role="status" class="text-sm text-emerald-600">{{ message }}</p>
        <div v-if="!data" class="py-6 text-sm text-gray-500">Caricamento...</div>
        <template v-else>
            <form v-if="!data.connected" class="space-y-4" @submit.prevent="action(saveCredentials, 'Credenziali salvate. Ora puoi collegare Google.')">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700">Client ID<input v-model="data.client_id" class="form-control" autocomplete="off" required /></label>
                    <label class="block text-sm font-medium text-gray-700">Client secret<input v-model="secret" type="password" class="form-control" autocomplete="new-password" :placeholder="data.has_secret ? 'Già configurato' : ''" :required="!data.has_secret" /></label>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">URI di reindirizzamento</label><div class="mt-1 flex items-center gap-2"><code class="min-w-0 break-all text-xs text-gray-600">{{ data.redirect_uri }}</code><button type="button" class="icon-btn shrink-0" title="Copia URI" @click="copy"><component :is="copied ? Check : Copy" class="h-4 w-4" /></button></div></div>
                <div class="flex flex-wrap gap-3"><button class="btn btn-outline" :disabled="busy"><Save class="h-4 w-4" />Salva credenziali</button><a v-if="data.has_secret" :href="`${base}/connect`" class="btn btn-primary"><Link2 class="h-4 w-4" />Collega Google</a></div>
            </form>
            <form v-else class="space-y-5" @submit.prevent="action(save, 'Impostazioni salvate.')">
                <div class="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div><label class="block text-sm font-medium text-gray-700">Calendario</label><AppSelect v-model="draft.calendar_id" :options="calendars" placeholder="Seleziona calendario" /></div>
                    <div><label class="block text-sm font-medium text-gray-700">Importa dal</label><AppDateInput v-model="draft.import_from" /></div>
                    <div><label class="block text-sm font-medium text-gray-700">Orizzonte futuro</label><AppSelect v-model="draft.months_ahead" :options="[{value: 3, label: '3 mesi'}, {value: 6, label: '6 mesi'}, {value: 12, label: '12 mesi'}, {value: 24, label: '24 mesi'}]" /></div>
                </div>
                <label class="flex items-center gap-3 text-sm font-medium text-gray-700"><input v-model="draft.enabled" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />Sincronizzazione automatica</label>
                <div class="flex flex-wrap gap-3"><button class="btn btn-primary" :disabled="busy || !draft.calendar_id"><Save class="h-4 w-4" />Salva</button><button type="button" class="btn btn-outline" :disabled="busy || !data.enabled || data.pending" @click="action(async () => { await axios.post(`${base}/sync`); await load(); }, 'Sincronizzazione richiesta.')"><RefreshCw :class="['h-4 w-4', data.pending ? 'animate-spin' : '']" />{{ data.pending ? 'In attesa...' : 'Sincronizza ora' }}</button><button type="button" class="icon-btn" title="Aggiorna calendari" :disabled="busy" @click="action(fetchCalendars)"><RefreshCw class="h-4 w-4" /></button><button type="button" class="icon-btn text-red-600" title="Scollega Google" @click="confirmDisconnect = true"><Unplug class="h-4 w-4" /></button></div>
            </form>
            <div v-if="data.connected" class="border-t border-gray-200 pt-4 space-y-2 text-sm">
                <p class="text-gray-500">Ultimo controllo: {{ date(data.last_synced_at) }}</p>
                <p v-if="data.last_error" role="alert" class="text-red-600">{{ data.last_error }}</p><p v-if="data.watch_error" class="text-amber-600">{{ data.watch_error }}</p>
                <div v-if="data.summary" class="flex flex-wrap gap-x-5 gap-y-2 text-gray-700"><span>{{ data.summary.created }} creati</span><span>{{ data.summary.updated }} aggiornati</span><span>{{ data.summary.unchanged }} invariati</span><span>{{ data.summary.cancelled }} rimossi dal calendario</span></div>
                <details v-if="data.summary?.unmatched?.length" class="text-gray-600"><summary class="cursor-pointer">Email non associate ({{ data.summary.unmatched.length }})</summary><p class="mt-2 break-words">{{ data.summary.unmatched.join(', ') }}</p></details>
            </div>
        </template>
    </section>
    <Modal :show="confirmDisconnect" max-width="md" @close="confirmDisconnect = false"><div class="p-6"><h3 class="text-lg font-semibold text-gray-900">Scollegare Google?</h3><p class="mt-3 text-sm text-gray-600">I meeting già importati restano nel Centro, ma non riceveranno aggiornamenti.</p><div class="mt-6 flex justify-end gap-3"><button class="btn btn-outline" @click="confirmDisconnect = false">Annulla</button><button class="btn btn-primary" :disabled="busy" @click="action(disconnect, 'Google scollegato.')">Scollega</button></div></div></Modal>
</template>
