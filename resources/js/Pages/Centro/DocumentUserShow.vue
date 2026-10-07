<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import CompensationBulkModal from '@/Components/CompensationBulkModal.vue';
import DocumentPdfDropzone from '@/Components/DocumentPdfDropzone.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateIt, dateTimeIt } from '@/utils/formatters';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, ChevronLeft, FileText, FolderUp, Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    user: Object,
    documents: Array,
    documentCategories: Object,
});

const page = usePage();
const isSuperadmin = computed(() => page.props.auth?.user?.role === 'superadmin');
const createModal = ref(null);
const documentReview = ref(false);
const documentForm = useForm({
    title: '',
    description: '',
    category: 'documenti_vari',
    document_year: new Date().getFullYear(),
    audience: 'users',
    user_ids: [props.user.id],
    group_ids: [],
    publication_confirmed: false,
    file: null,
});
const creationCategories = computed(() => Object.entries(props.documentCategories || {})
    .map(([value, label]) => ({ value, label })));
const creationYearOptions = Array.from({ length: new Date().getFullYear() - 1998 }, (_, index) => ({
    value: new Date().getFullYear() + 1 - index,
    label: String(new Date().getFullYear() + 1 - index),
}));
watch(() => props.user.id, (id) => {
    createModal.value = null;
    documentReview.value = false;
    documentForm.reset();
    documentForm.user_ids = [id];
});

function closeDocumentModal() {
    createModal.value = null;
    documentReview.value = false;
    documentForm.reset();
    documentForm.clearErrors();
}

function submitDocument() {
    if (!documentReview.value) {
        documentForm.clearErrors();
        if (!documentForm.file) {
            documentForm.setError('file', 'Seleziona un PDF prima di continuare.');
            return;
        }
        documentReview.value = true;
        return;
    }
    documentForm.publication_confirmed = true;
    documentForm.post(route('documents.store', { from_user: props.user.id }), {
        forceFormData: true,
        preserveScroll: true,
        onError: () => { documentReview.value = false; documentForm.publication_confirmed = false; },
        onSuccess: closeDocumentModal,
    });
}

const selectedCategory = ref('all');
const readCount = computed(() => (props.documents || []).filter((document) => document.user_read_at).length);
const unreadCount = computed(() => Math.max(0, (props.documents || []).length - readCount.value));
const currentYear = new Date().getFullYear();
const latestDocumentYear = (props.documents || []).reduce((latest, document) => Math.max(latest, documentYear(document)), 0) || currentYear;
const selectedDocumentYear = ref(latestDocumentYear);
const hoveredDocumentYear = ref(null);
const yearVisibleCounts = ref({ [selectedDocumentYear.value]: 8 });
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
        .map(([year, documents]) => ({ year: Number(year), documents, total: documents.length }));
});

function documentYear(document) {
    const year = Number(document.document_year || new Date(document.created_at).getFullYear());
    return Number.isFinite(year) ? year : currentYear;
}

watch(selectedCategory, () => {
    yearVisibleCounts.value = { [selectedDocumentYear.value || currentYear]: 8 };
});

function categoryLabel(category) {
    return props.documentCategories?.[category || 'documenti_vari'] || 'Documenti Vari';
}

function filteredDocumentsForYear(group) {
    return group.documents.filter((document) => selectedCategory.value === 'all' || (document.category || 'documenti_vari') === selectedCategory.value);
}

function visibleDocumentsForYear(group) {
    return filteredDocumentsForYear(group).slice(0, yearVisibleCounts.value[group.year] || 8);
}

function showMoreYearDocuments(year) {
    yearVisibleCounts.value = {
        ...yearVisibleCounts.value,
        [year]: (yearVisibleCounts.value[year] || 8) + 8,
    };
}

