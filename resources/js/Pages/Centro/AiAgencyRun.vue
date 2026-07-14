<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BrainCircuit, Check, ChevronLeft, CircleAlert, CircleCheck, Clock3, Euro, Globe2, Sparkles, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    run: Object,
    proposal: Object,
    approvedServices: Array,
    services: Array,
    steps: Array,
    workflowMappings: Object,
    budget: Object,
    projectFiles: Array,
});

const analysisForm = useForm({});
const approvalForm = useForm({ service_ids: props.approvedServices?.length ? [...props.approvedServices] : (props.proposal?.recommended_services || []).map((item) => item.service_id) });
const deleteOpen = ref(false);
const deleteText = ref('');
const activeStep = computed(() => (props.steps || []).find((step) => Number(step.position) === 0));
const initialStepInput = (() => {
    try { return JSON.parse(activeStep.value?.input_data || '{}'); } catch { return {}; }
})();
const briefForm = useForm({
    answers: { ...(initialStepInput.answers || {}) },
    file_assessments: { ...(initialStepInput.file_assessments || {}) },
});
const strategyForm = useForm({});
const workflowStep = (position) => (props.steps || []).find((step) => Number(step.position) === position);
const clientAnalysisStep = computed(() => workflowStep(1));
const competitorAnalysisStep = computed(() => workflowStep(2));
const strategyStep = computed(() => workflowStep(3));
const strategicDocuments = computed(() => [clientAnalysisStep.value, competitorAnalysisStep.value, strategyStep.value]
    .filter((step) => step?.output_data)
    .map((step) => {
        try { return { step, content: JSON.parse(step.output_data) }; } catch { return { step, content: {} }; }
    }));
const isReady = computed(() => props.run.status === 'proposal_ready');
const isApproved = computed(() => props.run.status === 'approved');
const serviceById = computed(() => Object.fromEntries((props.services || []).map((service) => [service.id, service])));
const stepsByService = computed(() => {
    const grouped = {};
    (props.steps || []).forEach((step) => { grouped[step.service_id] = [...(grouped[step.service_id] || []), step]; });
    return grouped;
});

function analyze() {
    analysisForm.post(route('ai-agency.analyze', props.run.id), { preserveScroll: true });
}

function toggleService(id) {
    if (!isReady.value) return;
    approvalForm.service_ids = approvalForm.service_ids.includes(id)
        ? approvalForm.service_ids.filter((value) => value !== id)
        : [...approvalForm.service_ids, id];
}

function approve() {
    approvalForm.post(route('ai-agency.approve', props.run.id), { preserveScroll: true });
}

function destroyRun() {
    if (deleteText.value !== 'ELIMINA') return;
    router.delete(route('ai-agency.destroy', props.run.id));
}

function startFirstStep() {
    router.post(route('ai-agency.steps.start', [props.run.id, activeStep.value.id]), {}, { preserveScroll: true });
}

function saveBrief() {
    briefForm.put(route('ai-agency.steps.update', [props.run.id, activeStep.value.id]), { preserveScroll: true });
}

function submitBrief() {
    briefForm.put(route('ai-agency.steps.update', [props.run.id, activeStep.value.id]), {
        preserveScroll: true,
        onSuccess: () => router.post(route('ai-agency.steps.submit', [props.run.id, activeStep.value.id]), {}, { preserveScroll: true }),
    });
}

function approveBrief() {
    router.post(route('ai-agency.steps.approve', [props.run.id, activeStep.value.id]), {}, { preserveScroll: true });
}

function executeStrategy() {
    strategyForm.post(route('ai-agency.pm-strategy.execute', props.run.id), { preserveScroll: true });
}

function approveStrategy() {
    strategyForm.post(route('ai-agency.pm-strategy.approve', props.run.id), { preserveScroll: true });
}

const stepStatusLabel = { todo: 'Da fare', in_progress: 'In corso', approval: 'Da approvare', completed: 'Completato', blocked: 'Bloccato' };
const assessmentOptions = [
    { value: 'relevant', label: 'Pertinente' },
    { value: 'uncertain', label: 'Da verificare' },
    { value: 'irrelevant', label: 'Non pertinente' },
];
</script>

