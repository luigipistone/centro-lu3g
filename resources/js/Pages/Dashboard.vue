<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    recentClients: Array,
    upcomingTasks: Array,
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Centro LU3G</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div v-for="card in [
                        ['Clienti', stats.clients, 'clients.index'],
                        ['Progetti attivi', stats.activeProjects, 'projects.index'],
                        ['Task aperti', stats.openTasks, 'tasks.index'],
                        ['Urgenti', stats.urgentTasks, 'tasks.index'],
                    ]" :key="card[0]" class="rounded bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">{{ card[0] }}</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ card[1] }}</div>
                        <Link :href="route(card[2])" class="mt-4 inline-flex text-sm font-medium text-indigo-600">Apri</Link>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="rounded bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900">Clienti recenti</h3>
                        <div class="mt-4 divide-y divide-gray-100">
                            <div v-for="client in recentClients" :key="client.id" class="py-3">
                                <div class="font-medium text-gray-900">{{ client.name }}</div>
                                <div class="text-sm text-gray-500">{{ client.email || client.phone || 'Nessun contatto' }}</div>
                            </div>
                            <div v-if="!recentClients.length" class="py-3 text-sm text-gray-500">Nessun cliente importato.</div>
                        </div>
                    </section>

                    <section class="rounded bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900">Scadenze task</h3>
                        <div class="mt-4 divide-y divide-gray-100">
                            <div v-for="task in upcomingTasks" :key="task.id" class="py-3">
                                <div class="font-medium text-gray-900">{{ task.title }}</div>
                                <div class="text-sm text-gray-500">{{ task.due_date }} · {{ task.priority }} · {{ task.status }}</div>
                            </div>
                            <div v-if="!upcomingTasks.length" class="py-3 text-sm text-gray-500">Nessuna scadenza presente.</div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
