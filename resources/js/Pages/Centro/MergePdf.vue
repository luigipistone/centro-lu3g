<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolDropzone from '@/Components/ToolDropzone.vue';
import PdfToolNavigation from '@/Components/PdfToolNavigation.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeft, ArrowUp, Download, FileText, Files, GripVertical, LoaderCircle, Trash2, X } from '@lucide/vue';
import { PDFDocument } from 'pdf-lib';
import { computed, ref } from 'vue';

const maxFiles = 40;
const maxFileBytes = 40 * 1024 * 1024;
const maxTotalBytes = 120 * 1024 * 1024;
const input = ref(null);
const files = ref([]);
const loading = ref(false);
const processing = ref(false);
const progress = ref(0);
const draggedId = ref(null);
const result = ref(null);
const error = ref('');
const totalPages = computed(() => files.value.reduce((sum, entry) => sum + entry.pages, 0));

function formatBytes(bytes) {
    return bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

async function addFiles(selected) {
    const incoming = Array.from(selected || []);
    if (!incoming.length || loading.value || processing.value) return;
    loading.value = true;
    error.value = '';
    const issues = [];
    let total = files.value.reduce((sum, entry) => sum + entry.file.size, 0);
    try {
        for (const file of incoming) {
            if (files.value.length >= maxFiles || total + file.size > maxTotalBytes) {
                issues.push('Limite raggiunto: 40 PDF e 120 MB complessivi.');
                break;
            }
            if (files.value.some(entry => entry.file.name === file.name && entry.file.size === file.size && entry.file.lastModified === file.lastModified)) continue;
            if (file.size > maxFileBytes) { issues.push(`${file.name}: supera 40 MB.`); continue; }
            if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') { issues.push(`${file.name}: non è un PDF.`); continue; }
            try {
                const bytes = await file.arrayBuffer();
                if (new TextDecoder().decode(bytes.slice(0, 5)) !== '%PDF-') throw new Error('invalid');
                const pdf = await PDFDocument.load(bytes);
                const pages = pdf.getPageCount();
                if (!pages) throw new Error('empty');
                files.value.push({ id: crypto.randomUUID(), file, pages });
                total += file.size;
            } catch {
                issues.push(`${file.name}: PDF protetto, vuoto o non leggibile.`);
            }
        }
    } finally {
        loading.value = false;
        error.value = issues.join(' ');
        result.value = null;
    }
}

function onInput(event) {
    addFiles(event.target.files);
    event.target.value = '';
}

function removeFile(id) {
    files.value = files.value.filter(entry => entry.id !== id);
    result.value = null;
}

function clearFiles() {
    files.value = [];
    result.value = null;
    error.value = '';
}

function moveFile(from, to) {
    if (to < 0 || to >= files.value.length || from === to) return;
    const next = [...files.value];
    next.splice(to, 0, next.splice(from, 1)[0]);
    files.value = next;
    result.value = null;
}

function onSortDrop(targetId) {
    const from = files.value.findIndex(entry => entry.id === draggedId.value);
    const to = files.value.findIndex(entry => entry.id === targetId);
    if (from >= 0 && to >= 0) moveFile(from, to);
    draggedId.value = null;
}

async function merge() {
    if (files.value.length < 2 || loading.value || processing.value) return;
    processing.value = true;
    progress.value = 0;
    result.value = null;
    error.value = '';
    try {
        const output = await PDFDocument.create();
        for (const [index, entry] of files.value.entries()) {
            const source = await PDFDocument.load(await entry.file.arrayBuffer());
            const pages = await output.copyPages(source, source.getPageIndices());
            pages.forEach(page => output.addPage(page));
            progress.value = index + 1;
            await new Promise(resolve => setTimeout(resolve, 0));
        }
        result.value = {
            data: await output.save(),
            name: `${files.value[0].file.name.replace(/\.pdf$/i, '')}-unito.pdf`,
            pages: totalPages.value,
        };
    } catch {
        error.value = 'Impossibile unire questi PDF. Verifica i file o prova con documenti più piccoli.';
    } finally {
        processing.value = false;
    }
}

function download() {
    if (!result.value) return;
    const url = URL.createObjectURL(new Blob([result.value.data], { type: 'application/pdf' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = result.value.name;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 30000);
}
</script>

<template>
    <Head title="Unisci PDF" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Unisci PDF</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <PdfToolNavigation />
                <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <h3 class="text-base font-semibold text-gray-900">File PDF</h3>
                            <button v-if="files.length" type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-800" :disabled="loading || processing" @click="clearFiles"><Trash2 class="h-4 w-4" /> Rimuovi tutti</button>
                        </div>
                        <ToolDropzone :label="loading ? 'Lettura dei PDF...' : 'Trascina qui i PDF o selezionali'" hint="Almeno 2 file · fino a 40 MB ciascuno" aria-label="Seleziona o trascina i PDF da unire" :disabled="loading || processing" @select="input?.click()" @drop="addFiles($event.dataTransfer?.files)" />
                        <input ref="input" type="file" accept="application/pdf,.pdf" multiple class="hidden" @change="onInput" />
                        <div v-if="files.length" class="mt-5 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200">
                            <div v-for="(entry, index) in files" :key="entry.id" draggable="true" :class="['flex min-w-0 items-center gap-2 border-b border-gray-100 px-3 py-2.5 last:border-b-0', draggedId === entry.id ? 'opacity-50' : '']" @dragstart="draggedId = entry.id" @dragend="draggedId = null" @dragover.prevent @drop.prevent.stop="onSortDrop(entry.id)">
                                <GripVertical class="hidden h-4 w-4 shrink-0 cursor-grab text-gray-400 sm:block" :stroke-width="1.7" />
                                <span class="w-5 shrink-0 text-center text-xs font-semibold text-gray-400">{{ index + 1 }}</span>
                                <FileText class="h-5 w-5 shrink-0 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800" :title="entry.file.name">{{ entry.file.name }}</p><p class="text-xs text-gray-500">{{ entry.pages }} {{ entry.pages === 1 ? 'pagina' : 'pagine' }} · {{ formatBytes(entry.file.size) }}</p></div>
                                <button type="button" class="icon-btn h-8 w-8 shrink-0" :disabled="index === 0 || processing" :title="`Sposta ${entry.file.name} su`" :aria-label="`Sposta ${entry.file.name} su`" @click="moveFile(index, index - 1)"><ArrowUp class="h-4 w-4" /></button>
                                <button type="button" class="icon-btn h-8 w-8 shrink-0" :disabled="index === files.length - 1 || processing" :title="`Sposta ${entry.file.name} giù`" :aria-label="`Sposta ${entry.file.name} giù`" @click="moveFile(index, index + 1)"><ArrowDown class="h-4 w-4" /></button>
                                <button type="button" class="icon-btn h-8 w-8 shrink-0 text-red-500" :disabled="processing" :title="`Rimuovi ${entry.file.name}`" :aria-label="`Rimuovi ${entry.file.name}`" @click="removeFile(entry.id)"><X class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </section>
                    <section class="surface flex flex-col p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Unione</h3>
                        <div class="mt-4 text-sm text-gray-600">
                            <p>{{ files.length }} {{ files.length === 1 ? 'PDF selezionato' : 'PDF selezionati' }}</p>
                            <p v-if="files.length" class="mt-1">{{ totalPages }} {{ totalPages === 1 ? 'pagina' : 'pagine' }} nel documento finale</p>
                        </div>
                        <p v-if="files.length > 1" class="mt-4 text-xs text-gray-500">Le pagine seguiranno l'ordine dei file qui accanto.</p>
                        <div class="mt-auto pt-5">
                            <button type="button" class="btn btn-primary w-full justify-center" :disabled="files.length < 2 || loading || processing" @click="merge"><LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" /><Files v-else class="h-4 w-4" /> {{ processing ? `Unione ${progress}/${files.length}` : 'Unisci PDF' }}</button>
                            <div v-if="processing" role="progressbar" :aria-valuenow="progress" :aria-valuemax="files.length" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[hsl(var(--primary-app))] transition-all duration-300" :style="{ width: `${Math.max(5, progress / files.length * 100)}%` }" /></div>
                        </div>
                    </section>
                </div>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <section v-if="result" class="surface flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
                    <div class="min-w-0"><h3 class="text-base font-semibold text-gray-900">PDF pronto</h3><p class="mt-1 break-all text-sm text-gray-500">{{ result.name }} · {{ result.pages }} pagine · {{ formatBytes(result.data.length) }}</p></div>
                    <button type="button" class="btn btn-primary shrink-0" @click="download"><Download class="h-4 w-4" /> Scarica PDF</button>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