<template>
    <Head :title="`Agenzia AI · ${run.project_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <Link :href="route('ai-agency.index')" class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                        <ChevronLeft class="h-3.5 w-3.5" :stroke-width="1.8" /> Agenzia AI
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ run.project_name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ run.client_name || 'Analisi strategica del progetto' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="icon-btn text-red-600 hover:bg-red-50" title="Elimina analisi" @click="deleteOpen = true">
                        <Trash2 class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                    <button v-if="['draft', 'error'].includes(run.status)" type="button" class="btn btn-primary" :disabled="analysisForm.processing" @click="analyze">
                        <Sparkles class="h-4 w-4" :stroke-width="1.8" />
                        {{ analysisForm.processing ? 'Analisi in corso...' : 'Genera analisi' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.errors?.analysis || run.error_message" class="surface flex gap-3 border-red-200 bg-red-50/95 p-4 text-sm text-red-800">
                    <CircleAlert class="h-5 w-5 shrink-0" :stroke-width="1.8" />
                    <span>{{ $page.props.errors?.analysis || run.error_message }}</span>
                </div>

                <section v-if="run.status === 'draft'" class="surface p-8 text-center">
                    <BrainCircuit class="mx-auto h-9 w-9 text-[hsl(var(--primary-app))]" :stroke-width="1.6" />
                    <h3 class="mt-3 text-lg font-semibold text-gray-900">Il progetto è pronto per l'analisi</h3>
                    <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-gray-500">Il PM leggerà progetto, cliente, task, messaggi, file e moduli Decisioni. La ricerca web sarà usata solo quando utile.</p>
                </section>

                <template v-if="proposal?.executive_summary">
                    <section class="surface p-5">
                        <div class="flex items-start gap-3">
                            <span class="section-icon"><BrainCircuit class="h-4 w-4" :stroke-width="1.8" /></span>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Sintesi strategica</h3>
                                <p class="mt-2 max-w-5xl text-sm leading-6 text-gray-600">{{ proposal.executive_summary }}</p>
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section class="surface p-5">
                            <h3 class="text-base font-semibold text-gray-900">Servizi consigliati</h3>
                            <p class="mt-1 text-sm text-gray-500">Seleziona solo i punti fondamentali da approvare.</p>
                            <div class="mt-4 space-y-3">
                                <button v-for="item in proposal.recommended_services" :key="item.service_id" type="button" class="w-full rounded-[var(--radius-sm)] border p-4 text-left transition" :class="approvalForm.service_ids.includes(item.service_id) ? 'border-blue-300 bg-blue-50/80' : 'border-gray-200 bg-white/70 opacity-65'" @click="toggleService(item.service_id)">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ item.name }}</p>
                                            <p class="mt-1 text-sm leading-5 text-gray-600">{{ item.motivation }}</p>
                                        </div>
                                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="approvalForm.service_ids.includes(item.service_id) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400'">
                                            <Check class="h-3.5 w-3.5" :stroke-width="2.2" />
                                        </span>
                                    </div>
                                    <p class="mt-3 text-xs font-semibold text-gray-400">Confidenza {{ item.confidence }}%</p>
                                </button>
                            </div>
                        </section>

                        <section class="surface p-5">
                            <h3 class="text-base font-semibold text-gray-900">Strategia</h3>
                            <div class="mt-4 space-y-5">
                                <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Priorità</p><ol class="mt-2 space-y-2 text-sm text-gray-700"><li v-for="(item, index) in proposal.priorities" :key="item"><span class="mr-2 font-semibold text-gray-400">{{ index + 1 }}.</span>{{ item }}</li></ol></div>
                                <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Roadmap</p><ol class="mt-2 space-y-2 text-sm text-gray-700"><li v-for="(item, index) in proposal.roadmap" :key="item"><span class="mr-2 font-semibold text-gray-400">{{ index + 1 }}.</span>{{ item }}</li></ol></div>
                                <div v-if="proposal.key_characteristics?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Caratteristiche</p><ul class="mt-2 space-y-2 text-sm text-gray-700"><li v-for="item in proposal.key_characteristics" :key="item">{{ item }}</li></ul></div>
                            </div>
                        </section>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section v-if="proposal.autonomous_inferences?.length" class="surface p-5">
                            <div class="flex items-center gap-2"><Globe2 class="h-4 w-4 text-[hsl(var(--primary-app))]" :stroke-width="1.8" /><h3 class="text-base font-semibold text-gray-900">Inferenze del PM</h3></div>
                            <ul class="mt-3 space-y-2 text-sm leading-6 text-gray-600"><li v-for="item in proposal.autonomous_inferences" :key="item">{{ item }}</li></ul>
                        </section>
                        <section v-if="proposal.missing_information?.length" class="surface p-5">
                            <h3 class="text-base font-semibold text-gray-900">Informazioni mancanti</h3>
                            <ul class="mt-3 space-y-2 text-sm leading-6 text-gray-600"><li v-for="item in proposal.missing_information" :key="item">{{ item }}</li></ul>
                        </section>
                    </div>

                    <section v-if="isReady" class="surface flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                        <div><h3 class="text-base font-semibold text-gray-900">Approvazione strategica</h3><p class="mt-1 text-sm text-gray-500">Prepara i workflow dei servizi selezionati. Nessuna attività operativa verrà eseguita.</p></div>
                        <button type="button" class="btn btn-primary shrink-0" :disabled="approvalForm.processing || !approvalForm.service_ids.length" @click="approve"><CircleCheck class="h-4 w-4" :stroke-width="1.8" />Approva strategia</button>
                    </section>
                </template>

                <section v-if="isApproved" class="surface p-5">
                    <div class="flex items-start justify-between gap-3"><div><h3 class="text-base font-semibold text-gray-900">Workflow preparati</h3><p class="mt-1 text-sm text-gray-500">Proiezione operativa pronta. Gli agenti specialisti non sono ancora attivi.</p></div><span class="rounded-full bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-700">Approvata</span></div>
                    <div class="mt-5 space-y-5">
                        <div v-for="serviceId in approvedServices" :key="serviceId" class="rounded-[var(--radius-sm)] border border-gray-200 bg-white/70 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-semibold text-gray-900">{{ serviceById[serviceId]?.name || 'Servizio' }}</p><span class="text-xs font-semibold text-gray-400">{{ workflowMappings?.[serviceId] || 'Workflow da collegare' }}</span></div>
                            <div v-if="stepsByService[serviceId]?.length" class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                                <div v-for="step in stepsByService[serviceId]" :key="step.id" class="flex items-center gap-3 py-3">
                                    <CircleCheck v-if="step.status === 'completed'" class="h-4 w-4 shrink-0 text-green-600" :stroke-width="1.8" />
                                    <Clock3 v-else class="h-4 w-4 shrink-0" :class="step.status === 'blocked' ? 'text-gray-300' : 'text-[hsl(var(--primary-app))]'" :stroke-width="1.8" />
                                    <span class="min-w-0 flex-1 text-sm font-medium" :class="step.status === 'blocked' ? 'text-gray-400' : 'text-gray-700'">{{ step.name }}</span>
                                    <span class="text-xs font-semibold" :class="step.status === 'completed' ? 'text-green-600' : 'text-gray-400'">{{ stepStatusLabel[step.status] || step.status }}</span>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-sm text-amber-700">Collega questo servizio a un workflow nei moduli prima dell'esecuzione operativa.</p>
                        </div>
                    </div>
                </section>

                <section v-if="isApproved && activeStep" class="surface p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[hsl(var(--primary-app))]">Primo modulo operativo</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">Raccolta informazioni cliente</h3>
                            <p class="mt-1 text-sm text-gray-500">Completa il brief e valida gli allegati prima di sbloccare l’analisi cliente.</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600">{{ stepStatusLabel[activeStep.status] || activeStep.status }}</span>
                    </div>

                    <div v-if="$page.props.errors?.step" class="mt-4 rounded-[var(--radius-sm)] border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $page.props.errors.step }}</div>

                    <div v-if="activeStep.status === 'todo'" class="mt-5 rounded-[var(--radius-sm)] border border-dashed border-gray-200 bg-gray-50/70 p-6 text-center">
                        <p class="text-sm text-gray-600">L’avvio non utilizza crediti AI. Prepara il questionario dalle informazioni già mancanti.</p>
                        <button type="button" class="btn btn-primary mt-4" @click="startFirstStep">Avvia raccolta</button>
                    </div>

                    <form v-if="activeStep.status === 'in_progress'" class="mt-6 space-y-7" @submit.prevent="saveBrief">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Informazioni da completare</h4>
                            <div class="mt-3 space-y-5">
                                <label v-for="(question, index) in proposal.missing_information" :key="question" class="block">
                                    <span class="mb-2 block text-sm font-medium leading-5 text-gray-700">{{ question }}</span>
                                    <textarea v-model="briefForm.answers[index]" class="form-control min-h-24 resize-y" placeholder="Inserisci la risposta..." />
                                </label>
                            </div>
                        </div>

                        <div v-if="projectFiles?.length">
                            <h4 class="text-sm font-semibold text-gray-900">Pertinenza degli allegati</h4>
                            <p class="mt-1 text-sm text-gray-500">I file non pertinenti saranno esclusi dalle analisi successive.</p>
                            <div class="mt-3 divide-y divide-gray-100 border-y border-gray-100">
                                <div v-for="file in projectFiles" :key="file.id" class="grid gap-3 py-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-800">{{ file.name }}</p><p class="mt-0.5 text-xs text-gray-400">{{ file.mime_type }}</p></div>
                                    <div class="flex flex-wrap gap-2">
                                        <button v-for="option in assessmentOptions" :key="option.value" type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition" :class="briefForm.file_assessments[file.id] === option.value ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'" @click="briefForm.file_assessments[file.id] = option.value">{{ option.label }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" class="btn btn-outline" :disabled="briefForm.processing">Salva bozza</button>
                            <button type="button" class="btn btn-primary" :disabled="briefForm.processing" @click="submitBrief">Invia per approvazione</button>
                        </div>
                    </form>

                    <div v-if="activeStep.status === 'approval'" class="mt-5 rounded-[var(--radius-sm)] border border-blue-200 bg-blue-50/60 p-5">
                        <h4 class="font-semibold text-gray-900">Brief pronto</h4>
                        <p class="mt-1 text-sm text-gray-600">L’approvazione è il punto fondamentale che autorizza il passaggio all’Analisi Cliente.</p>
                        <div class="mt-4 flex flex-wrap justify-end gap-2"><button type="button" class="btn btn-outline" @click="saveBrief">Riapri</button><button type="button" class="btn btn-primary" @click="approveBrief">Approva brief</button></div>
                    </div>

                    <div v-if="activeStep.status === 'completed'" class="mt-5 flex items-center gap-3 rounded-[var(--radius-sm)] border border-green-200 bg-green-50/70 p-4 text-sm text-green-800"><CircleCheck class="h-5 w-5 shrink-0" :stroke-width="1.9" />Brief approvato. Il modulo Analisi Cliente è stato sbloccato.</div>
                </section>

                <section v-if="isApproved && activeStep?.status === 'completed'" class="surface p-5">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-[hsl(var(--primary-app))]">Fase autonoma</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">Analisi e strategia</h3>
                            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">Il PM usa brief, allegati e ricerca web per eseguire tre moduli senza richiedere altri input. Si fermerà soltanto sull’approvazione della strategia.</p>
                        </div>
                        <span v-if="strategyStep" class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600">{{ stepStatusLabel[strategyStep.status] || strategyStep.status }}</span>
                    </div>

                    <div v-if="$page.props.errors?.strategy" class="mt-4 rounded-[var(--radius-sm)] border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $page.props.errors.strategy }}</div>

                    <div v-if="clientAnalysisStep?.status === 'todo' && !strategicDocuments.length" class="mt-5 rounded-[var(--radius-sm)] border border-dashed border-gray-200 bg-gray-50/70 p-6 text-center">
                        <p class="text-sm text-gray-600">Una sola elaborazione produrrà tre documenti distinti, riducendo il consumo di token.</p>
                        <button type="button" class="btn btn-primary mt-4" :disabled="strategyForm.processing" @click="executeStrategy">
                            <Sparkles class="h-4 w-4" :stroke-width="1.8" />
                            {{ strategyForm.processing ? 'Elaborazione in corso...' : 'Avvia fase strategica' }}
                        </button>
                    </div>

                    <div v-if="strategicDocuments.length" class="mt-6 divide-y divide-gray-100 border-y border-gray-100">
                        <article v-for="document in strategicDocuments" :key="document.step.id" class="py-6 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-base font-semibold text-gray-900">{{ document.step.name }}</h4>
                                <span class="text-xs font-semibold" :class="document.step.status === 'completed' ? 'text-green-600' : 'text-blue-600'">{{ stepStatusLabel[document.step.status] }}</span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-gray-600">{{ document.content.summary }}</p>
                            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                                <div v-if="document.content.findings?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Evidenze</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.findings" :key="item">{{ item }}</li></ul></div>
                                <div v-if="document.content.recommendations?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Raccomandazioni</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.recommendations" :key="item">{{ item }}</li></ul></div>
                                <div v-if="document.content.risks?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Rischi</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.risks" :key="item">{{ item }}</li></ul></div>
                                <div v-if="document.content.assumptions?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Assunzioni</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.assumptions" :key="item">{{ item }}</li></ul></div>
                            </div>
                            <div v-if="document.content.sources?.length" class="mt-5"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Fonti</p><ul class="mt-2 space-y-1 text-xs text-gray-500"><li v-for="source in document.content.sources" :key="source" class="break-all">{{ source }}</li></ul></div>
                        </article>
                    </div>

                    <div v-if="strategyStep?.status === 'approval'" class="mt-6 flex flex-col gap-4 rounded-[var(--radius-sm)] border border-blue-200 bg-blue-50/60 p-5 md:flex-row md:items-center md:justify-between">
                        <div><h4 class="font-semibold text-gray-900">Approvazione strategica</h4><p class="mt-1 text-sm text-gray-600">Questo è il prossimo punto fondamentale. Dopo l’approvazione verrà sbloccata la sitemap.</p></div>
                        <button type="button" class="btn btn-primary shrink-0" :disabled="strategyForm.processing" @click="approveStrategy"><CircleCheck class="h-4 w-4" :stroke-width="1.8" />Approva strategia</button>
                    </div>
                </section>

                <div v-if="run.input_tokens" class="flex flex-wrap items-center gap-x-5 gap-y-2 px-1 text-xs text-gray-400">
                    <span>{{ run.input_tokens }} token input</span><span>{{ run.output_tokens }} token output</span><span>{{ run.web_searches }} ricerche web</span><span class="inline-flex items-center gap-1"><Euro class="h-3.5 w-3.5" /> costo stimato {{ Number(run.estimated_cost_eur).toFixed(4).replace('.', ',') }} €</span>
                </div>
            </div>
        </div>

        <div v-if="deleteOpen" class="fixed inset-0 z-[9000] flex items-center justify-center bg-black/20 px-4 backdrop-blur-sm" @click.self="deleteOpen = false">
            <div class="surface w-full max-w-md bg-white p-5">
                <h3 class="text-lg font-semibold text-gray-900">Eliminare questa analisi?</h3>
                <p class="mt-2 text-sm text-gray-500">Digita <span class="font-mono font-semibold">ELIMINA</span> per confermare.</p>
                <input v-model="deleteText" class="form-control mt-4 font-mono" placeholder="ELIMINA" />
                <div class="mt-5 flex justify-end gap-2"><button class="btn btn-outline" @click="deleteOpen = false">Annulla</button><button class="btn btn-danger" :disabled="deleteText !== 'ELIMINA'" @click="destroyRun">Elimina</button></div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
