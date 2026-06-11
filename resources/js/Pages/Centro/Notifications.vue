<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

defineProps({
    notifications: Array,
});

const page = usePage();
const confirmClear = ref(false);
const confirmText = ref('');

function markRead(notification) {
    router.patch(route('notifications.read', notification.id), {}, { preserveScroll: true });
}

function markAllRead() {
    router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
}

function remove(notification) {
    router.delete(route('notifications.destroy', notification.id), { preserveScroll: true });
}

function removeAll() {
    confirmClear.value = true;
    confirmText.value = '';
}

function closeConfirm() {
    confirmClear.value = false;
    confirmText.value = '';
}

function confirmRemoveAll() {
    if (confirmText.value !== 'SVUOTA') return;
    router.delete(route('notifications.destroy-all'), { preserveScroll: true, onFinish: closeConfirm });
}

function formatDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('it-IT', {
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
                <p class="text-sm text-gray-500">Aggiornamenti su task, commenti e attivita che ti riguardano.</p>
            </div>
        </template>

        <div v-if="confirmClear" class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/40 px-4 py-6">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">Svuotare tutte le notifiche?</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Questa azione e' irreversibile. Digita <span class="font-mono font-semibold text-gray-900">SVUOTA</span> per confermare.
                </p>
                <input v-model="confirmText" class="form-control font-mono" placeholder="SVUOTA" autocomplete="off" />
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeConfirm">
                        Annulla
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="confirmText !== 'SVUOTA'"
                        @click="confirmRemoveAll"
                    >
                        Svuota
                    </button>
                </div>
            </div>
        </div>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div v-if="page.props.flash?.status" class="rounded-md border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
                        {{ page.props.flash.status }}
                    </div>
                    <div class="ml-auto flex gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            @click="markAllRead"
                        >
                            <Check class="h-4 w-4" :stroke-width="1.6" />
                            Segna lette
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50"
                            @click="removeAll"
                        >
                            <Trash2 class="h-4 w-4" :stroke-width="1.6" />
                            Svuota
                        </button>
                    </div>
                </div>

                <section class="overflow-hidden rounded-md bg-white shadow-sm">
                    <div v-if="notifications.length" class="divide-y divide-gray-100">
                        <article
                            v-for="notification in notifications"
                            :key="notification.id"
                            :class="['flex items-start gap-3 px-5 py-4 transition hover:bg-gray-50', notification.read ? '' : 'bg-indigo-50/60']"
                        >
                            <span :class="['mt-2 h-2.5 w-2.5 shrink-0 rounded-full', notification.read ? 'bg-gray-300' : 'bg-indigo-600']" />
                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="notification.task_id"
                                    :href="route('tasks.show', notification.task_id)"
                                    class="block text-sm font-medium text-gray-900 hover:text-indigo-600"
                                    @click="markRead(notification)"
                                >
                                    {{ notification.message }}
                                </Link>
                                <p v-else class="text-sm font-medium text-gray-900">{{ notification.message }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ formatDate(notification.created_at) }}
                                    <span v-if="notification.task_title"> · {{ notification.task_title }}</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button
                                    v-if="!notification.read"
                                    type="button"
                                    class="rounded-md p-1.5 text-gray-500 hover:bg-white hover:text-indigo-600"
                                    title="Segna come letta"
                                    @click="markRead(notification)"
                                >
                                    <Check class="h-4 w-4" :stroke-width="1.6" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md p-1.5 text-gray-500 hover:bg-white hover:text-red-600"
                                    title="Elimina"
                                    @click="remove(notification)"
                                >
                                    <Trash2 class="h-4 w-4" :stroke-width="1.6" />
                                </button>
                            </div>
                        </article>
                    </div>
                    <div v-else class="px-5 py-12 text-center text-sm text-gray-500">
                        Nessuna notifica.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
