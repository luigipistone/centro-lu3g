<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Palette, Trash2 } from '@lucide/vue';
import { computed, onMounted, reactive, ref } from 'vue';
import { hslToRgb, hsvToRgb, parseHex, parseHsl, parseRgb, rgbToHex, rgbToHsl, rgbToHsv } from '@/utils/colorConverter';

const page = usePage();
const storageKey = `centro-color-history-${page.props.auth?.user?.id || 'user'}`;
const color = ref({ r: 31, g: 99, b: 214 });
const picker = reactive(rgbToHsv(color.value));
const pickerArea = ref(null);
let activePointerId = null;
const hexInput = ref(rgbToHex(color.value));
const rgbInput = reactive({ r: String(color.value.r), g: String(color.value.g), b: String(color.value.b) });
const hslInput = reactive(rgbToHsl(color.value));
const history = ref([]);
const error = ref('');
const copied = ref('');
let copiedTimer;

const hex = computed(() => rgbToHex(color.value));
const pickerHue = computed(() => rgbToHex(hsvToRgb({ h: picker.h, s: 100, v: 100 })));
const rgbText = computed(() => `rgb(${color.value.r}, ${color.value.g}, ${color.value.b})`);
const hslText = computed(() => {
    const { h, s, l } = rgbToHsl(color.value);
    return `hsl(${h}, ${s}%, ${l}%)`;
});
const inputClass = 'mt-2 block w-full min-w-0 rounded-[var(--radius-sm)] border-gray-200 bg-white text-sm text-gray-900 focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]';

function syncInputs(source) {
    if (source !== 'picker') {
        const next = rgbToHsv(color.value);
        if (next.s > 0) picker.h = next.h;
        picker.s = next.s;
        picker.v = next.v;
    }
    if (source !== 'hex') hexInput.value = hex.value;
    if (source !== 'rgb') Object.assign(rgbInput, color.value);
    if (source !== 'hsl') Object.assign(hslInput, rgbToHsl(color.value));
    error.value = '';
    copied.value = '';
}

function updateHex() {
    const parsed = parseHex(hexInput.value);
    if (!parsed) { error.value = 'Inserisci un HEX di 3 o 6 caratteri.'; return; }
    color.value = parsed;
    syncInputs('hex');
}

function normalizeHexInput() {
    if (!error.value) hexInput.value = hex.value;
}

function updateRgb() {
    const parsed = parseRgb(rgbInput);
    if (!parsed) { error.value = 'I valori RGB devono essere numeri interi da 0 a 255.'; return; }
    color.value = parsed;
    syncInputs('rgb');
}

function updateHsl() {
    const parsed = parseHsl(hslInput);
    if (!parsed) { error.value = 'Usa tonalità 0–360 e saturazione/luminosità 0–100.'; return; }
    color.value = hslToRgb(parsed);
    syncInputs('hsl');
}

function setColor(value) {
    const parsed = parseHex(value);
    if (!parsed) return;
    color.value = parsed;
    syncInputs();
}

function updateFromPicker() {
    color.value = hsvToRgb(picker);
    syncInputs('picker');
}

function updatePickerHue(event) {
    picker.h = Number(event.target.value);
    updateFromPicker();
}

function setPickerPosition(event) {
    if (!pickerArea.value) return;
    const bounds = pickerArea.value.getBoundingClientRect();
    picker.s = Math.max(0, Math.min(100, ((event.clientX - bounds.left) / bounds.width) * 100));
    picker.v = 100 - Math.max(0, Math.min(100, ((event.clientY - bounds.top) / bounds.height) * 100));
    updateFromPicker();
}

function startPickerDrag(event) {
    if (event.button !== 0 || activePointerId !== null) return;
    activePointerId = event.pointerId;
    pickerArea.value.setPointerCapture(event.pointerId);
    setPickerPosition(event);
}

function movePickerDrag(event) {
    if (event.pointerId === activePointerId) setPickerPosition(event);
}

function stopPickerDrag(event) {
    if (event.pointerId !== activePointerId) return;
    if (pickerArea.value?.hasPointerCapture(event.pointerId)) pickerArea.value.releasePointerCapture(event.pointerId);
    activePointerId = null;
}

