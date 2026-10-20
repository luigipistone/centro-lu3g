<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolAccordion from '@/Components/ToolAccordion.vue';
import ToolDropzone from '@/Components/ToolDropzone.vue';
import PdfToolNavigation from '@/Components/PdfToolNavigation.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileImage, Images, Plus, Trash2, X } from '@lucide/vue';
import { zip } from 'fflate';
import * as pdfjs from 'pdfjs-dist/build/pdf.mjs';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { onBeforeUnmount, ref, watch } from 'vue';

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

const maxFiles = 20;
const maxBytes = 100 * 1024 * 1024;
const maxImages = 200;
const maxOutputBytes = 250 * 1024 * 1024;
const input = ref(null);
const files = ref([]);
const format = ref('png');
const removeDuplicates = ref(true);
const pageMode = ref('all');
const pageRange = ref('');
const processing = ref(false);
const progress = ref('');
const openSetting = ref('format');
const error = ref('');
const extracted = ref([]);
const archive = ref(null);

watch([format, removeDuplicates, pageMode, pageRange, files], clearResult, { deep: true });
onBeforeUnmount(clearResult);

function clearResult() {
    extracted.value.forEach(image => URL.revokeObjectURL(image.url));
    extracted.value = [];
    archive.value = null;
}

function formatBytes(bytes) {
    return bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

async function addFiles(selected) {
    if (processing.value) return;
    const issues = [];
    let total = files.value.reduce((sum, entry) => sum + entry.file.size, 0);
    for (const file of Array.from(selected || [])) {
        if (files.value.length >= maxFiles || total + file.size > maxBytes) {
            issues.push('Limite raggiunto: 20 PDF e 100 MB complessivi.');
            break;
        }
        if (files.value.some(entry => entry.file.name === file.name && entry.file.size === file.size && entry.file.lastModified === file.lastModified)) continue;
        const signature = new TextDecoder().decode(await file.slice(0, 5).arrayBuffer());
        if (signature !== '%PDF-') {
            issues.push(`${file.name}: non è un PDF valido.`);
            continue;
        }
        files.value.push({ id: crypto.randomUUID(), file });
        total += file.size;
    }
    error.value = issues.join(' ');
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
}

function selectedPages(value, count) {
    if (pageMode.value === 'all') return new Set(Array.from({ length: count }, (_, index) => index + 1));
    if (!value.trim()) throw new Error('Indica le pagine da estrarre, ad esempio 1-3,5.');
    const pages = new Set();
    for (const part of value.split(',')) {
        const match = /^\s*(\d+)(?:\s*-\s*(\d+))?\s*$/.exec(part);
        if (!match) throw new Error('Intervallo non valido. Usa numeri e intervalli, ad esempio 1-3,5.');
        const start = Number(match[1]);
        const end = Number(match[2] || match[1]);
        if (start < 1 || end < start || end > count) throw new Error(`Le pagine richieste devono essere comprese tra 1 e ${count}.`);
        for (let page = start; page <= end; page++) pages.add(page);
    }
    return pages;
}

function imageObject(page, document, name) {
    const objects = name.startsWith('g_') ? document.commonObjs : page.objs;
    return new Promise((resolve, reject) => {
        try {
            const existing = objects.get(name, resolve);
            if (existing) resolve(existing);
        } catch (cause) {
            reject(cause);
        }
    });
}

function imageCanvas(image) {
    const { width, height } = image;
    if (!width || !height || width * height > 40000000) throw new Error('Un’immagine supera il limite di 40 megapixel.');
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('Il browser non può elaborare questa immagine.');
    if (image.bitmap) {
        context.drawImage(image.bitmap, 0, 0);
        return canvas;
    }
    if (!image.data) throw new Error('Il PDF contiene un’immagine non leggibile.');
    const pixels = context.createImageData(width, height);
    const source = image.data;
    if (image.kind === pdfjs.ImageKind.RGBA_32BPP) {
        pixels.data.set(source);
    } else if (image.kind === pdfjs.ImageKind.RGB_24BPP) {
        for (let inputIndex = 0, outputIndex = 0; inputIndex < width * height * 3; inputIndex += 3, outputIndex += 4) {
            pixels.data[outputIndex] = source[inputIndex];
            pixels.data[outputIndex + 1] = source[inputIndex + 1];
            pixels.data[outputIndex + 2] = source[inputIndex + 2];
            pixels.data[outputIndex + 3] = 255;
        }
    } else if (image.kind === pdfjs.ImageKind.GRAYSCALE_1BPP) {
        const rowBytes = Math.ceil(width / 8);
        for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
            const color = source[y * rowBytes + (x >> 3)] & (0x80 >> (x & 7)) ? 255 : 0;
            const position = (y * width + x) * 4;
            pixels.data[position] = color;
            pixels.data[position + 1] = color;
            pixels.data[position + 2] = color;
            pixels.data[position + 3] = 255;
        }
    } else {
        throw new Error('Formato immagine interno al PDF non supportato.');
    }
    context.putImageData(pixels, 0, 0);
    return canvas;
}

