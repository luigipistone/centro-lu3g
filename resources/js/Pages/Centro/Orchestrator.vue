<script setup>
import AppSelect from '@/Components/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { CheckCircle2, Lock, Play, RefreshCw, Sparkles } from '@lucide/vue';

const props = defineProps({
    projects: Array,
    services: Array,
    decisionModules: Array,
    runs: Array,
    aiConfigured: Boolean,
});

const selectedWorkflowByRun = ref({});

const generateForm = useForm({
    project_id: '',
});

const projectOptions = computed(() => (props.projects || []).map((project) => ({
    value: project.id,
    label: `${project.name}${project.client_name ? ` · ${project.client_name}` : ''}`,
})));

const workflowOptions = computed(() => (props.decisionModules || [])
    .filter((module) => !module.parent_module_id)
    .map((module) => ({
        value: module.id,
        label: module.name,
    })));

watch(
    () => props.runs,
    (runs) => {
        const next = { ...selectedWorkflowByRun.value };
        (runs || []).forEach((run) => {
            if (!next[run.id]) {
                next[run.id] = run.workflow_module_id || workflowOptions.value[0]?.value || '';
            }
        });
        selectedWorkflowByRun.value = next;
    },
    { immediate: true },
);

function generateAnalysis() {
    generateForm.post(route('orchestrator.generate'), {
        preserveScroll: true,
    });
}

function approveRun(run) {
    const workflowModuleId = selectedWorkflowByRun.value[run.id];
    if (!workflowModuleId) return;

    router.post(route('orchestrator.approve', run.id), {
        workflow_module_id: workflowModuleId,
    }, {
        preserveScroll: true,
    });
}

function executeStep(step) {
    router.post(route('orchestrator.modules.execute', step.id), {}, {
        preserveScroll: true,
    });
}

function runCanApprove(run) {
    return run.status === 'draft' && workflowOptions.value.length;
}

function stepCanRun(step, run) {
    return run.status !== 'completed' && step.status === 'todo';
}

function statusLabel(status) {
    return {
        draft: 'Bozza',
        approved: 'Approvato',
        completed: 'Completato',
        blocked: 'Bloccato',
        todo: 'Da fare',
        in_progress: 'In corso',
    }[status] || status;
}

function stepStatusClass(status) {
    return {
        blocked: 'bg-gray-100 text-gray-500',
        todo: 'bg-blue-50 text-blue-700',
        in_progress: 'bg-amber-50 text-amber-700',
        completed: 'bg-emerald-50 text-emerald-700',
    }[status] || 'bg-gray-100 text-gray-500';
}
</script>

