<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateIt, dateTimeIt } from '@/utils/formatters';
import { Head, Link } from '@inertiajs/vue3';
import { Check, ChevronLeft, FileText } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    user: Object,
    documents: Array,
    documentCategories: Object,
});

const currentYearVisibleCount = ref(5);
const categoryFilters = ref({});
const readCount = computed(() => (props.documents || []).filter((document) => document.user_read_at).length);
const unreadCount = computed(() => Math.max(0, (props.documents || []).length - readCount.value));
const currentYear = new Date().getFullYear();
const categoryOptions = computed(() => [
    { value: 'all', label: 'Tutte le categorie' },
    ...Object.entries(props.documentCategories || {}).map(([value, label]) => ({ value, label })),
]);
const currentYearDocuments = computed(() => filterDocumentsByCategory(
    (props.documents || []).filter((document) => documentYear(document) === currentYear),
    currentYear,
));
const visibleCurrentYearDocuments = computed(() => currentYearDocuments.value.slice(0, currentYearVisibleCount.value));
const previousYearGroups = computed(() => {
    const groups = (props.documents || [])
        .filter((document) => documentYear(document) !== currentYear)
        .reduce((carry, document) => {
            const year = documentYear(document);
            carry[year] = carry[year] || [];
            carry[year].push(document);

            return carry;
        }, {});

    return Object.entries(groups)
        .sort(([yearA], [yearB]) => Number(yearB) - Number(yearA))
        .map(([year, documents]) => ({ year, documents, total: documents.length }));
});

function documentYear(document) {
    const year = Number(document.document_year || new Date(document.created_at).getFullYear());
    return Number.isFinite(year) ? year : currentYear;
}

function categoryFilterFor(year) {
    return categoryFilters.value[year] || 'all';
}

function setCategoryFilter(year, value) {
    categoryFilters.value = { ...categoryFilters.value, [year]: value };
    if (Number(year) === currentYear) currentYearVisibleCount.value = 5;
}

function filterDocumentsByCategory(documents, year) {
    const category = categoryFilterFor(year);
    if (category === 'all') return documents;

    return documents.filter((document) => (document.category || 'documenti_vari') === category);
}

function categoryLabel(category) {
    return props.documentCategories?.[category || 'documenti_vari'] || 'Documenti Vari';
}

function categoryBadgeStyle(category) {
    return {
        compensi: { backgroundColor: '#DCFCE7', color: '#166534' },
        contratti: { backgroundColor: '#DBEAFE', color: '#1E40AF' },
        corsi_attestati: { backgroundColor: '#FEF3C7', color: '#92400E' },
        documenti_identita: { backgroundColor: '#EDE9FE', color: '#5B21B6' },
        documenti_vari: { backgroundColor: '#F1F5F9', color: '#334155' },
    }[category || 'documenti_vari'] || { backgroundColor: '#F1F5F9', color: '#334155' };
}

function audienceLabel(document) {
    if (document.audience === 'all') return 'Tutti';
    if (document.audience === 'groups') return 'Gruppi';
    return 'Utente';
}

function showMoreCurrentYearDocuments() {
    currentYearVisibleCount.value += 5;
}
</script>

<template>
    <Head :title="`Documenti ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('documents.list')" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                    Documenti
                </Link>
                <div class="flex items-center gap-3">
                    <UserAvatar :user="user" size="md" />
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ user.name }}</h2>
                        <p class="text-sm text-gray-500">{{ user.email }}</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 sm:grid-cols-3">
                    <div class="surface p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Documenti</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ documents.length }}</p>
                    </div>
                    <div class="surface p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Letti</p>
                        <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ readCount }}</p>
                    </div>
                    <div class="surface p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Da leggere</p>
                        <p class="mt-2 text-2xl font-semibold text-amber-700">{{ unreadCount }}</p>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Documenti assegnati</h3>
                        <p class="mt-1 text-sm text-gray-500">Documenti {{ currentYear }} in evidenza e archivio diviso per anno.</p>
                    </div>

                    <div v-if="documents.length" class="space-y-6">
                        <section class="space-y-3">
                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">Anno corrente</h4>
                                    <p class="text-xs text-gray-500">{{ currentYearDocuments.length }} documenti del {{ currentYear }}</p>
                                </div>
                                <div class="w-full max-w-[260px]">
                                    <AppSelect
                                        :model-value="categoryFilterFor(currentYear)"
                                        :options="categoryOptions"
                                        @update:model-value="setCategoryFilter(currentYear, $event)"
                                    />
                                </div>
                            </div>

                            <div v-if="currentYearDocuments.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <Link
                                    v-for="document in visibleCurrentYearDocuments"
                                    :key="document.id"
                                    :href="route('documents.show', document.id)"
                                    class="surface group p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                            <FileText class="h-5 w-5" :stroke-width="1.7" />
                                        </span>
                                        <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold', document.user_read_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                                            <Check v-if="document.user_read_at" class="h-3.5 w-3.5" :stroke-width="1.8" />
                                            {{ document.user_read_at ? 'Letto' : 'Da leggere' }}
                                        </span>
                                    </div>
                                    <p class="mt-3 line-clamp-2 text-sm font-semibold text-gray-900">{{ document.title }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ audienceLabel(document) }} · {{ dateIt(document.created_at) }}</p>
                                    <p class="mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :style="categoryBadgeStyle(document.category)">
                                        {{ categoryLabel(document.category) }}
                                    </p>
                                    <div class="mt-4 rounded-[var(--radius-sm)] bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                        <p>{{ document.user_opened_at ? `Aperto ${dateTimeIt(document.user_opened_at)}` : 'Non ancora aperto' }}</p>
                                        <p class="mt-1">{{ document.user_read_at ? `Letto ${dateTimeIt(document.user_read_at)}` : 'Conferma lettura mancante' }}</p>
                                    </div>
                                </Link>
                            </div>
                            <div v-else class="surface px-5 py-8 text-center text-sm text-gray-500">
                                Nessun documento per il {{ currentYear }}.
                            </div>

                            <div v-if="currentYearDocuments.length > visibleCurrentYearDocuments.length" class="flex justify-center">
                                <button type="button" class="btn btn-outline" @click="showMoreCurrentYearDocuments">
                                    Carica altri
                                </button>
                            </div>
                        </section>

                        <section v-if="previousYearGroups.length" class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Anni precedenti</h4>
                                <p class="text-xs text-gray-500">Seleziona un anno per aprire l'archivio relativo.</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="group in previousYearGroups"
                                    :key="group.year"
                                    :href="route('documents.archive', group.year)"
                                    class="rounded-[var(--radius-sm)] border border-white bg-white/70 px-4 py-2 text-left text-gray-700 transition hover:-translate-y-0.5 hover:border-[hsl(var(--primary-app))] hover:bg-[hsl(var(--primary-app)/0.10)] hover:text-[hsl(var(--primary-app-dark))] hover:shadow-[0_12px_28px_rgba(28,42,73,0.08)]"
                                >
                                    <span class="block text-sm font-semibold">{{ group.year }}</span>
                                    <span class="block text-xs text-gray-500">{{ group.total }} documenti</span>
                                </Link>
                            </div>
                        </section>
                    </div>
                    <div v-else class="surface px-5 py-12 text-center text-sm text-gray-500">
                        Nessun documento assegnato a questo utente.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