function canvasBlob(canvas, type) {
    return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Conversione immagine non riuscita.')), type, 0.92));
}

function safeName(name) {
    return name.replace(/\.pdf$/i, '').replace(/[^\p{L}\p{N}._-]+/gu, '-').slice(0, 90) || 'documento';
}

function zipFiles(entries) {
    return new Promise((resolve, reject) => zip(entries, { level: 0 }, (cause, bytes) => cause ? reject(cause) : resolve(bytes)));
}

async function convert() {
    if (!files.value.length || processing.value) return;
    clearResult();
    error.value = '';
    processing.value = true;
    progress.value = 'Lettura dei PDF...';
    const results = [];
    const seen = new Set();
    let outputBytes = 0;
    try {
        for (const [fileIndex, entry] of files.value.entries()) {
            let task;
            try {
                task = pdfjs.getDocument({ data: new Uint8Array(await entry.file.arrayBuffer()), isOffscreenCanvasSupported: false });
                const pdf = await task.promise;
                const pages = selectedPages(pageRange.value, pdf.numPages);
                let imageIndex = 0;
                for (const pageNumber of [...pages].sort((a, b) => a - b)) {
                    progress.value = `${fileIndex + 1}/${files.value.length} PDF · pagina ${pageNumber}/${pdf.numPages}`;
                    const page = await pdf.getPage(pageNumber);
                    const operators = await page.getOperatorList();
                    let pageImageIndex = 0;
                    for (let index = 0; index < operators.fnArray.length; index++) {
                        const operator = operators.fnArray[index];
                        if (operator !== pdfjs.OPS.paintImageXObject && operator !== pdfjs.OPS.paintInlineImageXObject) continue;
                        const image = operator === pdfjs.OPS.paintImageXObject
                            ? await imageObject(page, pdf, operators.argsArray[index][0])
                            : operators.argsArray[index][0];
                        if (!image) continue;
                        if (results.length >= maxImages) throw new Error('Limite di 200 immagini raggiunto. Riduci il numero di PDF o di pagine.');
                        const canvas = imageCanvas(image);
                        try {
                            if (removeDuplicates.value) {
                                const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
                                const digest = await crypto.subtle.digest('SHA-256', pixels);
                                const key = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
                                if (seen.has(key)) continue;
                                seen.add(key);
                            }
                            const type = `image/${format.value === 'jpg' ? 'jpeg' : format.value}`;
                            if (format.value === 'jpg') {
                                const context = canvas.getContext('2d');
                                context.globalCompositeOperation = 'destination-over';
                                context.fillStyle = '#fff';
                                context.fillRect(0, 0, canvas.width, canvas.height);
                            }
                            const blob = await canvasBlob(canvas, type);
                            if (blob.type !== type) throw new Error(`${format.value.toUpperCase()} non è supportato da questo browser.`);
                            outputBytes += blob.size;
                            if (outputBytes > maxOutputBytes) throw new Error('Le immagini estratte superano 250 MB. Seleziona meno pagine o usa WebP/JPEG.');
                            pageImageIndex++;
                            imageIndex++;
                            const name = `${safeName(entry.file.name)}_P${pageNumber}_IMG${pageImageIndex}.${format.value}`;
                            results.push({ name, blob, width: canvas.width, height: canvas.height, fileIndex, url: URL.createObjectURL(blob) });
                        } finally {
                            canvas.width = 0;
                            canvas.height = 0;
                        }
                    }
                    page.cleanup();
                }
                if (!imageIndex) progress.value = `Nessuna immagine in ${entry.file.name}`;
            } catch (cause) {
                if (cause?.name === 'PasswordException') throw new Error(`${entry.file.name}: PDF protetto da password.`);
                throw cause;
            } finally {
                await task?.destroy();
            }
        }
        if (!results.length) throw new Error('Nessuna immagine trovata nelle pagine selezionate.');
        progress.value = 'Preparazione dello ZIP...';
        const entries = {};
        const usedFolders = new Set();
        const folderNames = files.value.map((entry, index) => {
            const base = safeName(entry.file.name);
            let name = base;
            if (usedFolders.has(name)) name = `${base}-${index + 1}`;
            while (usedFolders.has(name)) name += '-2';
            usedFolders.add(name);
            return name;
        });
        for (const item of results) {
            const folder = files.value.length > 1 ? `${folderNames[item.fileIndex]}/` : '';
            entries[`${folder}${item.name}`] = new Uint8Array(await item.blob.arrayBuffer());
        }
        archive.value = await zipFiles(entries);
        extracted.value = results;
    } catch (cause) {
        results.forEach(image => URL.revokeObjectURL(image.url));
        error.value = cause?.message || 'Estrazione non riuscita. Controlla i PDF e riprova.';
    } finally {
        progress.value = '';
        processing.value = false;
    }
}

