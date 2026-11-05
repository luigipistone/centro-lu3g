<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClearableSearchInput from '@/Components/ClearableSearchInput.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw, ChevronLeft, ChevronRight, Save, KeyRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({ configured: Boolean });
const configured = ref(props.configured);
const token = ref('');
const saving = ref(false);
const tokenMessage = ref('');
const tokenError = ref('');
async function saveToken() {
    saving.value = true;
    tokenMessage.value = '';
    tokenError.value = '';
    try {
        await axios.put(route('settings.report.update'), { token: token.value.trim() });
        configured.value = true;
        token.value = '';
        result.value = null;
        tokenMessage.value = 'Token salvato e cifrato.';
    } catch (e) {
        tokenError.value = e.response?.data?.errors?.token?.[0] || 'Salvataggio non riuscito. Riprova.';
    } finally {
        saving.value = false;
    }
}
const result = ref(null);
const loading = ref(false);
const error = ref('');
const search = ref('');
const view = ref('clients');
const page = ref(1);
const rows = computed(() => (result.value?.[view.value] || []).filter(row => row.name.toLocaleLowerCase('it').includes(search.value.trim().toLocaleLowerCase('it'))));
const pages = computed(() => Math.max(1, Math.ceil(rows.value.length / 25)));
const visible = computed(() => rows.value.slice((page.value - 1) * 25, page.value * 25));
watch([search, view], () => { page.value = 1; });
async function load() {
    loading.value = true;
    error.value = '';
    result.value = null;
    try {
        result.value = (await axios.post(route('settings.report.preview'))).data;
        page.value = 1;
    } catch (e) {
        error.value = e.response?.status === 429 ? 'Attendi un minuto prima di riprovare.' : (e.response?.data?.message || 'Lettura non riuscita. Riprova.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head title="LU3G Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('settings.index')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500 hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" />Torna alle impostazioni</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">LU3G Report</h2>
            </div>
        </template>
        <div class="py-8"><div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
            <form class="app-card" @submit.prevent="saveToken">
                <h3 class="section-title"><span class="section-icon"><KeyRound class="h-4 w-4" :stroke-width="1.7" /></span>Connessione LU3G Report</h3>
                <div class="mt-5 grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <div class="min-w-0"><label for="report-token" class="block text-sm font-medium text-gray-700">Token API</label><input id="report-token" v-model="token" type="password" class="form-control" :placeholder="configured ? 'Inserisci un nuovo token per sostituirlo' : 'Inserisci il token'" autocomplete="new-password" maxlength="4000" :disabled="saving || loading" /></div>
                    <button type="submit" class="btn btn-primary" :disabled="saving || loading || !token.trim()"><Save class="h-4 w-4" />{{ saving ? 'Salvataggio...' : 'Salva token' }}</button>
                </div>
                <p v-if="tokenError" role="alert" class="mt-2 text-sm text-red-600">{{ tokenError }}</p>
                <p v-else-if="tokenMessage || configured" role="status" class="mt-2 text-sm text-emerald-600">{{ tokenMessage || 'Token configurato.' }}</p>
            </form>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500">Anteprima · Solo clienti attivi · Nessuna modifica ai clienti</p>
                <button class="btn btn-primary" :disabled="loading || saving || !configured" @click="load"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />{{ loading ? 'Lettura in corso...' : 'Aggiorna anteprima' }}</button>
            </div>
            <p v-if="!configured" class="surface p-5 text-sm text-gray-700">Connessione non configurata.</p>
            <p v-if="error" role="alert" class="surface p-5 text-sm text-red-600">{{ error }}</p>
            <p v-if="loading" role="status" class="text-sm text-gray-500">Verifica dell’elenco completo in corso...</p>
            <template v-if="result">
                <div class="flex flex-wrap gap-5 text-sm text-gray-700"><span v-for="(count, label) in result.summary" :key="label"><strong>{{ count }}</strong> {{ label }}</span></div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex gap-2" role="tablist" aria-label="Associazioni"><button v-for="tab in [['clients', 'Clienti'], ['areas', 'Categorie']]" :key="tab[0]" role="tab" :aria-selected="view === tab[0]" :class="['settings-tab', { 'settings-tab-active': view === tab[0] }]" @click="view = tab[0]">{{ tab[1] }}</button></div>
                    <ClearableSearchInput v-model="search" class="w-full sm:max-w-sm" placeholder="Cerca per nome..." />
                </div>
                <div class="surface overflow-hidden">
                    <div v-for="row in visible" :key="row.external_id" class="grid gap-2 border-b border-gray-200/40 p-4 last:border-0 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <div class="min-w-0"><p class="break-words font-semibold text-gray-900">{{ row.name }}</p><p class="mt-1 text-xs text-gray-500">{{ view === 'clients' ? row.status : (row.excluded ? 'Esclusa' : (row.candidates.length ? 'Da confermare' : 'Senza corrispondenza')) }}</p></div>
                        <div class="min-w-0 text-sm text-gray-700"><p v-for="candidate in row.candidates" :key="candidate.id" class="break-words">{{ candidate.name }}<span v-if="candidate.reasons" class="text-gray-500"> · {{ candidate.reasons.join(', ') }}</span></p><p v-if="!row.candidates.length" class="text-gray-500">{{ row.client_id ? 'Associazione ID presente' : '—' }}</p></div>
                    </div>
                    <p v-if="!visible.length" class="p-5 text-sm text-gray-500">Nessun risultato.</p>
                </div>
                <div class="flex items-center justify-end gap-3 text-sm text-gray-500"><span>{{ rows.length }} risultati · {{ page }} / {{ pages }}</span><button class="icon-btn" :disabled="page <= 1" aria-label="Pagina precedente" title="Pagina precedente" @click="page--"><ChevronLeft class="h-4 w-4" /></button><button class="icon-btn" :disabled="page >= pages" aria-label="Pagina successiva" title="Pagina successiva" @click="page++"><ChevronRight class="h-4 w-4" /></button></div>
            </template>
        </div></div>
    </AuthenticatedLayout>
</template>
