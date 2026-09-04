<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateIt } from '@/utils/formatters';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Bold, Check, Download, FileText, Heading3, Italic, Link2, List, ListOrdered, MessageSquare, Plus, Quote, Send, Table2, Trash2, Underline, Upload, Users, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    canManage: Boolean,
    activeAdminSection: String,
    documents: Array,
    messages: Array,
    attendanceReport: Object,
    groups: Array,
    users: Array,
    documentUsers: Array,
    documentCategories: Object,
});

const page = usePage();
const confirmDelete = ref(null);
const confirmDeleteText = ref('');
const selectedDocumentYear = ref(new Date().getFullYear());
const hoveredDocumentYear = ref(null);
const yearVisibleCounts = ref({ [new Date().getFullYear()]: 5 });
const documentDescriptionEditor = ref(null);
const messageBodyEditor = ref(null);
const createModal = ref(null);
const categoryFilters = ref({});
const isSuperadmin = computed(() => page.props.auth?.user?.role === 'superadmin');
const activeAdminSection = computed(() => props.activeAdminSection || null);
const reportYear = ref(props.attendanceReport?.year || new Date().getFullYear());
const reportMonth = ref(props.attendanceReport?.month || (new Date().getMonth() + 1));
const reportUserId = ref(props.attendanceReport?.selected_user_id || 'all');

const documentForm = useForm({
    title: '',
    description: '',
    category: 'documenti_vari',
    audience: 'all',
    file: null,
    user_ids: [],
    group_ids: [],
});

const groupForm = useForm({
    name: '',
    description: '',
    user_ids: [],
});

const messageForm = useForm({
    title: '',
    body: '',
    audience: 'all',
    user_ids: [],
    group_ids: [],
});

const audienceOptions = [
    { value: 'all', label: 'Tutti' },
    { value: 'users', label: 'Utenti specifici' },
    { value: 'groups', label: 'Gruppi' },
];

