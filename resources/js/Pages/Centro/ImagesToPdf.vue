<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeft, ArrowUp, Download, FileImage, GripVertical, Plus, RotateCw, Trash2, Upload, X } from '@lucide/vue';
import { zipSync } from 'fflate';
import { PDFDocument } from 'pdf-lib';
import { onBeforeUnmount, ref, watch } from 'vue';

const maxFiles = 100;
const maxFileBytes = 20 * 1024 * 1024;
const maxTotalBytes = 120 * 1024 * 1024;
const input = ref(null);
const images = ref([]);
const loading = ref(false);
const processing = ref(false);
const progress = ref(0);
const dragDepth = ref(0);
const draggedId = ref(null);
const orientation = ref('portrait');
const pageSize = ref('fit');
const margin = ref(0);
const merge = ref(true);
const result = ref(null);
const error = ref('');

watch([orientation, pageSize, margin, merge, images], () => { result.value = null; }, { deep: true });
onBeforeUnmount(() => images.value.forEach(image => URL.revokeObjectURL(image.preview)));

function formatBytes(bytes) {
    return bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function imageType(bytes) {
    if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'jpeg';
    if (bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47) return 'png';
    if (String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' && String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP') return 'webp';
    return null;
}

async function addFiles(selected) {
    const incoming = Array.from(selected || []);
    if (!incoming.length || processing.value || loading.value) return;
    loading.value = true;
    error.value = '';
    const issues = [];
    let total = images.value.reduce((sum, image) => sum + image.file.size, 0);
    try {
        for (const file of incoming) {
            if (images.value.length >= maxFiles || total + file.size > maxTotalBytes) {
                issues.push('Limite raggiunto: 100 immagini e 120 MB complessivi.');
                break;
            }
            if (file.size > maxFileBytes) { issues.push(`${file.name}: supera 20 MB.`); continue; }
            if (images.value.some(image => image.file.name === file.name && image.file.size === file.size && image.file.lastModified === file.lastModified)) continue;
            const bytes = new Uint8Array(await file.slice(0, 12).arrayBuffer());
            const type = imageType(bytes);
            if (!type) { issues.push(`${file.name}: formato non supportato.`); continue; }
            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                if (bitmap.width * bitmap.height > 40000000) {
                    bitmap.close();
                    throw new Error('too-large');
                }
                const width = bitmap.width;
                const height = bitmap.height;
                bitmap.close();
                images.value.push({ id: crypto.randomUUID(), file, type, width, height, rotation: 0, preview: URL.createObjectURL(file) });
                total += file.size;
            } catch {
                issues.push(`${file.name}: immagine non leggibile o troppo grande.`);
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

function onDrop(event) {
    dragDepth.value = 0;
    addFiles(event.dataTransfer?.files);
}

function removeImage(id) {
    const image = images.value.find(item => item.id === id);
    if (image) URL.revokeObjectURL(image.preview);
    images.value = images.value.filter(item => item.id !== id);
    result.value = null;
}

function clearImages() {
    images.value.forEach(image => URL.revokeObjectURL(image.preview));
    images.value = [];
    result.value = null;
    error.value = '';
}

function moveImage(from, to) {
    if (to < 0 || to >= images.value.length || from === to) return;
    const next = [...images.value];
    next.splice(to, 0, next.splice(from, 1)[0]);
    images.value = next;
}

function onSortDrop(targetId) {
    const from = images.value.findIndex(image => image.id === draggedId.value);
    const to = images.value.findIndex(image => image.id === targetId);
    if (from >= 0 && to >= 0) moveImage(from, to);
    draggedId.value = null;
}

function canvasBlob(canvas, type) {
    return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('conversion')), type, 0.94));
}

async function addPage(pdf, entry) {
    const bitmap = await createImageBitmap(entry.file, { imageOrientation: 'from-image' });
    const quarterTurn = entry.rotation % 180 !== 0;
    const canvas = document.createElement('canvas');
    canvas.width = quarterTurn ? bitmap.height : bitmap.width;
    canvas.height = quarterTurn ? bitmap.width : bitmap.height;
    const context = canvas.getContext('2d');
    if (!context) { bitmap.close(); throw new Error('canvas'); }
    const imageFormat = entry.type === 'jpeg' ? 'image/jpeg' : 'image/png';
    if (imageFormat === 'image/jpeg') {
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
    }
    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate(entry.rotation * Math.PI / 180);
    context.drawImage(bitmap, -bitmap.width / 2, -bitmap.height / 2);
    bitmap.close();
    const bytes = await (await canvasBlob(canvas, imageFormat)).arrayBuffer();
    canvas.width = 0;
    canvas.height = 0;
    const embedded = imageFormat === 'image/jpeg' ? await pdf.embedJpg(bytes) : await pdf.embedPng(bytes);
    let width;
    let height;
    if (pageSize.value === 'fit') {
        const longSide = Math.max(embedded.width, embedded.height) * 0.75;
        const shortSide = Math.min(embedded.width, embedded.height) * 0.75;
        width = orientation.value === 'portrait' ? shortSide : longSide;
        height = orientation.value === 'portrait' ? longSide : shortSide;
    } else {
        [width, height] = pageSize.value === 'a4' ? [595.28, 841.89] : [612, 792];
        if (orientation.value === 'landscape') [width, height] = [height, width];
    }
    const scale = Math.min(1, 14000 / Math.max(width, height));
    width *= scale;
    height *= scale;
    const inset = Math.min(Number(margin.value), Math.min(width, height) / 3);
    const availableWidth = width - inset * 2;
    const availableHeight = height - inset * 2;
    const imageScale = Math.min(availableWidth / embedded.width, availableHeight / embedded.height);
    const drawWidth = embedded.width * imageScale;
    const drawHeight = embedded.height * imageScale;
    const page = pdf.addPage([width, height]);
    page.drawImage(embedded, { x: (width - drawWidth) / 2, y: (height - drawHeight) / 2, width: drawWidth, height: drawHeight });
}

function outputName(name) {
    return name.replace(/\.[^.]+$/, '').replace(/[^\p{L}\p{N}._-]+/gu, '-').slice(0, 90) || 'immagine';
}

async function convert() {
    if (!images.value.length || processing.value || loading.value) return;
    processing.value = true;
    progress.value = 0;
    result.value = null;
    error.value = '';
    try {
        if (merge.value || images.value.length === 1) {
            const pdf = await PDFDocument.create();
            for (const image of images.value) {
                await addPage(pdf, image);
                progress.value++;
                await new Promise(resolve => setTimeout(resolve, 0));
            }
            result.value = { name: images.value.length === 1 ? `${outputName(images.value[0].file.name)}.pdf` : 'immagini.pdf', data: await pdf.save(), count: images.value.length, type: 'application/pdf' };
        } else {
            const files = {};
            for (const [index, image] of images.value.entries()) {
                const pdf = await PDFDocument.create();
                await addPage(pdf, image);
                files[`${String(index + 1).padStart(3, '0')}-${outputName(image.file.name)}.pdf`] = await pdf.save();
                progress.value++;
                await new Promise(resolve => setTimeout(resolve, 0));
            }
            result.value = { name: 'immagini-pdf.zip', data: zipSync(files, { level: 0 }), count: images.value.length, type: 'application/zip' };
        }
    } catch {
        result.value = null;
        error.value = 'Conversione non riuscita. Controlla le immagini o prova con file più piccoli.';
    } finally {
        processing.value = false;
    }
}

function download() {
    if (!result.value) return;
    const url = URL.createObjectURL(new Blob([result.value.data], { type: result.value.type }));
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
    <Head title="Immagini in PDF" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Immagini in PDF</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between gap-3"><h3 class="text-base font-semibold text-gray-900">Immagini</h3><button v-if="images.length" type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-800" :disabled="processing || loading" @click="clearImages"><Trash2 class="h-4 w-4" /> Rimuovi tutte</button></div>
                        <div role="button" tabindex="0" aria-label="Seleziona o trascina immagini" :class="['flex min-h-44 flex-1 cursor-pointer flex-col items-center justify-center gap-2 rounded-[var(--radius)] border-2 border-dashed px-5 py-8 text-center transition focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]', dragDepth ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.08)]' : 'border-gray-200 bg-gray-50/70 hover:border-[hsl(var(--primary-app)/0.6)] hover:bg-[hsl(var(--primary-app)/0.04)]', processing || loading ? 'pointer-events-none opacity-60' : '']" @click="input?.click()" @keydown.enter.prevent="input?.click()" @keydown.space.prevent="input?.click()" @dragenter.prevent="dragDepth++" @dragover.prevent @dragleave.prevent="dragDepth = Math.max(0, dragDepth - 1)" @drop.prevent="onDrop">
                            <span class="flex h-12 w-12 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Upload class="h-6 w-6" :stroke-width="1.7" /></span>
                            <span class="text-sm font-semibold text-gray-800">{{ loading ? 'Lettura delle immagini...' : 'Trascina qui le immagini o selezionale' }}</span>
                            <span class="text-xs text-gray-500">JPG, JPEG, PNG o WebP · fino a 100 immagini</span>
                        </div>
                        <input ref="input" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple class="hidden" @change="onInput" />
                        <div v-if="images.length" class="mt-5 min-w-0">
                            <div class="mb-2 flex items-center justify-between text-xs font-semibold text-gray-500"><span>{{ images.length }} {{ images.length === 1 ? 'immagine' : 'immagini' }}</span><span>{{ formatBytes(images.reduce((sum, image) => sum + image.file.size, 0)) }}</span></div>
                            <div class="max-h-[420px] divide-y divide-gray-100 overflow-y-auto border-y border-gray-100">
                                <div v-for="(image, index) in images" :key="image.id" draggable="true" :class="['flex min-w-0 flex-wrap items-center gap-2 py-2.5 transition sm:flex-nowrap', draggedId === image.id ? 'opacity-50' : '']" @dragstart="draggedId = image.id" @dragend="draggedId = null" @dragover.prevent @drop.prevent="onSortDrop(image.id)">
                                    <GripVertical class="hidden h-4 w-4 shrink-0 cursor-grab text-gray-300 sm:block" :stroke-width="1.7" />
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-sm)] bg-gray-50"><img :src="image.preview" :alt="image.file.name" class="max-h-full max-w-full object-contain" :style="{ transform: `rotate(${image.rotation}deg)` }" /></div>
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800">{{ image.file.name }}</p><p class="text-xs text-gray-500">{{ image.width }} × {{ image.height }} · {{ formatBytes(image.file.size) }}</p></div>
                                    <div class="ml-auto flex w-full shrink-0 items-center justify-end gap-0.5 sm:w-auto"><button type="button" class="icon-btn h-8 w-8" :disabled="processing || index === 0" :title="`Sposta su ${image.file.name}`" @click="moveImage(index, index - 1)"><ArrowUp class="h-4 w-4" /></button><button type="button" class="icon-btn h-8 w-8" :disabled="processing || index === images.length - 1" :title="`Sposta giù ${image.file.name}`" @click="moveImage(index, index + 1)"><ArrowDown class="h-4 w-4" /></button><button type="button" class="icon-btn h-8 w-8" :disabled="processing" :title="`Ruota ${image.file.name}`" @click="image.rotation = (image.rotation + 90) % 360"><RotateCw class="h-4 w-4" /></button><button type="button" class="icon-btn h-8 w-8" :disabled="processing" :title="`Rimuovi ${image.file.name}`" @click="removeImage(image.id)"><X class="h-4 w-4" /></button></div>
                                </div>
                            </div>
                            <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[hsl(var(--primary-app))]" :disabled="processing || loading" @click="input?.click()"><Plus class="h-4 w-4" /> Aggiungi immagini</button>
                        </div>
                    </section>
                    <section class="surface p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Impostazioni PDF</h3>
                        <fieldset class="mt-5" :disabled="processing"><legend class="mb-2 text-sm font-semibold text-gray-800">Orientamento</legend><div class="grid grid-cols-2 rounded-[var(--radius-sm)] bg-gray-100 p-1"><button v-for="option in [{ value: 'portrait', label: 'Verticale' }, { value: 'landscape', label: 'Orizzontale' }]" :key="option.value" type="button" :aria-pressed="orientation === option.value" :class="['min-h-9 rounded-[var(--radius-sm)] px-2 text-sm font-semibold transition', orientation === option.value ? 'bg-white text-[hsl(var(--primary-app))] shadow-sm' : 'text-gray-600']" @click="orientation = option.value">{{ option.label }}</button></div></fieldset>
                        <label class="mt-5 block text-sm font-semibold text-gray-800">Formato pagina<select v-model="pageSize" :disabled="processing" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm"><option value="fit">Adatta all’immagine</option><option value="a4">A4</option><option value="letter">US Letter</option></select></label>
                        <fieldset class="mt-5" :disabled="processing"><legend class="mb-2 text-sm font-semibold text-gray-800">Margini</legend><div class="grid grid-cols-3 gap-2"><label v-for="option in [{ value: 0, label: 'Nessuno' }, { value: 20, label: 'Piccoli' }, { value: 40, label: 'Grandi' }]" :key="option.value" :class="['flex min-h-10 cursor-pointer items-center justify-center rounded-[var(--radius-sm)] border px-2 text-center text-xs font-semibold transition', margin === option.value ? 'border-[hsl(var(--primary-app)/0.6)] bg-[hsl(var(--primary-app)/0.06)] text-[hsl(var(--primary-app))]' : 'border-gray-200 text-gray-600 hover:border-gray-300']"><input v-model.number="margin" type="radio" name="pdf-margin" :value="option.value" class="sr-only" />{{ option.label }}</label></div></fieldset>
                        <label class="mt-5 flex items-center gap-2 text-sm text-gray-700"><input v-model="merge" type="checkbox" :disabled="processing" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" /> Un PDF unico</label>
                        <button type="button" class="btn btn-primary mt-5 w-full justify-center" :disabled="!images.length || processing || loading" @click="convert"><FileImage class="h-4 w-4" /> {{ processing ? `Conversione ${progress}/${images.length}` : 'Converti in PDF' }}</button>
                        <div v-if="processing" role="progressbar" :aria-valuenow="progress" :aria-valuemax="images.length" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[hsl(var(--primary-app))] transition-all duration-300" :style="{ width: `${Math.max(5, progress / images.length * 100)}%` }" /></div>
                    </section>
                </div>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <section v-if="result" class="surface flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6"><div class="min-w-0"><h3 class="text-base font-semibold text-gray-900">Conversione completata</h3><p class="mt-1 text-sm text-gray-500">{{ result.count }} {{ result.count === 1 ? 'immagine' : 'immagini' }} · {{ formatBytes(result.data.length) }}</p></div><button type="button" class="btn btn-primary shrink-0" @click="download"><Download class="h-4 w-4" /> {{ result.type === 'application/zip' ? 'Scarica ZIP' : 'Scarica PDF' }}</button></section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
