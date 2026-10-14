<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleAlert, Download, FileText, Plus, Scissors, Trash2, Upload, X } from '@lucide/vue';
import { zipSync } from 'fflate';
import { PDFDocument } from 'pdf-lib';
import { computed, ref, watch } from 'vue';

const maxFileBytes = 40 * 1024 * 1024;
const input = ref(null);
const file = ref(null);
const pageCount = ref(0);
const dragDepth = ref(0);
const loading = ref(false);
const processing = ref(false);
const progress = ref(0);
const totalOutputs = ref(0);
const mode = ref('extract');
const ranges = ref([{ id: crypto.randomUUID(), from: 1, to: 1 }]);
const mergeRanges = ref(false);
const groupSize = ref(2);
const extractAll = ref(true);
const pageSelection = ref('');
const mergeExtracted = ref(false);
const results = ref([]);
const error = ref('');
const baseName = computed(() => file.value?.name.replace(/\.pdf$/i, '') || 'documento');
const modes = [
    { value: 'extract', label: 'Estrai pagine' },
    { value: 'ranges', label: 'Intervalli' },
    { value: 'fixed', label: 'Ogni N pagine' },
];
const plannedCount = computed(() => {
    if (!file.value) return 0;
    try { return plannedGroups().length; } catch { return 0; }
});

watch([mode, ranges, mergeRanges, groupSize, extractAll, pageSelection, mergeExtracted], () => {
    results.value = [];
    error.value = '';
}, { deep: true });

function formatBytes(bytes) {
    return bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function clearFile() {
    file.value = null;
    pageCount.value = 0;
    results.value = [];
    error.value = '';
    if (input.value) input.value.value = '';
}

async function selectFile(selected) {
    const next = Array.from(selected || [])[0];
    if (!next) return;
    clearFile();
    if (!/\.pdf$/i.test(next.name) && next.type !== 'application/pdf') {
        error.value = 'Seleziona un file PDF.';
        return;
    }
    if (next.size > maxFileBytes) {
        error.value = 'Il PDF supera il limite di 40 MB.';
        return;
    }
    loading.value = true;
    try {
        const bytes = await next.arrayBuffer();
        if (new TextDecoder().decode(bytes.slice(0, 5)) !== '%PDF-') throw new Error('invalid');
        const pdf = await PDFDocument.load(bytes);
        if (!pdf.getPageCount()) throw new Error('empty');
        file.value = next;
        pageCount.value = pdf.getPageCount();
        groupSize.value = Math.min(2, pageCount.value);
        ranges.value = [{ id: crypto.randomUUID(), from: 1, to: 1 }];
    } catch {
        error.value = 'Impossibile aprire il PDF. Verifica che non sia protetto o danneggiato.';
    } finally {
        loading.value = false;
    }
}

function onInput(event) {
    selectFile(event.target.files);
    event.target.value = '';
}

function onDrop(event) {
    dragDepth.value = 0;
    selectFile(event.dataTransfer?.files);
}

function addRange() {
    const last = ranges.value.at(-1);
    const start = Math.min(pageCount.value, Number(last?.to || 0) + 1);
    ranges.value.push({ id: crypto.randomUUID(), from: start, to: start });
}

function numberInRange(value, min, max) {
    const number = Number(value);
    return Number.isSafeInteger(number) && number >= min && number <= max;
}

function parsePageSelection(value) {
    const parts = value.split(',').map(part => part.trim());
    if (!parts.length || parts.some(part => !part)) throw new Error('Inserisci pagine come 1, 3-5, 8.');
    const pages = [];
    const seen = new Set();
    for (const part of parts) {
        const match = part.match(/^(\d+)(?:\s*-\s*(\d+))?$/);
        if (!match) throw new Error('Usa numeri di pagina separati da virgole, per esempio 1, 3-5, 8.');
        const from = Number(match[1]);
        const to = Number(match[2] || match[1]);
        if (!numberInRange(from, 1, pageCount.value) || !numberInRange(to, from, pageCount.value)) {
            throw new Error(`Le pagine devono essere comprese tra 1 e ${pageCount.value}, in ordine crescente.`);
        }
        for (let page = from; page <= to; page++) {
            if (!seen.has(page)) { pages.push(page - 1); seen.add(page); }
        }
    }
    return pages;
}

function plannedGroups() {
    if (mode.value === 'ranges') {
        if (!ranges.value.length) throw new Error('Aggiungi almeno un intervallo.');
        const groups = ranges.value.map((range) => {
            const from = Number(range.from);
            const to = Number(range.to);
            if (!numberInRange(from, 1, pageCount.value) || !numberInRange(to, from, pageCount.value)) {
                throw new Error(`Controlla gli intervalli: usa pagine da 1 a ${pageCount.value}, con la fine dopo l’inizio.`);
            }
            return { pages: Array.from({ length: to - from + 1 }, (_, index) => from + index - 1), label: `Pagine ${from}-${to}`, suffix: `${from}-${to}` };
        });
        return mergeRanges.value ? [{ pages: groups.flatMap(group => group.pages), label: 'Intervalli uniti', suffix: 'intervalli' }] : groups;
    }
    if (mode.value === 'fixed') {
        if (!numberInRange(groupSize.value, 1, pageCount.value)) throw new Error(`Scegli un numero di pagine tra 1 e ${pageCount.value}.`);
        const groups = [];
        const size = Number(groupSize.value);
        for (let from = 1; from <= pageCount.value; from += size) {
            const to = Math.min(pageCount.value, from + size - 1);
            groups.push({ pages: Array.from({ length: to - from + 1 }, (_, index) => from + index - 1), label: `Pagine ${from}-${to}`, suffix: `${from}-${to}` });
        }
        return groups;
    }
    const pages = extractAll.value ? Array.from({ length: pageCount.value }, (_, index) => index) : parsePageSelection(pageSelection.value);
    return mergeExtracted.value
        ? [{ pages, label: `${pages.length} pagine estratte`, suffix: 'estratte' }]
        : pages.map(page => ({ pages: [page], label: `Pagina ${page + 1}`, suffix: `pagina-${page + 1}` }));
}

async function split() {
    if (!file.value || processing.value) return;
    error.value = '';
    results.value = [];
    let groups;
    try { groups = plannedGroups(); } catch (cause) { error.value = cause.message; return; }
    processing.value = true;
    progress.value = 0;
    totalOutputs.value = groups.length;
    try {
        const source = await PDFDocument.load(await file.value.arrayBuffer());
        for (const [index, group] of groups.entries()) {
            const output = await PDFDocument.create();
            const pages = await output.copyPages(source, group.pages);
            pages.forEach(page => output.addPage(page));
            results.value.push({ id: index, name: `${baseName.value}-${group.suffix}.pdf`, label: group.label, data: await output.save() });
            progress.value = index + 1;
            await new Promise(resolve => setTimeout(resolve, 0));
        }
    } catch {
        results.value = [];
        error.value = 'Non è stato possibile dividere il PDF. Verifica il file e riprova.';
    } finally {
        processing.value = false;
    }
}

function download(data, name, type = 'application/pdf') {
    const url = URL.createObjectURL(new Blob([data], { type }));
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 30000);
}

