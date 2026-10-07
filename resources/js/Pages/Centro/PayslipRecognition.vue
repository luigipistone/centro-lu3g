<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Check, FileSearch, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ users: Array });
const files = ref([]);
const rows = ref([]);
const busy = ref(false);
const publishing = ref(false);
const error = ref('');
const published = ref(0);
const input = ref(null);
const userOptions = computed(() => [
    { value: '', label: 'Seleziona destinatario' },
    ...(props.users || []).map((user) => ({ value: user.id, label: user.name })),
]);
const selectedRows = computed(() => rows.value.filter((row) => row.selected));
const canPublish = computed(() => selectedRows.value.length > 0 && selectedRows.value.every((row) => row.user_id && row.title && !row.duplicate));

function setFiles(newFiles) {
    files.value = [...newFiles];
    rows.value = [];
    error.value = '';
    published.value = 0;
}

function drop(event) {
    setFiles([...event.dataTransfer.files]);
}

async function analyze() {
    if (!files.value.length || busy.value) return;
    busy.value = true;
    error.value = '';
    const data = new FormData();
    files.value.forEach((file) => data.append('files[]', file));
    try {
        const response = await window.axios.post(route('documents.compensi.recognition.preview'), data);
        rows.value = response.data.rows.map((row) => ({ ...row, selected: Boolean(row.user_id && row.title && !row.duplicate) }));
    } catch (failure) {
        error.value = failure.response?.data?.errors?.files?.[0] || failure.response?.data?.message || 'Analisi non riuscita.';
    } finally {
        busy.value = false;
    }
}

async function publish() {
    if (!canPublish.value || publishing.value) return;
    publishing.value = true;
    error.value = '';
    const data = new FormData();
    data.append('publication_confirmed', '1');
    files.value.forEach((file, index) => {
        data.append('files[]', file);
        data.append(`user_ids[${index}]`, rows.value[index]?.user_id || '');
        data.append(`selected[${index}]`, rows.value[index]?.selected ? '1' : '0');
    });
    try {
        const response = await window.axios.post(route('documents.compensi.recognition.publish'), data);
        published.value = response.data.published;
        rows.value = [];
        files.value = [];
        if (input.value) input.value.value = '';
    } catch (failure) {
        error.value = failure.response?.data?.errors?.files?.[0] || failure.response?.data?.message || 'Pubblicazione non riuscita. Nessun documento è stato pubblicato.';
    } finally {
        publishing.value = false;
    }
}
</script>

<template>
    <Head title="Riconosci cedolini" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Riconosci cedolini</h2>
            <p class="mt-1 text-sm text-gray-500">Controlla periodo e destinatario prima della pubblicazione.</p>
        </template>
        <div class="space-y-5">
            <Link :href="route('documents.list')" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-blue-600"><ArrowLeft class="h-4 w-4" /> Documenti</Link>
            <div class="surface bg-white p-5">
                <label class="block text-sm font-medium text-gray-700">Cedolini PDF</label>
                <div class="mt-1 flex min-h-32 cursor-pointer flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 text-center hover:border-blue-400" @click="input?.click()" @dragover.prevent @drop.prevent="drop">
                    <Upload class="h-5 w-5 text-blue-600" />
                    <span class="text-sm text-gray-700">Trascina i PDF qui o scegli i file</span>
                    <span v-if="files.length" class="text-xs text-gray-500">{{ files.length }} file selezionati</span>
                </div>
                <input ref="input" type="file" accept="application/pdf,.pdf" multiple class="hidden" @change="setFiles($event.target.files || [])" />
                <div class="mt-4 flex justify-end"><button type="button" class="btn btn-primary" :disabled="!files.length || busy || publishing" @click="analyze"><FileSearch class="h-4 w-4" /> {{ busy ? 'Analisi in corso...' : 'Analizza' }}</button></div>
            </div>
            <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</div>
            <div v-if="published" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700" role="status">{{ published }} documenti pubblicati.</div>
            <div v-if="rows.length" class="surface bg-white p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div><h3 class="text-base font-semibold text-gray-900">Anteprima pubblicazione</h3><p class="mt-1 text-sm text-gray-500">{{ selectedRows.length }} di {{ rows.length }} selezionati. I file non riconosciuti restano esclusi.</p></div>
                    <button type="button" class="btn btn-primary" :disabled="!canPublish || publishing" @click="publish"><Check class="h-4 w-4" /> {{ publishing ? 'Pubblicazione...' : `Pubblica ${selectedRows.length} documenti` }}</button>
                </div>
                <div class="space-y-3">
                    <div v-for="row in rows" :key="row.index" class="grid gap-3 rounded-md border border-gray-200 p-3 md:grid-cols-[24px_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] md:items-center">
                        <input v-model="row.selected" type="checkbox" class="rounded border-gray-300 text-blue-600" :disabled="!row.title || row.duplicate" :aria-label="`Seleziona ${row.file_name}`" />
                        <div class="min-w-0"><p class="truncate text-sm font-medium text-gray-900" :title="row.file_name">{{ row.file_name }}</p><p class="mt-1 text-xs text-gray-500">{{ row.name || 'Nominativo non leggibile' }}</p></div>
                        <div class="text-sm font-medium text-gray-800">{{ row.title || 'Periodo non riconosciuto' }}</div>
                        <div><AppSelect v-model="row.user_id" :options="userOptions" searchable :disabled="!row.title || row.duplicate" /><p v-if="row.duplicate" class="mt-1 text-xs text-amber-700">Già pubblicato per questa persona</p><p v-else-if="!row.user_id && row.title" class="mt-1 inline-flex items-center gap-1 text-xs text-amber-700"><AlertCircle class="h-3 w-3" /> Seleziona la persona</p><p v-else-if="row.match === 'fiscal_code'" class="mt-1 text-xs text-green-700">Identificato tramite codice fiscale</p></div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
