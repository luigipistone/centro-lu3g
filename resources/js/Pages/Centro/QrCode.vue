<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, ImagePlus, QrCode as QrCodeIcon, X } from '@lucide/vue';
import QRCodeStyling from 'qr-code-styling';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

const kinds = [
    ['url', 'Link'], ['text', 'Testo'], ['email', 'Email'], ['phone', 'Telefono'],
    ['sms', 'SMS'], ['whatsapp', 'WhatsApp'], ['wifi', 'Wi-Fi'], ['vcard', 'Contatto'], ['event', 'Evento'],
];
const kind = ref('url');
const fields = reactive({ value: '', subject: '', message: '', wifiPassword: '', wifiSecurity: 'WPA', firstName: '', lastName: '', organization: '', phone: '', email: '', website: '', eventEnd: '', eventLocation: '' });
const design = reactive({ foreground: '#172033', background: '#ffffff', shape: 'square', frame: 'none', frameText: 'SCANSIONA QUI', frameColor: '#172033' });
const logo = ref('');
const previewUrl = ref('');
const generatedSvg = ref('');
const error = ref('');
const inputClass = 'mt-2 block w-full min-w-0 rounded-[var(--radius-sm)] border-gray-200 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]';

function escapeWifi(value) { return value.replace(/([\\;,:"])/g, '\\$1'); }
function escapeVcard(value) { return value.replace(/([\\;,\n])/g, '\\$1'); }
function escapeXml(value) { return value.replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[character]); }
function cleanPhone(value) { return value.replace(/[^\d+]/g, ''); }
function utcDate(value) { return value ? new Date(value).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '') : ''; }

const payload = computed(() => {
    const value = fields.value.trim();
    if (kind.value === 'url') return value ? (/^https?:\/\//i.test(value) ? value : `https://${value}`) : '';
    if (kind.value === 'text') return value;
    if (kind.value === 'email') {
        if (!value) return '';
        const query = new URLSearchParams();
        if (fields.subject) query.set('subject', fields.subject);
        if (fields.message) query.set('body', fields.message);
        return `mailto:${value}${query.size ? `?${query}` : ''}`;
    }
    if (kind.value === 'phone') return value ? `tel:${cleanPhone(value)}` : '';
    if (kind.value === 'sms') return value ? `sms:${cleanPhone(value)}${fields.message ? `?body=${encodeURIComponent(fields.message)}` : ''}` : '';
    if (kind.value === 'whatsapp') return value ? `https://wa.me/${value.replace(/\D/g, '')}${fields.message ? `?text=${encodeURIComponent(fields.message)}` : ''}` : '';
    if (kind.value === 'wifi') return value ? `WIFI:T:${fields.wifiSecurity};S:${escapeWifi(value)};${fields.wifiSecurity === 'nopass' ? '' : `P:${escapeWifi(fields.wifiPassword)};`};` : '';
    if (kind.value === 'vcard') {
        if (!fields.firstName.trim() && !fields.lastName.trim()) return '';
        return ['BEGIN:VCARD', 'VERSION:3.0', `N:${escapeVcard(fields.lastName)};${escapeVcard(fields.firstName)};;;`, `FN:${escapeVcard(`${fields.firstName} ${fields.lastName}`.trim())}`, fields.organization && `ORG:${escapeVcard(fields.organization)}`, fields.phone && `TEL:${cleanPhone(fields.phone)}`, fields.email && `EMAIL:${fields.email.trim()}`, fields.website && `URL:${fields.website.trim()}`, 'END:VCARD'].filter(Boolean).join('\r\n');
    }
    if (kind.value === 'event') {
        if (!value || !fields.subject || !fields.eventEnd) return '';
        const start = new Date(value);
        const end = new Date(fields.eventEnd);
        if (!Number.isFinite(start.getTime()) || !Number.isFinite(end.getTime()) || end <= start) return '';
        return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'BEGIN:VEVENT', `DTSTART:${utcDate(value)}`, `DTEND:${utcDate(fields.eventEnd)}`, `SUMMARY:${escapeVcard(fields.subject)}`, fields.eventLocation && `LOCATION:${escapeVcard(fields.eventLocation)}`, fields.message && `DESCRIPTION:${escapeVcard(fields.message)}`, 'END:VEVENT', 'END:VCALENDAR'].filter(Boolean).join('\r\n');
    }
    return '';
});

const mainLabel = computed(() => ({ url: 'Indirizzo web', text: 'Testo', email: 'Indirizzo email', phone: 'Numero di telefono', sms: 'Numero di telefono', whatsapp: 'Numero WhatsApp', wifi: 'Nome della rete', event: 'Inizio' })[kind.value]);
const mainPlaceholder = computed(() => ({ url: 'https://esempio.it', text: 'Scrivi il contenuto', email: 'nome@esempio.it', phone: '+39 012 3456789', sms: '+39 012 3456789', whatsapp: '+39 012 3456789', wifi: 'Nome rete' })[kind.value]);

function setKind(next) {
    kind.value = next;
    Object.assign(fields, { value: '', subject: '', message: '', wifiPassword: '', firstName: '', lastName: '', organization: '', phone: '', email: '', website: '', eventEnd: '', eventLocation: '' });
}

function setPreview(svg) {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    generatedSvg.value = svg;
    previewUrl.value = svg ? URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' })) : '';
}

function composeSvg(raw) {
    const document = new DOMParser().parseFromString(raw, 'image/svg+xml');
    const qr = document.documentElement;
    qr.setAttribute('x', '32');
    qr.setAttribute('y', '32');
    qr.setAttribute('width', '512');
    qr.setAttribute('height', '512');
    const framed = design.frame !== 'none';
    const label = design.frame === 'label' ? `<rect x="24" y="542" width="528" height="66" rx="8" fill="${design.frameColor}"/><text x="288" y="584" fill="#ffffff" font-family="Arial,sans-serif" font-size="25" font-weight="700" text-anchor="middle">${escapeXml(design.frameText.slice(0, 24))}</text>` : '';
    return `<svg xmlns="http://www.w3.org/2000/svg" width="576" height="${design.frame === 'label' ? 632 : 576}" viewBox="0 0 576 ${design.frame === 'label' ? 632 : 576}"><rect width="576" height="100%" fill="${design.background}"/>${framed ? `<rect x="12" y="12" width="552" height="${design.frame === 'label' ? 608 : 552}" rx="14" fill="none" stroke="${design.frameColor}" stroke-width="8"/>` : ''}${new XMLSerializer().serializeToString(qr)}${label}</svg>`;
}

let generation = 0;
watch([payload, design, logo], async () => {
    const current = ++generation;
    error.value = '';
    setPreview('');
    if (!payload.value) return;
    if (payload.value.length > 2200) { error.value = 'Il contenuto è troppo lungo per un QR leggibile.'; return; }
    if (design.foreground.toLowerCase() === design.background.toLowerCase()) { error.value = 'Scegli colori diversi per codice e sfondo.'; return; }
    try {
        const qr = new QRCodeStyling({
            width: 512, height: 512, type: 'svg', margin: 24, data: payload.value,
            qrOptions: { errorCorrectionLevel: logo.value ? 'H' : 'Q' },
            dotsOptions: { color: design.foreground, type: design.shape },
            cornersSquareOptions: { color: design.foreground, type: design.shape === 'square' ? 'square' : 'extra-rounded' },
            cornersDotOptions: { color: design.foreground, type: design.shape === 'dots' ? 'dot' : 'square' },
            backgroundOptions: { color: design.background },
            ...(logo.value ? { image: logo.value, imageOptions: { crossOrigin: 'anonymous', margin: 7, imageSize: 0.18, hideBackgroundDots: true, saveAsBlob: true } } : {}),
        });
        const blob = await qr.getRawData('svg');
        if (current === generation) setPreview(composeSvg(await blob.text()));
    } catch {
        if (current === generation) error.value = 'Impossibile generare il QR. Controlla il contenuto.';
    }
}, { deep: true, immediate: true });

function selectLogo(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
        error.value = 'Usa un logo PNG, JPG o WebP fino a 2 MB.';
        event.target.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = () => { logo.value = String(reader.result); };
    reader.readAsDataURL(file);
}

function download(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function save(format) {
    if (!generatedSvg.value) return;
    if (format === 'svg') { download(new Blob([generatedSvg.value], { type: 'image/svg+xml' }), 'codice-qr.svg'); return; }
    try {
        const image = new Image();
        image.src = previewUrl.value;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = 1152;
        canvas.height = design.frame === 'label' ? 1264 : 1152;
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => { if (blob) download(blob, 'codice-qr.png'); }, 'image/png');
    } catch { error.value = 'Impossibile scaricare il PNG. Riprova con SVG.'; }
}

onBeforeUnmount(() => { if (previewUrl.value) URL.revokeObjectURL(previewUrl.value); });
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
            <div class="mx-auto grid max-w-[1200px] items-start gap-5 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                <section class="surface min-w-0 p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">Contenuto</h3>
                    <div class="mt-5 flex flex-wrap gap-2" role="group" aria-label="Tipo di contenuto">
                        <button v-for="[id, title] in kinds" :key="id" type="button" :aria-pressed="kind === id" :class="['rounded-[var(--radius-sm)] border px-3 py-2 text-sm font-medium transition', kind === id ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50']" @click="setKind(id)">{{ title }}</button>
                    </div>
                    <label v-if="kind !== 'vcard'" class="mt-6 block text-sm font-medium text-gray-700">{{ mainLabel }}
                        <textarea v-if="kind === 'text'" v-model="fields.value" rows="5" :placeholder="mainPlaceholder" :class="inputClass" />
                        <input v-else v-model="fields.value" :type="kind === 'event' ? 'datetime-local' : kind === 'email' ? 'email' : 'text'" :placeholder="mainPlaceholder" :class="inputClass" />
                    </label>
                    <div v-if="kind === 'email' || kind === 'sms' || kind === 'whatsapp'" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label v-if="kind === 'email'" class="block text-sm font-medium text-gray-700">Oggetto<input v-model="fields.subject" type="text" :class="inputClass" /></label>
                        <label class="block text-sm font-medium text-gray-700">Messaggio<textarea v-model="fields.message" rows="3" :class="inputClass" /></label>
                    </div>
                    <div v-if="kind === 'wifi'" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Protezione<select v-model="fields.wifiSecurity" :class="inputClass"><option value="WPA">WPA/WPA2</option><option value="WEP">WEP</option><option value="nopass">Rete aperta</option></select></label>
                        <label v-if="fields.wifiSecurity !== 'nopass'" class="block text-sm font-medium text-gray-700">Password<input v-model="fields.wifiPassword" type="text" autocomplete="off" :class="inputClass" /></label>
                    </div>
                    <div v-if="kind === 'vcard'" class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label v-for="[key, title, type] in [['firstName', 'Nome', 'text'], ['lastName', 'Cognome', 'text'], ['organization', 'Azienda', 'text'], ['phone', 'Telefono', 'tel'], ['email', 'Email', 'email'], ['website', 'Sito web', 'url']]" :key="key" class="block text-sm font-medium text-gray-700">{{ title }}<input v-model="fields[key]" :type="type" :class="inputClass" /></label>
                    </div>
                    <div v-if="kind === 'event'" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Fine<input v-model="fields.eventEnd" type="datetime-local" :class="inputClass" /></label>
                        <label class="block text-sm font-medium text-gray-700">Titolo<input v-model="fields.subject" type="text" :class="inputClass" /></label>
                        <label class="block text-sm font-medium text-gray-700">Luogo<input v-model="fields.eventLocation" type="text" :class="inputClass" /></label>
                        <label class="block text-sm font-medium text-gray-700">Descrizione<input v-model="fields.message" type="text" :class="inputClass" /></label>
                    </div>

                    <div class="mt-8 border-t border-gray-100 pt-6">
                        <h3 class="text-base font-semibold text-gray-900">Aspetto</h3>
                        <label class="mt-4 block text-sm font-medium text-gray-700">Forma<select v-model="design.shape" :class="inputClass"><option value="square">Quadrata</option><option value="rounded">Arrotondata</option><option value="dots">Puntini</option></select></label>
                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <label class="block text-sm font-medium text-gray-700">Codice<input v-model="design.foreground" type="color" class="qr-color mt-2 block h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200" /></label>
                            <label class="block text-sm font-medium text-gray-700">Sfondo<input v-model="design.background" type="color" class="qr-color mt-2 block h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200" /></label>
                        </div>
                        <label class="mt-4 block text-sm font-medium text-gray-700">Cornice<select v-model="design.frame" :class="inputClass"><option value="none">Nessuna</option><option value="border">Bordo</option><option value="label">Bordo con testo</option></select></label>
                        <div v-if="design.frame !== 'none'" class="mt-4 grid gap-4 sm:grid-cols-2">
                            <label v-if="design.frame === 'label'" class="block text-sm font-medium text-gray-700">Testo cornice<input v-model="design.frameText" type="text" maxlength="24" :class="inputClass" /></label>
                            <label class="block text-sm font-medium text-gray-700">Colore cornice<input v-model="design.frameColor" type="color" class="qr-color mt-2 block h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200" /></label>
                        </div>
                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><ImagePlus class="h-4 w-4" />{{ logo ? 'Cambia logo' : 'Aggiungi logo' }}<input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="selectLogo" /></label>
                            <button v-if="logo" type="button" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-red-600" @click="logo = ''"><X class="h-4 w-4" />Rimuovi</button>
                        </div>
                    </div>
                </section>
                <section class="surface min-w-0 p-5 sm:sticky sm:top-5 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">Anteprima</h3>
                    <div class="mt-5 flex min-h-[320px] items-center justify-center rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 p-4 sm:p-6">
                        <img v-if="previewUrl" :src="previewUrl" alt="Anteprima del codice QR" class="block h-auto w-full max-w-[340px] object-contain" />
                        <div v-else class="text-center text-gray-400"><QrCodeIcon class="mx-auto h-16 w-16" :stroke-width="1.4" /><p class="mt-3 text-sm">{{ error || 'Inserisci un contenuto per generare il QR' }}</p></div>
                    </div>
                    <p v-if="error && previewUrl" class="mt-3 text-sm text-red-600" role="alert">{{ error }}</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button v-for="format in ['PNG', 'SVG']" :key="format" type="button" :disabled="!generatedSvg" class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app))] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40" @click="save(format.toLowerCase())"><Download class="h-4 w-4" />Scarica {{ format }}</button>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.qr-color { padding: 0; background: transparent; overflow: hidden; }
.qr-color::-webkit-color-swatch-wrapper { padding: 0; }
.qr-color::-webkit-color-swatch { border: 0; }
.qr-color::-moz-color-swatch { border: 0; }
</style>
