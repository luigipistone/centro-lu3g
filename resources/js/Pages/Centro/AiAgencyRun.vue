<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BrainCircuit, Check, ChevronDown, ChevronLeft, CircleAlert, CircleCheck, Clock3, Download, Euro, FileCheck2, LockKeyhole, Play, RotateCcw, Sparkles, Trash2 } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({ run: Object, proposal: Object, brief: Object, approvedServices: Array, services: Array, steps: Array, budget: Object });
const analysisForm = useForm({});
const informationForm = useForm({ answers: {} });
const approvalForm = useForm({ service_ids: props.approvedServices?.length ? [...props.approvedServices] : (props.proposal?.recommended_services || []).map((item) => item.service_id) });
const deleteOpen = ref(false);
const deleteText = ref('');
const expandedSections = ref({ client_analysis: true });
const expandedSteps = ref({});
const stepAnswers = ref({});
const autoRunning = ref(false);
const executionError = ref('');
const readiness = computed(() => props.proposal?.readiness || {});
const isReady = computed(() => props.run.status === 'proposal_ready');
const isApproved = computed(() => ['approved', 'operating', 'awaiting_information', 'operation_error', 'completed'].includes(props.run.status));
const serviceById = computed(() => Object.fromEntries((props.services || []).map((service) => [service.id, service])));
const stepsByService = computed(() => {
    const grouped = {};
    (props.steps || []).forEach((step) => { grouped[step.service_id] = [...(grouped[step.service_id] || []), step]; });
    return grouped;
});
const hasExecutableStep = computed(() => (props.steps || []).some((step) => ['todo', 'error'].includes(step.status)));
const needsStepInformation = computed(() => (props.steps || []).some((step) => step.status === 'needs_information'));
const completedStepCount = computed(() => (props.steps || []).filter((step) => step.status === 'completed').length);
const documents = computed(() => [
    { key: 'client_analysis', title: 'Analisi Cliente', content: props.proposal?.client_analysis },
    { key: 'competitor_analysis', title: 'Analisi Competitor', content: props.proposal?.competitor_analysis },
    { key: 'strategy', title: 'Strategia', content: props.proposal?.strategy },
].filter((item) => item.content?.summary));

function analyze() { analysisForm.post(route('ai-agency.analyze', props.run.id), { preserveScroll: true }); }
function toggleSection(key) { expandedSections.value[key] = !expandedSections.value[key]; }
function stepData(step) { try { return JSON.parse(step.output_data || '{}'); } catch { return {}; } }
function setStepAnswer(stepId, index, value) { stepAnswers.value[stepId] ||= {}; stepAnswers.value[stepId][index] = value; }
function reloadWorkflow() { return new Promise((resolve) => router.reload({ only: ['run', 'steps', 'budget'], preserveScroll: true, onFinish: resolve })); }
async function executeWorkflow() {
    if (autoRunning.value) return;
    autoRunning.value = true;
    executionError.value = '';
    try {
        let shouldContinue = true;
        while (shouldContinue) {
            const { data } = await axios.post(route('ai-agency.execute-next', props.run.id));
            shouldContinue = !!data.continue;
            await reloadWorkflow();
        }
    } catch (error) {
        executionError.value = error.response?.data?.message || 'Non è stato possibile eseguire la fase.';
        await reloadWorkflow();
    } finally {
        autoRunning.value = false;
    }
}
async function submitStepInformation(step) {
    executionError.value = '';
    try {
        const questions = stepData(step).questions || [];
        await axios.post(route('ai-agency.steps.information', [props.run.id, step.id]), { answers: questions.map((_, index) => stepAnswers.value[step.id]?.[index] || '') });
        await reloadWorkflow();
        executeWorkflow();
    } catch (error) {
        executionError.value = error.response?.data?.message || 'Completa tutte le informazioni indispensabili.';
    }
}
function provideInformation() { informationForm.post(route('ai-agency.information.store', props.run.id), { preserveScroll: true }); }
function toggleService(id) {
    if (!isReady.value) return;
    approvalForm.service_ids = approvalForm.service_ids.includes(id) ? approvalForm.service_ids.filter((value) => value !== id) : [...approvalForm.service_ids, id];
}
function approve() { approvalForm.post(route('ai-agency.approve', props.run.id), { preserveScroll: true }); }
function destroyRun() {
    if (deleteText.value !== 'ELIMINA') return;
    router.delete(route('ai-agency.destroy', props.run.id));
}
const statusLabel = { draft: 'Da analizzare', analyzing: 'Analisi in corso', needs_information: 'Informazioni necessarie', proposal_ready: 'Strategia pronta', approved: 'Strategia approvata', operating: 'Workflow operativo', awaiting_information: 'Informazione indispensabile', operation_error: 'Esecuzione da controllare', completed: 'Processo completato', error: 'Da controllare' };
</script>

