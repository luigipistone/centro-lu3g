<script setup>
import AppSelect from '@/Components/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, BrainCircuit, CircleAlert, Euro, Plus, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ projects: Array, runs: Array, budget: Object, configured: Boolean, canConfigure: Boolean });
const form = useForm({ project_id: '' });
const configurationOpen = ref(false);
const configurationForm = useForm({ api_key: '' });
const projectOptions = computed(() => (props.projects || []).map((project) => ({
    value: project.id,
    label: `${project.name}${project.client_name ? ` · ${project.client_name}` : ''}`,
})));

const statusLabel = {
    draft: 'Da analizzare',
    analyzing: 'Analisi in corso',
    proposal_ready: 'Proposta pronta',
    approved: 'Strategia approvata',
    error: 'Da controllare',
};

function createAnalysis() {
    form.post(route('ai-agency.store'));
}

function saveConfiguration() {
    configurationForm.put(route('ai-agency.configure'), {
        preserveScroll: true,
        onSuccess: () => {
            configurationForm.reset();
            configurationOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Agenzia AI" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Agenzia AI</h2>
                    <p class="mt-1 text-sm text-gray-500">Analisi strategiche dei progetti, guidate dai moduli LU3G.</p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 bg-white/80 px-3 py-2 text-sm">
                    <Euro class="h-4 w-4 text-[hsl(var(--primary-app))]" :stroke-width="1.8" />
                    <span class="font-semibold text-gray-800">{{ Number(budget?.remaining || 0).toFixed(2).replace('.', ',') }} €</span>
                    <span class="text-gray-500">disponibili questo mese</span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <section v-if="!configured" class="surface flex items-start gap-3 border-amber-200 bg-amber-50/95 p-4">
                    <CircleAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" :stroke-width="1.8" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-amber-900">Configurazione AI da completare</p>
                        <p class="mt-1 text-sm text-amber-800">La struttura è pronta. Per generare analisi serve configurare la chiave OpenAI sul server.</p>
                    </div>
                    <button v-if="canConfigure" type="button" class="btn btn-outline shrink-0" @click="configurationOpen = true">Configura</button>
                </section>

                <section class="surface p-5">
                    <div class="grid items-end gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-gray-700">Progetto da analizzare</span>
                            <AppSelect v-model="form.project_id" :options="projectOptions" searchable placeholder="Cerca e seleziona un progetto" />
                            <span v-if="form.errors.project_id" class="mt-1 block text-xs text-red-600">{{ form.errors.project_id }}</span>
                        </label>
                        <button type="button" class="btn btn-primary h-[38px]" :disabled="!form.project_id || form.processing" @click="createAnalysis">
                            <Plus class="h-4 w-4" :stroke-width="1.8" />
                            Nuova analisi
                        </button>
                    </div>
                </section>

                <section>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Analisi</h3>
                        <span class="text-xs font-semibold text-gray-400">{{ runs?.length || 0 }} elementi</span>
                    </div>
                    <div v-if="runs?.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <Link v-for="run in runs" :key="run.id" :href="route('ai-agency.show', run.id)" class="surface hover-lift group block p-5">
                            <div class="flex items-start justify-between gap-3">
                                <span class="section-icon"><BrainCircuit class="h-4 w-4" :stroke-width="1.8" /></span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold text-gray-600">{{ statusLabel[run.status] || run.status }}</span>
                            </div>
                            <h3 class="mt-4 line-clamp-2 text-base font-semibold text-gray-900">{{ run.project_name }}</h3>
                            <p class="mt-1 truncate text-sm text-gray-500">{{ run.client_name || 'Nessun cliente' }}</p>
                            <div class="mt-5 flex items-center justify-between gap-3 border-t border-gray-100 pt-4 text-xs text-gray-500">
                                <span>{{ new Date(run.created_at).toLocaleDateString('it-IT') }}</span>
                                <ArrowRight class="h-4 w-4 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.8" />
                            </div>
                        </Link>
                    </div>
                    <div v-else class="surface p-10 text-center">
                        <Sparkles class="mx-auto h-8 w-8 text-[hsl(var(--primary-app))]" :stroke-width="1.6" />
                        <h3 class="mt-3 text-base font-semibold text-gray-900">Nessuna analisi</h3>
                        <p class="mt-1 text-sm text-gray-500">Seleziona un progetto per preparare la prima proposta strategica.</p>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="configurationOpen" class="fixed inset-0 z-[9000] flex items-center justify-center bg-black/20 px-4 backdrop-blur-sm" @click.self="configurationOpen = false">
            <form class="surface w-full max-w-lg bg-white p-5" @submit.prevent="saveConfiguration">
                <h3 class="text-lg font-semibold text-gray-900">Connessione OpenAI</h3>
                <p class="mt-2 text-sm leading-6 text-gray-500">La chiave viene cifrata e non sarà più mostrata dopo il salvataggio.</p>
                <label class="mt-4 block"><span class="mb-2 block text-sm font-semibold text-gray-700">API key</span><input v-model="configurationForm.api_key" type="password" class="form-control" autocomplete="new-password" placeholder="sk-..." /></label>
                <p v-if="configurationForm.errors.api_key" class="mt-2 text-xs text-red-600">{{ configurationForm.errors.api_key }}</p>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="configurationOpen = false">Annulla</button><button type="submit" class="btn btn-primary" :disabled="configurationForm.processing">Salva</button></div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
