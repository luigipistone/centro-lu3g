<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import AppSelect from '@/Components/AppSelect.vue';
import { Mail, Save, RefreshCw, Unplug, ExternalLink } from '@lucide/vue';
const state = ref({}); const labels = ref([]); const secret = ref(''); const error = ref(''); const feedback = ref(''); const busy = ref(false);
async function run(action) { busy.value = true; error.value = ''; feedback.value = ''; try { await action(); } catch (e) { error.value = e.response?.data?.message || 'Operazione non riuscita.'; } finally { busy.value = false; } }
async function load() {
    state.value = (await axios.get('/requests/gmail')).data;
    if (state.value.connected) {
        labels.value = state.value.label_id ? [{value:state.value.label_id,label:state.value.label_name}] : [];
        const result = (await axios.get('/requests/gmail/labels')).data;
        labels.value = result.labels;
        feedback.value = result.warning || '';
    }
}
onMounted(() => run(load));
function credentials() { run(async () => { state.value = (await axios.put('/requests/gmail/credentials', {client_id:state.value.client_id,client_secret:secret.value})).data; secret.value=''; feedback.value='Credenziali salvate.'; }); }
function save() { run(async () => { state.value=(await axios.put('/requests/gmail',{label_id:state.value.label_id,enabled:state.value.enabled})).data; feedback.value='Impostazioni salvate.'; }); }
function sync() { run(async () => { await axios.post('/requests/gmail/sync'); feedback.value='Controllo richiesto. Avvio entro un minuto.'; }); }
function disconnect() { run(async () => { await axios.delete('/requests/gmail'); await load(); }); }
</script>
<template>
    <section class="surface space-y-5 p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900"><Mail class="h-5 w-5" />Gmail · Richieste clienti</h3><span class="text-sm text-gray-500">{{ state.email || 'Non collegato' }}</span></div>
        <details v-if="state.admin" :open="!state.configured" class="border-b border-gray-200 pb-4">
            <summary class="cursor-pointer text-sm font-semibold text-gray-700">Credenziali Google</summary>
            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="credentials">
                <label class="block min-w-0 text-sm text-gray-700">Client ID<input v-model="state.client_id" class="form-control" required autocomplete="off" /></label>
                <label class="block min-w-0 text-sm text-gray-700">Client secret<input v-model="secret" type="password" class="form-control" required autocomplete="new-password" /></label>
                <div class="min-w-0 sm:col-span-2"><span class="text-xs text-gray-500">URI di reindirizzamento</span><code class="block break-all text-sm text-gray-700">{{ state.redirect_uri }}</code></div>
                <button class="btn btn-primary w-fit" :disabled="busy"><Save class="h-4 w-4" />Salva credenziali</button>
            </form>
        </details>
        <a v-if="state.configured && !state.connected" href="/requests/gmail/connect" class="btn btn-primary"><ExternalLink class="h-4 w-4" />Collega la mia casella</a>
        <template v-if="state.connected">
            <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto]"><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Etichetta Gmail</span><AppSelect v-model="state.label_id" :options="labels" searchable placeholder="Invia al Centro" /></label><label class="flex items-center gap-2 py-3 text-sm text-gray-700"><input v-model="state.enabled" type="checkbox" />Controllo automatico</label></div>
            <div class="flex flex-wrap items-center gap-3"><button class="btn btn-primary" :disabled="busy || !state.label_id" @click="save"><Save class="h-4 w-4" />Salva</button><button class="btn btn-outline" :disabled="busy || !state.enabled" @click="sync"><RefreshCw class="h-4 w-4" :class="{'animate-spin':busy}" />Controlla ora</button><button class="icon-btn text-red-500" title="Scollega la mia casella" aria-label="Scollega la mia casella" :disabled="busy" @click="disconnect"><Unplug class="h-4 w-4" /></button></div>
            <p v-if="state.last_synced_at" class="text-xs text-gray-500">Ultimo controllo: {{ new Date(state.last_synced_at).toLocaleString('it-IT') }}</p>
        </template>
        <p v-if="error || state.last_error" role="alert" class="text-sm text-red-600">{{ error || state.last_error }}</p>
        <p v-if="feedback" role="status" class="text-sm text-green-700">{{ feedback }}</p>
    </section>
</template>