<template>
    <Head :title="`Agenzia AI · ${run.project_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <Link :href="route('ai-agency.index')" class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ChevronLeft class="h-3.5 w-3.5" />Agenzia AI</Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ run.project_name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ run.client_name || 'Processo strategico del progetto' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600">{{ statusLabel[run.status] || run.status }}</span>
                    <button type="button" class="icon-btn text-red-600 hover:bg-red-50" title="Elimina processo" @click="deleteOpen = true"><Trash2 class="h-4 w-4" /></button>
                    <button v-if="['draft', 'error'].includes(run.status)" type="button" class="btn btn-primary" :disabled="analysisForm.processing" @click="analyze"><Sparkles class="h-4 w-4" />{{ analysisForm.processing ? 'Analisi in corso...' : (brief?.questions?.length ? 'Riprendi analisi completa' : 'Avvia analisi completa') }}</button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.errors?.analysis || run.error_message" class="surface flex gap-3 border-red-200 bg-red-50/95 p-4 text-sm text-red-800"><CircleAlert class="h-5 w-5 shrink-0" /><span>{{ $page.props.errors?.analysis || run.error_message }}</span></div>

                <section v-if="run.status === 'draft'" class="surface p-8 text-center">
                    <BrainCircuit class="mx-auto h-9 w-9 text-[hsl(var(--primary-app))]" :stroke-width="1.6" />
                    <h3 class="mt-3 text-lg font-semibold text-gray-900">Acquisizione e analisi completa</h3>
                    <p class="mx-auto mt-2 max-w-3xl text-sm leading-6 text-gray-500">Il PM leggerà progetto e allegati, controllerà la qualità delle fonti e procederà autonomamente con Cliente, Competitor e Strategia. Farà domande soltanto in presenza di informazioni realmente bloccanti.</p>
                </section>

                <section v-if="readiness.summary" class="surface p-5">
                    <div class="flex items-start gap-3">
                        <span class="section-icon"><FileCheck2 class="h-4 w-4" /></span>
                        <div class="min-w-0 flex-1"><h3 class="text-base font-semibold text-gray-900">Verifica delle fonti</h3><p class="mt-2 text-sm leading-6 text-gray-600">{{ readiness.summary }}</p></div>
                    </div>
                    <div v-if="readiness.conflicts?.length" class="mt-5"><p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Incongruenze rilevate</p><ul class="mt-2 space-y-2 text-sm text-gray-700"><li v-for="item in readiness.conflicts" :key="item">{{ item }}</li></ul></div>
                    <div v-if="readiness.document_assessments?.length" class="mt-5 divide-y divide-gray-100 border-y border-gray-100">
                        <div v-for="file in readiness.document_assessments" :key="file.name" class="grid gap-2 py-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"><div><p class="text-sm font-semibold text-gray-800">{{ file.name }}</p><p class="mt-1 text-xs text-gray-500">{{ file.reason }}</p></div><span class="text-xs font-semibold text-gray-500">{{ file.assessment }}</span></div>
                    </div>
                </section>

                <section v-if="run.status === 'needs_information'" class="surface p-5">
                    <h3 class="text-lg font-semibold text-gray-900">Informazioni indispensabili</h3>
                    <p class="mt-1 text-sm text-gray-500">Il PM non può prendere decisioni affidabili senza questi elementi. Dopo l’invio riprenderà automaticamente l’analisi completa.</p>
                    <div v-if="$page.props.errors?.information" class="mt-4 rounded-[var(--radius-sm)] border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $page.props.errors.information }}</div>
                    <form class="mt-6 space-y-5" @submit.prevent="provideInformation">
                        <label v-for="(question, index) in readiness.blocking_questions" :key="question" class="block"><span class="mb-2 block text-sm font-medium leading-5 text-gray-700">{{ question }}</span><textarea v-model="informationForm.answers[index]" class="form-control min-h-24 resize-y" placeholder="Inserisci la risposta..." /></label>
                        <div class="flex justify-end"><button type="submit" class="btn btn-primary" :disabled="informationForm.processing"><Sparkles class="h-4 w-4" />{{ informationForm.processing ? 'Analisi in corso...' : 'Integra e riprendi analisi' }}</button></div>
                    </form>
                </section>

                <template v-if="documents.length">
                    <section v-for="document in documents" :key="document.key" class="surface overflow-hidden">
                        <button type="button" class="flex w-full items-center justify-between gap-4 p-5 text-left" :aria-expanded="!!expandedSections[document.key]" @click="toggleSection(document.key)">
                            <h3 class="text-lg font-semibold text-gray-900">{{ document.title }}</h3>
                            <ChevronDown class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200" :class="expandedSections[document.key] ? 'rotate-180' : ''" />
                        </button>
                        <div v-show="expandedSections[document.key]" class="border-t border-gray-100 px-5 pb-5 pt-4">
                        <p class="max-w-5xl text-sm leading-6 text-gray-600">{{ document.content.summary }}</p>
                        <div class="mt-6 grid gap-6 lg:grid-cols-2">
                            <div v-if="document.content.findings?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Evidenze</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.findings" :key="item">{{ item }}</li></ul></div>
                            <div v-if="document.content.recommendations?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Raccomandazioni</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.recommendations" :key="item">{{ item }}</li></ul></div>
                            <div v-if="document.content.risks?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Rischi</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.risks" :key="item">{{ item }}</li></ul></div>
                            <div v-if="document.content.assumptions?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Assunzioni</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.assumptions" :key="item">{{ item }}</li></ul></div>
                        </div>
                        <div v-if="document.content.sources?.length" class="mt-5"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Fonti</p><ul class="mt-2 space-y-1 text-xs text-gray-500"><li v-for="source in document.content.sources" :key="source" class="break-all">{{ source }}</li></ul></div>
                        </div>
                    </section>

                    <div class="space-y-6">
                        <section class="surface overflow-hidden"><button type="button" class="flex w-full items-center justify-between gap-4 p-5 text-left" @click="toggleSection('priorities')"><h3 class="text-base font-semibold text-gray-900">Priorità</h3><ChevronDown class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="expandedSections.priorities ? 'rotate-180' : ''" /></button><ol v-show="expandedSections.priorities" class="space-y-3 border-t border-gray-100 px-5 pb-5 pt-4 text-sm text-gray-700"><li v-for="(item, index) in proposal.priorities" :key="item"><span class="mr-2 font-semibold text-gray-400">{{ index + 1 }}.</span>{{ item }}</li></ol></section>
                        <section class="surface overflow-hidden"><button type="button" class="flex w-full items-center justify-between gap-4 p-5 text-left" @click="toggleSection('roadmap')"><h3 class="text-base font-semibold text-gray-900">Roadmap strategica</h3><ChevronDown class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="expandedSections.roadmap ? 'rotate-180' : ''" /></button><ol v-show="expandedSections.roadmap" class="space-y-3 border-t border-gray-100 px-5 pb-5 pt-4 text-sm text-gray-700"><li v-for="(item, index) in proposal.roadmap" :key="item"><span class="mr-2 font-semibold text-gray-400">{{ index + 1 }}.</span>{{ item }}</li></ol></section>
                    </div>

                    <section class="surface overflow-hidden">
                        <button type="button" class="flex w-full items-center justify-between gap-4 p-5 text-left" @click="toggleSection('services')"><h3 class="text-lg font-semibold text-gray-900">Servizi risultanti dalle analisi</h3><ChevronDown class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="expandedSections.services ? 'rotate-180' : ''" /></button>
                        <div v-show="expandedSections.services" class="border-t border-gray-100 px-5 pb-5 pt-4">
                        <p class="mt-1 text-sm text-gray-500">Questa è l’unica approvazione strategica. Modifica la selezione solo sui punti fondamentali.</p>
                        <div class="mt-5 grid gap-3 lg:grid-cols-2">
                            <button v-for="item in proposal.recommended_services" :key="item.service_id" type="button" class="rounded-[var(--radius-sm)] border p-4 text-left transition" :class="approvalForm.service_ids.includes(item.service_id) ? 'border-blue-300 bg-blue-50/80' : 'border-gray-200 bg-white/70 opacity-60'" @click="toggleService(item.service_id)">
                                <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-gray-900">{{ item.name }}</p><p class="mt-1 text-sm leading-5 text-gray-600">{{ item.motivation }}</p></div><span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="approvalForm.service_ids.includes(item.service_id) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400'"><Check class="h-3.5 w-3.5" /></span></div>
                                <p class="mt-3 text-xs font-semibold text-gray-400">Confidenza {{ item.confidence }}%</p>
                            </button>
                        </div>
                        <div v-if="isReady" class="mt-6 flex justify-end"><button type="button" class="btn btn-primary" :disabled="approvalForm.processing || !approvalForm.service_ids.length" @click="approve"><CircleCheck class="h-4 w-4" />Approva strategia e crea workflow</button></div>
                        <div v-if="isApproved" class="mt-6 flex items-center gap-3 rounded-[var(--radius-sm)] border border-green-200 bg-green-50 p-4 text-sm text-green-800"><CircleCheck class="h-5 w-5" />Strategia approvata. I workflow sono stati creati.</div>
                        </div>
                    </section>
                </template>

                <section v-if="isApproved && steps?.length" class="surface overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-5"><button type="button" class="flex min-w-0 flex-1 items-center justify-between gap-4 text-left" @click="toggleSection('workflows')"><div><h3 class="text-lg font-semibold text-gray-900">Workflow operativi</h3><p class="mt-1 text-xs text-gray-400">{{ completedStepCount }} di {{ steps.length }} fasi completate</p></div><ChevronDown class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="expandedSections.workflows ? 'rotate-180' : ''" /></button><button v-if="hasExecutableStep" type="button" class="btn btn-primary" :disabled="autoRunning || needsStepInformation" @click="executeWorkflow"><RotateCcw v-if="autoRunning" class="h-4 w-4 animate-spin" /><Play v-else class="h-4 w-4" />{{ autoRunning ? 'Esecuzione automatica...' : (run.status === 'operation_error' ? 'Riprendi workflow' : 'Avvia workflow') }}</button></div>
                    <div v-show="expandedSections.workflows" class="space-y-5 border-t border-gray-100 px-5 pb-5 pt-4">
                        <div v-if="executionError" class="rounded-[var(--radius-sm)] border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ executionError }}</div>
                        <div v-for="serviceId in approvedServices" :key="serviceId">
                            <p class="font-semibold text-gray-900">{{ serviceById[serviceId]?.name || 'Servizio senza workflow collegato' }}</p>
                            <div v-if="stepsByService[serviceId]?.length" class="mt-2 divide-y divide-gray-100 border-y border-gray-100">
                                <div v-for="step in stepsByService[serviceId]" :key="step.id" class="py-3">
                                    <div class="flex w-full items-center gap-3 text-left" :class="[step.status === 'completed' && !stepData(step).content ? 'opacity-65' : '', stepData(step).content ? 'cursor-pointer' : '']" @click="stepData(step).content && (expandedSteps[step.id] = !expandedSteps[step.id])">
                                        <CircleCheck v-if="step.status === 'completed'" class="h-4 w-4 shrink-0 text-green-600" /><RotateCcw v-else-if="step.status === 'running'" class="h-4 w-4 shrink-0 animate-spin text-[hsl(var(--primary-app))]" /><Clock3 v-else-if="['todo', 'error', 'needs_information'].includes(step.status)" class="h-4 w-4 shrink-0 text-[hsl(var(--primary-app))]" /><LockKeyhole v-else class="h-4 w-4 shrink-0 text-gray-400" />
                                        <span class="flex-1 text-sm text-gray-700">{{ step.name }}</span><span class="text-xs font-medium" :class="step.status === 'completed' ? 'text-green-600' : (step.status === 'error' ? 'text-red-600' : 'text-gray-400')">{{ step.status === 'completed' ? 'Completato' : (step.status === 'todo' ? 'Da fare' : (step.status === 'running' ? 'In corso' : (step.status === 'needs_information' ? 'Informazione necessaria' : (step.status === 'error' ? 'Da riprendere' : 'Bloccato')))) }}</span><a v-if="stepData(step).content" :href="route('ai-agency.steps.pdf', [run.id, step.id])" target="_blank" class="icon-btn h-8 w-8" title="Apri PDF" @click.stop><Download class="h-4 w-4" /></a><ChevronDown v-if="stepData(step).content" class="h-4 w-4 text-gray-400 transition-transform" :class="expandedSteps[step.id] ? 'rotate-180' : ''" />
                                    </div>
                                    <div v-if="step.status === 'error' && step.error_message" class="ml-7 mt-2 text-xs text-red-600">{{ step.error_message }}</div>
                                    <div v-show="expandedSteps[step.id] && stepData(step).content" class="ml-7 mt-3 whitespace-pre-wrap rounded-[var(--radius-sm)] bg-gray-50 p-4 text-sm leading-6 text-gray-700">{{ stepData(step).content }}</div>
                                    <form v-if="step.status === 'needs_information'" class="ml-7 mt-4 space-y-4 rounded-[var(--radius-sm)] border border-amber-200 bg-amber-50/70 p-4" @submit.prevent="submitStepInformation(step)"><p class="text-sm font-semibold text-amber-900">Servono solo queste informazioni indispensabili</p><label v-for="(question, index) in stepData(step).questions" :key="question" class="block"><span class="mb-1.5 block text-sm text-amber-900">{{ question }}</span><textarea :value="stepAnswers[step.id]?.[index] || ''" class="form-control min-h-20 bg-white" @input="setStepAnswer(step.id, index, $event.target.value)" /></label><div class="flex justify-end"><button type="submit" class="btn btn-primary">Invia e riprendi automaticamente</button></div></form>
                                </div>
                            </div>
                            <p v-else class="mt-2 text-sm text-amber-700">Nessun workflow collegato a questo servizio.</p>
                        </div>
                        <div v-if="run.status === 'completed'" class="flex items-center gap-3 rounded-[var(--radius-sm)] border border-green-200 bg-green-50 p-4 text-sm text-green-800"><CircleCheck class="h-5 w-5" />Tutti i workflow disponibili sono stati completati.</div>
                    </div>
                </section>

                <div v-if="run.input_tokens" class="flex flex-wrap items-center gap-x-5 gap-y-2 px-1 text-xs text-gray-400"><span>{{ run.input_tokens }} token input</span><span>{{ run.output_tokens }} token output</span><span>{{ run.web_searches }} ricerche web</span><span class="inline-flex items-center gap-1"><Euro class="h-3.5 w-3.5" />costo stimato {{ Number(run.estimated_cost_eur).toFixed(4).replace('.', ',') }} €</span></div>
            </div>
        </div>

        <div v-if="deleteOpen" class="fixed inset-0 z-[9000] flex items-center justify-center bg-black/20 px-4 backdrop-blur-sm" @click.self="deleteOpen = false"><div class="surface w-full max-w-md bg-white p-5"><h3 class="text-lg font-semibold text-gray-900">Eliminare questo processo?</h3><p class="mt-2 text-sm text-gray-500">Digita <span class="font-mono font-semibold">ELIMINA</span> per confermare.</p><input v-model="deleteText" class="form-control mt-4 font-mono" placeholder="ELIMINA" /><div class="mt-5 flex justify-end gap-2"><button class="btn btn-outline" @click="deleteOpen = false">Annulla</button><button class="btn btn-danger" :disabled="deleteText !== 'ELIMINA'" @click="destroyRun">Elimina</button></div></div></div>
    </AuthenticatedLayout>
</template>
