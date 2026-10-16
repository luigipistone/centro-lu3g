<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QrColorField from '@/Components/QrColorField.vue';
import ToolAccordion from '@/Components/ToolAccordion.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, ContactRound, Download, ImagePlus, Link2, Mail, MessageCircle, MessageSquare, Phone, QrCode as QrCodeIcon, Type, Wifi, X } from '@lucide/vue';
import QRCodeStyling from 'qr-code-styling';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

const kinds = [
    ['url', 'Link', Link2], ['text', 'Testo', Type], ['email', 'Email', Mail], ['phone', 'Telefono', Phone],
    ['sms', 'SMS', MessageSquare], ['whatsapp', 'WhatsApp', MessageCircle], ['wifi', 'Wi-Fi', Wifi],
    ['vcard', 'Contatto', ContactRound], ['event', 'Evento', CalendarDays],
];
const kind = ref('url');
const fields = reactive({ value: '', subject: '', message: '', wifiPassword: '', wifiSecurity: 'WPA', firstName: '', lastName: '', organization: '', phone: '', email: '', website: '', eventEnd: '', eventLocation: '' });
const design = reactive({ foreground: '#172033', background: '#ffffff', cornerColor: '#172033', centerColor: '#172033', shape: 'square', corner: 'square', center: 'square', frame: 'none', frameText: 'SCANSIONA QUI', frameColor: '#172033', gradient: false, secondColor: '#0877ed', logoSize: 0.18 });
const openSetting = ref('shape');
const shapeOptions = [['square', 'Quadrata'], ['rounded', 'Arrotondata'], ['dots', 'Puntini'], ['classy', 'Classica'], ['classy-rounded', 'Morbida'], ['extra-rounded', 'Molto tonda']];
const cornerOptions = [['square', 'Quadrati'], ['extra-rounded', 'Arrotondati'], ['dot', 'Tondi']];
const centerOptions = [['square', 'Quadrati'], ['dot', 'Tondi']];
const frameOptions = [['none', 'Nessuna'], ['border', 'Bordo'], ['label', 'Testo sotto'], ['label-top', 'Testo sopra']];
const optionPreviews = reactive({});
const logo = ref('');
const previewUrl = ref('');
const generatedSvg = ref('');
const generating = ref(false);
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

function clearPreview() {
    const previous = previewUrl.value;
    generatedSvg.value = '';
    previewUrl.value = '';
    if (previous) window.setTimeout(() => URL.revokeObjectURL(previous), 1000);
}

async function showPreview(svg, current) {
    const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        if (current !== generation) { URL.revokeObjectURL(url); return; }
        const previous = previewUrl.value;
        generatedSvg.value = svg;
        previewUrl.value = url;
        generating.value = false;
        if (previous) window.setTimeout(() => URL.revokeObjectURL(previous), 1000);
    } catch {
        URL.revokeObjectURL(url);
        throw new Error('preview');
    }
}

function composeSvg(raw, options = design) {
    const document = new DOMParser().parseFromString(raw, 'image/svg+xml');
    const qr = document.documentElement;
    qr.setAttribute('x', '32');
    qr.setAttribute('y', options.frame === 'label-top' ? '96' : '32');
    qr.setAttribute('width', '512');
    qr.setAttribute('height', '512');
    const labeled = options.frame === 'label' || options.frame === 'label-top';
    const height = labeled ? 632 : 576;
    const labelY = options.frame === 'label-top' ? 24 : 542;
    const label = labeled ? `<rect x="24" y="${labelY}" width="528" height="66" rx="8" fill="${options.frameColor}"/><text x="288" y="${labelY + 42}" fill="${contrastText(options.frameColor)}" font-family="Arial,sans-serif" font-size="25" font-weight="700" text-anchor="middle">${escapeXml(options.frameText.slice(0, 24))}</text>` : '';
    return `<svg xmlns="http://www.w3.org/2000/svg" width="576" height="${height}" viewBox="0 0 576 ${height}"><rect width="576" height="100%" fill="${options.background}"/>${options.frame !== 'none' ? `<rect x="12" y="12" width="552" height="${height - 24}" rx="14" fill="none" stroke="${options.frameColor}" stroke-width="8"/>` : ''}${new XMLSerializer().serializeToString(qr)}${label}</svg>`;
}

