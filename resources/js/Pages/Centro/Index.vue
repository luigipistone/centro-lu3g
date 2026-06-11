<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    section: String,
    title: String,
    description: String,
    columns: Array,
    fields: Array,
    rows: Array,
    clients: Array,
    projects: Array,
    services: Array,
    users: Array,
    billingStats: Object,
});

const editing = ref(null);
const page = usePage();
const canWrite = computed(() => props.fields.length > 0);
const billingSearch = ref('');
const billingType = ref('all');
const billingStatus = ref('all');

const routeBase = computed(() => {
    if (props.section === 'settings') return 'settings';
    return props.section;
});

const defaults = computed(() => Object.fromEntries(props.fields.map((field) => {
    if (field.type === 'checkbox') return [field.name, true];
    if (field.name === 'status' && props.section === 'projects') return [field.name, 'active'];
    if (field.name === 'status' && props.section === 'tasks') return [field.name, 'todo'];
    if (field.name === 'priority') return [field.name, 'medium'];
    if (field.name === 'color') return [field.name, '#2563eb'];
    return [field.name, ''];
})));

const form = useForm({ ...defaults.value });

function optionsFor(field) {
    if (field.type === 'client') return props.clients;
    if (field.type === 'project') return props.projects;
    if (field.type === 'service') return props.services;
    if (field.type === 'user') return props.users;
    return (field.options || []).map((value) => ({ id: value, name: value }));
}

function resetForm() {
    editing.value = null;
    form.clearErrors();
    form.defaults({ ...defaults.value });
    form.reset();
    Object.assign(form, { ...defaults.value });
}

function editRow(row) {
    editing.value = row;
    form.clearErrors();
    props.fields.forEach((field) => {
        form[field.name] = row[field.name] ?? (field.type === 'checkbox' ? false : '');
    });
}

