<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Link2, Tags } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';

const fields = reactive({ url: '', source: '', medium: '', campaign: '', id: '', term: '', content: '' });
const copied = ref(false);
const copyError = ref('');
const openOptional = ref(false);
const inputClass = 'mt-2 block w-full min-w-0 rounded-[var(--radius-sm)] border-gray-200 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]';

const validUrl = computed(() => {
    try {
        const parsed = new URL(fields.url.trim());
        return ['http:', 'https:'].includes(parsed.protocol) && parsed.hostname && !parsed.username && !parsed.password;
    } catch {
        return false;
    }
});

const generatedUrl = computed(() => {
    if (!validUrl.value || !fields.source.trim() || !fields.medium.trim() || (!fields.campaign.trim() && !fields.id.trim())) return '';
    const url = new URL(fields.url.trim());
    const parameters = {
        utm_source: fields.source,
        utm_medium: fields.medium,
        utm_campaign: fields.campaign,
        utm_id: fields.id,
        utm_term: fields.term,
        utm_content: fields.content,
    };
    for (const [key, value] of Object.entries(parameters)) {
        if (value.trim()) url.searchParams.set(key, value.trim());
        else url.searchParams.delete(key);
    }
    return url.toString();
});

watch(fields, () => { copied.value = false; copyError.value = ''; });

async function copyUrl() {
    if (!generatedUrl.value) return;
    try {
        await navigator.clipboard.writeText(generatedUrl.value);
        copied.value = true;
    } catch {
        copyError.value = 'Copia non disponibile. Seleziona il link per copiarlo.';
    }
}
</script>

<template>
    <Head title="Crea link UTM" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="mb-1 inline-flex items-center gap-1 self-start text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Crea link UTM</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.75fr)]">
                    <section class="surface min-w-0 p-5 sm:p-6">
                        <div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Tags class="h-6 w-6" :stroke-width="1.7" /></span><h3 class="text-base font-semibold text-gray-900">Campagna</h3></div>
                        <div class="mt-5 space-y-5">
                            <label class="block text-sm font-medium text-gray-700">Indirizzo web <span class="text-red-500">*</span><input v-model="fields.url" type="url" maxlength="2048" placeholder="https://esempio.it/pagina" :class="inputClass" /></label>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <label class="block min-w-0 text-sm font-medium text-gray-700">Sorgente <span class="text-red-500">*</span><input v-model="fields.source" type="text" maxlength="150" placeholder="newsletter" :class="inputClass" /></label>
                                <label class="block min-w-0 text-sm font-medium text-gray-700">Mezzo <span class="text-red-500">*</span><input v-model="fields.medium" type="text" maxlength="150" placeholder="email" :class="inputClass" /></label>
                                <label class="block min-w-0 text-sm font-medium text-gray-700">Nome campagna<input v-model="fields.campaign" type="text" maxlength="150" placeholder="lancio_autunno" :class="inputClass" /></label>
                                <label class="block min-w-0 text-sm font-medium text-gray-700">ID campagna<input v-model="fields.id" type="text" maxlength="150" placeholder="campagna_123" :class="inputClass" /></label>
                            </div>
                            <p class="text-xs text-gray-500">Inserisci almeno il nome o l’ID della campagna.</p>
                            <div class="overflow-hidden rounded-[var(--radius-sm)] border border-gray-200">
                                <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-gray-800 hover:bg-gray-50" :aria-expanded="openOptional" aria-controls="utm-optional-fields" @click="openOptional = !openOptional"><span>Altri parametri</span><span class="text-xs font-medium text-gray-500">{{ openOptional ? '−' : '+' }}</span></button>
                                <div v-if="openOptional" id="utm-optional-fields" class="grid gap-5 border-t border-gray-100 px-4 py-4 sm:grid-cols-2">
                                    <label class="block min-w-0 text-sm font-medium text-gray-700">Termine<input v-model="fields.term" type="text" maxlength="150" placeholder="parola_chiave" :class="inputClass" /></label>
                                    <label class="block min-w-0 text-sm font-medium text-gray-700">Contenuto<input v-model="fields.content" type="text" maxlength="150" placeholder="pulsante_header" :class="inputClass" /></label>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="surface flex min-w-0 flex-col p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">Link generato</h3>
                        <div class="mt-5 min-w-0 flex-1">
                            <textarea :value="generatedUrl" readonly rows="7" aria-label="Link UTM generato" :placeholder="validUrl ? 'Completa sorgente, mezzo e nome o ID campagna' : 'Inserisci un indirizzo web valido'" class="block w-full resize-none rounded-[var(--radius-sm)] border-gray-200 bg-gray-50 text-sm text-gray-800 focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                            <p v-if="fields.url && !validUrl" class="mt-2 text-xs text-red-600">Usa un indirizzo completo che inizi con https:// o http://.</p>
                            <p v-if="generatedUrl.length > 2048" class="mt-2 text-xs text-red-600">Il link supera i 2048 caratteri consentiti da Link brevi.</p>
                            <p v-if="copyError" role="alert" class="mt-2 text-xs text-red-600">{{ copyError }}</p>
                        </div>
                        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                            <button type="button" class="btn btn-primary min-w-0 flex-1 justify-center" :disabled="!generatedUrl" @click="copyUrl"><Check v-if="copied" class="h-4 w-4" /><Copy v-else class="h-4 w-4" /> {{ copied ? 'Copiato' : 'Copia link' }}</button>
                            <Link v-if="generatedUrl && generatedUrl.length <= 2048" :href="route('tools.short-links', { url: generatedUrl })" class="btn min-w-0 flex-1 justify-center border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"><Link2 class="h-4 w-4" /> Accorcia link</Link>
                            <button v-else type="button" class="btn min-w-0 flex-1 justify-center border border-gray-200 bg-white text-gray-700 disabled:opacity-50" disabled><Link2 class="h-4 w-4" /> Accorcia link</button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
