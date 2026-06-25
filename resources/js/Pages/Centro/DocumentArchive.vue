<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { dateIt } from '@/utils/formatters';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronLeft, FileText, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    canManage: Boolean,
    year: Number,
    documents: Array,
    groups: Array,
    users: Array,
    documentCategories: Object,
});

const page = usePage();
const categoryFilter = ref('all');
const confirmDelete = ref(null);
const confirmDeleteText = ref('');
const isSuperadmin = computed(() => page.props.auth?.user?.role === 'superadmin');

const categoryOptions = computed(() => [
    { value: 'all', label: 'Tutte le categorie' },
    ...Object.entries(props.documentCategories || {}).map(([value, label]) => ({ value, label })),
]);

const filteredDocuments = computed(() => {
    if (categoryFilter.value === 'all') return props.documents || [];

    return (props.documents || []).filter((document) => (document.category || 'documenti_vari') === categoryFilter.value);
});

function categoryLabel(category) {
    return props.documentCategories?.[category || 'documenti_vari'] || 'Documenti Vari';
}

function audienceLabel(document) {
    if (document.audience === 'all') return 'Tutti';
    if (document.audience === 'groups') {
        const names = (document.group_ids || [])
            .map((id) => (props.groups || []).find((group) => group.id === id)?.name)
            .filter(Boolean);

        return names.length ? names.join(', ') : 'Gruppi';
    }

    const names = (document.user_ids || [])
        .map((id) => (props.users || []).find((user) => user.id === id)?.name)
        .filter(Boolean);

    return names.length ? names.join(', ') : 'Utenti';
}

function fileSize(bytes) {
    const value = Number(bytes || 0);
    if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function openDocument(document) {
    router.visit(route('documents.show', document.id));
}

function removeDocument(document) {
    if (isSuperadmin.value) {
        router.delete(route('documents.destroy', document.id), { preserveScroll: true });
        return;
    }

    confirmDelete.value = {
        title: document.title,
        route: route('documents.destroy', document.id),
    };
    confirmDeleteText.value = '';
}

function closeDeleteConfirm() {
    confirmDelete.value = null;
    confirmDeleteText.value = '';
}

function confirmDeleteAction() {
    if (!confirmDelete.value || confirmDeleteText.value !== 'ELIMINA') return;

    router.delete(confirmDelete.value.route, {
        preserveScroll: true,
        onFinish: closeDeleteConfirm,
    });
}
</script>

<template>
    <Head :title="`Archivio documenti ${year}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('documents.index')" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                    Documenti
                </Link>
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Archivio documenti {{ year }}</h2>
                    <p class="text-sm text-gray-500">Documenti archiviati per anno, filtrabili per categoria.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="confirmDelete" class="fixed inset-0 z-[5100] flex items-center justify-center bg-transparent px-4 py-6" @click.self="closeDeleteConfirm">
                    <div class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-xl">
                        <h3 class="text-base font-semibold text-gray-900">Eliminare il documento?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Questa azione elimina <span class="font-semibold text-gray-900">{{ confirmDelete.title }}</span>. Digita
                            <span class="font-mono font-semibold text-gray-900">ELIMINA</span> per confermare.
                        </p>
                        <input v-model="confirmDeleteText" class="form-control font-mono" placeholder="ELIMINA" autocomplete="off" />
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" class="btn btn-outline" @click="closeDeleteConfirm">Annulla</button>
                            <button type="button" class="btn btn-danger" :disabled="confirmDeleteText !== 'ELIMINA'" @click="confirmDeleteAction">
                                Elimina
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="page.props.flash?.status" class="rounded-[var(--radius-sm)] border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <section class="surface space-y-4 p-5">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Archivio {{ year }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ filteredDocuments.length }} di {{ documents.length }} documenti</p>
                        </div>
                        <div class="w-full max-w-[280px]">
                            <AppSelect v-model="categoryFilter" :options="categoryOptions" />
                        </div>
                    </div>

                    <div v-if="filteredDocuments.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="document in filteredDocuments"
                            :key="document.id"
                            role="button"
                            tabindex="0"
                            :class="['surface group cursor-pointer p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]', !canManage && !document.user_read_at ? 'ring-1 ring-amber-100' : '']"
                            @click="openDocument(document)"
                            @keydown.enter.prevent="openDocument(document)"
                            @keydown.space.prevent="openDocument(document)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                        <FileText class="h-5 w-5" :stroke-width="1.7" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="line-clamp-2 text-sm font-semibold text-gray-900 transition group-hover:text-[hsl(var(--primary-app))]">
                                            {{ document.title }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500">{{ audienceLabel(document) }} · {{ fileSize(document.file_size) }}</p>
                                        <p class="mt-1 inline-flex rounded-full bg-[hsl(var(--primary-app)/0.08)] px-2 py-0.5 text-[11px] font-semibold text-[hsl(var(--primary-app-dark))]">
                                            {{ categoryLabel(document.category) }}
                                        </p>
                                    </div>
                                </div>
                                <button v-if="canManage" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina documento" @click.stop="removeDocument(document)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>

                            <div v-if="document.description" class="mt-3 line-clamp-2 text-sm text-gray-500" v-html="document.description"></div>

                            <div class="mt-4 flex items-center justify-between gap-3 text-xs text-gray-500">
                                <span>{{ dateIt(document.created_at) }}</span>
                                <span v-if="canManage" class="font-semibold text-gray-700">{{ document.read_count }}/{{ document.recipient_count }} letti</span>
                                <span v-else :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 font-semibold', document.user_read_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                                    <Check v-if="document.user_read_at" class="h-3.5 w-3.5" :stroke-width="1.8" />
                                    {{ document.user_read_at ? 'Letto' : 'Da leggere' }}
                                </span>
                            </div>
                        </article>
                    </div>

                    <div v-else class="rounded-[var(--radius)] border border-white bg-white/72 px-5 py-10 text-center text-sm text-gray-500">
                        Nessun documento in questa categoria per il {{ year }}.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