function handlePickerKey(event) {
    const step = event.shiftKey ? 10 : 1;
    if (event.key === 'ArrowLeft') picker.s = Math.max(0, picker.s - step);
    else if (event.key === 'ArrowRight') picker.s = Math.min(100, picker.s + step);
    else if (event.key === 'ArrowUp') picker.v = Math.min(100, picker.v + step);
    else if (event.key === 'ArrowDown') picker.v = Math.max(0, picker.v - step);
    else return;
    event.preventDefault();
    updateFromPicker();
}

function rememberColor(value) {
    history.value = [value, ...history.value.filter((item) => item !== value)].slice(0, 8);
    try { localStorage.setItem(storageKey, JSON.stringify(history.value)); } catch { /* Private browsing may disable storage. */ }
}

async function copyValue(kind) {
    if (error.value) return;
    const value = kind === 'rgb' ? rgbText.value : kind === 'hsl' ? hslText.value : hex.value;
    try {
        await navigator.clipboard.writeText(value);
        rememberColor(hex.value);
        copied.value = kind;
        window.clearTimeout(copiedTimer);
        copiedTimer = window.setTimeout(() => { copied.value = ''; }, 2200);
    } catch {
        error.value = 'Copia non disponibile. Seleziona il valore per copiarlo.';
    }
}

function clearHistory() {
    history.value = [];
    try { localStorage.removeItem(storageKey); } catch { /* Private browsing may disable storage. */ }
}

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
        if (Array.isArray(saved)) history.value = [...new Set(saved.filter((item) => typeof item === 'string' && parseHex(item)).map((item) => rgbToHex(parseHex(item))))].slice(0, 8);
    } catch { history.value = []; }
});
</script>

