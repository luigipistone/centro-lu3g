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
});

const analysisForm = useForm({});
const approvalForm = useForm({ service_ids: props.approvedServices?.length ? [...props.approvedServices] : (props.proposal?.recommended_services || []).map((item) => item.service_id) });
const deleteOpen = ref(false);
const deleteText = ref('');
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
                                <div v-for="step in stepsByService[serviceId]" :key="step.id" class="flex items-center gap-3 py-3"><Clock3 class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.8" /><span class="min-w-0 flex-1 text-sm font-medium text-gray-700">{{ step.name }}</span><span class="text-xs text-gray-400">{{ step.agent_role || 'Ruolo da assegnare' }}</span></div>
                            </div>
                            <p v-else class="mt-3 text-sm text-amber-700">Collega questo servizio a un workflow nei moduli prima dell'esecuzione operativa.</p>
                        </div>
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
