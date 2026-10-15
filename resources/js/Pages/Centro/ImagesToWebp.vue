<script setup>
import ToolDropzone from '@/Components/ToolDropzone.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Images, Plus, Trash2, X } from '@lucide/vue';
import { zipSync } from 'fflate';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const maxFiles = 100;
const maxFileBytes = 20 * 1024 * 1024;
const maxTotalBytes = 120 * 1024 * 1024;
const input = ref(null);
const images = ref([]);
const quality = ref(75);
const lossless = ref(false);
const processing = ref(false);
const loading = ref(false);
const completed = ref(0);
const error = ref('');
let worker = null;
let runId = 0;

const readyCount = computed(() => images.value.filter(image => image.status === 'done').length);
const inputBytes = computed(() => images.value.reduce((total, image) => total + image.file.size, 0));
const outputBytes = computed(() => images.value.reduce((total, image) => total + (image.bytes?.byteLength || 0), 0));

function formatBytes(bytes) {
    return bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function resetResults() {
    images.value.forEach(image => { image.bytes = null; image.status = 'pending'; });
    completed.value = 0;
}

watch([quality, lossless], resetResults);

function stopWorker() {
    runId++;
    worker?.terminate();
    worker = null;
    processing.value = false;
}

onBeforeUnmount(() => {
    stopWorker();
    images.value.forEach(image => URL.revokeObjectURL(image.preview));
});

function isSupported(file) {
    return ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || /\.(jpe?g|png|webp)$/i.test(file.name);
}

async function addFiles(selected) {
    if (processing.value || loading.value) return;
    loading.value = true;
    error.value = '';
    const issues = [];
    let total = inputBytes.value;
    try {
        for (const file of Array.from(selected || [])) {
        if (images.value.length >= maxFiles || total + file.size > maxTotalBytes) {
            issues.push('Limite raggiunto: 100 immagini e 120 MB complessivi.');
            break;
        }
        if (file.size > maxFileBytes) { issues.push(`${file.name}: supera 20 MB.`); continue; }
        if (!isSupported(file)) { issues.push(`${file.name}: formato non supportato.`); continue; }
        if (images.value.some(image => image.file.name === file.name && image.file.size === file.size && image.file.lastModified === file.lastModified)) continue;
        try {
            const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            const { width, height } = bitmap;
            bitmap.close();
            if (width * height > 40000000) throw new Error('too-large');
            images.value.push({ id: crypto.randomUUID(), file, width, height, preview: URL.createObjectURL(file), status: 'pending', bytes: null });
            total += file.size;
        } catch {
            issues.push(`${file.name}: immagine non leggibile o troppo grande.`);
        }
        }
    } finally {
        loading.value = false;
        error.value = issues.join(' ');
    }
}

function onInput(event) {
    addFiles(event.target.files);
    event.target.value = '';
}

function removeImage(id) {
    const image = images.value.find(item => item.id === id);
    if (image) URL.revokeObjectURL(image.preview);
    images.value = images.value.filter(item => item.id !== id);
}

function clearImages() {
    images.value.forEach(image => URL.revokeObjectURL(image.preview));
    images.value = [];
    completed.value = 0;
    error.value = '';
}

async function convert() {
    if (!images.value.length || processing.value || loading.value) return;
    resetResults();
    error.value = '';
    processing.value = true;
    const currentRun = ++runId;
    worker = new Worker(new URL('../../workers/webp-converter.worker.js', import.meta.url), { type: 'module' });

    for (const image of images.value) {
        if (currentRun !== runId) break;
        image.status = 'working';
        const result = await new Promise(resolve => {
            const timer = window.setTimeout(() => resolve({ error: 'Tempo di conversione esaurito.' }), 120000);
            worker.onmessage = ({ data }) => {
                if (data.id !== image.id) return;
                window.clearTimeout(timer);
                resolve(data);
            };
            worker.onerror = () => {
                window.clearTimeout(timer);
                resolve({ error: 'Conversione non riuscita.' });
            };
            worker.postMessage({ id: image.id, file: image.file, quality: quality.value, lossless: lossless.value });
        });
        if (currentRun !== runId) break;
        image.bytes = result.bytes || null;
        image.status = result.bytes ? 'done' : 'failed';
        completed.value++;
        if (result.error) {
            worker.terminate();
            worker = new Worker(new URL('../../workers/webp-converter.worker.js', import.meta.url), { type: 'module' });
        }
    }
    if (currentRun === runId) {
        worker?.terminate();
        worker = null;
        processing.value = false;
    }
}

function outputName(name) {
    return (name.replace(/\.[^.]+$/, '').replace(/[\\/<>:"|?*\x00-\x1f]/g, '-').trim() || 'immagine').slice(0, 100);
}

function download() {
    const files = {};
    images.value.filter(image => image.bytes).forEach(image => {
        const base = outputName(image.file.name);
        let name = `${base}.webp`;
        let suffix = 2;
        while (files[name]) name = `${base}-${suffix++}.webp`;
        files[name] = new Uint8Array(image.bytes);
    });
    if (!Object.keys(files).length) return;
    try {
        const bytes = zipSync(files, { level: 0 });
        const url = URL.createObjectURL(new Blob([bytes], { type: 'application/zip' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'immagini-webp.zip';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 30000);
    } catch {
        error.value = 'Impossibile creare lo ZIP. Prova con meno immagini per volta.';
    }
}
</script>

<template>
    <Head title="Converti in WebP" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Converti in WebP</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between gap-3"><h3 class="text-base font-semibold text-gray-900">Immagini</h3><button v-if="images.length" type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-800" :disabled="processing || loading" @click="clearImages"><Trash2 class="h-4 w-4" /> Rimuovi tutte</button></div>
                        <ToolDropzone :label="loading ? 'Lettura delle immagini...' : 'Trascina qui le immagini o selezionale'" hint="JPG, JPEG, PNG o WebP · fino a 100 immagini" aria-label="Seleziona o trascina immagini" :disabled="processing || loading" @select="input?.click()" @drop="addFiles($event.dataTransfer?.files)" />
                        <input ref="input" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple class="hidden" @change="onInput" />
                        <div v-if="images.length" class="mt-5 min-w-0">
                            <div class="mb-2 flex items-center justify-between text-xs font-semibold text-gray-500"><span>{{ images.length }} {{ images.length === 1 ? 'immagine' : 'immagini' }}</span><span>{{ formatBytes(inputBytes) }}</span></div>
                            <div class="max-h-[420px] divide-y divide-gray-100 overflow-y-auto rounded-[var(--radius-sm)] border border-gray-100">
                                <div v-for="image in images" :key="image.id" class="flex min-w-0 items-center gap-3 px-3 py-2.5">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-sm)] bg-gray-50"><img :src="image.preview" :alt="image.file.name" class="max-h-full max-w-full object-contain" /></div>
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800">{{ image.file.name }}</p><p class="text-xs text-gray-500">{{ image.width }} × {{ image.height }} · {{ formatBytes(image.file.size) }}<template v-if="image.bytes"> → {{ formatBytes(image.bytes.byteLength) }}</template></p><p v-if="image.status === 'failed'" class="text-xs font-medium text-red-600">Conversione non riuscita</p><p v-else-if="image.status === 'working'" class="text-xs font-medium text-[hsl(var(--primary-app))]">Conversione in corso...</p><p v-else-if="image.status === 'done'" class="text-xs font-medium text-green-700">Pronta</p></div>
                                    <button type="button" class="icon-btn h-8 w-8 shrink-0" :disabled="processing || loading" :title="`Rimuovi ${image.file.name}`" @click="removeImage(image.id)"><X class="h-4 w-4" /></button>
                                </div>
                            </div>
                            <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[hsl(var(--primary-app))]" :disabled="processing || loading" @click="input?.click()"><Plus class="h-4 w-4" /> Aggiungi immagini</button>
                        </div>
                    </section>
                    <section class="surface p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Impostazioni WebP</h3>
                        <label class="mt-5 flex items-center justify-between gap-3 text-sm font-medium text-gray-700"><span>Senza perdita</span><input v-model="lossless" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" :disabled="processing" /></label>
                        <div v-if="!lossless" class="mt-5"><label for="webp-quality" class="flex items-center justify-between text-sm font-medium text-gray-700"><span>Qualità</span><span>{{ quality }}%</span></label><input id="webp-quality" v-model.number="quality" type="range" min="1" max="100" class="mt-3 w-full accent-[hsl(var(--primary-app))]" :disabled="processing" /></div>
                        <button type="button" class="btn btn-primary mt-6 w-full justify-center" :disabled="!images.length || processing || loading" @click="convert"><Images class="h-4 w-4" /> {{ processing ? `Conversione ${completed}/${images.length}` : 'Converti in WebP' }}</button>
                        <div v-if="processing" role="progressbar" :aria-valuenow="completed" :aria-valuemax="images.length" aria-valuemin="0" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[hsl(var(--primary-app))] transition-all duration-300" :style="{ width: `${completed / images.length * 100}%` }" /></div>
                    </section>
                </div>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <section v-if="readyCount && !processing" class="surface flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6"><div class="min-w-0"><h3 class="text-base font-semibold text-gray-900">Conversione completata</h3><p class="mt-1 text-sm text-gray-500">{{ readyCount }} {{ readyCount === 1 ? 'immagine pronta' : 'immagini pronte' }} · {{ formatBytes(outputBytes) }}</p></div><button type="button" class="btn btn-primary shrink-0" @click="download"><Download class="h-4 w-4" /> Scarica ZIP</button></section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