function toggleDocumentYear(year) {
    selectedDocumentYear.value = selectedDocumentYear.value === year ? null : year;
    if (!yearVisibleCounts.value[year]) {
        yearVisibleCounts.value = { ...yearVisibleCounts.value, [year]: 8 };
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
                <div v-if="isSuperadmin" class="flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" title="Carica una cartella di Compensi" aria-label="Carica una cartella di Compensi" @click="createModal = 'compensi'">
                        <FolderUp class="h-4 w-4" :stroke-width="1.7" />
                    </button>
                    <button type="button" class="btn btn-primary" @click="createModal = 'document'">
                        <Plus class="h-4 w-4" :stroke-width="1.7" /> Nuovo documento
                    </button>
                </div>
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
                            <p class="mt-1 text-sm text-gray-500">Documenti {{ latestDocumentYear }} in evidenza e archivio diviso per anno.</p>
                        </div>
                        <div class="w-full max-w-[260px]">
                            <AppSelect v-model="selectedCategory" :options="categoryOptions" searchable />
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
                                    <div class="mt-4 space-y-3 pb-6">
                                        <div v-if="filteredDocumentsForYear(group).length" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                            <Link
                                                v-for="document in visibleDocumentsForYear(group)"
                                                :key="document.id"
                                                :href="route('documents.show', { id: document.id, from_user: user.id })"
                                                class="group min-w-0 rounded-[var(--radius-sm)] border border-gray-200 bg-white p-3 transition-colors hover:border-blue-200 hover:bg-blue-50/50 focus-visible:outline-2 focus-visible:outline-blue-400"
                                            >
                                                <div class="flex items-start justify-between gap-2">
                                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                                        <FileText class="h-4 w-4" :stroke-width="1.7" />
                                                    </span>
                                                    <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold', document.user_read_at ? 'bg-emerald-50 text-emerald-700' : document.user_opened_at ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700']">
                                                        <Check v-if="document.user_read_at" class="h-3 w-3" :stroke-width="1.8" />
                                                        {{ document.user_read_at ? 'Letto' : document.user_opened_at ? 'Aperto' : 'Da leggere' }}
                                                    </span>
                                                </div>
                                                <p class="mt-2 truncate text-sm font-semibold text-gray-900" :title="document.title">{{ document.title }}</p>
                                                <p class="mt-1 truncate text-xs text-gray-500">{{ categoryLabel(document.category) }} · {{ dateIt(document.created_at) }}</p>
                                                <p class="mt-1 truncate text-xs text-gray-400">{{ document.user_read_at ? `Letto ${dateTimeIt(document.user_read_at)}` : document.user_opened_at ? `Aperto ${dateTimeIt(document.user_opened_at)}` : 'Non ancora aperto' }}</p>
                                            </Link>
                                        </div>
                                        <div v-else class="rounded-[var(--radius-sm)] border border-gray-200 bg-white/70 px-5 py-8 text-center text-sm text-gray-500">{{ selectedCategory === 'all' ? 'Nessun documento per questo anno.' : 'Nessun documento con il filtro selezionato.' }}</div>

                                        <div v-if="filteredDocumentsForYear(group).length > visibleDocumentsForYear(group).length" class="flex justify-center">
                                            <button type="button" class="btn btn-outline" @click="showMoreYearDocuments(group.year)">Carica altri</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div v-else class="rounded-[var(--radius-sm)] border border-gray-200 bg-white/70 px-5 py-12 text-center text-sm text-gray-500">
                        Nessun documento assegnato a questo utente.
                    </div>
                </section>
            </div>
        </div>
        <Teleport to="body">
            <CompensationBulkModal v-if="isSuperadmin && createModal === 'compensi'" :key="user.id" :users="[user]" :initial-user-id="user.id" @close="createModal = null" />
            <div v-if="isSuperadmin && createModal === 'document'" class="fixed inset-0 z-[8000] flex items-center justify-center bg-black/15 px-4 py-6 backdrop-blur-sm" @click.self="closeDocumentModal">
                <form class="surface max-h-[calc(100dvh-3rem)] w-full max-w-3xl space-y-5 overflow-y-auto bg-white p-5" @submit.prevent="submitDocument">
                    <div class="flex items-start justify-between gap-4">
                        <div><h3 class="text-base font-semibold text-gray-900">Nuovo documento</h3><p class="mt-1 text-sm text-gray-500">Carica un PDF per {{ user.name }}.</p></div>
                        <button type="button" class="icon-btn" aria-label="Chiudi" :disabled="documentForm.processing" @click="closeDocumentModal"><X class="h-4 w-4" :stroke-width="1.7" /></button>
                    </div>
                    <div v-if="!documentReview" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div><label class="block text-sm font-medium text-gray-700">Titolo</label><input v-model="documentForm.title" class="form-control" required placeholder="Es. Compenso Gennaio 2026" /><p v-if="documentForm.errors.title" class="mt-1 text-sm text-red-600">{{ documentForm.errors.title }}</p></div>
                            <div><label class="block text-sm font-medium text-gray-700">Categoria</label><AppSelect v-model="documentForm.category" :options="creationCategories" /><p v-if="documentForm.errors.category" class="mt-1 text-sm text-red-600">{{ documentForm.errors.category }}</p></div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div><label class="block text-sm font-medium text-gray-700">Destinatario</label><div class="mt-1 flex min-h-11 items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 px-3 py-2"><UserAvatar :user="user" size="xs" /><span class="text-sm font-semibold text-gray-800">{{ user.name }}</span></div></div>
                            <div><label class="block text-sm font-medium text-gray-700">Anno</label><AppSelect v-model="documentForm.document_year" :options="creationYearOptions" searchable /><p v-if="documentForm.errors.document_year" class="mt-1 text-sm text-red-600">{{ documentForm.errors.document_year }}</p></div>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700">Descrizione</label><textarea v-model="documentForm.description" rows="4" class="form-control" placeholder="Nota interna opzionale..."></textarea><p v-if="documentForm.errors.description" class="mt-1 text-sm text-red-600">{{ documentForm.errors.description }}</p></div>
                        <div><label class="block text-sm font-medium text-gray-700">PDF</label><DocumentPdfDropzone v-model="documentForm.file" :error="documentForm.errors.file" @update:model-value="documentForm.clearErrors('file')" /></div>
                    </div>
                    <div v-else class="rounded-[var(--radius-sm)] border border-blue-100 bg-blue-50/60 p-4">
                        <p class="text-sm font-semibold text-gray-900">Conferma pubblicazione</p>
                        <p class="mt-1 text-sm text-gray-600">{{ documentForm.title }} · {{ documentCategories?.[documentForm.category] }} · {{ documentForm.document_year }}</p>
                        <p class="mt-2 text-xs font-semibold uppercase text-gray-500">Destinatario</p>
                        <p class="mt-1 text-sm text-gray-800">{{ user.name }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button v-if="documentReview" type="button" class="btn btn-outline" @click="documentReview = false">Indietro</button>
                        <button type="submit" class="btn btn-primary" :disabled="documentForm.processing">{{ documentForm.processing ? 'Pubblicazione...' : documentReview ? 'Conferma e pubblica' : 'Continua' }}</button>
                    </div>
                </form>
            </div>
        </Teleport>
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

@media (prefers-reduced-motion: reduce) {
    .document-year-button,
    .document-year-expand {
        transition: none;
    }
}
</style>