function contrastText(hex) {
    const red = parseInt(hex.slice(1, 3), 16);
    const green = parseInt(hex.slice(3, 5), 16);
    const blue = parseInt(hex.slice(5, 7), 16);
    return (red * 299 + green * 587 + blue * 114) / 1000 > 165 ? '#172033' : '#ffffff';
}

async function createQrSvg(data, options, image = '') {
    const qr = new QRCodeStyling({
        width: 512, height: 512, type: 'svg', margin: 24, data,
        qrOptions: { errorCorrectionLevel: image ? 'H' : 'Q' },
        dotsOptions: { color: options.foreground, type: options.shape, ...(options.gradient ? { gradient: { type: 'linear', rotation: 0.8, colorStops: [{ offset: 0, color: options.foreground }, { offset: 1, color: options.secondColor }] } } : {}) },
        cornersSquareOptions: { color: options.cornerColor, type: options.corner },
        cornersDotOptions: { color: options.centerColor, type: options.center },
        backgroundOptions: { color: options.background },
        ...(image ? { image, imageOptions: { crossOrigin: 'anonymous', margin: 7, imageSize: Number(options.logoSize), hideBackgroundDots: true, saveAsBlob: true } } : {}),
    });
    const blob = await qr.getRawData('svg');
    return composeSvg(await blob.text(), options);
}

async function buildOptionPreviews() {
    const samples = [
        ...shapeOptions.map(([id]) => [`shape:${id}`, { shape: id }]),
        ...cornerOptions.map(([id]) => [`corner:${id}`, { corner: id }]),
        ...centerOptions.map(([id]) => [`center:${id}`, { center: id }]),
        ...frameOptions.map(([id]) => [`frame:${id}`, { frame: id }]),
    ];
    for (const [key, override] of samples) {
        try {
            const options = { ...design, foreground: '#172033', background: '#ffffff', cornerColor: '#172033', centerColor: '#172033', gradient: false, ...override };
            const svg = await createQrSvg('https://centro.lu3g.com', options);
            optionPreviews[key] = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
        } catch { /* A label remains available if a miniature cannot render. */ }
    }
}

buildOptionPreviews();

