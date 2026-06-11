<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    section: String,
    title: String,
    record: Object,
    related: Object,
});

const labels = {
    name: 'Nome',
    legal_name: 'Ragione sociale',
    email: 'Email',
    phone: 'Telefono',
    city: 'Citta',
    province: 'Provincia',
    vat_number: 'Partita IVA',
    tax_code: 'Codice fiscale',
    website: 'Sito web',
    status: 'Stato',
    priority: 'Priorita',
    task_type: 'Tipo',
    start_date: 'Inizio',
    due_date: 'Scadenza',
    due_time: 'Ora',
    recurring_enabled: 'Ricorrente',
    recurring_mode: 'Modalita ricorrenza',
    recurring_interval_value: 'Ogni',
    recurring_interval_unit: 'Unita ricorrenza',
    recurring_weekday: 'Giorno settimana',
    recurring_month_day: 'Giorno mese',
    parent_task_id: 'Task padre',
    project_id: 'Progetto',
    client_id: 'Cliente',
    service_id: 'Servizio',
    description: 'Descrizione',
    notes: 'Note',
};

const valueLabels = {
    active: 'Attivo',
    completed: 'Completato',
    on_hold: 'In pausa',
    archived: 'Archiviato',
    todo: 'Da fare',
    in_progress: 'In corso',
    in_review: 'Review',
    done: 'Fatte',
    low: 'Bassa',
    medium: 'Media',
    high: 'Alta',
    urgent: 'Urgente',
    project: 'Task',
    task: 'Task',
    ongoing: 'Continuativa',
    meeting: 'Meeting',
    draft: 'Bozza',
    sent: 'Inviato',
    accepted: 'Accettato',
    rejected: 'Rifiutato',
    paid: 'Pagato',
    partially_paid: 'Parziale',
    overdue: 'Scaduto',
    cancelled: 'Annullato',
    week: 'Settimana',
    month: 'Mese',
    fixed: 'Fissa',
    relative: 'Relativa',
    0: 'No',
    1: 'Si',
};

function displayValue(value) {
    if (value === true) return 'Si';
    if (value === false) return 'No';
    return valueLabels[value] || value || '-';
}

const visibleEntries = Object.entries(props.record).filter(([key, value]) =>
    !['id', 'created_by', 'updated_at', 'created_at', 'password', 'remember_token'].includes(key)
    && value !== null
    && value !== ''
);

const commentForm = useForm({ content: '' });
const lineForm = useForm({
    description: '',
    quantity: 1,
    unit_price: 0,
    vat_rate: 22,
    discount_pct: 0,
});
const paymentForm = useForm({
    amount: props.record.total_amount || 0,
    paid_at: new Date().toISOString().slice(0, 10),
    method: '',
    notes: '',
});
const documentForm = useForm({
    issue_date: props.record.issue_date || new Date().toISOString().slice(0, 10),
    due_date: props.record.due_date || '',
    status: props.record.status || 'draft',
    payment_method: props.record.payment_method || '',
    payment_terms_days: props.record.payment_terms_days || '',
    causale: props.record.causale || '',
    notes: props.record.notes || '',
    footer_notes: props.record.footer_notes || '',
    withholding_pct: props.record.withholding_pct || 0,
    pension_fund_pct: props.record.pension_fund_pct || 0,
    pension_fund_label: props.record.pension_fund_label || '',
});
const emailForm = useForm({
    recipient: props.related?.client?.pec || props.related?.client?.email || '',
    cc: '',
    subject: '',
    message: '',
    include_xml: ['fattura', 'nota_credito'].includes(props.record.doc_type),
});
const contactForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: '',
    notes: '',
});
const subscriptionDefaults = {
    name: '',
    description: '',
    amount: 0,
    vat_rate: 22,
    vat_nature_code: '',
    frequency_value: 1,
    frequency_unit: 'month',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    next_invoice_date: new Date().toISOString().slice(0, 10),
    payment_terms_days: props.record.payment_terms_days || 30,
    auto_generate: true,
    active: true,
    notes: '',
};
const subscriptionForm = useForm({ ...subscriptionDefaults });
const editingSubscription = ref(null);
const subtaskForm = useForm({
    title: '',
    priority: 'medium',
    due_date: '',
});
const selectedAssignees = ref([...(props.related.assignees || [])]);
const selectedFollowers = ref([...(props.related.followers || [])]);

