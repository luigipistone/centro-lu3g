<script setup>
import AppSelect from '@/Components/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Check, CheckCircle2, ChevronLeft, Lock, Play, RefreshCw, Sparkles, Trash2, X } from '@lucide/vue';

const props = defineProps({
    projects: Array,
    runs: Array,
    hasDecisionModules: Boolean,
    aiConfigured: Boolean,
    focusedRunId: String,
});

const page = usePage();
const deleteTarget = ref(null);
const deleteText = ref('');
const isSuperadmin = computed(() => page.props.auth?.user?.role === 'superadmin');

const generateForm = useForm({
    project_id: '',
});

const projectOptions = computed(() => (props.projects || []).map((project) => ({
    value: project.id,
    label: `${project.name}${project.client_name ? ` · ${project.client_name}` : ''}`,
})));

const isExecutionView = computed(() => Boolean(props.focusedRunId));
const displayRuns = computed(() => {
    if (!props.focusedRunId) return props.runs || [];
    return (props.runs || []).filter((run) => run.id === props.focusedRunId);
});

function generateAnalysis() {
    generateForm.post(route('orchestrator.generate'), {
        preserveScroll: true,
    });
}

function approveRun(run) {
    router.post(route('orchestrator.approve', run.id));
}

function executeStep(step) {
    router.post(route('orchestrator.modules.execute', step.id), {}, {
        preserveScroll: true,
    });
}

function requestDelete(run) {
    if (isSuperadmin.value) {
        router.delete(route('orchestrator.destroy', run.id), { preserveScroll: true });
        return;
    }

    deleteTarget.value = run;
    deleteText.value = '';
}

function closeDelete() {
    deleteTarget.value = null;
    deleteText.value = '';
}

function confirmDelete() {
    if (!deleteTarget.value || deleteText.value !== 'ELIMINA') return;

    router.delete(route('orchestrator.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onFinish: closeDelete,
    });
}

