<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BrainCircuit, Check, ChevronLeft, CircleAlert, CircleCheck, Clock3, Euro, FileCheck2, Sparkles, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ run: Object, proposal: Object, brief: Object, approvedServices: Array, services: Array, steps: Array, budget: Object });
const analysisForm = useForm({});
const informationForm = useForm({ answers: {} });
const approvalForm = useForm({ service_ids: props.approvedServices?.length ? [...props.approvedServices] : (props.proposal?.recommended_services || []).map((item) => item.service_id) });
const deleteOpen = ref(false);
const deleteText = ref('');
const readiness = computed(() => props.proposal?.readiness || {});
const isReady = computed(() => props.run.status === 'proposal_ready');
const isApproved = computed(() => props.run.status === 'approved');
const serviceById = computed(() => Object.fromEntries((props.services || []).map((service) => [service.id, service])));
const stepsByService = computed(() => {
    const grouped = {};
    (props.steps || []).forEach((step) => { grouped[step.service_id] = [...(grouped[step.service_id] || []), step]; });
    return grouped;
});
const documents = computed(() => [
    { key: 'client_analysis', title: 'Analisi Cliente', content: props.proposal?.client_analysis },
    { key: 'competitor_analysis', title: 'Analisi Competitor', content: props.proposal?.competitor_analysis },
    { key: 'strategy', title: 'Strategia', content: props.proposal?.strategy },
].filter((item) => item.content?.summary));