let generation = 0;
watch([payload, design, logo], async () => {
    const current = ++generation;
    error.value = '';
    generating.value = Boolean(payload.value);
    if (!payload.value) { clearPreview(); return; }
    if (payload.value.length > 2200) { clearPreview(); generating.value = false; error.value = 'Il contenuto è troppo lungo per un QR leggibile.'; return; }
    if (design.foreground.toLowerCase() === design.background.toLowerCase()) { clearPreview(); generating.value = false; error.value = 'Scegli colori diversi per codice e sfondo.'; return; }
    try {
        const svg = await createQrSvg(payload.value, { ...design }, logo.value);
        if (current === generation) await showPreview(svg, current);
    } catch {
        if (current === generation) { clearPreview(); generating.value = false; error.value = 'Impossibile generare il QR. Controlla il contenuto.'; }
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
    if (!generatedSvg.value || generating.value) return;
    if (format === 'svg') { download(new Blob([generatedSvg.value], { type: 'image/svg+xml' }), 'codice-qr.svg'); return; }
    try {
        const image = new Image();
        image.src = previewUrl.value;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = 1152;
        canvas.height = ['label', 'label-top'].includes(design.frame) ? 1264 : 1152;
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
            <div class="mx-auto grid max-w-[1600px] items-stretch gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:px-8">
                <div class="min-w-0 space-y-5">
                <section class="surface min-w-0 p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">Contenuto</h3>
                    <div class="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-5" role="group" aria-label="Tipo di contenuto">
                        <button v-for="[id, title, icon] in kinds" :key="id" type="button" :aria-pressed="kind === id" :class="['flex min-h-[74px] min-w-0 flex-col items-center justify-center gap-1.5 rounded-[var(--radius-sm)] border px-1.5 py-2 text-center text-xs font-semibold transition', kind === id ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50']" @click="setKind(id)"><component :is="icon" class="h-5 w-5 shrink-0" :stroke-width="1.8" /><span class="max-w-full break-words">{{ title }}</span></button>
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
                </section>
                <section class="surface min-w-0 p-5 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">Anteprima</h3>
                    <div class="mt-5 flex min-h-[320px] items-center justify-center rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 p-4 sm:p-6">
                        <img v-if="previewUrl" :src="previewUrl" alt="Anteprima del codice QR" class="block h-auto w-full max-w-[420px] object-contain" />
                        <div v-else class="text-center text-gray-400"><QrCodeIcon class="mx-auto h-16 w-16" :stroke-width="1.4" /><p class="mt-3 text-sm">{{ error || 'Inserisci un contenuto per generare il QR' }}</p></div>
                    </div>
                    <p v-if="error && previewUrl" class="mt-3 text-sm text-red-600" role="alert">{{ error }}</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button v-for="format in ['PNG', 'SVG']" :key="format" type="button" :disabled="!generatedSvg || generating" class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app))] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40" @click="save(format.toLowerCase())"><Download class="h-4 w-4" />Scarica {{ format }}</button>
                    </div>
                </section>
                </div>
                <aside class="surface min-w-0 self-start overflow-hidden lg:sticky lg:top-5">
                    <div class="divide-y divide-gray-100">
                        <div class="px-4 py-4"><h3 class="text-base font-semibold text-gray-900">Aspetto</h3></div>
                        <ToolAccordion title="Forma" :summary="shapeOptions.find(([id]) => id === design.shape)?.[1]" panel-id="qr-shape" :open="openSetting === 'shape'" @toggle="openSetting = openSetting === 'shape' ? '' : 'shape'">
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800">Moduli</h4>
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    <button v-for="[id, title] in shapeOptions" :key="id" type="button" :aria-pressed="design.shape === id" :class="['qr-option', design.shape === id ? 'qr-option-active' : '']" @click="design.shape = id"><img v-if="optionPreviews[`shape:${id}`]" :src="optionPreviews[`shape:${id}`]" alt="" class="qr-option-image" /><QrCodeIcon v-else class="qr-option-placeholder" /><span>{{ title }}</span></button>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800">Angoli</h4>
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    <button v-for="[id, title] in cornerOptions" :key="id" type="button" :aria-pressed="design.corner === id" :class="['qr-option', design.corner === id ? 'qr-option-active' : '']" @click="design.corner = id"><img v-if="optionPreviews[`corner:${id}`]" :src="optionPreviews[`corner:${id}`]" alt="" class="qr-option-image" /><QrCodeIcon v-else class="qr-option-placeholder" /><span>{{ title }}</span></button>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800">Centro degli angoli</h4>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button v-for="[id, title] in centerOptions" :key="id" type="button" :aria-pressed="design.center === id" :class="['qr-option', design.center === id ? 'qr-option-active' : '']" @click="design.center = id"><img v-if="optionPreviews[`center:${id}`]" :src="optionPreviews[`center:${id}`]" alt="" class="qr-option-image" /><QrCodeIcon v-else class="qr-option-placeholder" /><span>{{ title }}</span></button>
                                </div>
                            </div>
                        </div>
                        </ToolAccordion>
                        <ToolAccordion title="Colori" :summary="design.gradient ? 'Sfumatura' : 'Colori pieni'" panel-id="qr-colors" :open="openSetting === 'colors'" @toggle="openSetting = openSetting === 'colors' ? '' : 'colors'">
                            <div>
                                <div class="mt-3 grid grid-cols-2 gap-4">
                                    <QrColorField v-model="design.foreground" label="Codice" />
                                    <QrColorField v-model="design.background" label="Sfondo" />
                                    <QrColorField v-model="design.cornerColor" label="Angoli" />
                                    <QrColorField v-model="design.centerColor" label="Centro angoli" />
                                </div>
                                <label class="mt-4 flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700"><input v-model="design.gradient" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app))]" />Sfumatura sul codice</label>
                                <QrColorField v-if="design.gradient" v-model="design.secondColor" label="Secondo colore" class="mt-3" />
                            </div>
                        </ToolAccordion>

                        <ToolAccordion title="Cornice" :summary="frameOptions.find(([id]) => id === design.frame)?.[1]" panel-id="qr-frame" :open="openSetting === 'frame'" @toggle="openSetting = openSetting === 'frame' ? '' : 'frame'">
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="[id, title] in frameOptions" :key="id" type="button" :aria-pressed="design.frame === id" :class="['qr-option', design.frame === id ? 'qr-option-active' : '']" @click="design.frame = id"><img v-if="optionPreviews[`frame:${id}`]" :src="optionPreviews[`frame:${id}`]" alt="" class="qr-option-image" /><QrCodeIcon v-else class="qr-option-placeholder" /><span>{{ title }}</span></button>
                            </div>
                            <div v-if="design.frame !== 'none'" class="mt-5 grid gap-4 sm:grid-cols-2">
                                <label v-if="design.frame === 'label' || design.frame === 'label-top'" class="block text-sm font-medium text-gray-700">Testo cornice<input v-model="design.frameText" type="text" maxlength="24" :class="inputClass" /></label>
                                <QrColorField v-model="design.frameColor" label="Colore cornice" />
                            </div>
                        </ToolAccordion>

                        <ToolAccordion title="Logo" :summary="logo ? 'Caricato' : 'Nessuno'" panel-id="qr-logo" :open="openSetting === 'logo'" @toggle="openSetting = openSetting === 'logo' ? '' : 'logo'">
                            <div class="flex flex-wrap items-center gap-3">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><ImagePlus class="h-4 w-4" />{{ logo ? 'Cambia logo' : 'Carica logo' }}<input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="selectLogo" /></label>
                                <button v-if="logo" type="button" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-red-600" @click="logo = ''"><X class="h-4 w-4" />Rimuovi</button>
                            </div>
                            <div v-if="logo" class="mt-5 flex items-center gap-4 rounded-[var(--radius-sm)] border border-gray-200 p-3">
                                <img :src="logo" alt="Logo selezionato" class="h-12 w-12 shrink-0 object-contain" />
                                <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Dimensione<input v-model.number="design.logoSize" type="range" min="0.1" max="0.25" step="0.01" class="mt-2 w-full accent-[hsl(var(--primary-app))]" /></label>
                            </div>
                        </ToolAccordion>
                    </div>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.qr-option { display: flex; min-width: 0; flex-direction: column; align-items: center; gap: 0.4rem; border: 1px solid #e5e7eb; border-radius: var(--radius-sm); padding: 0.55rem; background: #fff; color: #4b5563; font-size: 0.75rem; font-weight: 600; text-align: center; transition: border-color 0.15s, background-color 0.15s; }
.qr-option:hover { background: #f9fafb; }
.qr-option-active { border-color: hsl(var(--primary-app)); background: hsl(var(--primary-app) / 0.07); color: hsl(var(--primary-app)); }
.qr-option-image { width: 100%; max-width: 72px; height: 72px; object-fit: contain; }
.qr-option-placeholder { width: 48px; height: 72px; color: #9ca3af; }
</style>
