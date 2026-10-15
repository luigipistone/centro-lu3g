<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolDropzone from '@/Components/ToolDropzone.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Download, FileText, Plus, Scissors, Trash2, Upload, X } from '@lucide/vue';
import { zipSync } from 'fflate';
import { PDFDocument } from 'pdf-lib';
import { computed, ref, watch } from 'vue';

const maxFileBytes = 40 * 1024 * 1024;
const input = ref(null);
const file = ref(null);
const pageCount = ref(0);
const loading = ref(false);
const processing = ref(false);
const progress = ref(0);
const totalOutputs = ref(0);
const mode = ref('extract');
const ranges = ref([{ id: crypto.randomUUID(), from: 1, to: 1 }]);
const groupSize = ref(2);
const extractAll = ref(true);
const pageSelection = ref('');
const result = ref(null);
const error = ref('');
const baseName = computed(() => file.value?.name.replace(/\.pdf$/i, '') || 'documento');
const modes = [
    { value: 'extract', label: 'Estrai pagine', description: 'Un PDF per ogni pagina scelta' },
    { value: 'ranges', label: 'Intervalli', description: 'Un PDF per ogni intervallo indicato' },
    { value: 'fixed', label: 'Ogni N pagine', description: 'Dividi il documento in parti uguali' },
];
const plannedCount = computed(() => {
    if (!file.value) return 0;
    try { return plannedGroups().length; } catch { return 0; }
});

watch([mode, ranges, groupSize, extractAll, pageSelection], () => {
    result.value = null;
    error.value = '';
}, { deep: true });

