<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, QrCode as QrCodeIcon } from '@lucide/vue';
import QRCode from 'qrcode';
import { computed, ref, watch } from 'vue';

const kind = ref('url');
const value = ref('');
const emailSubject = ref('');
const emailBody = ref('');
const wifiPassword = ref('');
const wifiSecurity = ref('WPA');
const foreground = ref('#172033');
const background = ref('#ffffff');
const svg = ref('');
const error = ref('');
const kinds = [
    { value: 'url', label: 'Link' },
    { value: 'text', label: 'Testo' },
    { value: 'email', label: 'Email' },
    { value: 'phone', label: 'Telefono' },
    { value: 'wifi', label: 'Wi-Fi' },
];
const label = computed(() => ({ url: 'Indirizzo web', text: 'Testo', email: 'Indirizzo email', phone: 'Numero di telefono', wifi: 'Nome della rete' })[kind.value]);
const placeholder = computed(() => ({ url: 'https://esempio.it', text: 'Scrivi il contenuto del QR', email: 'nome@esempio.it', phone: '+39 012 3456789', wifi: 'Nome rete' })[kind.value]);

function escapeWifi(text) {
    return text.replace(/([\\;,:"])/g, '\\$1');
}

const payload = computed(() => {
    const input = value.value.trim();
    if (!input) return '';
    if (kind.value === 'url') return /^https?:\/\//i.test(input) ? input : `https://${input}`;
    if (kind.value === 'email') {
        const query = new URLSearchParams();
        if (emailSubject.value) query.set('subject', emailSubject.value);
        if (emailBody.value) query.set('body', emailBody.value);
        return `mailto:${input}${query.size ? `?${query}` : ''}`;
    }
    if (kind.value === 'phone') return `tel:${input.replace(/\s/g, '')}`;
    if (kind.value === 'wifi') return `WIFI:T:${wifiSecurity.value};S:${escapeWifi(input)};P:${escapeWifi(wifiPassword.value)};;`;
    return input;
});

let generation = 0;
watch([payload, foreground, background], async () => {
    const current = ++generation;
    svg.value = '';
    error.value = '';
    if (!payload.value) return;
    if (payload.value.length > 2500) {
        error.value = 'Il contenuto è troppo lungo per un QR leggibile.';
        return;
    }
    try {
        const result = await QRCode.toString(payload.value, { type: 'svg', width: 512, margin: 4, errorCorrectionLevel: 'M', color: { dark: foreground.value, light: background.value } });
        if (current === generation) svg.value = result;
    } catch {
        if (current === generation) error.value = 'Impossibile generare il QR. Controlla il contenuto.';
    }
}, { immediate: true });

function download(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function save(format) {
    if (!svg.value) return;
    if (format === 'svg') {
        download(new Blob([svg.value], { type: 'image/svg+xml' }), 'codice-qr.svg');
        return;
    }
    const png = await QRCode.toDataURL(payload.value, { width: 1024, margin: 4, errorCorrectionLevel: 'M', color: { dark: foreground.value, light: background.value } });
    const response = await fetch(png);
    download(await response.blob(), 'codice-qr.png');
}
</script>

<template>
    <Head title="Crea QR Code" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.8" />Torna a Strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Crea QR Code</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto grid max-w-[1200px] items-stretch gap-5 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                <section class="surface p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">Contenuto</h3>
                    <div class="mt-5 flex flex-wrap gap-2" role="group" aria-label="Tipo di contenuto">
                        <button v-for="option in kinds" :key="option.value" type="button" :aria-pressed="kind === option.value" :class="['rounded-[var(--radius-sm)] border px-3 py-2 text-sm font-medium transition', kind === option.value ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50']" @click="kind = option.value; value = ''">{{ option.label }}</button>
                    </div>
                    <label class="mt-6 block text-sm font-medium text-gray-700">{{ label }}
                        <textarea v-if="kind === 'text'" v-model="value" rows="5" :placeholder="placeholder" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                        <input v-else v-model="value" :type="kind === 'email' ? 'email' : kind === 'url' ? 'url' : 'text'" :placeholder="placeholder" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                    </label>
                    <div v-if="kind === 'email'" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Oggetto<input v-model="emailSubject" type="text" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label>
                        <label class="block text-sm font-medium text-gray-700">Messaggio<input v-model="emailBody" type="text" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label>
                    </div>
                    <div v-if="kind === 'wifi'" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Protezione<select v-model="wifiSecurity" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm"><option value="WPA">WPA/WPA2</option><option value="WEP">WEP</option><option value="nopass">Rete aperta</option></select></label>
                        <label v-if="wifiSecurity !== 'nopass'" class="block text-sm font-medium text-gray-700">Password<input v-model="wifiPassword" type="text" autocomplete="off" class="mt-2 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm" /></label>
                    </div>
                    <h3 class="mt-8 text-base font-semibold text-gray-900">Colori</h3>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <label class="block text-sm font-medium text-gray-700">Codice<input v-model="foreground" type="color" class="mt-2 h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200 bg-white p-1" /></label>
                        <label class="block text-sm font-medium text-gray-700">Sfondo<input v-model="background" type="color" class="mt-2 h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200 bg-white p-1" /></label>
                    </div>
                </section>
                <section class="surface flex flex-col p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">Anteprima</h3>
                    <div class="mt-5 flex min-h-[320px] flex-1 items-center justify-center rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 p-6">
                        <div v-if="svg" class="w-full max-w-[280px] overflow-hidden rounded-[var(--radius-sm)] bg-white" aria-label="Anteprima del codice QR" v-html="svg" />
                        <div v-else class="text-center text-gray-400"><QrCodeIcon class="mx-auto h-16 w-16" :stroke-width="1.4" /><p class="mt-3 text-sm">{{ error || 'Inserisci un contenuto per generare il QR' }}</p></div>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button v-for="format in ['PNG', 'SVG']" :key="format" type="button" :disabled="!svg" class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app))] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40" @click="save(format.toLowerCase())"><Download class="h-4 w-4" />Scarica {{ format }}</button>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
