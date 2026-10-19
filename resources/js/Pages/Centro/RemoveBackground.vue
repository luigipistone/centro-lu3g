<script setup>
import ToolDropzone from '@/Components/ToolDropzone.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, ImageMinus, LoaderCircle, X } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';

const input = ref(null);
const file = ref(null);
const originalUrl = ref('');
const resultUrl = ref('');
const processing = ref(false);
const error = ref('');
const comparison = ref(50);
const progressText = ref('');
let worker = null;

function clearUrls() {
    if (originalUrl.value) URL.revokeObjectURL(originalUrl.value);
    if (resultUrl.value) URL.revokeObjectURL(resultUrl.value);
    originalUrl.value = '';
    resultUrl.value = '';
}

function clear() {
    if (processing.value) return;
    clearUrls();
    file.value = null;
    error.value = '';
    if (input.value) input.value.value = '';
}

async function choose(selected) {
    if (processing.value) return;
    const next = Array.from(selected || [])[0];
    if (!next) return;
    error.value = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(next.type)) {
        error.value = 'Seleziona un JPG, PNG o WebP.';
        return;
    }
    if (next.size > 20 * 1024 * 1024) {
        error.value = 'L’immagine supera il limite di 20 MB.';
        return;
    }
    try {
        const bitmap = await createImageBitmap(next);
        const pixels = bitmap.width * bitmap.height;
        bitmap.close();
        if (pixels > 25000000) {
            error.value = 'L’immagine supera il limite di 25 megapixel.';
            return;
        }
    } catch {
        error.value = 'Impossibile leggere l’immagine.';
        return;
    }
    clearUrls();
    file.value = next;
    originalUrl.value = URL.createObjectURL(next);
    comparison.value = 50;
}

function onInput(event) {
    choose(event.target.files);
    event.target.value = '';
}

function process() {
    if (!file.value || processing.value) return;
    if (resultUrl.value) URL.revokeObjectURL(resultUrl.value);
    resultUrl.value = '';
    error.value = '';
    processing.value = true;
    progressText.value = 'Preparazione del modello…';
    try {
        if (!worker) worker = new Worker(new URL('../../workers/remove-background.worker.js', import.meta.url), { type: 'module' });
        worker.onmessage = ({ data }) => {
            if (data.type === 'status') { progressText.value = data.message; return; }
            if (data.type === 'done') resultUrl.value = URL.createObjectURL(new Blob([data.bytes], { type: 'image/png' }));
            else error.value = data.message;
            processing.value = false;
        };
        worker.onerror = () => {
            error.value = 'Impossibile avviare il modello nel browser.';
            worker.terminate();
            worker = null;
            processing.value = false;
        };
        worker.postMessage({ file: file.value });
    } catch {
        error.value = 'Impossibile avviare il modello nel browser.';
        processing.value = false;
    }
}

function download() {
    if (!resultUrl.value) return;
    const link = document.createElement('a');
    link.href = resultUrl.value;
    link.download = `${file.value.name.replace(/\.[^.]+$/, '')}-senza-sfondo.png`;
    link.click();
}

onBeforeUnmount(() => {
    worker?.terminate();
    clearUrls();
});
</script>

<template>
    <Head title="Rimuovi sfondo" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" />Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Rimuovi sfondo</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto grid max-w-[1600px] gap-5 px-4 sm:px-6 lg:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)] lg:px-8">
                <section class="surface flex min-h-[480px] flex-col p-5 sm:p-6">
                    <div class="mb-5 flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><ImageMinus class="h-6 w-6" :stroke-width="1.7" /></span><h3 class="text-base font-semibold text-gray-900">Immagine</h3></div>
                    <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onInput" />
                    <ToolDropzone v-if="!file" label="Trascina qui un'immagine oppure selezionala" hint="JPG, PNG o WebP · fino a 20 MB" aria-label="Seleziona un'immagine" class="min-h-[360px]" @select="input?.click()" @drop="choose($event.dataTransfer.files)" />
                    <div v-else class="flex flex-1 flex-col gap-4">
                        <div class="flex min-w-0 items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 px-4 py-3"><span class="min-w-0 truncate text-sm font-medium text-gray-800">{{ file.name }}</span><button type="button" :disabled="processing" aria-label="Rimuovi immagine" title="Rimuovi immagine" class="shrink-0 text-gray-500 hover:text-red-600 disabled:opacity-50" @click="clear"><X class="h-5 w-5" /></button></div>
                        <div class="relative flex min-h-[320px] flex-1 items-center justify-center overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white checkerboard">
                            <img :src="originalUrl" alt="Immagine originale" class="absolute inset-0 h-full w-full object-contain" :style="resultUrl ? { clipPath: `inset(0 ${100 - comparison}% 0 0)` } : {}" />
                            <img v-if="resultUrl" :src="resultUrl" alt="Immagine senza sfondo" class="absolute inset-0 h-full w-full object-contain" :style="{ clipPath: `inset(0 0 0 ${comparison}%)` }" />
                            <div v-if="resultUrl" class="pointer-events-none absolute inset-y-0 w-px bg-white shadow-md" :style="{ left: `${comparison}%` }"></div>
                        </div>
                        <label v-if="resultUrl" class="flex items-center gap-3 text-sm text-gray-600"><span>Prima</span><input v-model.number="comparison" type="range" min="0" max="100" class="min-w-0 flex-1" aria-label="Confronta prima e dopo" /><span>Dopo</span></label>
                    </div>
                </section>
                <aside class="surface flex min-h-[480px] flex-col p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">Risultato</h3>
                    <div class="mt-5 flex-1 text-sm text-gray-600">
                        <p v-if="resultUrl" class="text-green-700">PNG trasparente pronto. Controlla il risultato nell'anteprima.</p>
                        <p v-else-if="processing" role="status" class="flex items-center gap-2"><LoaderCircle class="h-4 w-4 shrink-0 animate-spin" />{{ progressText }}</p>
                        <p v-else>Seleziona un'immagine e avvia la rimozione dello sfondo.</p>
                    </div>
                    <p v-if="error" role="alert" class="mb-3 text-sm text-red-600">{{ error }}</p>
                    <button v-if="resultUrl" type="button" class="btn btn-primary flex w-full items-center justify-center gap-2" @click="download"><Download class="h-4 w-4" />Scarica PNG</button>
                    <button v-else type="button" class="btn btn-primary flex w-full items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!file || processing" @click="process"><LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />Rimuovi sfondo</button>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.checkerboard { background-image: linear-gradient(45deg, #e5e7eb 25%, transparent 25%), linear-gradient(-45deg, #e5e7eb 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e5e7eb 75%), linear-gradient(-45deg, transparent 75%, #e5e7eb 75%); background-size: 24px 24px; background-position: 0 0, 0 12px, 12px -12px, -12px 0; }
</style>
