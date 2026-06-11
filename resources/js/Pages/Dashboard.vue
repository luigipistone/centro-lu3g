<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    recentClients: Array,
    upcomingTasks: Array,
    urgentTasks: Array,
    myTasks: Array,
    activeProjects: Array,
});

const page = usePage();

function dateShort(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
}

function priorityDot(priority) {
    return {
        low: 'bg-emerald-500',
        medium: 'bg-amber-500',
        high: 'bg-orange-500',
        urgent: 'bg-red-500',
    }[priority] || 'bg-gray-400';
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
                <p class="text-sm text-gray-500">Bentornato, {{ page.props.auth?.user?.email }}</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-8 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="card in [
                            ['Clienti', stats.clients, 'clients.index'],
                            ['Progetti Attivi', stats.activeProjects, 'projects.index'],
                            ['Task Aperti', stats.openTasks, 'tasks.index'],
                            ['Urgenti', stats.urgentTasks, 'tasks.index'],
                        ]"
                        :key="card[0]"
                        :href="route(card[2])"
                        class="rounded-md border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-200 hover:shadow"
                    >
                        <div class="text-sm text-gray-500">{{ card[0] }}</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ card[1] }}</div>
                    </Link>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-900">Task in scadenza</h3>
                            <Link :href="route('tasks.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Apri task</Link>
                        </div>
                        <div class="space-y-1">
                            <Link
                                v-for="task in upcomingTasks"
                                :key="task.id"
                                :href="route('tasks.show', task.id)"
                                class="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-gray-50"
                            >
                                <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', priorityDot(task.priority)]"></span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900">
                                    {{ task.title }}
                                    <span v-if="task.client_name" class="font-normal text-gray-500"> - {{ task.client_name }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-gray-500">{{ dateShort(task.due_date) }}</span>
                            </Link>
                            <p v-if="!upcomingTasks.length" class="py-2 text-sm text-gray-500">Nessun task in scadenza</p>
                        </div>
                    </section>

                    <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-gray-900">I miei task</h3>
                        <div class="space-y-1">
                            <Link
                                v-for="task in myTasks"
                                :key="task.id"
                                :href="route('tasks.show', task.id)"
                                class="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-gray-50"
                            >
                                <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', priorityDot(task.priority)]"></span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900">{{ task.title }}</span>
                                <span class="shrink-0 text-xs text-gray-500">{{ dateShort(task.due_date) }}</span>
                            </Link>
                            <p v-if="!myTasks.length" class="py-2 text-sm text-gray-500">Nessun task assegnato</p>
                        </div>
                    </section>

                    <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-gray-900">Progetti attivi</h3>
                        <div class="space-y-1">
                            <Link
                                v-for="project in activeProjects"
                                :key="project.id"
                                :href="route('projects.show', project.id)"
                                class="flex items-center justify-between gap-3 rounded-md px-2 py-2 transition hover:bg-gray-50"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: project.color || '#64748b' }"></span>
                                    <span class="truncate text-sm font-medium text-gray-900">{{ project.name }}</span>
                                </span>
                                <span class="shrink-0 truncate text-xs text-gray-500">{{ project.client_name }}</span>
                            </Link>
                            <p v-if="!activeProjects.length" class="py-2 text-sm text-gray-500">Nessun progetto attivo</p>
                        </div>
                    </section>

                    <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-gray-900">Clienti recenti</h3>
                        <div class="space-y-1">
                            <Link
                                v-for="client in recentClients"
                                :key="client.id"
                                :href="route('clients.show', client.id)"
                                class="flex items-center justify-between gap-3 rounded-md px-2 py-2 transition hover:bg-gray-50"
                            >
                                <span class="truncate text-sm font-medium text-gray-900">{{ client.name }}</span>
                                <span class="shrink-0 truncate text-xs text-gray-500">{{ client.email || client.phone }}</span>
                            </Link>
                            <p v-if="!recentClients.length" class="py-2 text-sm text-gray-500">Nessun cliente</p>
                        </div>
                    </section>

                    <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-gray-900">Task urgenti</h3>
                        <div class="space-y-1">
                            <Link
                                v-for="task in urgentTasks"
                                :key="task.id"
                                :href="route('tasks.show', task.id)"
                                class="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-red-50"
                            >
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-red-500"></span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900">{{ task.title }}</span>
                                <span class="shrink-0 text-xs text-gray-500">{{ dateShort(task.due_date) }}</span>
                            </Link>
                            <p v-if="!urgentTasks.length" class="py-2 text-sm text-gray-500">Nessun task urgente</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
