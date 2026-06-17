<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Archive, Bell, Check, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    notifications: Array,
    archived: Boolean,
    activeCount: Number,
    archivedCount: Number,
});

const page = usePage();
const confirmArchiveAll = ref(false);
const confirmText = ref('');
const browserPermission = ref(typeof window !== 'undefined' && 'Notification' in window ? window.Notification.permission : 'unsupported');
const appTimeZone = 'Europe/Rome';

const emptyLabel = computed(() => props.archived ? 'Nessuna notifica archiviata.' : 'Nessuna notifica attiva.');

function markRead(notification) {
    router.patch(route('notifications.read', notification.id), {}, { preserveScroll: true });
}

function markAllRead() {
    router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
}

function archive(notification) {
    router.delete(route('notifications.destroy', notification.id), { preserveScroll: true });
}

function restore(notification) {
    router.patch(route('notifications.restore', notification.id), {}, { preserveScroll: true });
}

function archiveAll() {
    confirmArchiveAll.value = true;
    confirmText.value = '';
}

function closeConfirm() {
    confirmArchiveAll.value = false;
    confirmText.value = '';
}

function confirmArchiveAllNotifications() {
    if (confirmText.value !== 'ARCHIVIA') return;
    router.delete(route('notifications.destroy-all'), { preserveScroll: true, onFinish: closeConfirm });
}

async function enableBrowserNotifications() {
    if (typeof window === 'undefined' || !('Notification' in window)) return;
    browserPermission.value = await window.Notification.requestPermission();
}

function formatDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('it-IT', {
        timeZone: appTimeZone,
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Notifiche" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Notifiche</h2>
                <p class="text-sm text-gray-500">Aggiornamenti su task, commenti e attività che ti riguardano.</p>
            </div>
        </template>

        <div v-if="confirmArchiveAll" class="fixed inset-0 z-[5100] flex items-center justify-center bg-transparent px-4 py-6" @click.self="closeConfirm">
            <div class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">Archiviare tutte le notifiche?</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Le notifiche resteranno disponibili nell'archivio. Digita <span class="font-mono font-semibold text-gray-900">ARCHIVIA</span> per confermare.
                </p>
                <input v-model="confirmText" class="form-control font-mono" placeholder="ARCHIVIA" autocomplete="off" />
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="closeConfirm">Annulla</button>
                    <button type="button" class="btn btn-danger" :disabled="confirmText !== 'ARCHIVIA'" @click="confirmArchiveAllNotifications">
                        Archivia
                    </button>
                </div>
            </div>
        </div>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                <div class="surface flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('notifications.index')"
                            :class="['rounded-[var(--radius-sm)] px-3 py-2 text-sm font-semibold transition', !archived ? 'bg-gray-950 text-white shadow-sm' : 'text-gray-500 hover:bg-white/70 hover:text-gray-900']"
                        >
                            Attive {{ activeCount || 0 }}
                        </Link>
                        <Link
                            :href="route('notifications.index', { archived: 1 })"
                            :class="['rounded-[var(--radius-sm)] px-3 py-2 text-sm font-semibold transition', archived ? 'bg-gray-950 text-white shadow-sm' : 'text-gray-500 hover:bg-white/70 hover:text-gray-900']"
                        >
                            Archivio {{ archivedCount || 0 }}
                        </Link>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button v-if="browserPermission === 'default'" type="button" class="btn btn-outline" @click="enableBrowserNotifications">
                            <Bell class="h-4 w-4" :stroke-width="1.7" />
                            Attiva browser
                        </button>
                        <button v-if="!archived" type="button" class="btn btn-outline" @click="markAllRead">
                            <Check class="h-4 w-4" :stroke-width="1.7" />
                            Segna lette
                        </button>
                        <button v-if="!archived" type="button" class="btn btn-outline text-gray-700" @click="archiveAll">
                            <Archive class="h-4 w-4" :stroke-width="1.7" />
                            Archivia tutte
                        </button>
                    </div>
                </div>

                <div v-if="page.props.flash?.status" class="rounded-[var(--radius-sm)] border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <section class="surface overflow-hidden">
                    <div v-if="notifications.length" class="divide-y divide-gray-100">
                        <article
                            v-for="notification in notifications"
                            :key="notification.id"
                            :class="['group flex items-start gap-3 px-5 py-4 transition hover:bg-white/72', notification.read || archived ? '' : 'bg-indigo-50/56']"
                        >
                            <span :class="['mt-2 h-2.5 w-2.5 shrink-0 rounded-full', notification.read || archived ? 'bg-gray-300' : 'bg-indigo-600']" />
                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="notification.task_id"
                                    :href="route('tasks.show', notification.task_id)"
                                    class="block text-sm font-semibold text-gray-900 transition hover:text-indigo-600"
                                    @click="!archived && markRead(notification)"
                                >
                                    {{ notification.message }}
                                </Link>
                                <p v-else class="text-sm font-semibold text-gray-900">{{ notification.message }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ formatDate(notification.created_at) }}
                                    <span v-if="notification.task_title"> · {{ notification.task_title }}</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                <button
                                    v-if="!archived && !notification.read"
                                    type="button"
                                    class="icon-btn h-9 w-9"
                                    title="Segna come letta"
                                    @click="markRead(notification)"
                                >
                                    <Check class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button
                                    v-if="!archived"
                                    type="button"
                                    class="icon-btn h-9 w-9"
                                    title="Archivia"
                                    @click="archive(notification)"
                                >
                                    <Archive class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="icon-btn h-9 w-9"
                                    title="Ripristina"
                                    @click="restore(notification)"
                                >
                                    <RotateCcw class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>
                        </article>
                    </div>
                    <div v-else class="px-5 py-12 text-center text-sm text-gray-500">
                        {{ emptyLabel }}
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