<template>
    <Head title="Orchestratore" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Orchestratore</h2>
                    <p class="mt-1 text-sm text-gray-500">Analizza un progetto, propone un workflow e gestisce l'esecuzione sequenziale dei moduli.</p>
                </div>
                <span
                    :class="[
                        'inline-flex w-fit items-center rounded-full px-3 py-1.5 text-xs font-semibold',
                        aiConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700',
                    ]"
                >
                    {{ aiConfigured ? 'AI configurata' : 'AI non configurata' }}
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="surface p-5">
                    <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Progetto esistente</label>
                            <AppSelect v-model="generateForm.project_id" :options="projectOptions" searchable placeholder="Seleziona progetto" />
                            <div v-if="generateForm.errors.project_id" class="mt-1 text-sm text-red-600">{{ generateForm.errors.project_id }}</div>
                        </div>
                        <button type="button" class="btn btn-primary min-h-10" :disabled="generateForm.processing || !generateForm.project_id" @click="generateAnalysis">
                            <Sparkles class="h-4 w-4" :stroke-width="1.7" />
                            Genera analisi
                        </button>
                    </div>
                </section>

                <section v-if="!(decisionModules || []).length" class="rounded-[var(--radius)] border border-amber-100 bg-amber-50 p-4 text-sm font-medium text-amber-800">
                    Non ho trovato moduli nella cartella Decisioni. Crea una cartella chiamata Decisioni e inserisci i workflow/moduli da usare.
                </section>

                <section v-if="runs?.length" class="space-y-4">
                    <article v-for="run in runs" :key="run.id" class="content-card rounded-[var(--radius)] border border-gray-200 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ statusLabel(run.status) }}</span>
                                    <span v-if="run.workflow_name" class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ run.workflow_name }}</span>
                                </div>
                                <h3 class="mt-3 text-lg font-semibold text-gray-900">{{ run.project_name }}</h3>
                                <p v-if="run.client_name" class="mt-1 text-sm text-gray-500">{{ run.client_name }}</p>
                            </div>

                            <div v-if="runCanApprove(run)" class="w-full max-w-md space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Workflow da utilizzare</label>
                                <div class="flex gap-2">
                                    <AppSelect v-model="selectedWorkflowByRun[run.id]" :options="workflowOptions" searchable placeholder="Seleziona workflow" />
                                    <button type="button" class="btn btn-primary shrink-0" @click="approveRun(run)">
                                        Approva
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-3">
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Servizi consigliati</div>
                                <div v-if="run.recommended_services?.length" class="mt-2 flex flex-wrap gap-1.5">
                                    <span v-for="service in run.recommended_services" :key="service" class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-600">{{ service }}</span>
                                </div>
                                <p v-else class="mt-2 text-xs text-gray-500">Nessun servizio suggerito.</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Priorità</div>
                                <p class="mt-2 text-sm font-semibold text-gray-800">{{ run.recommended_priority || 'Media' }}</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Workflow proposti</div>
                                <p class="mt-2 line-clamp-3 text-sm text-gray-600">{{ (run.workflow_options || []).join(', ') || 'Nessuna proposta.' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-[var(--radius-sm)] border border-gray-100 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Roadmap</div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ run.roadmap || 'Roadmap non disponibile.' }}</p>
                        </div>

                        <div v-if="run.modules?.length" class="mt-5 space-y-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Fasi del workflow</div>
                            <div
                                v-for="step in run.modules"
                                :key="step.id"
                                class="rounded-[var(--radius-sm)] border border-gray-100 bg-white p-3"
                            >
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', stepStatusClass(step.status)]">{{ statusLabel(step.status) }}</span>
                                            <span class="text-xs font-semibold text-gray-400">#{{ step.position }}</span>
                                        </div>
                                        <h4 class="mt-2 font-semibold text-gray-900">{{ step.module_name }}</h4>
                                    </div>
                                    <button type="button" class="btn btn-outline shrink-0" :disabled="!stepCanRun(step, run)" @click="executeStep(step)">
                                        <Play v-if="step.status === 'todo'" class="h-4 w-4" :stroke-width="1.7" />
                                        <RefreshCw v-else-if="step.status === 'in_progress'" class="h-4 w-4" :stroke-width="1.7" />
                                        <CheckCircle2 v-else-if="step.status === 'completed'" class="h-4 w-4" :stroke-width="1.7" />
                                        <Lock v-else class="h-4 w-4" :stroke-width="1.7" />
                                        Esegui
                                    </button>
                                </div>
                                <div v-if="step.output" class="mt-3 rounded-[var(--radius-sm)] bg-gray-50 p-3 text-sm leading-6 text-gray-600">
                                    <pre class="whitespace-pre-wrap font-sans">{{ step.output }}</pre>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <section v-else class="rounded-[var(--radius)] border border-dashed border-gray-200 bg-gray-50 p-8 text-center">
                    <Sparkles class="mx-auto h-8 w-8 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                    <h3 class="mt-3 text-base font-semibold text-gray-900">Nessuna analisi generata</h3>
                    <p class="mt-1 text-sm text-gray-500">Seleziona un progetto e genera la prima analisi orchestrata.</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
