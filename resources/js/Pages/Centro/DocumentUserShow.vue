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

const categoryFilters = ref({});
const readCount = computed(() => (props.documents || []).filter((document) => document.user_read_at).length);
const unreadCount = computed(() => Math.max(0, (props.documents || []).length - readCount.value));
const currentYear = new Date().getFullYear();
const selectedDocumentYear = ref(currentYear);
const hoveredDocumentYear = ref(null);
const yearVisibleCounts = ref({ [currentYear]: 5 });
const categoryOptions = computed(() => [
    { value: 'all', label: 'Tutte le categorie' },
    ...Object.entries(props.documentCategories || {}).map(([value, label]) => ({ value, label })),
]);
const documentYearGroups = computed(() => {
    const groups = (props.documents || [])
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
    yearVisibleCounts.value = { ...yearVisibleCounts.value, [year]: 5 };
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

function filteredDocumentsForYear(group) {
    return filterDocumentsByCategory(group.documents, group.year);
}

function visibleDocumentsForYear(group) {
    return filteredDocumentsForYear(group).slice(0, yearVisibleCounts.value[group.year] || 5);
}

function showMoreYearDocuments(year) {
    yearVisibleCounts.value = {
        ...yearVisibleCounts.value,
        [year]: (yearVisibleCounts.value[year] || 5) + 5,
    };
}

function toggleDocumentYear(year) {
    selectedDocumentYear.value = selectedDocumentYear.value === year ? null : year;
    if (!yearVisibleCounts.value[year]) {
        yearVisibleCounts.value = { ...yearVisibleCounts.value, [year]: 5 };
    }
}

function yearScaleClass(year) {
    if (hoveredDocumentYear.value === year) return 'scale-[1.18] text-[hsl(var(--primary-app))]';
    return selectedDocumentYear.value === year ? 'text-gray-950' : 'text-gray-500';
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
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
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
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Documenti assegnati</h3>
                            <p class="mt-1 text-sm text-gray-500">Documenti {{ currentYear }} in evidenza e archivio diviso per anno.</p>
                        </div>
                        <div v-if="selectedDocumentYear" class="w-full max-w-[260px]">
                            <AppSelect
                                :model-value="categoryFilterFor(selectedDocumentYear)"
                                :options="categoryOptions"
                                @update:model-value="setCategoryFilter(selectedDocumentYear, $event)"
                            />
                        </div>
                    </div>

                    <div v-if="documents.length" class="document-year-stack">
                        <section v-for="group in documentYearGroups" :key="group.year" class="document-year-section">
                            <button
                                type="button"
                                :class="['document-year-button origin-left', yearScaleClass(group.year)]"
                                :aria-expanded="selectedDocumentYear === group.year"
                                @mouseenter="hoveredDocumentYear = group.year"
                                @mouseleave="hoveredDocumentYear = null"
                                @focus="hoveredDocumentYear = group.year"
                                @blur="hoveredDocumentYear = null"
                                @click="toggleDocumentYear(group.year)"
                            >
                                <span class="text-2xl font-semibold leading-none">{{ group.year }}</span>
                                <span v-if="selectedDocumentYear === group.year" class="text-xs font-medium text-gray-400">{{ group.total }} {{ group.total === 1 ? 'documento' : 'documenti' }}</span>
                            </button>

                            <div
                                :class="['document-year-expand', selectedDocumentYear === group.year ? 'is-open' : '']"
                                :aria-hidden="selectedDocumentYear !== group.year"
                            >
                                <div class="document-year-expand-inner">
                                    <div class="mt-5 space-y-4 pb-7">
                                        <div v-if="filteredDocumentsForYear(group).length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                            <Link
                                                v-for="document in visibleDocumentsForYear(group)"
                                                :key="document.id"
                                                :href="route('documents.show', document.id)"
                                                class="surface document-preview-card group p-4 transition hover:-translate-y-0.5"
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
                                        <div v-else class="surface px-5 py-8 text-center text-sm text-gray-500">Nessun documento per questa categoria.</div>

                                        <div v-if="filteredDocumentsForYear(group).length > visibleDocumentsForYear(group).length" class="flex justify-center">
                                            <button type="button" class="btn btn-outline" @click="showMoreYearDocuments(group.year)">Carica altri</button>
                                        </div>
                                    </div>
                                </div>
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

<style scoped>
.document-year-stack {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: stretch;
}

.document-year-stack::before {
    content: '';
    position: absolute;
    left: 0.28rem;
    top: 1.25rem;
    bottom: 1.25rem;
    width: 1px;
    background: rgb(148 163 184 / 0.2);
}

.document-year-section {
    position: relative;
    padding-left: 1.5rem;
}

.document-year-section::before {
    content: '';
    position: absolute;
    left: 0;
    top: 1.1rem;
    width: 0.58rem;
    height: 0.58rem;
    border: 2px solid white;
    border-radius: 9999px;
    background: hsl(var(--primary-app) / 0.42);
    box-shadow: 0 0 0 1px hsl(var(--primary-app) / 0.14);
}

.document-year-button {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.2rem;
    padding: 0.55rem 0;
    text-align: left;
    cursor: pointer;
    transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), color 180ms ease;
    will-change: transform;
}

.document-year-button:focus-visible {
    border-radius: var(--radius-sm);
    outline: 2px solid hsl(var(--primary-app) / 0.35);
    outline-offset: 4px;
}

.document-year-expand {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(-6px);
    pointer-events: none;
    transition:
        grid-template-rows 360ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 220ms ease,
        transform 320ms cubic-bezier(0.22, 1, 0.36, 1);
}

.document-year-expand.is-open {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.document-year-expand-inner {
    min-height: 0;
    overflow: hidden;
}

.document-preview-card {
    box-shadow: 0 5px 14px rgb(28 42 73 / 0.055);
}

.document-preview-card:hover {
    box-shadow: 0 8px 20px rgb(28 42 73 / 0.075);
}

@media (prefers-reduced-motion: reduce) {
    .document-year-button,
    .document-year-expand {
        transition: none;
    }
}
</style>