function runCanApprove(run) {
    return run.status === 'draft' && run.matched_workflows?.length;
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

function workflowGroups(run) {
    const groups = new Map();

    (run.modules || []).forEach((step) => {
        const key = step.workflow_module_id || 'workflow';
        if (!groups.has(key)) {
            groups.set(key, {
                id: key,
                name: step.workflow_name || run.workflow_name || 'Workflow',
                steps: [],
            });
        }
        groups.get(key).steps.push(step);
    });

    return Array.from(groups.values());
}
</script>

<template>
    <Head title="Orchestratore" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <Link v-if="isExecutionView" :href="route('orchestrator.index')" class="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                        <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                        Orchestratore
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ isExecutionView ? 'Esecuzione workflow' : 'Orchestratore' }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ isExecutionView ? 'Esegui un modulo alla volta seguendo l’ordine definito.' : 'Analizza il progetto e approva la strategia proposta.' }}</p>
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
                <div v-if="deleteTarget" class="fixed inset-0 z-[7000] flex items-center justify-center bg-transparent px-4 py-6" @click.self="closeDelete">
                    <div class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-xl">
                        <h3 class="text-base font-semibold text-gray-900">Eliminare questo contenuto?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Verranno eliminati l'analisi di <span class="font-semibold text-gray-900">{{ deleteTarget.project_name }}</span>,
                            il workflow e tutti gli output generati. Digita
                            <span class="font-mono font-semibold text-gray-900">ELIMINA</span> per confermare.
                        </p>
                        <input v-model="deleteText" class="form-control mt-4 font-mono" placeholder="ELIMINA" autocomplete="off" />
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" class="btn btn-outline" @click="closeDelete">Annulla</button>
                            <button type="button" class="btn btn-danger" :disabled="deleteText !== 'ELIMINA'" @click="confirmDelete">Elimina</button>
                        </div>
                    </div>
                </div>

                <section v-if="!isExecutionView" class="surface p-5">
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

                <section v-if="!isExecutionView && !hasDecisionModules" class="rounded-[var(--radius)] border border-amber-100 bg-amber-50 p-4 text-sm font-medium text-amber-800">
                    Non ho trovato moduli nella cartella Decisioni. Crea la cartella e inserisci i moduli decisionali da usare nell'analisi.
                </section>

                <section v-if="displayRuns.length" class="space-y-4">
                    <article v-for="run in displayRuns" :key="run.id" class="content-card rounded-[var(--radius)] border border-gray-200 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ statusLabel(run.status) }}</span>
                                </div>
                                <h3 class="mt-3 text-lg font-semibold text-gray-900">{{ run.project_name }}</h3>
                                <p v-if="run.client_name" class="mt-1 text-sm text-gray-500">{{ run.client_name }}</p>
                            </div>

                            <div class="flex items-start justify-end gap-2">
                                <button v-if="run.status === 'draft'" type="button" class="btn btn-primary shrink-0" :disabled="!runCanApprove(run)" @click="approveRun(run)">
                                    Approva strategia
                                </button>
                                <button
                                    type="button"
                                    class="icon-btn h-8 w-8 shrink-0 text-red-600 hover:bg-red-50"
                                    title="Elimina contenuto"
                                    aria-label="Elimina contenuto dell'orchestratore"
                                    @click="requestDelete(run)"
                                >
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>
                        </div>

                        <div v-if="run.status === 'draft'" class="mt-5 grid gap-3 lg:grid-cols-2">
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Servizi consigliati</div>
                                <div v-if="run.recommended_services?.length" class="mt-3 space-y-2">
                                    <div v-for="service in run.recommended_services" :key="service" class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <Check class="h-4 w-4 text-emerald-600" :stroke-width="2" />
                                        {{ service }}
                                    </div>
                                </div>
                                <p v-else class="mt-2 text-xs text-gray-500">Nessun servizio suggerito.</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Servizi non consigliati</div>
                                <div v-if="run.not_recommended_services?.length" class="mt-3 space-y-2">
                                    <div v-for="service in run.not_recommended_services" :key="service" class="flex items-center gap-2 text-sm text-gray-600">
                                        <X class="h-4 w-4 text-red-500" :stroke-width="2" />
                                        {{ service }}
                                    </div>
                                </div>
                                <p v-else class="mt-2 text-xs text-gray-500">Nessun servizio escluso.</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 p-3 lg:col-span-2">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Priorità</div>
                                <ol v-if="run.recommended_priorities?.length" class="mt-3 space-y-2">
                                    <li v-for="(priority, index) in run.recommended_priorities" :key="priority" class="flex items-center gap-3 text-sm font-semibold text-gray-700">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs text-gray-500">{{ index + 1 }}</span>
                                        {{ priority }}
                                    </li>
                                </ol>
                            </div>
                        </div>

                        <div v-if="run.status === 'draft'" class="mt-4 rounded-[var(--radius-sm)] border border-gray-100 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Motivazioni</div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ run.motivations || 'Motivazioni non disponibili.' }}</p>
                        </div>

                        <div v-if="run.status === 'draft'" class="mt-4 rounded-[var(--radius-sm)] border border-gray-100 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Roadmap</div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ run.roadmap || 'Roadmap non disponibile.' }}</p>
                        </div>

                        <div v-if="run.status === 'draft'" class="mt-4 rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Workflow che verranno avviati</div>
                            <div v-if="run.matched_workflows?.length" class="mt-3 flex flex-wrap gap-2">
                                <span v-for="workflow in run.matched_workflows" :key="workflow.id" class="rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-gray-700">
                                    {{ workflow.name }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-sm text-amber-700">Nessun workflow corrispondente trovato nella cartella Workflow.</p>
                            <p v-if="run.unmatched_services?.length" class="mt-3 text-xs text-amber-700">
                                Senza workflow corrispondente: {{ run.unmatched_services.join(', ') }}.
                            </p>
                        </div>

                        <div v-if="run.modules?.length" class="mt-5 space-y-5">
                            <section v-for="workflow in workflowGroups(run)" :key="workflow.id" class="space-y-2">
                                <h4 class="text-sm font-semibold text-gray-900">{{ workflow.name }}</h4>
                                <div v-for="step in workflow.steps" :key="step.id" class="rounded-[var(--radius-sm)] border border-gray-100 bg-white p-3">
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
                            </section>
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
