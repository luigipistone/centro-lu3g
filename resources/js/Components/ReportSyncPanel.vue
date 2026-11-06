<script setup>
import ClearableSearchInput from '@/Components/ClearableSearchInput.vue';
import { router } from '@inertiajs/vue3';
import { RefreshCw, ChevronLeft, ChevronRight, Save, KeyRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({ configured: Boolean, latestRun: Object });
const configured = ref(props.configured);
const token = ref('');
const busy = ref('');
const error = ref('');
const message = ref('');
const result = ref(null);
const outcome = ref(props.latestRun?.result ? JSON.parse(props.latestRun.result) : null);
const search = ref('');
const page = ref(1);
const rows = computed(() => (result.value?.clients || []).filter(row => row.name.toLocaleLowerCase('it').includes(search.value.trim().toLocaleLowerCase('it'))));
const pages = computed(() => Math.max(1, Math.ceil(rows.value.length / 25)));
const visible = computed(() => rows.value.slice((page.value - 1) * 25, page.value * 25));
watch(search, () => { page.value = 1; });
const labels = { name: 'Nome', legal_name: 'Ragione sociale', vat_number: 'Partita IVA', email: 'Email', phone: 'Telefono', website: 'Sito web', street: 'Indirizzo', postal_code: 'CAP', city: 'Città', country: 'Paese', province: 'Provincia' };
function action(row) {
    const choice = result.value?.automatic?.choices[row.external_id];
    return !choice ? 'Da verificare' : choice === 'new' ? 'Nuovo' : 'Collegato';
}
async function saveToken() {
    busy.value = 'token'; error.value = ''; message.value = '';
    try {
        await axios.put(route('settings.report.update'), { token: token.value.trim() });
        configured.value = true; token.value = ''; message.value = 'Token salvato.';
        router.reload({ only: ['reportSettings'] });
    } catch (e) { error.value = e.response?.data?.errors?.token?.[0] || 'Salvataggio non riuscito.'; }
    finally { busy.value = ''; }
}
async function load() {
    busy.value = 'preview'; error.value = '';
    try { result.value = (await axios.post(route('settings.report.preview'))).data; page.value = 1; }
    catch (e) { error.value = e.response?.data?.message || 'Anteprima non disponibile.'; }
    finally { busy.value = ''; }
}
async function synchronize() {
    busy.value = 'sync'; error.value = ''; outcome.value = null;
    try { outcome.value = (await axios.post(route('settings.report.automatic'))).data; result.value = null; router.reload({ only: ['reportSettings'] }); }
    catch (e) { error.value = e.response?.data?.message || 'Sincronizzazione non riuscita.'; }
    finally { busy.value = ''; }
}
</script>

<template>
        <div class="space-y-5">
            <form class="app-card" @submit.prevent="saveToken">
                <h3 class="section-title"><span class="section-icon"><KeyRound class="h-4 w-4" /></span>Connessione LU3G Report</h3>
                <div class="mt-4 grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <div class="min-w-0"><label for="report-token" class="block text-sm font-medium text-gray-700">Token API</label><input id="report-token" v-model="token" type="password" class="form-control" :placeholder="configured ? 'Sostituisci token' : 'Inserisci token'" autocomplete="new-password" maxlength="4000" :disabled="Boolean(busy)" /></div>
                    <button class="btn btn-primary" :disabled="Boolean(busy) || !token.trim()"><Save class="h-4 w-4" />Salva token</button>
                </div>
                <p role="status" class="mt-2 text-sm text-gray-500">{{ message || (configured ? 'Connessione configurata · Sincronizzazione giornaliera alle 06:00 (Italia)' : 'Connessione non configurata') }}</p>
            </form>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-sm text-gray-500">Clienti attivi e archiviati</span>
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-outline" :disabled="Boolean(busy) || !configured" @click="load">{{ busy === 'preview' ? 'Lettura...' : 'Anteprima' }}</button>
                    <button class="btn btn-primary" :disabled="Boolean(busy) || !configured" @click="synchronize"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': busy === 'sync' }" />{{ busy === 'sync' ? 'Sincronizzazione...' : 'Sincronizza ora' }}</button>
                </div>
            </div>
            <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
            <div v-if="outcome" role="status" class="surface p-4 text-sm text-gray-700">
                <p>{{ outcome.created }} creati · {{ outcome.updated }} aggiornati · {{ outcome.unchanged }} invariati · {{ outcome.archived }} archiviati per assenza dal Report · {{ outcome.errors.length }} da verificare</p>
                <p v-for="(failure, index) in outcome.errors" :key="index" class="mt-2 text-red-600">{{ failure.name }}: {{ failure.message }}</p>
            </div>
            <p v-else-if="latestRun" class="text-sm text-gray-500">Ultima esecuzione: {{ latestRun.started_at }} · {{ latestRun.created_count }} creati · {{ latestRun.updated_count }} aggiornati · {{ latestRun.unchanged_count }} invariati · {{ latestRun.error_count }} errori</p>
            <template v-if="result">
                <div class="flex flex-wrap items-center justify-between gap-3"><span class="text-sm text-gray-500">{{ result.clients.length }} clienti</span><ClearableSearchInput v-model="search" class="w-full sm:max-w-sm" placeholder="Cerca cliente..." /></div>
                <div class="surface overflow-hidden">
                    <details v-for="row in visible" :key="row.external_id" class="border-b border-gray-200/40 last:border-0">
                        <summary class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-sm">
                            <span class="min-w-0 flex-1 truncate font-semibold text-gray-900" :title="row.name">{{ row.name }}</span>
                            <span class="shrink-0 text-xs text-gray-500">{{ result.source_statuses[row.external_id] === 'active' ? 'Attivo' : 'Archiviato' }}</span>
                            <span class="hidden w-24 shrink-0 text-right text-xs text-gray-500 sm:block">{{ action(row) }}</span>
                            <ChevronRight class="h-3.5 w-3.5 shrink-0 text-gray-500" />
                        </summary>
                        <div class="grid gap-x-6 gap-y-1 px-4 pb-3 text-xs text-gray-700 sm:grid-cols-2 lg:grid-cols-3">
                            <p class="sm:col-span-2 lg:col-span-3">{{ action(row) }}</p>
                            <p v-for="(value, key) in result.profile_changes[row.external_id]" :key="key" class="break-words"><span class="text-gray-500">{{ labels[key] || key }}: </span>{{ value }}</p>
                        </div>
                    </details>
                    <p v-if="!visible.length" class="p-4 text-sm text-gray-500">Nessun risultato.</p>
                </div>
                <div class="flex items-center justify-end gap-3 text-sm text-gray-500"><span>{{ page }} / {{ pages }}</span><button class="icon-btn" :disabled="page <= 1" aria-label="Pagina precedente" title="Pagina precedente" @click="page--"><ChevronLeft class="h-4 w-4" /></button><button class="icon-btn" :disabled="page >= pages" aria-label="Pagina successiva" title="Pagina successiva" @click="page++"><ChevronRight class="h-4 w-4" /></button></div>
            </template>
        </div>
</template>