const visibleDocuments = computed(() => props.documents || []);
const visibleMessages = computed(() => props.messages || []);
const documentUsers = computed(() => props.documentUsers || []);
const currentYear = new Date().getFullYear();
const reportYearOptions = computed(() => Array.from({ length: 6 }, (_, index) => {
    const year = currentYear - 4 + index;
    return { value: year, label: String(year) };
}));
const reportMonthOptions = [
    { value: 1, label: 'Gennaio' },
    { value: 2, label: 'Febbraio' },
    { value: 3, label: 'Marzo' },
    { value: 4, label: 'Aprile' },
    { value: 5, label: 'Maggio' },
    { value: 6, label: 'Giugno' },
    { value: 7, label: 'Luglio' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Settembre' },
    { value: 10, label: 'Ottobre' },
    { value: 11, label: 'Novembre' },
    { value: 12, label: 'Dicembre' },
];
const reportUserOptions = computed(() => [
    { value: 'all', label: 'Tutto il team' },
    ...(props.users || []).map((user) => ({ value: user.id, label: user.name })),
]);
const categoryOptions = computed(() => [
    { value: 'all', label: 'Tutte le categorie' },
    ...Object.entries(props.documentCategories || {}).map(([value, label]) => ({ value, label })),
]);
const documentCategoryOptions = computed(() => categoryOptions.value.filter((option) => option.value !== 'all'));
const documentYearGroups = computed(() => {
    const grouped = visibleDocuments.value.reduce((carry, document) => {
        const year = documentYear(document);
        carry[year] = carry[year] || [];
        carry[year].push(document);
        return carry;
    }, { [currentYear]: [] });

    return Object.entries(grouped)
        .sort(([yearA], [yearB]) => Number(yearB) - Number(yearA))
        .map(([year, documents]) => ({ year: Number(year), documents, total: documents.length }));
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

function filteredDocumentsForYear(group) {
    return filterDocumentsByCategory(group.documents, group.year);
}

function visibleDocumentsForYear(group) {
    return filteredDocumentsForYear(group).slice(0, yearVisibleCounts.value[group.year] || 5);
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

function resetDocumentForm() {
    documentForm.reset();
    documentForm.clearErrors();
    nextTick(() => {
        if (documentDescriptionEditor.value) {
            documentDescriptionEditor.value.innerHTML = '';
        }
    });
}

function closeCreateModal() {
    createModal.value = null;
}

function submitDocument() {
    updateDocumentDescriptionFromEditor();
    documentForm.post(route('documents.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetDocumentForm();
            closeCreateModal();
        },
    });
}

function updateDocumentDescriptionFromEditor() {
    documentForm.description = documentDescriptionEditor.value?.innerHTML || '';
}

function runDocumentEditorCommand(command, value = null) {
    documentDescriptionEditor.value?.focus();
    document.execCommand(command, false, value);
    updateDocumentDescriptionFromEditor();
}

function addDocumentEditorLink() {
    const url = window.prompt('URL del link');
    if (!url) return;

    runDocumentEditorCommand('createLink', url);
}

function submitGroup() {
    groupForm.post(route('document-groups.store'), {
        preserveScroll: true,
        onSuccess: () => {
            groupForm.reset();
            closeCreateModal();
        },
    });
}

function resetMessageForm() {
    messageForm.reset();
    messageForm.clearErrors();
    nextTick(() => {
        if (messageBodyEditor.value) {
            messageBodyEditor.value.innerHTML = '';
        }
    });
}

function submitMessage() {
    updateMessageBodyFromEditor();
    messageForm.post(route('document-messages.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetMessageForm();
            closeCreateModal();
        },
    });
}

function updateMessageBodyFromEditor() {
    messageForm.body = messageBodyEditor.value?.innerHTML || '';
}

function runMessageEditorCommand(command, value = null) {
    messageBodyEditor.value?.focus();
    document.execCommand(command, false, value);
    updateMessageBodyFromEditor();
}

function addMessageEditorLink() {
    const url = window.prompt('URL del link');
    if (!url) return;

    runMessageEditorCommand('createLink', url);
}

function toggleDocumentUser(userId) {
    documentForm.user_ids = documentForm.user_ids.includes(userId)
        ? documentForm.user_ids.filter((id) => id !== userId)
        : [...documentForm.user_ids, userId];
}

function toggleDocumentGroup(groupId) {
    documentForm.group_ids = documentForm.group_ids.includes(groupId)
        ? documentForm.group_ids.filter((id) => id !== groupId)
        : [...documentForm.group_ids, groupId];
}

function toggleMessageUser(userId) {
    messageForm.user_ids = messageForm.user_ids.includes(userId)
        ? messageForm.user_ids.filter((id) => id !== userId)
        : [...messageForm.user_ids, userId];
}

function toggleMessageGroup(groupId) {
    messageForm.group_ids = messageForm.group_ids.includes(groupId)
        ? messageForm.group_ids.filter((id) => id !== groupId)
        : [...messageForm.group_ids, groupId];
}

function toggleGroupUser(userId) {
    groupForm.user_ids = groupForm.user_ids.includes(userId)
        ? groupForm.user_ids.filter((id) => id !== userId)
        : [...groupForm.user_ids, userId];
}

function removeDocument(document) {
    if (isSuperadmin.value) {
        router.delete(route('documents.destroy', document.id), { preserveScroll: true });
        return;
    }

    confirmDelete.value = {
        type: 'document',
        title: document.title,
        route: route('documents.destroy', document.id),
    };
    confirmDeleteText.value = '';
}

function removeGroup(group) {
    if (isSuperadmin.value) {
        router.delete(route('document-groups.destroy', group.id), { preserveScroll: true });
        return;
    }

    confirmDelete.value = {
        type: 'group',
        title: group.name,
        route: route('document-groups.destroy', group.id),
    };
    confirmDeleteText.value = '';
}

function removeMessage(message) {
    if (isSuperadmin.value) {
        router.delete(route('document-messages.destroy', message.id), { preserveScroll: true });
        return;
    }

    confirmDelete.value = {
        type: 'message',
        title: message.title,
        route: route('document-messages.destroy', message.id),
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

function openDocument(document) {
    router.visit(route('documents.show', document.id));
}

function openMessage(message) {
    router.visit(route('document-messages.show', message.id));
}

function audienceLabel(item) {
    if (item.audience === 'all') return 'Tutti';
    if (item.audience === 'groups') {
        const names = (item.group_ids || [])
            .map((id) => (props.groups || []).find((group) => group.id === id)?.name)
            .filter(Boolean);

        return names.length ? names.join(', ') : 'Gruppi';
    }

    const names = (item.user_ids || [])
        .map((id) => (props.users || []).find((user) => user.id === id)?.name)
        .filter(Boolean);

    return names.length ? names.join(', ') : 'Utenti';
}

function fileSize(bytes) {
    const value = Number(bytes || 0);
    if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function showMoreYearDocuments(year) {
    yearVisibleCounts.value = {
        ...yearVisibleCounts.value,
        [year]: (yearVisibleCounts.value[year] || 5) + 5,
    };
}

function loadReport() {
    const params = {
        year: reportYear.value,
        month: reportMonth.value,
    };
    if (reportUserId.value !== 'all') params.user_id = reportUserId.value;

    router.get(route('documents.reports'), params, {
        preserveScroll: true,
        preserveState: true,
    });
}

function reportExportHref() {
    const params = {
        year: reportYear.value,
        month: reportMonth.value,
    };
    if (reportUserId.value !== 'all') params.user_id = reportUserId.value;

    return route('documents.reports.export', params);
}

function deleteLabel(type) {
    if (type === 'group') return 'il gruppo';
    if (type === 'message') return 'il messaggio';

    return 'il documento';
}
</script>

<template>
    <Head title="Documenti" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Documenti</h2>
                <p class="text-sm text-gray-500">PDF aziendali, assegnazioni e conferme di lettura.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="confirmDelete" class="fixed inset-0 z-[5100] flex items-center justify-center bg-transparent px-4 py-6" @click.self="closeDeleteConfirm">
                    <div class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-xl">
                        <h3 class="text-base font-semibold text-gray-900">
                            Eliminare {{ deleteLabel(confirmDelete.type) }}?
                        </h3>
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

                <nav v-if="canManage" class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="route('documents.list')"
                            :class="['btn', activeAdminSection === 'documents' ? 'btn-primary' : 'btn-outline']"
                        >
                            <FileText class="h-4 w-4" :stroke-width="1.7" />
                            Tutti i documenti
                            <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ visibleDocuments.length }}</span>
                        </Link>
                        <Link
                            :href="route('documents.messages')"
                            :class="['btn', activeAdminSection === 'messages' ? 'btn-primary' : 'btn-outline']"
                        >
                            <MessageSquare class="h-4 w-4" :stroke-width="1.7" />
                            Tutti i messaggi
                            <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ visibleMessages.length }}</span>
                        </Link>
                        <Link
                            :href="route('documents.groups')"
                            :class="['btn', activeAdminSection === 'groups' ? 'btn-primary' : 'btn-outline']"
                        >
                            <Users class="h-4 w-4" :stroke-width="1.7" />
                            Gruppi
                            <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ groups.length }}</span>
                        </Link>
                        <Link
                            :href="route('documents.reports')"
                            :class="['btn', activeAdminSection === 'reports' ? 'btn-primary' : 'btn-outline']"
                        >
                            <Table2 class="h-4 w-4" :stroke-width="1.7" />
                            Report e dati
                        </Link>
                    </div>
                    <button v-if="activeAdminSection === 'documents'" type="button" class="btn btn-primary" @click="createModal = 'document'">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo documento
                    </button>
                    <button v-else-if="activeAdminSection === 'messages'" type="button" class="btn btn-primary" @click="createModal = 'message'">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo messaggio
                    </button>
                    <button v-else-if="activeAdminSection === 'groups'" type="button" class="btn btn-primary" @click="createModal = 'group'">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo gruppo
                    </button>
                </nav>

                <Teleport to="body">
                    <div v-if="canManage && createModal === 'document'" class="fixed inset-0 z-[8000] flex items-center justify-center bg-black/15 px-4 py-6 backdrop-blur-sm" @click.self="closeCreateModal">
                    <form class="surface max-h-[calc(100dvh-3rem)] w-full max-w-3xl space-y-5 overflow-y-auto bg-white p-5" @submit.prevent="submitDocument">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Nuovo documento</h3>
                                <p class="mt-1 text-sm text-gray-500">Carica un PDF e scegli chi deve leggerlo.</p>
                            </div>
                            <button type="button" class="icon-btn" aria-label="Chiudi" @click="closeCreateModal"><X class="h-4 w-4" :stroke-width="1.7" /></button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Titolo</label>
                                <input v-model="documentForm.title" class="form-control" required placeholder="Es. Policy ferie 2026" />
                                <div v-if="documentForm.errors.title" class="mt-1 text-sm text-red-600">{{ documentForm.errors.title }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Categoria</label>
                                <AppSelect v-model="documentForm.category" :options="documentCategoryOptions" />
                                <div v-if="documentForm.errors.category" class="mt-1 text-sm text-red-600">{{ documentForm.errors.category }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Destinatari</label>
                                <AppSelect v-model="documentForm.audience" :options="audienceOptions" />
                                <div v-if="documentForm.errors.audience" class="mt-1 text-sm text-red-600">{{ documentForm.errors.audience }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                                <div class="mt-1 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                    <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 px-2 py-1.5">
                                        <button type="button" class="icon-btn h-8 w-8" title="Titolo" @mousedown.prevent @click="runDocumentEditorCommand('formatBlock', 'h3')">
                                            <Heading3 class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8 text-xs font-bold" title="Testo normale" @mousedown.prevent @click="runDocumentEditorCommand('formatBlock', 'p')">
                                            P
                                        </button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runDocumentEditorCommand('bold')">
                                            <Bold class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runDocumentEditorCommand('italic')">
                                            <Italic class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runDocumentEditorCommand('underline')">
                                            <Underline class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runDocumentEditorCommand('insertUnorderedList')">
                                            <List class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runDocumentEditorCommand('insertOrderedList')">
                                            <ListOrdered class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Citazione" @mousedown.prevent @click="runDocumentEditorCommand('formatBlock', 'blockquote')">
                                            <Quote class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addDocumentEditorLink">
                                            <Link2 class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                    </div>
                                    <div
                                        ref="documentDescriptionEditor"
                                        class="min-h-[120px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                        contenteditable="true"
                                        data-placeholder="Nota interna opzionale..."
                                        @input="updateDocumentDescriptionFromEditor"
                                        @blur="updateDocumentDescriptionFromEditor"
                                    ></div>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">PDF</label>
                                <label class="mt-2 flex cursor-pointer items-center justify-between gap-3 rounded-[var(--radius)] border border-dashed border-gray-200 bg-white/70 px-4 py-4 text-sm font-semibold text-gray-600 transition hover:border-[hsl(var(--primary-app))] hover:text-[hsl(var(--primary-app))]">
                                    <span class="flex items-center gap-2">
                                        <Upload class="h-4 w-4" :stroke-width="1.7" />
                                        {{ documentForm.file?.name || 'Seleziona documento PDF' }}
                                    </span>
                                    <span class="text-xs text-gray-400">max 20 MB</span>
                                    <input type="file" accept="application/pdf,.pdf" class="hidden" @change="documentForm.file = $event.target.files[0]" />
                                </label>
                                <div v-if="documentForm.errors.file" class="mt-1 text-sm text-red-600">{{ documentForm.errors.file }}</div>
                            </div>
                        </div>

                        <div v-if="documentForm.audience === 'users'" class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                            <p class="mb-3 text-sm font-semibold text-gray-700">Seleziona utenti</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="user in users"
                                    :key="user.id"
                                    type="button"
                                    :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition', documentForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                                    @click="toggleDocumentUser(user.id)"
                                >
                                    <UserAvatar :user="user" size="xs" />
                                    {{ user.name }}
                                </button>
                            </div>
                            <div v-if="documentForm.errors.user_ids" class="mt-2 text-sm text-red-600">{{ documentForm.errors.user_ids }}</div>
                        </div>

                        <div v-if="documentForm.audience === 'groups'" class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                            <p class="mb-3 text-sm font-semibold text-gray-700">Seleziona gruppi</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="group in groups"
                                    :key="group.id"
                                    type="button"
                                    :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition', documentForm.group_ids.includes(group.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                                    @click="toggleDocumentGroup(group.id)"
                                >
                                    <Users class="h-4 w-4" :stroke-width="1.7" />
                                    {{ group.name }}
                                    <span class="text-xs text-gray-400">{{ group.members_count }}</span>
                                </button>
                            </div>
                            <div v-if="documentForm.errors.group_ids" class="mt-2 text-sm text-red-600">{{ documentForm.errors.group_ids }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary" :disabled="documentForm.processing">
                            <Plus class="h-4 w-4" :stroke-width="1.7" />
                            Pubblica documento
                        </button>
                    </form>
                    </div>

                    <div v-if="canManage && createModal === 'group'" class="fixed inset-0 z-[8000] flex items-center justify-center bg-black/15 px-4 py-6 backdrop-blur-sm" @click.self="closeCreateModal">
                    <form class="surface max-h-[calc(100dvh-3rem)] w-full max-w-xl space-y-4 overflow-y-auto bg-white p-5" @submit.prevent="submitGroup">
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="text-base font-semibold text-gray-900">Nuovo gruppo</h3>
                                <button type="button" class="icon-btn" aria-label="Chiudi" @click="closeCreateModal"><X class="h-4 w-4" :stroke-width="1.7" /></button>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Raggruppa gli utenti per invii mirati.</p>
                        </div>
                        <input v-model="groupForm.name" class="form-control" required placeholder="Nome gruppo" />
                        <textarea v-model="groupForm.description" rows="2" class="form-control" placeholder="Descrizione opzionale"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="user in users"
                                :key="user.id"
                                type="button"
                                :class="['inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition', groupForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                                @click="toggleGroupUser(user.id)"
                            >
                                <UserAvatar :user="user" size="xs" />
                                {{ user.name }}
                            </button>
                        </div>
                        <div v-if="groupForm.errors.user_ids" class="text-sm text-red-600">{{ groupForm.errors.user_ids }}</div>
                        <button type="submit" class="btn btn-outline" :disabled="groupForm.processing">
                            <Plus class="h-4 w-4" :stroke-width="1.7" />
                            Crea gruppo
                        </button>

                    </form>
                    </div>

                    <div v-if="canManage && createModal === 'message'" class="fixed inset-0 z-[8000] flex items-center justify-center bg-black/15 px-4 py-6 backdrop-blur-sm" @click.self="closeCreateModal">
                    <form class="surface max-h-[calc(100dvh-3rem)] w-full max-w-3xl space-y-5 overflow-y-auto bg-white p-5" @submit.prevent="submitMessage">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Nuovo messaggio</h3>
                                <p class="mt-1 text-sm text-gray-500">Invia una comunicazione a tutti, a un gruppo o a persone specifiche.</p>
                            </div>
                            <button type="button" class="icon-btn" aria-label="Chiudi" @click="closeCreateModal"><X class="h-4 w-4" :stroke-width="1.7" /></button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Titolo</label>
                                <input v-model="messageForm.title" class="form-control" required placeholder="Es. Comunicazione interna" />
                                <div v-if="messageForm.errors.title" class="mt-1 text-sm text-red-600">{{ messageForm.errors.title }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Destinatari</label>
                                <AppSelect v-model="messageForm.audience" :options="audienceOptions" />
                                <div v-if="messageForm.errors.audience" class="mt-1 text-sm text-red-600">{{ messageForm.errors.audience }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Messaggio</label>
                                <div class="mt-1 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                    <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 px-2 py-1.5">
                                        <button type="button" class="icon-btn h-8 w-8" title="Titolo" @mousedown.prevent @click="runMessageEditorCommand('formatBlock', 'h3')">
                                            <Heading3 class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8 text-xs font-bold" title="Testo normale" @mousedown.prevent @click="runMessageEditorCommand('formatBlock', 'p')">
                                            P
                                        </button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runMessageEditorCommand('bold')">
                                            <Bold class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runMessageEditorCommand('italic')">
                                            <Italic class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runMessageEditorCommand('underline')">
                                            <Underline class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runMessageEditorCommand('insertUnorderedList')">
                                            <List class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runMessageEditorCommand('insertOrderedList')">
                                            <ListOrdered class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Citazione" @mousedown.prevent @click="runMessageEditorCommand('formatBlock', 'blockquote')">
                                            <Quote class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addMessageEditorLink">
                                            <Link2 class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                    </div>
                                    <div
                                        ref="messageBodyEditor"
                                        class="min-h-[130px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                        contenteditable="true"
                                        data-placeholder="Scrivi il messaggio..."
                                        @input="updateMessageBodyFromEditor"
                                        @blur="updateMessageBodyFromEditor"
                                    ></div>
                                </div>
                                <div v-if="messageForm.errors.body" class="mt-1 text-sm text-red-600">{{ messageForm.errors.body }}</div>
                            </div>
                        </div>

                        <div v-if="messageForm.audience === 'users'" class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                            <p class="mb-3 text-sm font-semibold text-gray-700">Seleziona utenti</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="user in users"
                                    :key="user.id"
                                    type="button"
                                    :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition', messageForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                                    @click="toggleMessageUser(user.id)"
                                >
                                    <UserAvatar :user="user" size="xs" />
                                    {{ user.name }}
                                </button>
                            </div>
                            <div v-if="messageForm.errors.message_user_ids" class="mt-2 text-sm text-red-600">{{ messageForm.errors.message_user_ids }}</div>
                        </div>

                        <div v-if="messageForm.audience === 'groups'" class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                            <p class="mb-3 text-sm font-semibold text-gray-700">Seleziona gruppi</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="group in groups"
                                    :key="group.id"
                                    type="button"
                                    :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition', messageForm.group_ids.includes(group.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                                    @click="toggleMessageGroup(group.id)"
                                >
                                    <Users class="h-4 w-4" :stroke-width="1.7" />
                                    {{ group.name }}
                                    <span class="text-xs text-gray-400">{{ group.members_count }}</span>
                                </button>
                            </div>
                            <div v-if="messageForm.errors.message_group_ids" class="mt-2 text-sm text-red-600">{{ messageForm.errors.message_group_ids }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary" :disabled="messageForm.processing">
                            <Send class="h-4 w-4" :stroke-width="1.7" />
                            Pubblica messaggio
                        </button>
                    </form>
                    </div>
                </Teleport>

                <section v-if="!canManage || activeAdminSection === 'messages'" class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ canManage ? 'Tutti i messaggi' : 'Messaggi da leggere' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">Comunicazioni con conferma di lettura.</p>
                        </div>
                    </div>

                    <div v-if="visibleMessages.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="message in visibleMessages"
                            :key="message.id"
                            role="button"
                            tabindex="0"
                            :class="['surface group cursor-pointer p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]', !canManage && !message.user_read_at ? 'ring-1 ring-amber-100' : '']"
                            @click="openMessage(message)"
                            @keydown.enter.prevent="openMessage(message)"
                            @keydown.space.prevent="openMessage(message)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                        <MessageSquare class="h-5 w-5" :stroke-width="1.7" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="line-clamp-2 text-sm font-semibold text-gray-900 transition group-hover:text-[hsl(var(--primary-app))]">
                                            {{ message.title }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500">{{ audienceLabel(message) }} · {{ dateIt(message.created_at) }}</p>
                                    </div>
                                </div>
                                <button v-if="canManage" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina messaggio" @click.stop="removeMessage(message)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>

                            <div v-if="message.body" class="mt-3 line-clamp-2 text-sm text-gray-500" v-html="message.body"></div>

                            <div class="mt-4 flex items-center justify-between gap-3 text-xs text-gray-500">
                                <span>{{ message.creator_name || 'Il Centro' }}</span>
                                <span v-if="canManage" class="font-semibold text-gray-700">{{ message.read_count }}/{{ message.recipient_count }} letti</span>
                                <span v-else :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 font-semibold', message.user_read_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                                    <Check v-if="message.user_read_at" class="h-3.5 w-3.5" :stroke-width="1.8" />
                                    {{ message.user_read_at ? 'Letto' : 'Da leggere' }}
                                </span>
                            </div>
                        </article>
                    </div>
                    <div v-else class="surface px-5 py-8 text-center text-sm text-gray-500">
                        Nessun messaggio disponibile.
                    </div>
                </section>

                <section v-if="canManage && activeAdminSection === 'groups'" class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Gruppi documenti</h3>
                            <p class="mt-1 text-sm text-gray-500">Gruppi di persone utilizzabili come destinatari di documenti e messaggi.</p>
                        </div>
                    </div>

                    <div v-if="groups.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <article v-for="group in groups" :key="group.id" class="surface flex min-h-28 items-start justify-between gap-3 p-4">
                            <div class="min-w-0">
                                <span class="mb-3 flex h-9 w-9 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                    <Users class="h-4 w-4" :stroke-width="1.7" />
                                </span>
                                <p class="truncate text-sm font-semibold text-gray-900">{{ group.name }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ group.members_count }} utenti</p>
                            </div>
                            <button type="button" class="icon-btn h-8 w-8 shrink-0 text-red-600 hover:bg-red-50" title="Elimina gruppo" @click="removeGroup(group)">
                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                            </button>
                        </article>
                    </div>
                    <div v-else class="surface px-5 py-12 text-center text-sm text-gray-500">Nessun gruppo creato.</div>
                </section>

                <section v-if="canManage && activeAdminSection === 'reports'" class="space-y-6">
                    <div class="surface p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Report e dati</h3>
                                <p class="mt-1 text-sm text-gray-500">Presenze mensili nel formato del tracciato paghe.</p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[160px_140px_minmax(220px,1fr)_auto]">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Mese</label>
                                    <AppSelect v-model="reportMonth" :options="reportMonthOptions" @update:model-value="loadReport" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Anno</label>
                                    <AppSelect v-model="reportYear" :options="reportYearOptions" @update:model-value="loadReport" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Team</label>
                                    <AppSelect v-model="reportUserId" :options="reportUserOptions" @update:model-value="loadReport" />
                                </div>
                                <a :href="reportExportHref()" class="btn btn-primary self-end">
                                    <Download class="h-4 w-4" :stroke-width="1.7" />
                                    Esporta XLSX
                                </a>
                            </div>
                        </div>

                        <p v-if="attendanceReport?.scope_label" class="mt-4 text-sm font-medium text-gray-500">
                            Report: <span class="text-gray-900">{{ attendanceReport.scope_label }}</span>
                        </p>

                        <div v-if="attendanceReport" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <div class="rounded-[var(--radius-sm)] bg-white/70 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Persone</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceReport.summary.users }}</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] bg-white/70 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Ore ordinarie</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceReport.summary.ordinary }}</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] bg-white/70 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Ferie</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceReport.summary.vacation }}</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] bg-white/70 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Permessi</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceReport.summary.permissions }}</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] bg-white/70 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Malattia</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceReport.summary.sickness }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="attendanceReport" class="surface overflow-hidden">
                        <div class="border-b border-white/70 px-5 py-4">
                            <h3 class="text-base font-semibold text-gray-900">{{ attendanceReport.month_label }}</h3>
                            <p class="mt-1 text-sm text-gray-500">Anteprima sintetica. L'export contiene tutti i giorni del mese e i riepiloghi finali.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50/80">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Cognome Nome</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Matricola</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Ore ordinarie</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Ferie</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Permessi</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Malattia</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Altre assenze</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white/50">
                                    <tr v-for="row in attendanceReport.rows" :key="row.user_id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-semibold text-gray-900">{{ row.name }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ row.employee_code }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ row.total_labels.ordinary }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ row.total_labels.vacation }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ row.total_labels.permissions }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ row.total_labels.sickness }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ row.total_labels.other }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section v-if="canManage && activeAdminSection === 'documents'" class="surface p-5">
                    <div class="mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Documenti per utente</h3>
                            <p class="mt-1 text-sm text-gray-500">Apri il box di un utente per vedere tutti i suoi documenti e lo stato di lettura.</p>
                        </div>
                    </div>

                    <div v-if="documentUsers.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            v-for="user in documentUsers"
                            :key="user.id"
                            :href="route('documents.users.show', user.id)"
                            class="rounded-[var(--radius)] border border-white bg-white/72 p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]"
                        >
                            <div class="flex items-center gap-3">
                                <UserAvatar :user="user" size="md" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ user.name }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ user.email }}</p>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-1.5 text-center">
                                <div class="rounded-[var(--radius-sm)] bg-gray-50 px-1.5 py-1.5">
                                    <p class="text-sm font-semibold text-gray-900">{{ user.documents_count }}</p>
                                    <p class="whitespace-nowrap text-[9px] font-semibold uppercase tracking-normal text-gray-400">Totali</p>
                                </div>
                                <div class="rounded-[var(--radius-sm)] bg-emerald-50 px-1.5 py-1.5">
                                    <p class="text-sm font-semibold text-emerald-700">{{ user.read_count }}</p>
                                    <p class="whitespace-nowrap text-[9px] font-semibold uppercase tracking-normal text-emerald-500">Letti</p>
                                </div>
                                <div class="rounded-[var(--radius-sm)] bg-amber-50 px-1.5 py-1.5">
                                    <p class="text-sm font-semibold text-amber-700">{{ user.unread_count }}</p>
                                    <p class="whitespace-nowrap text-[9px] font-semibold uppercase tracking-normal text-amber-500">Da leggere</p>
                                </div>
                            </div>
                        </Link>
                    </div>
                    <p v-else class="py-6 text-center text-sm text-gray-500">Nessun utente disponibile.</p>
                </section>

                <section v-if="!canManage || activeAdminSection === 'documents'" class="space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ canManage ? 'Tutti i documenti' : 'I miei documenti' }}</h3>
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

                    <div v-if="visibleDocuments.length" class="document-year-stack">
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
                                        <article
                                            v-for="document in visibleDocumentsForYear(group)"
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
                                                        <p class="line-clamp-2 text-sm font-semibold text-gray-900 transition group-hover:text-[hsl(var(--primary-app))]">{{ document.title }}</p>
                                                        <p class="mt-1 text-xs text-gray-500">{{ audienceLabel(document) }} · {{ fileSize(document.file_size) }}</p>
                                                        <p class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :style="categoryBadgeStyle(document.category)">{{ categoryLabel(document.category) }}</p>
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
                        Nessun documento disponibile.
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

@media (prefers-reduced-motion: reduce) {
    .document-year-button,
    .document-year-expand {
        transition: none;
    }
}
</style>