function addComment() {
    commentForm.post(route('tasks.comments.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
}

function setTaskStatus(status) {
    router.patch(route('tasks.status.update', props.record.id), { status }, { preserveScroll: true });
}

function priorityClass(priority) {
    return {
        urgent: 'bg-red-100 text-red-700',
        high: 'bg-orange-100 text-orange-700',
        medium: 'bg-amber-100 text-amber-700',
        low: 'bg-emerald-100 text-emerald-700',
    }[priority] || 'bg-gray-100 text-gray-700';
}

function setSubtaskStatus(subtask, done) {
    router.patch(route('tasks.status.update', subtask.id), { status: done ? 'done' : 'todo' }, { preserveScroll: true });
}

function addLine() {
    lineForm.post(route('billing.lines.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => lineForm.reset(),
    });
}

function removeLine(line) {
    if (!confirm('Eliminare questa riga?')) return;
    router.delete(route('billing.lines.destroy', [props.record.id, line.id]), { preserveScroll: true });
}

function addPayment() {
    paymentForm.post(route('billing.payments.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset(),
    });
}

function removePayment(payment) {
    if (!confirm('Eliminare questo pagamento?')) return;
    router.delete(route('billing.payments.destroy', [props.record.id, payment.id]), { preserveScroll: true });
}

function saveDocument() {
    documentForm.put(route('billing.header.update', props.record.id), { preserveScroll: true });
}

function issueDocument() {
    if (!confirm('Emettere il documento e assegnare un numero progressivo?')) return;
    router.post(route('billing.issue', props.record.id), {}, { preserveScroll: true });
}

function duplicateDocument() {
    router.post(route('billing.duplicate', props.record.id));
}

function convertDocument(type) {
    router.post(route('billing.convert', [props.record.id, type]));
}

function printDocument() {
    window.print();
}

function sendDocumentEmail() {
    emailForm.post(route('billing.email', props.record.id), { preserveScroll: true });
}

function addContact() {
    contactForm.post(route('clients.contacts.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => contactForm.reset(),
    });
}

function removeContact(contact) {
    if (!confirm('Eliminare questo referente?')) return;
    router.delete(route('clients.contacts.destroy', [props.record.id, contact.id]), { preserveScroll: true });
}

function resetSubscriptionForm() {
    editingSubscription.value = null;
    subscriptionForm.clearErrors();
    subscriptionForm.defaults({ ...subscriptionDefaults });
    subscriptionForm.reset();
    Object.assign(subscriptionForm, { ...subscriptionDefaults });
}

function editSubscription(subscription) {
    editingSubscription.value = subscription;
    subscriptionForm.clearErrors();
    Object.keys(subscriptionDefaults).forEach((key) => {
        subscriptionForm[key] = subscription[key] ?? subscriptionDefaults[key];
    });
}

function saveSubscription() {
    if (editingSubscription.value) {
        subscriptionForm.put(route('clients.subscriptions.update', [props.record.id, editingSubscription.value.id]), {
            preserveScroll: true,
            onSuccess: resetSubscriptionForm,
        });
        return;
    }

    subscriptionForm.post(route('clients.subscriptions.store', props.record.id), {
        preserveScroll: true,
        onSuccess: resetSubscriptionForm,
    });
}

function toggleSubscription(subscription) {
    router.patch(route('clients.subscriptions.active', [props.record.id, subscription.id]), { active: !subscription.active }, { preserveScroll: true });
}

function generateSubscription(subscription) {
    router.post(route('clients.subscriptions.generate', [props.record.id, subscription.id]));
}

function removeSubscription(subscription) {
    if (!confirm(`Eliminare l'abbonamento "${subscription.name}"?`)) return;
    router.delete(route('clients.subscriptions.destroy', [props.record.id, subscription.id]), { preserveScroll: true });
}

function clientHasService(service) {
    return (props.related.clientServices || []).includes(service.id);
}

function toggleService(service) {
    if (clientHasService(service)) {
        router.delete(route('clients.services.detach', [props.record.id, service.id]), { preserveScroll: true });
        return;
    }

    router.post(route('clients.services.attach', [props.record.id, service.id]), {}, { preserveScroll: true });
}

function togglePerson(list, userId) {
    const index = list.value.indexOf(userId);
    if (index >= 0) {
        list.value.splice(index, 1);
        return;
    }

    list.value.push(userId);
}

function saveTaskPeople(type) {
    const list = type === 'assignees' ? selectedAssignees.value : selectedFollowers.value;
    router.put(route('tasks.people.sync', [props.record.id, type]), { user_ids: list }, { preserveScroll: true });
}

function addSubtask() {
    subtaskForm.post(route('tasks.subtasks.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => subtaskForm.reset(),
    });
}

function paymentTermsLabel(days) {
    if (!days) return null;
    return `${days} giorni`;
}

function fullClientAddress(record) {
    return [
        [record.street, record.street_number].filter(Boolean).join(' '),
        [record.postal_code, record.city, record.province ? `(${record.province})` : null].filter(Boolean).join(' '),
        record.country,
    ].filter(Boolean).join(' - ') || record.address;
}

function money(value) {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
}

function subscriptionFrequency(subscription) {
    const unit = subscription.frequency_unit === 'year' ? 'anno' : 'mese';
    const plural = Number(subscription.frequency_value) > 1 ? (subscription.frequency_unit === 'year' ? 'anni' : 'mesi') : unit;
    return `ogni ${subscription.frequency_value} ${plural}`;
}

function dateIt(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('it-IT');
}

function docTypeLabel(type) {
    return {
        preventivo: 'Preventivo',
        proforma: 'Proforma',
        fattura: 'Fattura',
        nota_credito: 'Nota credito',
    }[type] || type;
}

function docStatusLabel(status) {
    return {
        draft: 'Bozza',
        sent: 'Inviato',
        accepted: 'Accettato',
        rejected: 'Rifiutato',
        paid: 'Pagato',
        partially_paid: 'Parziale',
        overdue: 'Scaduto',
        cancelled: 'Annullato',
    }[status] || status;
}

function statusClass(status) {
    return {
        draft: 'bg-gray-100 text-gray-700',
        sent: 'bg-sky-100 text-sky-700',
        accepted: 'bg-indigo-100 text-indigo-700',
        rejected: 'bg-red-100 text-red-700',
        paid: 'bg-emerald-100 text-emerald-700',
        partially_paid: 'bg-amber-100 text-amber-700',
        overdue: 'bg-rose-100 text-rose-700',
        cancelled: 'bg-slate-100 text-slate-600',
    }[status] || 'bg-gray-100 text-gray-700';
}

function remainingAmount() {
    return Number(props.record.total_amount || 0) - Number(props.record.total_paid || 0);
}
</script>

<template>
    <Head :title="record.name || record.title || title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <Link :href="route(`${section}.index`)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        {{ title }}
                    </Link>
                    <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-800">
                        {{ record.name || record.title || record.number }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div v-if="section === 'billing'" class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4 rounded-md bg-white p-5 shadow-sm">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-gray-900">{{ docTypeLabel(record.doc_type) }}</h2>
                            <span class="font-mono text-sm text-gray-500">{{ record.number || 'bozza' }}</span>
                            <span :class="['rounded-full px-2 py-1 text-xs font-medium', statusClass(record.status)]">{{ docStatusLabel(record.status) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ related.client?.legal_name || related.client?.name || 'Cliente' }} · {{ dateIt(record.issue_date) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500" @click="saveDocument">Salva</button>
                        <button v-if="!record.number" type="button" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100" @click="issueDocument">Emetti</button>
                        <a :href="route('billing.pdf', record.id)" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Scarica PDF</a>
                        <a v-if="['fattura', 'nota_credito'].includes(record.doc_type)" :href="route('billing.xml', record.id)" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Scarica XML</a>
                        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printDocument">Stampa</button>
                        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="duplicateDocument">Duplica</button>
                        <button v-if="record.doc_type === 'preventivo'" type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="convertDocument('fattura')">Converti fattura</button>
                        <button v-if="record.doc_type === 'proforma'" type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="convertDocument('fattura')">Converti fattura</button>
                        <button v-if="record.doc_type === 'fattura'" type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="convertDocument('nota_credito')">Nota credito</button>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <div class="space-y-6">
                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Dati documento</h3>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Data emissione</label>
                                    <input v-model="documentForm.issue_date" type="date" class="form-control" required />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                                    <input v-model="documentForm.due_date" type="date" class="form-control" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stato</label>
                                    <select v-model="documentForm.status" class="form-control">
                                        <option value="draft">Bozza</option>
                                        <option value="sent">Inviato</option>
                                        <option value="accepted">Accettato</option>
                                        <option value="rejected">Rifiutato</option>
                                        <option value="paid">Pagato</option>
                                        <option value="partially_paid">Parziale</option>
                                        <option value="overdue">Scaduto</option>
                                        <option value="cancelled">Annullato</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Termini pagamento</label>
                                    <input v-model="documentForm.payment_terms_days" type="number" min="0" class="form-control" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Metodo pagamento</label>
                                    <input v-model="documentForm.payment_method" class="form-control" placeholder="Bonifico, MP05..." />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cassa previdenziale %</label>
                                    <input v-model="documentForm.pension_fund_pct" type="number" step="0.01" min="0" class="form-control" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Ritenuta %</label>
                                    <input v-model="documentForm.withholding_pct" type="number" step="0.01" min="0" class="form-control" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Causale</label>
                                    <input v-model="documentForm.causale" class="form-control" />
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Note interne</label>
                                    <textarea v-model="documentForm.notes" rows="2" class="form-control"></textarea>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Note visibili al cliente</label>
                                    <textarea v-model="documentForm.footer_notes" rows="3" class="form-control"></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <div class="mb-5 flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Righe documento</h3>
                                <div class="text-sm font-semibold text-gray-900">{{ money(record.total_amount) }}</div>
                            </div>
                            <form class="mb-5 grid gap-3 md:grid-cols-[1fr_90px_120px_90px_90px_auto]" @submit.prevent="addLine">
                                <input v-model="lineForm.description" class="form-control mt-0" placeholder="Descrizione" required />
                                <input v-model="lineForm.quantity" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <input v-model="lineForm.unit_price" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <input v-model="lineForm.vat_rate" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <input v-model="lineForm.discount_pct" class="form-control mt-0" type="number" step="0.01" min="0" max="100" />
                                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                            </form>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Descrizione</th>
                                            <th class="px-3 py-2 text-right">Qta</th>
                                            <th class="px-3 py-2 text-right">Prezzo</th>
                                            <th class="px-3 py-2 text-right">IVA</th>
                                            <th class="px-3 py-2 text-right">Subtotale</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="line in related.lines" :key="line.id">
                                            <td class="px-3 py-2">{{ line.description }}</td>
                                            <td class="px-3 py-2 text-right">{{ line.quantity }}</td>
                                            <td class="px-3 py-2 text-right">{{ money(line.unit_price) }}</td>
                                            <td class="px-3 py-2 text-right">{{ line.vat_rate }}%</td>
                                            <td class="px-3 py-2 text-right">{{ money(line.subtotal) }}</td>
                                            <td class="px-3 py-2 text-right"><button class="text-red-600" @click="removeLine(line)">Elimina</button></td>
                                        </tr>
                                        <tr v-if="!related.lines?.length">
                                            <td colspan="6" class="px-3 py-8 text-center text-gray-500">Nessuna riga.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Pagamenti</h3>
                            <form class="mb-5 grid gap-3 md:grid-cols-[120px_150px_1fr_auto]" @submit.prevent="addPayment">
                                <input v-model="paymentForm.amount" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <input v-model="paymentForm.paid_at" class="form-control mt-0" type="date" required />
                                <input v-model="paymentForm.method" class="form-control mt-0" placeholder="Metodo" />
                                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Registra</button>
                            </form>
                            <div class="space-y-2">
                                <div v-for="payment in related.payments" :key="payment.id" class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 text-sm">
                                    <span>{{ money(payment.amount) }} · {{ dateIt(payment.paid_at) }} · {{ payment.method || '-' }}</span>
                                    <button class="text-red-600" @click="removePayment(payment)">Elimina</button>
                                </div>
                                <p v-if="!related.payments?.length" class="text-sm text-gray-500">Nessun pagamento registrato.</p>
                            </div>
                        </section>
                    </div>

                    <aside class="space-y-6">
                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Riepilogo</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span>Imponibile</span><span>{{ money(record.total_taxable) }}</span></div>
                                <div class="flex justify-between"><span>IVA</span><span>{{ money(record.total_vat) }}</span></div>
                                <div class="flex justify-between"><span>Cassa</span><span>{{ money(record.total_pension_fund) }}</span></div>
                                <div class="flex justify-between"><span>Ritenuta</span><span class="text-red-600">-{{ money(record.total_withholding) }}</span></div>
                                <div class="my-3 border-t border-gray-100"></div>
                                <div class="flex justify-between text-lg font-semibold"><span>Totale</span><span>{{ money(record.total_amount) }}</span></div>
                                <div class="flex justify-between"><span>Pagato</span><span>{{ money(record.total_paid) }}</span></div>
                                <div class="flex justify-between font-semibold"><span>Residuo</span><span :class="remainingAmount() > 0 ? 'text-red-600' : 'text-emerald-600'">{{ money(remainingAmount()) }}</span></div>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Anteprima</h3>
                            <div class="rounded-md border border-gray-200 bg-white p-5 text-sm">
                                <div class="flex justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-gray-900">Centro LU3G</div>
                                        <div class="text-xs text-gray-500">Documento commerciale</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900">{{ docTypeLabel(record.doc_type) }}</div>
                                        <div class="font-mono text-xs text-gray-500">{{ record.number || 'bozza' }}</div>
                                    </div>
                                </div>
                                <div class="mt-6 rounded-md bg-gray-50 p-3">
                                    <div class="text-xs uppercase tracking-wide text-gray-400">Cliente</div>
                                    <div class="mt-1 font-medium text-gray-900">{{ related.client?.legal_name || related.client?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ related.client?.vat_number || related.client?.tax_code || '' }}</div>
                                </div>
                                <div class="mt-5 space-y-2">
                                    <div v-for="line in related.lines?.slice(0, 4)" :key="line.id" class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                                        <span class="truncate">{{ line.description }}</span>
                                        <span class="font-medium">{{ money(Number(line.subtotal) + (Number(line.subtotal) * Number(line.vat_rate || 0) / 100)) }}</span>
                                    </div>
                                    <div v-if="related.lines?.length > 4" class="text-xs text-gray-500">altre {{ related.lines.length - 4 }} righe...</div>
                                </div>
                                <div class="mt-5 flex justify-between border-t border-gray-200 pt-3 text-base font-semibold">
                                    <span>Totale</span>
                                    <span>{{ money(record.total_amount) }}</span>
                                </div>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Invio email</h3>
                            <form class="space-y-3" @submit.prevent="sendDocumentEmail">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Destinatario</label>
                                    <input v-model="emailForm.recipient" type="email" class="form-control" required />
                                    <div v-if="emailForm.errors.recipient" class="mt-1 text-sm text-red-600">{{ emailForm.errors.recipient }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">CC</label>
                                    <input v-model="emailForm.cc" class="form-control" placeholder="email1, email2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Oggetto</label>
                                    <input v-model="emailForm.subject" class="form-control" :placeholder="`${docTypeLabel(record.doc_type)} ${record.number || 'bozza'}`" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Messaggio</label>
                                    <textarea v-model="emailForm.message" rows="4" class="form-control" placeholder="Messaggio opzionale..."></textarea>
                                </div>
                                <label v-if="['fattura', 'nota_credito'].includes(record.doc_type)" class="flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="emailForm.include_xml" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Allega XML
                                </label>
                                <p class="text-xs text-gray-500">Viene sempre allegato il PDF. L'invio usa il mailer configurato in Laravel/Plesk.</p>
                                <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="emailForm.processing">
                                    Invia documento
                                </button>
                            </form>
                        </section>
                    </aside>
                </div>
            </div>

            <div v-else class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8">
                <section v-if="section === 'clients'" class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-md bg-white p-5 shadow-sm">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Progetti</div>
                            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ related.projects?.length || 0 }}</div>
                        </div>
                        <div class="rounded-md bg-white p-5 shadow-sm">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Task aperti</div>
                            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ related.tasks?.length || 0 }}</div>
                        </div>
                        <div class="rounded-md bg-white p-5 shadow-sm">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Documenti</div>
                            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ related.documents?.length || 0 }}</div>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Anagrafica</h3>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Ragione sociale</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ record.legal_name || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Settore</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ record.business_sector || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Email</dt>
                                    <dd class="mt-1 truncate text-sm text-gray-900">{{ record.email || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Telefono</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ record.phone || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Sito</dt>
                                    <dd class="mt-1 truncate text-sm text-gray-900">{{ record.website || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Sorgente</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ record.source || '-' }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Indirizzo</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ fullClientAddress(record) || '-' }}</dd>
                                </div>
                                <div v-if="record.notes" class="sm:col-span-2">
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Note</dt>
                                    <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ record.notes }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Dati fiscali e bancari</h3>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Partita IVA</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ record.vat_number || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Codice fiscale</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ record.tax_code || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">SDI</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ record.sdi_code || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">PEC</dt>
                                    <dd class="mt-1 truncate text-sm text-gray-900">{{ record.pec || '-' }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">IBAN</dt>
                                    <dd class="mt-1 break-all text-sm text-gray-900">{{ record.iban || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">IVA</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ record.vat_treatment || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] uppercase tracking-wide text-gray-400">Pagamento</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ paymentTermsLabel(record.payment_terms_days) || '-' }}</dd>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <section class="surface rounded-md p-5">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Abbonamenti</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ related.subscriptions?.length || 0 }} ricorrenze collegate al cliente</p>
                            </div>
                            <button v-if="editingSubscription" type="button" class="text-sm font-medium text-gray-500 hover:text-gray-800" @click="resetSubscriptionForm">
                                Annulla modifica
                            </button>
                        </div>

                        <form class="grid gap-3 rounded-md border border-gray-100 bg-gray-50 p-4 md:grid-cols-4" @submit.prevent="saveSubscription">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Nome *</label>
                                <input v-model="subscriptionForm.name" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Importo</label>
                                <input v-model="subscriptionForm.amount" type="number" step="0.01" min="0" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">IVA %</label>
                                <input v-model="subscriptionForm.vat_rate" type="number" step="0.01" min="0" class="form-control" required />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Descrizione riga fattura</label>
                                <input v-model="subscriptionForm.description" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ogni</label>
                                <input v-model="subscriptionForm.frequency_value" type="number" min="1" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Unita</label>
                                <select v-model="subscriptionForm.frequency_unit" class="form-control">
                                    <option value="month">Mese/i</option>
                                    <option value="year">Anno/i</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Inizio</label>
                                <input v-model="subscriptionForm.start_date" type="date" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fine</label>
                                <input v-model="subscriptionForm.end_date" type="date" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Prossima emissione</label>
                                <input v-model="subscriptionForm.next_invoice_date" type="date" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Termini pagamento</label>
                                <input v-model="subscriptionForm.payment_terms_days" type="number" min="0" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Natura IVA</label>
                                <input v-model="subscriptionForm.vat_nature_code" class="form-control" placeholder="N2.2, N4..." />
                            </div>
                            <div class="flex items-end gap-4">
                                <label class="mb-2 flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="subscriptionForm.auto_generate" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Auto
                                </label>
                                <label class="mb-2 flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="subscriptionForm.active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Attivo
                                </label>
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Note interne</label>
                                <input v-model="subscriptionForm.notes" class="form-control" />
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="subscriptionForm.processing">
                                    {{ editingSubscription ? 'Salva' : 'Crea' }}
                                </button>
                            </div>
                        </form>

                        <div class="mt-5 space-y-3">
                            <article
                                v-for="subscription in related.subscriptions || []"
                                :key="subscription.id"
                                :class="['rounded-md border p-4', subscription.active ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-70']"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="font-semibold text-gray-900">{{ subscription.name }}</h4>
                                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', subscription.active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600']">
                                                {{ subscription.active ? 'Attivo' : 'In pausa' }}
                                            </span>
                                            <span v-if="subscription.auto_generate" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">auto</span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ money(subscription.amount) }} + IVA {{ subscription.vat_rate }}% · {{ subscriptionFrequency(subscription) }} · prossima {{ dateIt(subscription.next_invoice_date) }}
                                        </p>
                                        <p v-if="subscription.description" class="mt-1 text-sm text-gray-600">{{ subscription.description }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="generateSubscription(subscription)">
                                            Genera fattura
                                        </button>
                                        <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="editSubscription(subscription)">
                                            Modifica
                                        </button>
                                        <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="toggleSubscription(subscription)">
                                            {{ subscription.active ? 'Pausa' : 'Riattiva' }}
                                        </button>
                                        <button type="button" class="rounded-md border border-red-100 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50" @click="removeSubscription(subscription)">
                                            Elimina
                                        </button>
                                    </div>
                                </div>
                            </article>
                            <p v-if="!(related.subscriptions || []).length" class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500">
                                Nessun abbonamento ricorrente per questo cliente.
                            </p>
                        </div>
                    </section>
                </section>

                <section v-if="section !== 'clients'" class="surface rounded-md p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Dettagli</h3>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div v-for="[key, value] in visibleEntries" :key="key" class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ labels[key] || key.replaceAll('_', ' ') }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ displayValue(value) }}</dd>
                        </div>
                    </dl>
                </section>

                <aside class="space-y-6">
                    <section v-if="section === 'clients'" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Servizi collegati</h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="service in related.services"
                                :key="service.id"
                                type="button"
                                :class="['inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium', clientHasService(service) ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50']"
                                @click="toggleService(service)"
                            >
                                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: service.color || '#2563eb' }"></span>
                                {{ service.name }}
                            </button>
                        </div>
                        <p v-if="!related.services?.length" class="mt-3 text-sm text-gray-500">Nessun servizio configurato.</p>
                    </section>

                    <section v-if="section === 'tasks'" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Stato</h3>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button
                                v-for="status in ['todo', 'in_progress', 'in_review', 'done']"
                                :key="status"
                                type="button"
                                :class="['rounded-md border px-3 py-2 text-xs font-medium', record.status === status ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50']"
                                @click="setTaskStatus(status)"
                            >
                                {{ displayValue(status) }}
                            </button>
                        </div>
                    </section>

                    <section v-if="section === 'tasks'" class="surface rounded-md p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">Assegnatari</h3>
                            <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-500" @click="saveTaskPeople('assignees')">Salva</button>
                        </div>
                        <div class="space-y-2">
                            <label v-for="user in related.users" :key="user.id" class="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    :checked="selectedAssignees.includes(user.id)"
                                    @change="togglePerson(selectedAssignees, user.id)"
                                />
                                <span class="truncate">{{ user.name }}</span>
                            </label>
                        </div>
                    </section>

                    <section v-if="section === 'tasks'" class="surface rounded-md p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">Follower</h3>
                            <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-500" @click="saveTaskPeople('followers')">Salva</button>
                        </div>
                        <div class="space-y-2">
                            <label v-for="user in related.users" :key="user.id" class="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    :checked="selectedFollowers.includes(user.id)"
                                    @change="togglePerson(selectedFollowers, user.id)"
                                />
                                <span class="truncate">{{ user.name }}</span>
                            </label>
                        </div>
                    </section>

                    <section v-if="related.client" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Cliente</h3>
                        <Link :href="route('clients.show', related.client.id)" class="mt-2 block text-sm font-medium text-indigo-600">
                            {{ related.client.name }}
                        </Link>
                    </section>

                    <section v-if="related.project" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Progetto</h3>
                        <Link :href="route('projects.show', related.project.id)" class="mt-2 block text-sm font-medium text-indigo-600">
                            {{ related.project.name }}
                        </Link>
                    </section>

                    <section v-for="name in ['projects', 'tasks', 'documents']" :key="name" v-show="related[name]?.length" class="surface rounded-md p-5">
                        <h3 class="mb-3 text-sm font-semibold capitalize text-gray-900">{{ name }}</h3>
                        <div class="space-y-2">
                            <div v-for="item in related[name]" :key="item.id" class="rounded-md bg-gray-50 px-3 py-2 text-sm">
                                <Link
                                    v-if="name === 'projects'"
                                    :href="route('projects.show', item.id)"
                                    class="font-medium text-indigo-600"
                                >{{ item.name }}</Link>
                                <Link
                                    v-else-if="name === 'tasks'"
                                    :href="route('tasks.show', item.id)"
                                    class="font-medium text-indigo-600"
                                >{{ item.title }}</Link>
                                <Link
                                    v-else-if="name === 'documents'"
                                    :href="route('billing.show', item.id)"
                                    class="font-medium text-indigo-600"
                                >{{ item.number || item.doc_type }}</Link>
                                <span v-else class="font-medium text-gray-900">{{ item.number || item.action || item.content }}</span>
                                <div class="mt-1 text-xs text-gray-500">{{ item.status ? displayValue(item.status) : item.created_at }}</div>
                            </div>
                        </div>
                    </section>
                </aside>

                <section v-if="section === 'clients'" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Referenti</h3>
                        <span class="text-xs text-gray-500">{{ related.contacts?.length || 0 }} contatti</span>
                    </div>

                    <form class="mb-5 grid gap-3 md:grid-cols-6" @submit.prevent="addContact">
                        <input v-model="contactForm.first_name" class="form-control mt-0" placeholder="Nome" required />
                        <input v-model="contactForm.last_name" class="form-control mt-0" placeholder="Cognome" required />
                        <input v-model="contactForm.email" class="form-control mt-0" type="email" placeholder="Email" />
                        <input v-model="contactForm.phone" class="form-control mt-0" placeholder="Telefono" />
                        <input v-model="contactForm.role" class="form-control mt-0" placeholder="Ruolo" />
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                    </form>

                    <div v-if="related.contacts?.length" class="grid gap-3 md:grid-cols-2">
                        <article v-for="contact in related.contacts" :key="contact.id" class="rounded-md border border-gray-100 bg-gray-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ contact.first_name }} {{ contact.last_name }}</h4>
                                    <p class="text-sm text-gray-500">{{ contact.role || 'Referente' }}</p>
                                </div>
                                <button class="text-sm font-medium text-red-600 hover:text-red-500" @click="removeContact(contact)">Elimina</button>
                            </div>
                            <div class="mt-3 space-y-1 text-sm text-gray-600">
                                <p v-if="contact.email">{{ contact.email }}</p>
                                <p v-if="contact.phone">{{ contact.phone }}</p>
                                <p v-if="contact.notes" class="whitespace-pre-wrap">{{ contact.notes }}</p>
                            </div>
                        </article>
                    </div>
                    <p v-else class="text-sm text-gray-500">Nessun referente inserito.</p>
                </section>

                <section v-if="section === 'tasks'" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-8">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sottoattivita</h3>
                            <span class="text-xs text-gray-500">{{ related.subtasks?.length || 0 }} elementi</span>
                        </div>
                        <form class="mb-4 grid gap-3 md:grid-cols-[1fr_150px_150px_auto]" @submit.prevent="addSubtask">
                            <input v-model="subtaskForm.title" class="form-control mt-0" placeholder="Nuova sottoattivita..." required />
                            <select v-model="subtaskForm.priority" class="form-control mt-0">
                                <option value="low">low</option>
                                <option value="medium">medium</option>
                                <option value="high">high</option>
                                <option value="urgent">urgent</option>
                            </select>
                            <input v-model="subtaskForm.due_date" class="form-control mt-0" type="date" />
                            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                        </form>
                        <div class="space-y-2">
                            <div v-for="subtask in related.subtasks" :key="subtask.id" class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm">
                                <label class="flex min-w-0 items-center gap-2">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        :checked="subtask.status === 'done'"
                                        @change="setSubtaskStatus(subtask, $event.target.checked)"
                                    />
                                    <Link :href="route('tasks.show', subtask.id)" class="truncate font-medium text-indigo-600 hover:text-indigo-500">
                                        {{ subtask.title }}
                                    </Link>
                                </label>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span :class="['rounded-full px-2 py-1 text-xs font-medium', priorityClass(subtask.priority)]">{{ displayValue(subtask.priority) }}</span>
                                    <span class="text-xs text-gray-500">{{ subtask.due_date || '-' }}</span>
                                </div>
                            </div>
                            <p v-if="!related.subtasks?.length" class="text-sm text-gray-500">Nessuna sottoattivita.</p>
                        </div>
                    </div>

                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Commenti</h3>
                    <form class="mb-5 flex gap-3" @submit.prevent="addComment">
                        <input v-model="commentForm.content" class="form-control mt-0" placeholder="Scrivi un commento..." required />
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Invia</button>
                    </form>
                    <div class="space-y-3">
                        <div v-for="comment in related.comments" :key="comment.id" class="rounded-md bg-gray-50 px-3 py-2 text-sm">
                            <div class="mb-1 text-xs font-medium text-gray-500">{{ comment.user_name || 'Utente' }} · {{ comment.created_at }}</div>
                            <div class="whitespace-pre-wrap text-gray-900">{{ comment.content }}</div>
                        </div>
                        <p v-if="!related.comments?.length" class="text-sm text-gray-500">Nessun commento.</p>
                    </div>
                </section>

                <section v-if="section === 'billing'" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Righe documento</h3>
                        <div class="text-sm font-semibold text-gray-900">Totale: EUR {{ Number(record.total_amount || 0).toFixed(2) }}</div>
                    </div>
                    <form class="mb-5 grid gap-3 md:grid-cols-[1fr_90px_120px_90px_90px_auto]" @submit.prevent="addLine">
                        <input v-model="lineForm.description" class="form-control mt-0" placeholder="Descrizione" required />
                        <input v-model="lineForm.quantity" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <input v-model="lineForm.unit_price" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <input v-model="lineForm.vat_rate" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <input v-model="lineForm.discount_pct" class="form-control mt-0" type="number" step="0.01" min="0" max="100" />
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Descrizione</th>
                                    <th class="px-3 py-2 text-right">Qta</th>
                                    <th class="px-3 py-2 text-right">Prezzo</th>
                                    <th class="px-3 py-2 text-right">IVA</th>
                                    <th class="px-3 py-2 text-right">Subtotale</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="line in related.lines" :key="line.id">
                                    <td class="px-3 py-2">{{ line.description }}</td>
                                    <td class="px-3 py-2 text-right">{{ line.quantity }}</td>
                                    <td class="px-3 py-2 text-right">{{ line.unit_price }}</td>
                                    <td class="px-3 py-2 text-right">{{ line.vat_rate }}%</td>
                                    <td class="px-3 py-2 text-right">{{ Number(line.subtotal).toFixed(2) }}</td>
                                    <td class="px-3 py-2 text-right"><button class="text-red-600" @click="removeLine(line)">Elimina</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="section === 'billing'" class="surface rounded-md p-5 lg:col-span-2">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Pagamenti</h3>
                    <form class="mb-5 grid gap-3 md:grid-cols-[120px_150px_1fr_auto]" @submit.prevent="addPayment">
                        <input v-model="paymentForm.amount" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <input v-model="paymentForm.paid_at" class="form-control mt-0" type="date" required />
                        <input v-model="paymentForm.method" class="form-control mt-0" placeholder="Metodo" />
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Registra</button>
                    </form>
                    <div class="space-y-2">
                        <div v-for="payment in related.payments" :key="payment.id" class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 text-sm">
                            <span>EUR {{ Number(payment.amount).toFixed(2) }} · {{ payment.paid_at }} · {{ payment.method || '-' }}</span>
                            <button class="text-red-600" @click="removePayment(payment)">Elimina</button>
                        </div>
                        <p v-if="!related.payments?.length" class="text-sm text-gray-500">Nessun pagamento registrato.</p>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
