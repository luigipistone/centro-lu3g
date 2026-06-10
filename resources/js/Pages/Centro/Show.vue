<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

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
    due_date: 'Scadenza',
    due_time: 'Ora',
    description: 'Descrizione',
    notes: 'Note',
};

const visibleEntries = Object.entries(props.record).filter(([key, value]) =>
    !['id', 'created_by', 'updated_at', 'created_at', 'password', 'remember_token'].includes(key)
    && value !== null
    && value !== ''
);
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
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8">
                <section class="surface rounded-md p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Dettagli</h3>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div v-for="[key, value] in visibleEntries" :key="key" class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ labels[key] || key.replaceAll('_', ' ') }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ value }}</dd>
                        </div>
                    </dl>
                </section>

                <aside class="space-y-6">
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

                    <section v-for="name in ['projects', 'tasks', 'documents', 'comments']" :key="name" v-show="related[name]?.length" class="surface rounded-md p-5">
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
                                <span v-else class="font-medium text-gray-900">{{ item.number || item.action || item.content }}</span>
                                <div class="mt-1 text-xs text-gray-500">{{ item.status || item.created_at }}</div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
