<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { styledTextCategories, styledTextStyles, styleText } from '@/utils/styledText.js';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Search, Trash2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

const input = ref('');
const category = ref('Tutti');
const search = ref('');
const copiedId = ref(null);
const copyError = ref('');
const preview = computed(() => input.value || 'Esempio 123');
const visibleStyles = computed(() => styledTextStyles.filter(style =>
    (category.value === 'Tutti' || style.category === category.value)
    && style.label.toLocaleLowerCase('it').includes(search.value.trim().toLocaleLowerCase('it'))));
let copyTimer = null;
onBeforeUnmount(() => window.clearTimeout(copyTimer));

async function copy(style) {
    if (!input.value) return;
    try {
        await navigator.clipboard.writeText(styleText(input.value, style.id));
        copyError.value = '';
        copiedId.value = style.id;
        window.clearTimeout(copyTimer);
        copyTimer = window.setTimeout(() => { copiedId.value = null; }, 2500);
    } catch {
        copiedId.value = null;
        copyError.value = 'Impossibile copiare automaticamente. Verifica i permessi del browser.';
    }
}
</script>

<template>
    <Head title="Testo stilizzato" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.8" />Torna a Strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Testo stilizzato</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section class="surface p-5 sm:p-6">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <label for="styled-text-input" class="text-sm font-semibold text-gray-900">Testo</label>
                        <button type="button" :disabled="!input" title="Svuota testo" aria-label="Svuota testo" class="rounded-[var(--radius-sm)] p-2 text-gray-500 transition hover:bg-gray-100 disabled:opacity-40" @click="input = ''"><Trash2 class="h-4 w-4" /></button>
                    </div>
                    <textarea id="styled-text-input" v-model="input" rows="4" placeholder="Scrivi o incolla il testo" class="w-full resize-y rounded-[var(--radius-sm)] border border-gray-200 bg-white p-4 text-sm leading-6 text-gray-900 shadow-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                </section>
                <div class="surface p-4 sm:p-5">
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Categorie degli stili">
                        <button v-for="item in styledTextCategories" :key="item" type="button" :aria-pressed="category === item" :class="['rounded-[var(--radius-sm)] px-3 py-2 text-sm font-semibold transition', category === item ? 'bg-[hsl(var(--primary-app))] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']" @click="category = item">{{ item }}</button>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="relative w-full sm:max-w-xs">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input v-model="search" type="search" placeholder="Cerca uno stile" aria-label="Cerca uno stile" class="w-full rounded-[var(--radius-sm)] border-gray-200 py-2 pl-9 pr-9 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                            <button v-if="search" type="button" title="Cancella ricerca" aria-label="Cancella ricerca" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-500 hover:bg-gray-100" @click="search = ''"><X class="h-4 w-4" /></button>
                        </div>
                        <span class="text-xs text-gray-500">{{ visibleStyles.length }} stili</span>
                    </div>
                </div>
                <div v-if="visibleStyles.length" class="grid gap-4 sm:grid-cols-2">
                    <section v-for="style in visibleStyles" :key="style.id" class="surface flex min-w-0 flex-col justify-between gap-4 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h3 class="text-sm font-semibold text-gray-900">{{ style.label }}</h3><p class="mt-0.5 text-xs text-gray-500">{{ style.category }}</p></div>
                            <button type="button" :disabled="!input" :title="copiedId === style.id ? 'Copiato' : `Copia ${style.label.toLowerCase()}`" :aria-label="copiedId === style.id ? 'Copiato' : `Copia ${style.label.toLowerCase()}`" class="rounded-[var(--radius-sm)] p-2 text-gray-600 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40" @click="copy(style)"><Check v-if="copiedId === style.id" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" /></button>
                        </div>
                        <p :class="['min-h-12 whitespace-pre-wrap break-words text-lg leading-7 text-gray-900', !input ? 'text-gray-400' : '']">{{ styleText(preview, style.id) }}</p>
                    </section>
                </div>
                <p v-else class="py-8 text-center text-sm text-gray-500">Nessuno stile trovato.</p>
                <p v-if="copyError" role="alert" class="text-sm text-red-600">{{ copyError }}</p>
                <p class="text-xs text-gray-500">Le lettere senza equivalente Unicode restano invariate. L'aspetto può variare secondo l'app in cui incolli il testo.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