function downloadAll() {
    try {
        const contents = Object.fromEntries(results.value.map((result, index) => [`${index + 1}-${result.name}`, result.data]));
        download(zipSync(contents, { level: 0 }), `${baseName.value}-diviso.zip`, 'application/zip');
    } catch {
        error.value = 'Impossibile creare l’archivio. Scarica i PDF singolarmente.';
    }
}
</script>

<template>
    <Head title="Dividi PDF" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Dividi PDF</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between gap-3"><h3 class="text-base font-semibold text-gray-900">File PDF</h3><button v-if="file" type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-800" :disabled="processing" @click="clearFile"><Trash2 class="h-4 w-4" /> Rimuovi</button></div>
                        <div role="button" tabindex="0" aria-label="Seleziona o trascina un PDF" :class="['flex min-h-44 flex-1 cursor-pointer flex-col items-center justify-center gap-2 rounded-[var(--radius)] border-2 border-dashed px-5 py-8 text-center transition focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]', dragDepth ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.08)]' : 'border-gray-200 bg-gray-50/70 hover:border-[hsl(var(--primary-app)/0.6)] hover:bg-[hsl(var(--primary-app)/0.04)]', processing || loading ? 'pointer-events-none opacity-60' : '']" @click="input?.click()" @keydown.enter.prevent="input?.click()" @keydown.space.prevent="input?.click()" @dragenter.prevent="dragDepth++" @dragover.prevent @dragleave.prevent="dragDepth = Math.max(0, dragDepth - 1)" @drop.prevent="onDrop">
                            <span class="flex h-12 w-12 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Upload class="h-6 w-6" :stroke-width="1.7" /></span>
                            <span class="text-sm font-semibold text-gray-800">{{ loading ? 'Lettura del PDF...' : 'Trascina qui un PDF o selezionalo' }}</span>
                            <span class="text-xs text-gray-500">Un file fino a 40 MB</span>
                        </div>
                        <input ref="input" type="file" accept="application/pdf,.pdf" class="hidden" @change="onInput" />
                        <div v-if="file" class="mt-5 flex min-w-0 items-center gap-3 rounded-[var(--radius-sm)] border border-gray-100 px-3 py-3"><FileText class="h-5 w-5 shrink-0 text-[hsl(var(--primary-app))]" :stroke-width="1.7" /><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800">{{ file.name }}</p><p class="text-xs text-gray-500">{{ pageCount }} pagine · {{ formatBytes(file.size) }}</p></div><button type="button" class="icon-btn h-8 w-8 shrink-0" :disabled="processing" title="Sostituisci PDF" @click="input?.click()"><Upload class="h-4 w-4" /></button></div>
                    </section>
                    <section class="surface p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Divisione</h3>
                        <div class="mt-4 grid grid-cols-3 rounded-[var(--radius-sm)] bg-gray-100 p-1" role="group" aria-label="Modalità di divisione"><button v-for="option in modes" :key="option.value" type="button" :disabled="processing" :aria-pressed="mode === option.value" :class="['min-h-10 rounded-[var(--radius-sm)] px-2 text-xs font-semibold transition', mode === option.value ? 'bg-white text-[hsl(var(--primary-app))] shadow-sm' : 'text-gray-600 hover:text-gray-900']" @click="mode = option.value">{{ option.label }}</button></div>
                        <fieldset v-if="mode === 'ranges'" class="mt-5 space-y-3" :disabled="processing || !file"><legend class="mb-2 text-sm font-semibold text-gray-800">Intervalli di pagine</legend><div v-for="(range, index) in ranges" :key="range.id" class="flex items-end gap-2"><label class="min-w-0 flex-1 text-xs text-gray-500">Da<input v-model.number="range.from" type="number" min="1" :max="pageCount || undefined" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label><label class="min-w-0 flex-1 text-xs text-gray-500">A<input v-model.number="range.to" type="number" min="1" :max="pageCount || undefined" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label><button type="button" class="icon-btn h-10 w-10 shrink-0" :disabled="ranges.length === 1" :title="`Rimuovi intervallo ${index + 1}`" @click="ranges.splice(index, 1)"><X class="h-4 w-4" /></button></div><button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-[hsl(var(--primary-app))]" @click="addRange"><Plus class="h-4 w-4" /> Aggiungi intervallo</button><label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="mergeRanges" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" /> Unisci in un solo PDF</label></fieldset>
                        <fieldset v-else-if="mode === 'fixed'" class="mt-5" :disabled="processing || !file"><label class="block text-sm font-semibold text-gray-800">Pagine per file<input v-model.number="groupSize" type="number" min="1" :max="pageCount || undefined" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label><p class="mt-2 text-xs text-gray-500">L’ultimo file può contenere meno pagine.</p></fieldset>
                        <fieldset v-else class="mt-5 space-y-3" :disabled="processing || !file"><legend class="mb-2 text-sm font-semibold text-gray-800">Pagine da estrarre</legend><label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="extractAll" type="radio" :value="true" name="extract-scope" class="text-[hsl(var(--primary-app))]" /> Tutte le pagine</label><label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="extractAll" type="radio" :value="false" name="extract-scope" class="text-[hsl(var(--primary-app))]" /> Pagine selezionate</label><label v-if="!extractAll" class="block text-xs text-gray-500">Numeri e intervalli<input v-model="pageSelection" type="text" placeholder="1, 3-5, 8" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label><label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="mergeExtracted" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" /> Unisci in un solo PDF</label></fieldset>
                        <p v-if="file && plannedCount" class="mt-5 text-sm text-gray-600">Verranno creati {{ plannedCount }} PDF.</p>
                        <button type="button" class="btn btn-primary mt-3 w-full justify-center" :disabled="!file || loading || processing" @click="split"><Scissors class="h-4 w-4" /> {{ processing ? `Divisione ${progress}/${totalOutputs}` : 'Dividi PDF' }}</button>
                        <div v-if="processing" role="progressbar" :aria-valuenow="progress" :aria-valuemax="totalOutputs" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[hsl(var(--primary-app))] transition-all duration-300" :style="{ width: `${Math.max(5, progress / totalOutputs * 100)}%` }" /></div>
                        <p class="mt-4 flex items-start gap-2 text-xs text-gray-500"><CircleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" /> L’elaborazione avviene nel browser. Firme digitali e moduli interattivi potrebbero non restare validi.</p>
                    </section>
                </div>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <section v-if="results.length" class="surface p-5 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-base font-semibold text-gray-900">Risultati</h3><p class="mt-1 text-sm text-gray-500">{{ results.length }} {{ results.length === 1 ? 'PDF creato' : 'PDF creati' }}</p></div><button v-if="results.length > 1" type="button" class="btn btn-primary" @click="downloadAll"><Download class="h-4 w-4" /> Scarica tutti</button></div><div class="mt-4 divide-y divide-gray-100 border-t border-gray-100"><div v-for="result in results" :key="result.id" class="flex flex-wrap items-center justify-between gap-3 py-3"><div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900">{{ result.name }}</p><p class="text-xs text-gray-500">{{ result.label }} · {{ formatBytes(result.data.length) }}</p></div><button type="button" class="btn btn-outline shrink-0" @click="download(result.data, result.name)"><Download class="h-4 w-4" /> Scarica</button></div></div></section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