function analyze() { analysisForm.post(route('ai-agency.analyze', props.run.id), { preserveScroll: true }); }
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
const statusLabel = { draft: 'Da analizzare', analyzing: 'Analisi in corso', needs_information: 'Informazioni necessarie', proposal_ready: 'Strategia pronta', approved: 'Strategia approvata', error: 'Da controllare' };
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
                    <section v-for="document in documents" :key="document.key" class="surface p-5">
                        <h3 class="text-lg font-semibold text-gray-900">{{ document.title }}</h3>
                        <p class="mt-3 max-w-5xl text-sm leading-6 text-gray-600">{{ document.content.summary }}</p>
                        <div class="mt-6 grid gap-6 lg:grid-cols-2">
                            <div v-if="document.content.findings?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Evidenze</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.findings" :key="item">{{ item }}</li></ul></div>
                            <div v-if="document.content.recommendations?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Raccomandazioni</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.recommendations" :key="item">{{ item }}</li></ul></div>
                            <div v-if="document.content.risks?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Rischi</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.risks" :key="item">{{ item }}</li></ul></div>
                            <div v-if="document.content.assumptions?.length"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Assunzioni</p><ul class="mt-2 space-y-2 text-sm leading-5 text-gray-700"><li v-for="item in document.content.assumptions" :key="item">{{ item }}</li></ul></div>
                        </div>
                        <div v-if="document.content.sources?.length" class="mt-5"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Fonti</p><ul class="mt-2 space-y-1 text-xs text-gray-500"><li v-for="source in document.content.sources" :key="source" class="break-all">{{ source }}</li></ul></div>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section class="surface p-5"><h3 class="text-base font-semibold text-gray-900">Priorità</h3><ol class="mt-4 space-y-3 text-sm text-gray-700"><li v-for="(item, index) in proposal.priorities" :key="item"><span class="mr-2 font-semibold text-gray-400">{{ index + 1 }}.</span>{{ item }}</li></ol></section>
                        <section class="surface p-5"><h3 class="text-base font-semibold text-gray-900">Roadmap strategica</h3><ol class="mt-4 space-y-3 text-sm text-gray-700"><li v-for="(item, index) in proposal.roadmap" :key="item"><span class="mr-2 font-semibold text-gray-400">{{ index + 1 }}.</span>{{ item }}</li></ol></section>
                    </div>

                    <section class="surface p-5">
                        <h3 class="text-lg font-semibold text-gray-900">Servizi risultanti dalle analisi</h3>
                        <p class="mt-1 text-sm text-gray-500">Questa è l’unica approvazione strategica. Modifica la selezione solo sui punti fondamentali.</p>
                        <div class="mt-5 grid gap-3 lg:grid-cols-2">
                            <button v-for="item in proposal.recommended_services" :key="item.service_id" type="button" class="rounded-[var(--radius-sm)] border p-4 text-left transition" :class="approvalForm.service_ids.includes(item.service_id) ? 'border-blue-300 bg-blue-50/80' : 'border-gray-200 bg-white/70 opacity-60'" @click="toggleService(item.service_id)">
                                <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-gray-900">{{ item.name }}</p><p class="mt-1 text-sm leading-5 text-gray-600">{{ item.motivation }}</p></div><span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="approvalForm.service_ids.includes(item.service_id) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400'"><Check class="h-3.5 w-3.5" /></span></div>
                                <p class="mt-3 text-xs font-semibold text-gray-400">Confidenza {{ item.confidence }}%</p>
                            </button>
                        </div>
                        <div v-if="isReady" class="mt-6 flex justify-end"><button type="button" class="btn btn-primary" :disabled="approvalForm.processing || !approvalForm.service_ids.length" @click="approve"><CircleCheck class="h-4 w-4" />Approva strategia e crea workflow</button></div>
                        <div v-if="isApproved" class="mt-6 flex items-center gap-3 rounded-[var(--radius-sm)] border border-green-200 bg-green-50 p-4 text-sm text-green-800"><CircleCheck class="h-5 w-5" />Strategia approvata. I workflow sono stati creati.</div>
                    </section>
                </template>

                <section v-if="isApproved && steps?.length" class="surface p-5">
                    <h3 class="text-lg font-semibold text-gray-900">Workflow operativi</h3>
                    <div class="mt-5 space-y-5">
                        <div v-for="serviceId in approvedServices" :key="serviceId">
                            <p class="font-semibold text-gray-900">{{ serviceById[serviceId]?.name || 'Servizio senza workflow collegato' }}</p>
                            <div v-if="stepsByService[serviceId]?.length" class="mt-2 divide-y divide-gray-100 border-y border-gray-100"><div v-for="step in stepsByService[serviceId]" :key="step.id" class="flex items-center gap-3 py-3"><Clock3 class="h-4 w-4 text-gray-400" /><span class="flex-1 text-sm text-gray-700">{{ step.name }}</span><span class="text-xs text-gray-400">{{ step.status === 'todo' ? 'Da fare' : 'Bloccato' }}</span></div></div>
                            <p v-else class="mt-2 text-sm text-amber-700">Nessun workflow collegato a questo servizio.</p>
                        </div>
                    </div>
                </section>

                <div v-if="run.input_tokens" class="flex flex-wrap items-center gap-x-5 gap-y-2 px-1 text-xs text-gray-400"><span>{{ run.input_tokens }} token input</span><span>{{ run.output_tokens }} token output</span><span>{{ run.web_searches }} ricerche web</span><span class="inline-flex items-center gap-1"><Euro class="h-3.5 w-3.5" />costo stimato {{ Number(run.estimated_cost_eur).toFixed(4).replace('.', ',') }} €</span></div>
            </div>
        </div>

        <div v-if="deleteOpen" class="fixed inset-0 z-[9000] flex items-center justify-center bg-black/20 px-4 backdrop-blur-sm" @click.self="deleteOpen = false"><div class="surface w-full max-w-md bg-white p-5"><h3 class="text-lg font-semibold text-gray-900">Eliminare questo processo?</h3><p class="mt-2 text-sm text-gray-500">Digita <span class="font-mono font-semibold">ELIMINA</span> per confermare.</p><input v-model="deleteText" class="form-control mt-4 font-mono" placeholder="ELIMINA" /><div class="mt-5 flex justify-end gap-2"><button class="btn btn-outline" @click="deleteOpen = false">Annulla</button><button class="btn btn-danger" :disabled="deleteText !== 'ELIMINA'" @click="destroyRun">Elimina</button></div></div></div>
    </AuthenticatedLayout>
</template>
