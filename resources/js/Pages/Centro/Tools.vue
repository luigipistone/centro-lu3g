<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolAccordion from '@/Components/ToolAccordion.vue';
import ToolDropzone from '@/Components/ToolDropzone.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleAlert, Download, FileText, Plus, Trash2, X } from '@lucide/vue';
import { zipSync } from 'fflate';
import { computed, ref, watch } from 'vue';

const maxFiles = 10;
const maxFileBytes = 40 * 1024 * 1024;
const maxTotalBytes = 120 * 1024 * 1024;
const levels = [
    { value: 'extreme', label: 'Alta', detail: 'File più piccolo, immagini meno definite', setting: '/screen' },
    { value: 'recommended', label: 'Consigliata', detail: 'Buon equilibrio tra dimensione e qualità', setting: '/ebook' },
    { value: 'light', label: 'Leggera', detail: 'Qualità più alta, riduzione minore', setting: '/printer' },
];
const input = ref(null);
const files = ref([]);
const results = ref([]);
const level = ref('recommended');
const processing = ref(false);
const processingIndex = ref(0);
const error = ref('');
const totalBytes = computed(() => files.value.reduce((sum, entry) => sum + entry.file.size, 0));
const readyResults = computed(() => results.value.filter(result => result.data));
const savedBytes = computed(() => readyResults.value.reduce((sum, result) => sum + (result.originalSize - result.data.length), 0));
watch(level, () => { results.value = []; });

function formatBytes(bytes) {
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function addFiles(selected) {
    error.value = '';
    const incoming = Array.from(selected || []);
    if (!incoming.length) return;
    const next = [...files.value];
    let total = totalBytes.value;
    for (const file of incoming) {
        if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') {
            error.value = 'Sono ammessi solo file PDF.';
            continue;
        }
        if (file.size > maxFileBytes) {
            error.value = `${file.name} supera il limite di 40 MB.`;
            continue;
        }
        if (next.length >= maxFiles || total + file.size > maxTotalBytes) {
            error.value = 'Puoi elaborare fino a 10 PDF per volta, per un massimo di 120 MB complessivi.';
            break;
        }
        if (next.some(entry => entry.file.name === file.name && entry.file.size === file.size && entry.file.lastModified === file.lastModified)) continue;
        next.push({ id: crypto.randomUUID(), file });
        total += file.size;
    }
    files.value = next;
    results.value = [];
}

function onInput(event) {
    addFiles(event.target.files);
    event.target.value = '';
}

function onDrop(event) {
    addFiles(event.dataTransfer?.files);
}

function removeFile(id) {
    files.value = files.value.filter(entry => entry.id !== id);
    results.value = [];
    error.value = '';
}

