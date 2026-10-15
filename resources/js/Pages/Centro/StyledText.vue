<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { styledTextStyles, styleText } from '@/utils/styledText.js';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

const input = ref('');
const copiedId = ref(null);
const copyError = ref('');
const preview = computed(() => input.value || 'Esempio 123');
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
            <div class="mx-auto max-w-[1200px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section class="surface p-5 sm:p-6">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <label for="styled-text-input" class="text-sm font-semibold text-gray-900">Testo</label>
                        <button type="button" :disabled="!input" title="Svuota testo" aria-label="Svuota testo" class="rounded-[var(--radius-sm)] p-2 text-gray-500 transition hover:bg-gray-100 disabled:opacity-40" @click="input = ''"><Trash2 class="h-4 w-4" /></button>
                    </div>
                    <textarea id="styled-text-input" v-model="input" rows="4" placeholder="Scrivi o incolla il testo" class="w-full resize-y rounded-[var(--radius-sm)] border border-gray-200 bg-white p-4 text-sm leading-6 text-gray-900 shadow-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                </section>
                <div class="grid gap-4 sm:grid-cols-2">
                    <section v-for="style in styledTextStyles" :key="style.id" class="surface flex min-w-0 flex-col justify-between gap-4 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h3 class="text-sm font-semibold text-gray-900">{{ style.label }}</h3><p class="mt-0.5 text-xs text-gray-500">{{ style.detail }}</p></div>
                            <button type="button" :disabled="!input" :title="copiedId === style.id ? 'Copiato' : `Copia ${style.label.toLowerCase()} ${style.detail.toLowerCase()}`" :aria-label="copiedId === style.id ? 'Copiato' : `Copia ${style.label.toLowerCase()} ${style.detail.toLowerCase()}`" class="rounded-[var(--radius-sm)] p-2 text-gray-600 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40" @click="copy(style)"><Check v-if="copiedId === style.id" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" /></button>
                        </div>
                        <p :class="['min-h-12 whitespace-pre-wrap break-words text-lg leading-7 text-gray-900', !input ? 'text-gray-400' : '']">{{ styleText(preview, style.id) }}</p>
                    </section>
                </div>
                <p v-if="copyError" role="alert" class="text-sm text-red-600">{{ copyError }}</p>
                <p class="text-xs text-gray-500">Le lettere senza equivalente Unicode restano invariate. L'aspetto può variare secondo l'app in cui incolli il testo.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
