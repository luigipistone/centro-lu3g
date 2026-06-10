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
});

const editing = ref(null);
const page = usePage();
const canWrite = computed(() => props.fields.length > 0);

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
                                v-else-if="['select', 'client', 'project', 'service'].includes(field.type)"
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