<template>
    <Head title="Converti colori" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.8" />Torna a Strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Converti colori</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1200px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section class="surface p-5 sm:p-6">
                    <div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Palette class="h-6 w-6" :stroke-width="1.7" /></span><h3 class="text-base font-semibold text-gray-900">Colore</h3></div>
                    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(240px,0.8fr)_minmax(0,1.2fr)]">
                        <div class="min-w-0">
                            <div ref="pickerArea" role="group" tabindex="0" aria-label="Saturazione e luminosità. Usa le frecce per regolarle." class="relative h-44 cursor-crosshair touch-none overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--primary-app))] sm:h-52" :style="{ backgroundColor: pickerHue }" @pointerdown="startPickerDrag" @pointermove="movePickerDrag" @pointerup="stopPickerDrag" @pointercancel="stopPickerDrag" @keydown="handlePickerKey">
                                <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,#fff,transparent)]" />
                                <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_top,#000,transparent)]" />
                                <span class="pointer-events-none absolute h-5 w-5 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(0,0,0,0.55),0_1px_4px_rgba(0,0,0,0.35)]" :style="{ left: `${picker.s}%`, top: `${100 - picker.v}%`, transform: 'translate(-50%, -50%)' }" />
                            </div>
                            <input :value="picker.h" type="range" min="0" max="359" step="1" class="color-hue-slider mt-4 w-full cursor-pointer" aria-label="Tonalità" title="Tonalità" @input="updatePickerHue" />
                            <div class="mt-3 flex items-center gap-3"><span class="h-9 w-9 shrink-0 rounded-[var(--radius-sm)] border border-gray-200" :style="{ backgroundColor: hex }" /><span class="text-sm font-medium text-gray-700">{{ hex }}</span></div>
                        </div>
                        <div class="min-w-0 space-y-5">
                            <div class="grid gap-3 sm:grid-cols-[80px_minmax(0,1fr)_44px] sm:items-end">
                                <label for="color-hex" class="text-sm font-semibold text-gray-800 sm:pb-3">HEX</label>
                                <input id="color-hex" v-model="hexInput" type="text" maxlength="7" spellcheck="false" placeholder="#1F63D6" :class="inputClass" class="!mt-0" @input="updateHex" @blur="normalizeHexInput" />
                                <button type="button" class="icon-btn h-10 w-10" :disabled="!!error" :title="copied === 'hex' ? 'Copiato' : 'Copia HEX'" aria-label="Copia HEX" @click="copyValue('hex')"><Check v-if="copied === 'hex'" class="h-4 w-4" /><Copy v-else class="h-4 w-4" /></button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-[80px_minmax(0,1fr)_44px] sm:items-end">
                                <span class="text-sm font-semibold text-gray-800 sm:pb-3">RGB</span>
                                <div class="grid grid-cols-3 gap-2">
                                    <label v-for="channel in ['r', 'g', 'b']" :key="channel" class="min-w-0"><span class="block text-xs font-medium uppercase text-gray-500">{{ channel }}</span><input v-model="rgbInput[channel]" type="number" min="0" max="255" step="1" :aria-label="channel.toUpperCase()" :class="inputClass" class="!mt-1" @input="updateRgb" /></label>
                                </div>
                                <button type="button" class="icon-btn h-10 w-10" :disabled="!!error" :title="copied === 'rgb' ? 'Copiato' : 'Copia RGB'" aria-label="Copia RGB" @click="copyValue('rgb')"><Check v-if="copied === 'rgb'" class="h-4 w-4" /><Copy v-else class="h-4 w-4" /></button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-[80px_minmax(0,1fr)_44px] sm:items-end">
                                <span class="text-sm font-semibold text-gray-800 sm:pb-3">HSL</span>
                                <div class="grid grid-cols-3 gap-2">
                                    <label v-for="channel in ['h', 's', 'l']" :key="channel" class="min-w-0"><span class="block text-xs font-medium uppercase text-gray-500">{{ channel }}</span><input v-model="hslInput[channel]" type="number" :min="0" :max="channel === 'h' ? 360 : 100" step="any" :aria-label="channel.toUpperCase()" :class="inputClass" class="!mt-1" @input="updateHsl" /></label>
                                </div>
                                <button type="button" class="icon-btn h-10 w-10" :disabled="!!error" :title="copied === 'hsl' ? 'Copiato' : 'Copia HSL'" aria-label="Copia HSL" @click="copyValue('hsl')"><Check v-if="copied === 'hsl'" class="h-4 w-4" /><Copy v-else class="h-4 w-4" /></button>
                            </div>
                            <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                        </div>
                    </div>
                </section>

                <section class="surface p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3"><h3 class="text-base font-semibold text-gray-900">Copiati di recente</h3><button v-if="history.length" type="button" class="icon-btn h-9 w-9" title="Svuota cronologia" aria-label="Svuota cronologia" @click="clearHistory"><Trash2 class="h-4 w-4" /></button></div>
                    <p v-if="!history.length" class="mt-4 text-sm text-gray-500">Nessun colore copiato.</p>
                    <div v-else class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="saved in history" :key="saved" class="flex min-w-0 items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 p-2">
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left text-sm font-medium text-gray-800" :title="`Seleziona ${saved}`" @click="setColor(saved)"><span class="h-9 w-9 shrink-0 rounded-[var(--radius-sm)] border border-gray-200" :style="{ backgroundColor: saved }" /><span class="truncate">{{ saved }}</span></button>
                            <button type="button" class="icon-btn h-9 w-9 shrink-0" :aria-label="`Copia ${saved}`" :title="`Copia ${saved}`" @click="setColor(saved); copyValue('hex')"><Copy class="h-4 w-4" /></button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.color-hue-slider { appearance: none; height: 12px; border-radius: var(--radius-sm); background: linear-gradient(to right, #f00, #ff0, #0f0, #0ff, #00f, #f0f, #f00); }
.color-hue-slider::-webkit-slider-thumb { appearance: none; width: 20px; height: 20px; border: 2px solid #fff; border-radius: 50%; background: #fff; box-shadow: 0 0 0 1px #555, 0 1px 4px #5558; }
.color-hue-slider::-moz-range-thumb { width: 16px; height: 16px; border: 2px solid #fff; border-radius: 50%; background: #fff; box-shadow: 0 0 0 1px #555, 0 1px 4px #5558; }
</style>
