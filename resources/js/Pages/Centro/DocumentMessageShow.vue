<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateIt, dateTimeIt } from '@/utils/formatters';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronLeft, MessageSquare } from '@lucide/vue';

const props = defineProps({
    canManage: Boolean,
    message: Object,
    readers: Array,
});

const page = usePage();

function markRead() {
    router.post(route('document-messages.read', props.message.id), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="message.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('documents.messages')" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                    Messaggi
                </Link>
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ message.title }}</h2>
                    <p class="text-sm text-gray-500">Messaggio pubblicato il {{ dateIt(message.created_at) }}</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="rounded-[var(--radius-sm)] border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <article class="surface overflow-hidden">
                        <div class="flex items-center gap-3 border-b border-white/70 px-5 py-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                <MessageSquare class="h-5 w-5" :stroke-width="1.7" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ message.title }}</p>
                                <p class="text-xs text-gray-500">{{ message.creator_name || 'Il Centro' }}</p>
                            </div>
                        </div>

                        <div class="prose prose-sm max-w-none px-5 py-5 text-gray-700" v-html="message.body || '<p>Nessun testo inserito.</p>'"></div>
                    </article>

                    <aside class="space-y-6">
                        <section v-if="message.user_is_recipient" class="surface p-5">
                            <h3 class="text-base font-semibold text-gray-900">Lettura</h3>
                            <div class="mt-4 rounded-[var(--radius)] bg-gray-50/80 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Stato personale</p>
                                <p :class="['mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold', message.user_read_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                                    <Check v-if="message.user_read_at" class="h-4 w-4" :stroke-width="1.8" />
                                    {{ message.user_read_at ? `Letto il ${dateTimeIt(message.user_read_at)}` : 'Da confermare come letto' }}
                                </p>
                            </div>
                            <button
                                v-if="!message.user_read_at"
                                type="button"
                                class="btn btn-primary mt-4 w-full justify-center"
                                @click="markRead"
                            >
                                <Check class="h-4 w-4" :stroke-width="1.7" />
                                Segna come letto
                            </button>
                        </section>

                        <section v-if="canManage" class="surface overflow-hidden">
                            <div class="border-b border-white/70 px-5 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Letture utenti</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ message.read_count }} letti su {{ message.recipient_count }} destinatari</p>
                            </div>
                            <div class="max-h-[520px] divide-y divide-gray-100 overflow-y-auto">
                                <article v-for="reader in readers" :key="reader.id" class="flex items-center gap-3 px-5 py-3">
                                    <UserAvatar :user="reader" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ reader.name }}</p>
                                        <p class="text-xs text-gray-500">{{ reader.read_at ? `Letto ${dateTimeIt(reader.read_at)}` : (reader.opened_at ? `Aperto ${dateTimeIt(reader.opened_at)}` : 'Non aperto') }}</p>
                                    </div>
                                    <span :class="['h-2.5 w-2.5 rounded-full', reader.read_at ? 'bg-emerald-500' : (reader.opened_at ? 'bg-amber-400' : 'bg-gray-300')]" />
                                </article>
                                <p v-if="!readers.length" class="px-5 py-8 text-center text-sm text-gray-500">Nessun destinatario.</p>
                            </div>
                        </section>
                    </aside>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