function downloadBlob(blob, name) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 30000);
}

function downloadZip() {
    if (archive.value) downloadBlob(new Blob([archive.value], { type: 'application/zip' }), 'immagini-estratte.zip');
}
</script>

<template>
    <Head title="Estrai immagini da PDF" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Estrai immagini da PDF</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <PdfToolNavigation />
                <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between gap-3"><h3 class="text-base font-semibold text-gray-900">PDF</h3><button v-if="files.length" type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-800" :disabled="processing" @click="files = []"><Trash2 class="h-4 w-4" /> Rimuovi tutti</button></div>
                        <ToolDropzone label="Trascina qui i PDF o selezionali" hint="Fino a 20 PDF · 100 MB complessivi" aria-label="Seleziona o trascina PDF" :disabled="processing" @select="input?.click()" @drop="onDrop" />
                        <input ref="input" type="file" accept="application/pdf,.pdf" multiple class="hidden" @change="onInput" />
                        <div v-if="files.length" class="mt-5 min-w-0">
                            <div class="mb-2 flex items-center justify-between text-xs font-semibold text-gray-500"><span>{{ files.length }} PDF</span><span>{{ formatBytes(files.reduce((sum, entry) => sum + entry.file.size, 0)) }}</span></div>
                            <div class="max-h-[420px] divide-y divide-gray-100 overflow-y-auto rounded-[var(--radius-sm)] border border-gray-100">
                                <div v-for="entry in files" :key="entry.id" class="flex min-w-0 items-center gap-3 px-3 py-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.08)] text-[hsl(var(--primary-app))]"><FileImage class="h-5 w-5" /></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800">{{ entry.file.name }}</p><p class="text-xs text-gray-500">{{ formatBytes(entry.file.size) }}</p></div><button type="button" class="icon-btn h-8 w-8 shrink-0" :title="`Rimuovi ${entry.file.name}`" :disabled="processing" @click="removeFile(entry.id)"><X class="h-4 w-4" /></button></div>
                            </div>
                            <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[hsl(var(--primary-app))]" :disabled="processing" @click="input?.click()"><Plus class="h-4 w-4" /> Aggiungi PDF</button>
                        </div>
                    </section>
                    <section class="surface p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Impostazioni</h3>
                        <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200">
                            <ToolAccordion title="Formato immagini" :summary="format === 'jpg' ? 'JPEG' : format === 'webp' ? 'WebP' : 'PNG'" :open="openSetting === 'format'" :disabled="processing" panel-id="extract-image-format" @toggle="openSetting = openSetting === 'format' ? '' : 'format'">
                                <div class="grid grid-cols-3 gap-2"><button v-for="option in [{ value: 'png', label: 'PNG' }, { value: 'jpg', label: 'JPEG' }, { value: 'webp', label: 'WebP' }]" :key="option.value" type="button" :aria-pressed="format === option.value" :class="['rounded-[var(--radius-sm)] border px-2 py-2 text-sm transition', format === option.value ? 'border-[hsl(var(--primary-app)/0.6)] bg-[hsl(var(--primary-app)/0.06)] font-semibold text-[hsl(var(--primary-app))]' : 'border-gray-200 text-gray-700 hover:border-gray-300']" @click="format = option.value">{{ option.label }}</button></div>
                            </ToolAccordion>
                            <ToolAccordion title="Pagine" :summary="pageMode === 'all' ? 'Tutte' : pageRange || 'Selezionate'" :open="openSetting === 'pages'" :disabled="processing" panel-id="extract-image-pages" @toggle="openSetting = openSetting === 'pages' ? '' : 'pages'">
                                <div class="grid grid-cols-2 gap-2"><button v-for="option in [{ value: 'all', label: 'Tutte' }, { value: 'custom', label: 'Seleziona' }]" :key="option.value" type="button" :aria-pressed="pageMode === option.value" :class="['rounded-[var(--radius-sm)] border px-2 py-2 text-sm transition', pageMode === option.value ? 'border-[hsl(var(--primary-app)/0.6)] bg-[hsl(var(--primary-app)/0.06)] font-semibold text-[hsl(var(--primary-app))]' : 'border-gray-200 text-gray-700 hover:border-gray-300']" @click="pageMode = option.value">{{ option.label }}</button></div><input v-if="pageMode === 'custom'" v-model="pageRange" type="text" placeholder="Es. 1-3,5" aria-label="Intervallo pagine" class="mt-3 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" />
                            </ToolAccordion>
                            <ToolAccordion title="Duplicati" :summary="removeDuplicates ? 'Escludi immagini duplicate' : 'Mantieni tutte le immagini'" :open="openSetting === 'duplicates'" :disabled="processing" panel-id="extract-image-duplicates" @toggle="openSetting = openSetting === 'duplicates' ? '' : 'duplicates'">
                                <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="removeDuplicates" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" /> Escludi immagini duplicate</label>
                            </ToolAccordion>
                        </div>
                        <button type="button" class="btn btn-primary mt-6 w-full justify-center" :disabled="!files.length || processing" @click="convert"><Images class="h-4 w-4" /> {{ processing ? 'Estrazione in corso...' : 'Estrai immagini' }}</button>
                        <p v-if="processing" role="status" class="mt-3 text-center text-xs text-gray-500">{{ progress }}</p>
                    </section>
                </div>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <section v-if="extracted.length" class="surface p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4"><div><h3 class="text-base font-semibold text-gray-900">{{ extracted.length }} {{ extracted.length === 1 ? 'immagine estratta' : 'immagini estratte' }}</h3><p class="mt-1 text-sm text-gray-500">{{ formatBytes(archive?.length || 0) }} · ZIP pronto</p></div><button type="button" class="btn btn-primary" @click="downloadZip"><Download class="h-4 w-4" /> Scarica ZIP</button></div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div v-for="item in extracted.slice(0, 12)" :key="item.name" class="min-w-0 rounded-[var(--radius-sm)] border border-gray-100 p-3"><div class="flex h-28 items-center justify-center overflow-hidden bg-gray-50"><img :src="item.url" :alt="item.name" class="max-h-full max-w-full object-contain" /></div><p class="mt-2 truncate text-xs font-semibold text-gray-800" :title="item.name">{{ item.name }}</p><div class="mt-1 flex items-center justify-between gap-2 text-xs text-gray-500"><span>{{ item.width }} × {{ item.height }}</span><button type="button" class="font-semibold text-[hsl(var(--primary-app))]" :title="`Scarica ${item.name}`" @click="downloadBlob(item.blob, item.name)"><Download class="h-4 w-4" /></button></div></div></div>
                    <p v-if="extracted.length > 12" class="mt-3 text-sm text-gray-500">Altre {{ extracted.length - 12 }} immagini nello ZIP.</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
