<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolAccordion from '@/Components/ToolAccordion.vue';
import { cleanText } from '@/utils/textCleaner.js';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, RotateCcw, Sparkles, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';

const text = ref('');
const previous = ref(null);
const copied = ref(false);
const openSection = ref('spacing');
const options = reactive({
    trimText: true, trimLines: false, collapseSpaces: true, collapseBlankLines: false,
    removeBlankLines: false, removeDuplicateLines: false, stripHtml: false,
    decodeEntities: false, removeUrls: false, removeEmails: false,
    removeEmoji: false, removeDiacritics: false, removePunctuation: false,
    tabsToSpaces: false, joinLines: false, case: 'none', find: '', replace: '',
});
const groups = [
    { id: 'spacing', title: 'Spazi e righe', options: [
        ['trimText', 'Rimuovi spazi all’inizio e alla fine'],
        ['trimLines', 'Rimuovi spazi ai bordi di ogni riga'],
        ['collapseSpaces', 'Riduci spazi ripetuti'],
        ['collapseBlankLines', 'Riduci righe vuote ripetute'],
        ['removeBlankLines', 'Elimina righe vuote'],
        ['tabsToSpaces', 'Converti tabulazioni in spazi'],
        ['joinLines', 'Unisci le righe con uno spazio'],
    ] },
    { id: 'content', title: 'Contenuto', options: [
        ['removeDuplicateLines', 'Elimina righe duplicate'],
        ['removeUrls', 'Rimuovi link'],
        ['removeEmails', 'Rimuovi indirizzi email'],
        ['stripHtml', 'Rimuovi tag HTML'],
        ['decodeEntities', 'Decodifica entità HTML'],
    ] },
    { id: 'characters', title: 'Caratteri', options: [
        ['removeEmoji', 'Rimuovi emoji'],
        ['removeDiacritics', 'Rimuovi accenti'],
        ['removePunctuation', 'Rimuovi punteggiatura'],
    ] },
];
const characterCount = computed(() => text.value.length);

function clean() {
    if (!text.value) return;
    const result = cleanText(text.value, options, new DOMParser());
    if (result === text.value) return;
    previous.value = text.value;
    text.value = result;
    copied.value = false;
}

function undo() {
    if (previous.value === null) return;
    text.value = previous.value;
    previous.value = null;
    copied.value = false;
}

async function copy() {
    if (!text.value) return;
    try {
        await navigator.clipboard.writeText(text.value);
        copied.value = true;
        window.setTimeout(() => { copied.value = false; }, 2500);
    } catch { copied.value = false; }
}

function clear() {
    if (!text.value) return;
    previous.value = text.value;
    text.value = '';
    copied.value = false;
}
</script>

<template>
    <Head title="Pulisci testo" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.8" />Torna a Strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Pulisci testo</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section class="surface p-5 sm:p-6">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <label for="cleaner-text" class="text-sm font-semibold text-gray-900">Testo</label>
                        <span class="text-xs text-gray-500">{{ characterCount.toLocaleString('it-IT') }} caratteri</span>
                    </div>
                    <textarea id="cleaner-text" v-model="text" rows="12" placeholder="Incolla o scrivi qui il testo" class="w-full resize-y rounded-[var(--radius-sm)] border border-gray-200 bg-white p-4 text-sm leading-6 text-gray-900 shadow-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="button" :disabled="!text" class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app))] px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-50" @click="clean"><Sparkles class="h-4 w-4" />Applica modifiche</button>
                        <button type="button" :disabled="previous === null" class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="undo"><RotateCcw class="h-4 w-4" />Annulla</button>
                        <div class="ml-auto flex items-center gap-1">
                            <button type="button" :disabled="!text" :title="copied ? 'Copiato' : 'Copia testo'" :aria-label="copied ? 'Copiato' : 'Copia testo'" class="rounded-[var(--radius-sm)] p-2.5 text-gray-600 transition hover:bg-gray-100 disabled:opacity-40" @click="copy"><Check v-if="copied" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" /></button>
                            <button type="button" :disabled="!text" title="Svuota testo" aria-label="Svuota testo" class="rounded-[var(--radius-sm)] p-2.5 text-gray-600 transition hover:bg-gray-100 disabled:opacity-40" @click="clear"><Trash2 class="h-4 w-4" /></button>
                        </div>
                    </div>
                </section>

                <section class="surface overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4"><h3 class="text-base font-semibold text-gray-900">Modifiche al testo</h3></div>
                    <div class="divide-y divide-gray-100">
                        <ToolAccordion v-for="group in groups" :key="group.id" :title="group.title" :panel-id="`cleaner-${group.id}`" :open="openSection === group.id" @toggle="openSection = openSection === group.id ? '' : group.id">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label v-for="[key, label] in group.options" :key="key" class="flex cursor-pointer items-center gap-3 text-sm text-gray-700"><input v-model="options[key]" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app))]" />{{ label }}</label>
                            </div>
                        </ToolAccordion>
                        <ToolAccordion title="Maiuscole e minuscole" panel-id="cleaner-case" :open="openSection === 'case'" @toggle="openSection = openSection === 'case' ? '' : 'case'">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label v-for="choice in [{ value: 'none', label: 'Non cambiare' }, { value: 'upper', label: 'MAIUSCOLO' }, { value: 'lower', label: 'minuscolo' }, { value: 'title', label: 'Iniziale Maiuscola' }, { value: 'sentence', label: 'Formato frase' }]" :key="choice.value" class="flex cursor-pointer items-center gap-3 text-sm text-gray-700"><input v-model="options.case" type="radio" :value="choice.value" class="border-gray-300 text-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app))]" />{{ choice.label }}</label>
                            </div>
                        </ToolAccordion>
                        <ToolAccordion title="Trova e sostituisci" panel-id="cleaner-replace" :open="openSection === 'replace'" @toggle="openSection = openSection === 'replace' ? '' : 'replace'">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700">Trova<input v-model="options.find" type="text" autocomplete="off" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" /></label>
                                <label class="block text-sm font-medium text-gray-700">Sostituisci con<input v-model="options.replace" type="text" autocomplete="off" class="mt-1 block w-full rounded-[var(--radius-sm)] border-gray-200 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" /></label>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Lascia vuoto “Sostituisci con” per eliminare il testo trovato.</p>
                        </ToolAccordion>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