function formatBytes(bytes) {
    return bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function clearFile() {
    file.value = null;
    pageCount.value = 0;
    result.value = null;
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
        return groups;
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
    return pages.map(page => ({ pages: [page], label: `Pagina ${page + 1}`, suffix: `pagina-${page + 1}` }));
}

async function split() {
    if (!file.value || processing.value) return;
    error.value = '';
    result.value = null;
    let groups;
    try { groups = plannedGroups(); } catch (cause) { error.value = cause.message; return; }
    processing.value = true;
    progress.value = 0;
    totalOutputs.value = groups.length;
    try {
        const source = await PDFDocument.load(await file.value.arrayBuffer());
        const outputs = {};
        for (const [index, group] of groups.entries()) {
            const output = await PDFDocument.create();
            const pages = await output.copyPages(source, group.pages);
            pages.forEach(page => output.addPage(page));
            outputs[`${String(index + 1).padStart(3, '0')}-${baseName.value}-${group.suffix}.pdf`] = await output.save();
            progress.value = index + 1;
            await new Promise(resolve => setTimeout(resolve, 0));
        }
        if (groups.length === 1) {
            const [name, data] = Object.entries(outputs)[0];
            result.value = { name: name.replace(/^001-/, ''), data, count: 1, type: 'application/pdf' };
        } else {
            result.value = { name: `${baseName.value}-diviso.zip`, data: zipSync(outputs, { level: 0 }), count: groups.length, type: 'application/zip' };
        }
    } catch {
        result.value = null;
        error.value = 'Non è stato possibile creare i file. Verifica il PDF o prova con meno pagine.';
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
                        <ToolDropzone :label="loading ? 'Lettura del PDF...' : 'Trascina qui un PDF o selezionalo'" hint="Un file fino a 40 MB" aria-label="Seleziona o trascina un PDF" :disabled="processing || loading" @select="input?.click()" @drop="onDrop" />
                        <input ref="input" type="file" accept="application/pdf,.pdf" class="hidden" @change="onInput" />
                        <div v-if="file" class="mt-5 flex min-w-0 items-center gap-3 rounded-[var(--radius-sm)] border border-gray-100 px-3 py-3"><FileText class="h-5 w-5 shrink-0 text-[hsl(var(--primary-app))]" :stroke-width="1.7" /><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800">{{ file.name }}</p><p class="text-xs text-gray-500">{{ pageCount }} pagine · {{ formatBytes(file.size) }}</p></div><button type="button" class="icon-btn h-8 w-8 shrink-0" :disabled="processing" title="Sostituisci PDF" @click="input?.click()"><Upload class="h-4 w-4" /></button></div>
                    </section>
                    <section class="surface p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Divisione</h3>
                        <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200">
                            <div v-for="option in modes" :key="option.value" :class="mode === option.value ? 'bg-[hsl(var(--primary-app)/0.04)]' : 'bg-white'">
                                <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-[hsl(var(--primary-app)/0.04)] focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]" :disabled="processing" :aria-expanded="mode === option.value" :aria-controls="`split-options-${option.value}`" @click="mode = option.value">
                                    <span class="min-w-0"><span :class="['block text-sm font-semibold', mode === option.value ? 'text-[hsl(var(--primary-app))]' : 'text-gray-900']">{{ option.label }}</span><span class="block text-xs text-gray-500">{{ option.description }}</span></span>
                                    <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200', mode === option.value ? 'rotate-180 text-[hsl(var(--primary-app))]' : '']" :stroke-width="1.7" />
                                </button>
                                <div v-if="mode === option.value" :id="`split-options-${option.value}`" class="border-t border-[hsl(var(--primary-app)/0.12)] px-4 pb-4 pt-3">
                                    <fieldset v-if="option.value === 'ranges'" class="space-y-3" :disabled="processing || !file">
                                        <div v-for="(range, index) in ranges" :key="range.id" class="flex items-end gap-2">
                                            <label class="min-w-0 flex-1 text-xs text-gray-600">Da<input v-model.number="range.from" type="number" min="1" :max="pageCount || undefined" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label>
                                            <label class="min-w-0 flex-1 text-xs text-gray-600">A<input v-model.number="range.to" type="number" min="1" :max="pageCount || undefined" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label>
                                            <button type="button" class="icon-btn h-10 w-10 shrink-0" :disabled="ranges.length === 1" :title="`Rimuovi intervallo ${index + 1}`" @click="ranges.splice(index, 1)"><X class="h-4 w-4" /></button>
                                        </div>
                                        <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-[hsl(var(--primary-app))]" @click="addRange"><Plus class="h-4 w-4" /> Aggiungi intervallo</button>
                                    </fieldset>
                                    <fieldset v-else-if="option.value === 'fixed'" :disabled="processing || !file">
                                        <label class="block text-sm font-medium text-gray-700">Pagine per file<input v-model.number="groupSize" type="number" min="1" :max="pageCount || undefined" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label>
                                        <p class="mt-2 text-xs text-gray-500">L’ultimo file può contenere meno pagine.</p>
                                    </fieldset>
                                    <fieldset v-else class="space-y-3" :disabled="processing || !file">
                                        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="extractAll" type="radio" :value="true" name="extract-scope" class="text-[hsl(var(--primary-app))]" /> Tutte le pagine</label>
                                        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="extractAll" type="radio" :value="false" name="extract-scope" class="text-[hsl(var(--primary-app))]" /> Pagine selezionate</label>
                                        <label v-if="!extractAll" class="block text-xs text-gray-600">Numeri e intervalli<input v-model="pageSelection" type="text" placeholder="1, 3-5, 8" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                        <p v-if="file && plannedCount" class="mt-5 text-sm text-gray-600">Verranno creati {{ plannedCount }} PDF.</p>
                        <button type="button" class="btn btn-primary mt-3 w-full justify-center" :disabled="!file || loading || processing" @click="split"><Scissors class="h-4 w-4" /> {{ processing ? `Divisione ${progress}/${totalOutputs}` : 'Dividi PDF' }}</button>
                        <div v-if="processing" role="progressbar" :aria-valuenow="progress" :aria-valuemax="totalOutputs" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[hsl(var(--primary-app))] transition-all duration-300" :style="{ width: `${Math.max(5, progress / totalOutputs * 100)}%` }" /></div>
                    </section>
                </div>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <section v-if="result" class="surface flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
                    <div class="min-w-0"><h3 class="text-base font-semibold text-gray-900">Divisione completata</h3><p class="mt-1 text-sm text-gray-500">{{ result.count }} {{ result.count === 1 ? 'PDF' : 'PDF nello ZIP' }} · {{ formatBytes(result.data.length) }}</p></div>
                    <button type="button" class="btn btn-primary shrink-0" @click="download(result.data, result.name, result.type)"><Download class="h-4 w-4" /> {{ result.count === 1 ? 'Scarica PDF' : 'Scarica ZIP' }}</button>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
