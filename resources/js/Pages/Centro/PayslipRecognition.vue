<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Check, Files, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ users: Array });
const files = ref([]);
const rows = ref([]);
const busy = ref(false);
const publishing = ref(false);
const error = ref('');
const published = ref(0);
const input = ref(null);
const dragDepth = ref(0);
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
    dragDepth.value = 0;
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
            <div class="flex flex-col gap-2">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Documenti</h2>
                <p class="text-sm text-gray-500">Riconoscimento e pubblicazione dei cedolini.</p>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <Link :href="route('documents.list')" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Tutti i documenti</Link>
                <div class="surface bg-white p-5">
                    <div class="mb-4 flex items-center gap-2">
                        <Files class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        <h3 class="text-base font-semibold text-gray-900">Riconosci cedolini</h3>
                    </div>
                    <label class="block text-sm font-medium text-gray-700">Cedolini PDF</label>
                    <div
                        role="button"
                        tabindex="0"
                        aria-label="Seleziona o trascina i cedolini PDF"
                        :class="['mt-1 flex min-h-20 cursor-pointer items-center gap-3 rounded-[var(--radius)] border border-dashed px-4 py-3 text-sm transition focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]', dragDepth ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.08)]' : 'border-gray-200 bg-white/70 hover:border-[hsl(var(--primary-app))] hover:bg-[hsl(var(--primary-app)/0.04)]']"
                        @click="input?.click()"
                        @keydown.enter.prevent="input?.click()"
                        @keydown.space.prevent="input?.click()"
                        @dragenter.prevent="dragDepth++"
                        @dragover.prevent
                        @dragleave.prevent="dragDepth = Math.max(0, dragDepth - 1)"
                        @drop.prevent="drop"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Upload class="h-4 w-4" :stroke-width="1.7" /></span>
                        <span class="min-w-0"><span class="block font-semibold text-gray-700">{{ files.length ? `${files.length} PDF selezionati` : 'Trascina qui i PDF o selezionali' }}</span><span class="block text-xs text-gray-500">PDF · max 20 MB per file</span></span>
                    </div>
                    <input ref="input" type="file" accept="application/pdf,.pdf" multiple class="hidden" @change="setFiles($event.target.files || [])" />
                    <div class="mt-4 flex justify-end"><button type="button" class="btn btn-primary" :disabled="!files.length || busy || publishing" @click="analyze"><Files class="h-4 w-4" :stroke-width="1.7" /> {{ busy ? 'Analisi in corso...' : 'Analizza' }}</button></div>
                </div>
                <div v-if="error" class="rounded-[var(--radius-sm)] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ error }}</div>
                <div v-if="published" class="rounded-[var(--radius-sm)] border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700" role="status">{{ published }} documenti pubblicati.</div>
                <section v-if="rows.length" class="surface bg-white p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div><h3 class="text-base font-semibold text-gray-900">Anteprima pubblicazione</h3><p class="mt-1 text-sm text-gray-500">{{ selectedRows.length }} di {{ rows.length }} selezionati. I file non riconosciuti restano esclusi.</p></div>
                        <button type="button" class="btn btn-primary" :disabled="!canPublish || publishing" @click="publish"><Check class="h-4 w-4" :stroke-width="1.7" /> {{ publishing ? 'Pubblicazione...' : `Pubblica ${selectedRows.length} documenti` }}</button>
                    </div>
                    <div class="hidden border-b border-gray-100 pb-2 text-xs font-semibold uppercase text-gray-500 md:grid md:grid-cols-[24px_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] md:gap-4"><span></span><span>File</span><span>Documento</span><span>Destinatario</span></div>
                    <div v-for="row in rows" :key="row.index" class="grid grid-cols-[24px_minmax(0,1fr)] gap-3 border-b border-gray-100 py-3 last:border-b-0 md:grid-cols-[24px_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] md:items-center md:gap-4">
                        <input v-model="row.selected" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" :disabled="!row.title || row.duplicate" :aria-label="`Seleziona ${row.file_name}`" />
                        <div class="min-w-0"><p class="truncate text-sm font-medium text-gray-900" :title="row.file_name">{{ row.file_name }}</p><p class="mt-1 text-xs text-gray-500">{{ row.name || 'Nominativo non leggibile' }}</p></div>
                        <div class="col-start-2 text-sm font-medium text-gray-800 md:col-auto">{{ row.title || 'Periodo non riconosciuto' }}</div>
                        <div class="col-start-2 min-w-0 md:col-auto"><AppSelect v-model="row.user_id" :options="userOptions" searchable :disabled="!row.title || row.duplicate" /><p v-if="row.duplicate" class="mt-1 text-xs text-amber-700">Già pubblicato per questa persona</p><p v-else-if="!row.user_id && row.title" class="mt-1 inline-flex items-center gap-1 text-xs text-amber-700"><AlertCircle class="h-3 w-3" /> Seleziona la persona</p><p v-else-if="row.match === 'fiscal_code'" class="mt-1 text-xs text-green-700">Identificato tramite codice fiscale</p></div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