function submit() {
    const name = routeBase.value;
    if (editing.value) {
        form.put(route(`${name}.update`, editing.value.id), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post(route(`${name}.store`), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
}

function remove(row) {
    if (!confirm(`Eliminare "${row.name || row.title || row.number || row.email}"?`)) return;
    router.delete(route(`${routeBase.value}.destroy`, row.id), { preserveScroll: true });
}

function showRoute(row) {
    if (!['clients', 'projects', 'tasks', 'billing'].includes(props.section)) return null;
    return route(`${props.section}.show`, row.id);
}

function priorityClass(priority) {
    return {
        urgent: 'bg-red-100 text-red-700',
        high: 'bg-orange-100 text-orange-700',
        medium: 'bg-amber-100 text-amber-700',
        low: 'bg-emerald-100 text-emerald-700',
    }[priority] || 'bg-gray-100 text-gray-700';
}

const documentTypeLabels = {
    preventivo: 'Preventivo',
    proforma: 'Proforma',
    fattura: 'Fattura',
    nota_credito: 'Nota credito',
};

const documentStatusLabels = {
    draft: 'Bozza',
    sent: 'Inviato',
    accepted: 'Accettato',
    rejected: 'Rifiutato',
    paid: 'Pagato',
    partially_paid: 'Parziale',
    overdue: 'Scaduto',
    cancelled: 'Annullato',
};

function money(value) {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
}

function dateIt(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('it-IT');
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

const billingRows = computed(() => props.rows.filter((row) => {
    const search = billingSearch.value.trim().toLowerCase();
    const matchesSearch = !search
        || (row.number || '').toLowerCase().includes(search)
        || (row.client_name || '').toLowerCase().includes(search)
        || (row.notes || '').toLowerCase().includes(search);
    const matchesType = billingType.value === 'all' || row.doc_type === billingType.value;
    const matchesStatus = billingStatus.value === 'all' || row.status === billingStatus.value;

    return matchesSearch && matchesType && matchesStatus;
}));

const maxMonthly = computed(() => Math.max(1, ...((props.billingStats?.monthly || []).map((row) => Math.max(row.invoiced, row.paid)))));
const maxTopClient = computed(() => Math.max(1, ...((props.billingStats?.topClients || []).map((row) => row.total))));
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
                <p class="text-sm text-gray-500">{{ description }}</p>
            </div>
        </template>

        <div v-if="section === 'calendar'" class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="surface overflow-hidden rounded-md">
                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="row in rows"
                            :key="row.id"
                            :href="route('tasks.show', row.id)"
                            class="grid gap-3 px-5 py-4 hover:bg-gray-50 md:grid-cols-[150px_1fr_auto]"
                        >
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ row.due_date || 'Senza data' }}</div>
                                <div class="text-xs text-gray-500">{{ row.due_time || '' }}</div>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">{{ row.title }}</div>
                                <div class="text-sm text-gray-500">{{ row.client_name || row.project_name || '-' }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="['rounded-full px-2 py-1 text-xs font-medium', priorityClass(row.priority)]">{{ row.priority }}</span>
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ row.status }}</span>
                            </div>
                        </Link>
                        <div v-if="!rows.length" class="px-5 py-8 text-center text-sm text-gray-500">Nessuna attivita in calendario.</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="section === 'billing'" class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div
                        v-for="card in [
                            ['Fatturato anno', billingStats?.totalInvoiced, null],
                            ['Incassato anno', billingStats?.totalReceived, `${billingStats?.collectedPct || 0}% del fatturato`],
                            ['Da incassare', billingStats?.openAmount, null],
                            [`Scaduti (${billingStats?.overdueCount || 0})`, billingStats?.overdueAmount, 'Da sollecitare'],
                        ]"
                        :key="card[0]"
                        class="rounded-md bg-white p-5 shadow-sm"
                    >
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ card[0] }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900">{{ money(card[1]) }}</div>
                        <div v-if="card[2]" class="mt-1 text-xs text-gray-500">{{ card[2] }}</div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <section class="rounded-md bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">Andamento mensile {{ billingStats?.year }}</h3>
                            <span class="text-xs text-gray-500">Fatturato / incassato</span>
                        </div>
                        <div class="grid h-64 grid-cols-12 items-end gap-2 border-b border-gray-100 pb-2">
                            <div v-for="month in billingStats?.monthly || []" :key="month.month" class="flex h-full flex-col justify-end gap-1">
                                <div class="flex flex-1 items-end justify-center gap-1">
                                    <div class="w-3 rounded-t bg-indigo-500" :style="{ height: `${Math.max(2, (month.invoiced / maxMonthly) * 100)}%` }"></div>
                                    <div class="w-3 rounded-t bg-emerald-500" :style="{ height: `${Math.max(2, (month.paid / maxMonthly) * 100)}%` }"></div>
                                </div>
                                <div class="truncate text-center text-[10px] text-gray-500">{{ month.month }}</div>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-4 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Fatturato</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Incassato</span>
                        </div>
                    </section>

                    <section class="rounded-md bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900">Top clienti</h3>
                        <div class="mt-4 space-y-4">
                            <div v-for="client in billingStats?.topClients || []" :key="client.name">
                                <div class="mb-1 flex justify-between gap-3 text-xs">
                                    <span class="truncate font-medium text-gray-700">{{ client.name }}</span>
                                    <span class="text-gray-500">{{ money(client.total) }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-indigo-500" :style="{ width: `${Math.max(3, (client.total / maxTopClient) * 100)}%` }"></div>
                                </div>
                            </div>
                            <p v-if="!billingStats?.topClients?.length" class="text-sm text-gray-500">Nessuna fattura nell'anno.</p>
                        </div>
                    </section>
                </div>

                <section class="rounded-md bg-white p-5 shadow-sm">
                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_170px_170px_auto]">
                        <input
                            v-model="billingSearch"
                            class="form-control mt-0"
                            placeholder="Cerca per numero, cliente o note..."
                        />
                        <select v-model="billingType" class="form-control mt-0">
                            <option value="all">Tutti i tipi</option>
                            <option v-for="(label, value) in documentTypeLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <select v-model="billingStatus" class="form-control mt-0">
                            <option value="all">Tutti gli stati</option>
                            <option v-for="(label, value) in documentStatusLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="billingSearch = ''; billingType = 'all'; billingStatus = 'all'">Reset</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Numero</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Tipo</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Cliente</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Data</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Scadenza</th>
                                    <th class="px-3 py-3 text-right font-semibold text-gray-600">Totale</th>
                                    <th class="px-3 py-3 text-right font-semibold text-gray-600">Pagato</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Stato</th>
                                    <th class="px-3 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in billingRows" :key="row.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-3">
                                        <Link :href="route('billing.show', row.id)" class="font-mono text-xs font-medium text-indigo-600">
                                            {{ row.number || 'bozza' }}
                                        </Link>
                                    </td>
                                    <td class="px-3 py-3">{{ documentTypeLabels[row.doc_type] || row.doc_type }}</td>
                                    <td class="px-3 py-3">{{ row.client_name || '-' }}</td>
                                    <td class="px-3 py-3">{{ dateIt(row.issue_date) }}</td>
                                    <td class="px-3 py-3">{{ dateIt(row.due_date) }}</td>
                                    <td class="px-3 py-3 text-right font-medium">{{ money(row.total_amount) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-500">{{ money(row.total_paid) }}</td>
                                    <td class="px-3 py-3">
                                        <span :class="['rounded-full px-2 py-1 text-xs font-medium', statusClass(row.status)]">{{ documentStatusLabels[row.status] || row.status }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right">
                                        <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" @click="editRow(row)">Modifica</button>
                                        <button type="button" class="ml-4 text-sm font-medium text-red-600 hover:text-red-500" @click="remove(row)">Elimina</button>
                                    </td>
                                </tr>
                                <tr v-if="!billingRows.length">
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nessun documento trovato.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-md bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">{{ editing ? 'Modifica documento' : 'Nuovo documento' }}</h3>
                        <button v-if="editing" type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="resetForm">Annulla</button>
                    </div>

                    <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submit">
                        <div v-for="field in fields" :key="field.name" :class="field.type === 'textarea' ? 'md:col-span-3' : ''">
                            <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                            <textarea v-if="field.type === 'textarea'" v-model="form[field.name]" rows="3" class="form-control" />
                            <select v-else-if="['select', 'client', 'project', 'service', 'user'].includes(field.type)" v-model="form[field.name]" class="form-control" :required="field.required">
                                <option value="">-</option>
                                <option v-for="option in optionsFor(field)" :key="option.id" :value="option.id">{{ option.name }}</option>
                            </select>
                            <input v-else v-model="form[field.name]" :type="field.type" class="form-control" :required="field.required" />
                            <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                        </div>
                        <div class="md:col-span-3">
                            <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="form.processing">
                                {{ editing ? 'Salva modifiche' : 'Crea documento' }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>

        <div v-else class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 sm:px-6 lg:grid-cols-[360px_1fr] lg:px-8">
                <section v-if="canWrite" class="rounded bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">{{ editing ? 'Modifica' : 'Nuovo' }}</h3>
                        <button v-if="editing" type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="resetForm">Annulla</button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div v-for="field in fields" :key="field.name">
                            <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>

                            <textarea
                                v-if="field.type === 'textarea'"
                                v-model="form[field.name]"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <select
                                v-else-if="['select', 'client', 'project', 'service', 'user'].includes(field.type)"
                                v-model="form[field.name]"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :required="field.required"
                            >
                                <option value="">-</option>
                                <option v-for="option in optionsFor(field)" :key="option.id" :value="option.id">
                                    {{ option.name }}
                                </option>
                            </select>

                            <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form[field.name]" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Attivo
                            </label>

                            <input
                                v-else
                                v-model="form[field.name]"
                                :type="field.type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :required="field.required"
                            />

                            <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ editing ? 'Salva modifiche' : 'Crea' }}
                        </button>
                    </form>
                </section>

                <section :class="['overflow-hidden rounded bg-white shadow-sm', canWrite ? '' : 'lg:col-span-2']">
                    <div v-if="page.props.flash?.status" class="border-b border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ page.props.flash.status }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th v-for="column in columns" :key="column" class="px-4 py-3 text-left font-semibold text-gray-600">
                                        {{ column.replaceAll('_', ' ') }}
                                    </th>
                                    <th v-if="canWrite" class="px-4 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="row in rows" :key="row.id">
                                    <td v-for="column in columns" :key="column" class="max-w-xs truncate px-4 py-3 text-gray-800">
                                        <span v-if="column === 'active'">{{ row[column] ? 'Si' : 'No' }}</span>
                                        <Link
                                            v-else-if="column === columns[0] && showRoute(row)"
                                            :href="showRoute(row)"
                                            class="font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            {{ row[column] ?? '-' }}
                                        </Link>
                                        <span v-else>{{ row[column] ?? '-' }}</span>
                                    </td>
                                    <td v-if="canWrite" class="whitespace-nowrap px-4 py-3 text-right">
                                        <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" @click="editRow(row)">Modifica</button>
                                        <button type="button" class="ml-4 text-sm font-medium text-red-600 hover:text-red-500" @click="remove(row)">Elimina</button>
                                    </td>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td :colspan="columns.length + (canWrite ? 1 : 0)" class="px-4 py-8 text-center text-gray-500">
                                        Nessun dato presente.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
