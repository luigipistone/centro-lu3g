<script setup>
import ToolDropzone from '@/Components/ToolDropzone.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Film, X } from '@lucide/vue';
import axios from 'axios';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ available: { type: Boolean, required: true } });

const input = ref(null);
const file = ref(null);
const quality = ref('balanced');
const status = ref('idle');
const conversionId = ref(null);
const error = ref('');
let timer = null;

const choices = [
    { value: 'small', title: 'File più leggero', detail: 'Dimensioni ridotte' },
    { value: 'balanced', title: 'Bilanciata', detail: 'Qualità e peso equilibrati' },
    { value: 'high', title: 'Qualità alta', detail: 'Più dettagli, file più grande' },
];

function stopPolling() {
    if (timer) clearTimeout(timer);
    timer = null;
}
onBeforeUnmount(stopPolling);
onMounted(() => {
    const previous = sessionStorage.getItem('centro-video-conversion-id');
    if (previous) {
        conversionId.value = previous;
        status.value = 'queued';
        poll();
    }
});

function choose(selected) {
    const candidate = Array.from(selected || [])[0];
    if (!candidate) return;
    error.value = '';
    if (!/\.(mov|mp4)$/i.test(candidate.name)) { error.value = 'Seleziona un file MOV o MP4.'; return; }
    if (candidate.size > 100 * 1024 * 1024) { error.value = 'Il file supera il limite di 100 MB.'; return; }
    if (!candidate.size) { error.value = 'Il file è vuoto.'; return; }
    stopPolling();
    file.value = candidate;
    status.value = 'idle';
    conversionId.value = null;
    sessionStorage.removeItem('centro-video-conversion-id');
}

function onInput(event) {
    choose(event.target.files);
    event.target.value = '';
}

function reset() {
    stopPolling();
    file.value = null;
    conversionId.value = null;
    sessionStorage.removeItem('centro-video-conversion-id');
    status.value = 'idle';
    error.value = '';
}

async function poll() {
    if (!conversionId.value) return;
    try {
        const { data } = await axios.get(route('tools.video-to-webm.status', conversionId.value));
        status.value = data.status;
        if (data.status === 'failed') error.value = data.error || 'Conversione non riuscita.';
        if (data.status === 'expired') error.value = 'Il file è scaduto. Caricalo di nuovo.';
        if (data.status === 'expired') sessionStorage.removeItem('centro-video-conversion-id');
        if (['queued', 'processing'].includes(data.status)) timer = setTimeout(poll, 3000);
    } catch (cause) {
        if (cause.response?.status === 404) {
            sessionStorage.removeItem('centro-video-conversion-id');
            conversionId.value = null;
            status.value = 'expired';
            error.value = 'Il file non è più disponibile.';
            return;
        }
        error.value = 'Impossibile aggiornare lo stato. Riprova tra poco.';
        timer = setTimeout(poll, 5000);
    }
}

async function convert() {
    if (!file.value || ['uploading', 'queued', 'processing'].includes(status.value)) return;
    error.value = '';
    status.value = 'uploading';
    const body = new FormData();
    body.append('video', file.value);
    body.append('quality', quality.value);
    try {
        const { data } = await axios.post(route('tools.video-to-webm.store'), body);
        conversionId.value = data.id;
        sessionStorage.setItem('centro-video-conversion-id', data.id);
        status.value = 'queued';
        poll();
    } catch (cause) {
        status.value = 'failed';
        error.value = cause.response?.data?.errors?.video?.[0] || cause.response?.data?.message || 'Caricamento non riuscito.';
    }
}
</script>

<template>
    <Head title="Video in WebM" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Video in WebM</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <div class="grid items-stretch gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)]">
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <div class="mb-4 flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Film class="h-5 w-5" :stroke-width="1.7" /></span><h3 class="text-base font-semibold text-gray-900">Video</h3></div>
                        <ToolDropzone class="min-h-[250px]" label="Trascina qui un MOV o MP4 oppure selezionalo" hint="Un file alla volta, fino a 100 MB" aria-label="Seleziona o trascina un video MOV o MP4" :disabled="!available || ['uploading', 'queued', 'processing'].includes(status)" @select="input?.click()" @drop="choose($event.dataTransfer?.files)" />
                        <input ref="input" type="file" accept=".mov,.mp4,video/quicktime,video/mp4" class="hidden" @change="onInput" />
                        <div v-if="file" class="mt-4 flex min-w-0 items-center gap-3 rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 px-4 py-3">
                            <Film class="h-5 w-5 shrink-0 text-gray-500" /><span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800" :title="file.name">{{ file.name }}</span><span class="shrink-0 text-xs text-gray-500">{{ (file.size / 1024 / 1024).toFixed(1) }} MB</span><button type="button" class="icon-btn h-8 w-8 shrink-0" title="Rimuovi video" :disabled="['uploading', 'queued', 'processing'].includes(status)" @click="reset"><X class="h-4 w-4" /></button>
                        </div>
                    </section>
                    <section class="surface flex flex-col p-5 sm:p-6">
                        <h3 class="mb-4 text-base font-semibold text-gray-900">Qualità</h3>
                        <fieldset class="space-y-2" :disabled="['uploading', 'queued', 'processing'].includes(status)">
                            <label v-for="choice in choices" :key="choice.value" class="flex cursor-pointer items-center gap-3 rounded-[var(--radius-sm)] border px-3 py-3 transition" :class="quality === choice.value ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.04)]' : 'border-gray-200 hover:border-gray-300'"><input v-model="quality" type="radio" name="quality" :value="choice.value" class="text-[hsl(var(--primary-app))]" /><span class="min-w-0"><span class="block text-sm font-semibold text-gray-800">{{ choice.title }}</span><span class="block text-xs text-gray-500">{{ choice.detail }}</span></span></label>
                        </fieldset>
                        <button type="button" class="btn btn-primary mt-5 w-full justify-center" :disabled="!available || !file || ['uploading', 'queued', 'processing'].includes(status)" @click="convert">{{ status === 'uploading' ? 'Caricamento...' : status === 'queued' ? 'In attesa...' : status === 'processing' ? 'Conversione in corso...' : 'Converti in WebM' }}</button>
                        <p v-if="!available" role="alert" class="mt-3 text-sm text-amber-700">La conversione video non è ancora disponibile su questo server.</p>
                        <p v-if="status === 'queued' || status === 'processing'" role="status" class="mt-3 text-sm text-gray-600">{{ status === 'queued' ? 'Video in attesa di conversione.' : 'Il video è in conversione. Puoi lasciare aperta questa pagina.' }}</p>
                        <a v-if="status === 'done' && conversionId" :href="route('tools.video-to-webm.download', conversionId)" class="btn btn-primary mt-3 w-full justify-center"><Download class="h-4 w-4" /> Scarica WebM</a>
                        <p v-if="status === 'done'" class="mt-2 text-xs text-gray-500">Il file resta disponibile per due ore dal caricamento.</p>
                        <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