function clearFiles() {
    files.value = [];
    results.value = [];
    error.value = '';
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

function outputName(name) {
    return `${name.replace(/\.pdf$/i, '')}-compresso.pdf`;
}

async function compress() {
    if (!files.value.length || processing.value) return;
    processing.value = true;
    processingIndex.value = 0;
    error.value = '';
    results.value = [];
    let gs;
    try {
        const { load } = await import('@wasm-zoo/ghostscript');
        gs = await load();
        for (const [index, entry] of files.value.entries()) {
            processingIndex.value = index + 1;
            try {
                const original = await entry.file.arrayBuffer();
                if (new TextDecoder().decode(original.slice(0, 5)) !== '%PDF-') {
                    throw new Error('Il file non contiene un PDF valido.');
                }
                const preset = levels.find(option => option.value === level.value);
                const result = await gs.exec([
                    '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
                    '-sDEVICE=pdfwrite', `-dPDFSETTINGS=${preset.setting}`,
                    '-dDetectDuplicateImages=true', '-dCompressFonts=true',
                    '-sOutputFile=/out/compressed.pdf', '/input.pdf',
                ], {
                    files: [{ name: '/input.pdf', data: original.slice(0) }],
                    dirs: ['/out'], outputs: ['/out/compressed.pdf'], timeoutMs: 120000,
                });
                const compressed = result.files.find(file => file.name === '/out/compressed.pdf')?.data;
                if (!compressed?.length || new TextDecoder().decode(compressed.slice(0, 5)) !== '%PDF-') {
                    throw new Error('Non è stato prodotto un PDF valido.');
                }
                const reduced = compressed.length < original.byteLength;
                results.value.push({
                    id: entry.id, name: entry.file.name, outputName: reduced ? outputName(entry.file.name) : entry.file.name,
                    originalSize: original.byteLength,
                    data: reduced ? compressed : new Uint8Array(original), reduced,
                });
            } catch {
                results.value.push({ id: entry.id, name: entry.file.name, error: 'Impossibile comprimere questo PDF. Verifica che non sia protetto o danneggiato.' });
            }
        }
    } catch {
        error.value = 'Il motore PDF non è disponibile. Riprova dopo aver ricaricato la pagina.';
    } finally {
        gs?.dispose();
        processing.value = false;
    }
}

function downloadAll() {
    if (!readyResults.value.length) return;
    try {
        const contents = Object.fromEntries(readyResults.value.map((result, index) => [`${index + 1}-${result.outputName}`, result.data]));
        download(zipSync(contents, { level: 0 }), 'pdf-compressi.zip', 'application/zip');
    } catch {
        error.value = 'Impossibile creare l’archivio. Scarica i PDF singolarmente.';
    }
}
</script>

<template>
    <Head title="Strumenti" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Comprimi PDF</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <h3 class="text-base font-semibold text-gray-900">File PDF</h3>
                            <button v-if="files.length" type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-800" :disabled="processing" @click="clearFiles"><Trash2 class="h-4 w-4" /> Rimuovi tutti</button>
                        </div>
                        <ToolDropzone label="Trascina qui i PDF o selezionali" hint="Fino a 10 file, 40 MB ciascuno" :aria-label="files.length ? 'Aggiungi PDF' : 'Seleziona o trascina file PDF'" :disabled="processing" @select="input?.click()" @drop="onDrop" />
                        <input ref="input" type="file" accept="application/pdf,.pdf" multiple class="hidden" @change="onInput" />
                        <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>

                        <div v-if="files.length" class="mt-5">
                            <div class="mb-2 flex items-center justify-between text-xs font-semibold text-gray-500"><span>{{ files.length }} file</span><span>{{ formatBytes(totalBytes) }}</span></div>
                            <div class="divide-y divide-gray-100 rounded-[var(--radius-sm)] border border-gray-100">
                                <div v-for="entry in files" :key="entry.id" class="flex min-w-0 items-center gap-3 px-3 py-3">
                                    <FileText class="h-5 w-5 shrink-0 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800">{{ entry.file.name }}</p><p class="text-xs text-gray-500">{{ formatBytes(entry.file.size) }}</p></div>
                                    <button type="button" class="icon-btn h-8 w-8 shrink-0" :disabled="processing" :title="`Rimuovi ${entry.file.name}`" @click="removeFile(entry.id)"><X class="h-4 w-4" /></button>
                                </div>
                            </div>
                            <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[hsl(var(--primary-app))]" :disabled="processing" @click="input?.click()"><Plus class="h-4 w-4" /> Aggiungi PDF</button>
                        </div>
                    </section>

                    <section class="surface p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Compressione</h3>
                        <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200">
                            <ToolAccordion v-for="option in levels" :key="option.value" :title="option.label" :summary="level === option.value ? 'Selezionata' : option.detail" :open="level === option.value" :disabled="processing" :panel-id="`compression-${option.value}`" @toggle="level = option.value">
                                <p class="text-xs text-gray-600">{{ option.detail }}</p>
                            </ToolAccordion>
                        </div>
                        <button type="button" class="btn btn-primary mt-5 w-full justify-center" :disabled="!files.length || processing" @click="compress">{{ processing ? `Compressione ${processingIndex}/${files.length}` : 'Comprimi PDF' }}</button>
                        <div v-if="processing" role="progressbar" :aria-valuenow="processingIndex" :aria-valuemax="files.length" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[hsl(var(--primary-app))] transition-all duration-300" :style="{ width: `${(processingIndex / files.length) * 100}%` }" /></div>
                        <p class="mt-4 flex items-start gap-2 text-xs text-gray-500"><CircleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" /> PDF firmati digitalmente o interattivi potrebbero perdere la validità della firma o alcune funzioni.</p>
                    </section>
                </div>

                <section v-if="results.length" class="surface p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-base font-semibold text-gray-900">Risultati</h3><p v-if="savedBytes > 0" class="mt-1 text-sm text-gray-500">Spazio risparmiato: {{ formatBytes(savedBytes) }}</p></div><button v-if="readyResults.length > 1" type="button" class="btn btn-primary" @click="downloadAll"><Download class="h-4 w-4" /> Scarica tutti</button></div>
                    <div class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                        <div v-for="result in results" :key="result.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900">{{ result.name }}</p><p v-if="result.data" class="text-xs text-gray-500">{{ formatBytes(result.originalSize) }} - {{ formatBytes(result.data.length) }} · {{ result.reduced ? `${Math.round((1 - result.data.length / result.originalSize) * 100)}% in meno` : 'Originale mantenuto: nessuna riduzione' }}</p><p v-else class="text-xs text-red-600">{{ result.error }}</p></div>
                            <button v-if="result.data" type="button" class="btn btn-outline shrink-0" @click="download(result.data, result.outputName)"><Download class="h-4 w-4" /> Scarica</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
