<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateIt } from '@/utils/formatters';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Bold, Check, FileText, Heading3, Italic, Link2, List, ListOrdered, MessageSquare, Plus, Quote, Send, Trash2, Underline, Upload, Users } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    canManage: Boolean,
    documents: Array,
    messages: Array,
    groups: Array,
    users: Array,
    documentUsers: Array,
    documentCategories: Object,
});

const page = usePage();
const confirmDelete = ref(null);
const confirmDeleteText = ref('');
const currentYearVisibleCount = ref(5);
const documentDescriptionEditor = ref(null);
const messageBodyEditor = ref(null);
const categoryFilters = ref({});
const activeAdminSection = ref('documents');
const isSuperadmin = computed(() => page.props.auth?.user?.role === 'superadmin');

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
const categoryOptions = computed(() => [
    { value: 'all', label: 'Tutte le categorie' },
    ...Object.entries(props.documentCategories || {}).map(([value, label]) => ({ value, label })),
]);
const documentCategoryOptions = computed(() => categoryOptions.value.filter((option) => option.value !== 'all'));
const currentYearDocuments = computed(() => filterDocumentsByCategory(
    visibleDocuments.value.filter((document) => documentYear(document) === currentYear),
    currentYear,
));
const visibleCurrentYearDocuments = computed(() => currentYearDocuments.value.slice(0, currentYearVisibleCount.value));
const previousYearGroups = computed(() => {
    const groups = visibleDocuments.value
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

function resetDocumentForm() {
    documentForm.reset();
    documentForm.clearErrors();
    nextTick(() => {
        if (documentDescriptionEditor.value) {
            documentDescriptionEditor.value.innerHTML = '';
        }
    });
}

function submitDocument() {
    updateDocumentDescriptionFromEditor();
    documentForm.post(route('documents.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: resetDocumentForm,
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
        onSuccess: () => groupForm.reset(),
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
        onSuccess: resetMessageForm,
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

function showMoreCurrentYearDocuments() {
    currentYearVisibleCount.value += 5;
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
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Documenti</h2>
                <p class="text-sm text-gray-500">PDF aziendali, assegnazioni e conferme di lettura.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
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

                <section v-if="canManage" class="surface p-2">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            :class="['rounded-[var(--radius-sm)] px-4 py-3 text-left transition', activeAdminSection === 'documents' ? 'bg-[hsl(var(--primary-app))] text-white shadow-[0_12px_28px_rgba(28,42,73,0.16)]' : 'bg-white/70 text-gray-600 hover:bg-[hsl(var(--primary-app)/0.08)] hover:text-[hsl(var(--primary-app-dark))]']"
                            @click="activeAdminSection = 'documents'"
                        >
                            <span class="block text-sm font-semibold">Documenti</span>
                            <span :class="['mt-0.5 block text-xs', activeAdminSection === 'documents' ? 'text-white/75' : 'text-gray-400']">
                                {{ visibleDocuments.length }} documenti e letture utenti
                            </span>
                        </button>
                        <button
                            type="button"
                            :class="['rounded-[var(--radius-sm)] px-4 py-3 text-left transition', activeAdminSection === 'messages' ? 'bg-[hsl(var(--primary-app))] text-white shadow-[0_12px_28px_rgba(28,42,73,0.16)]' : 'bg-white/70 text-gray-600 hover:bg-[hsl(var(--primary-app)/0.08)] hover:text-[hsl(var(--primary-app-dark))]']"
                            @click="activeAdminSection = 'messages'"
                        >
                            <span class="block text-sm font-semibold">Messaggi</span>
                            <span :class="['mt-0.5 block text-xs', activeAdminSection === 'messages' ? 'text-white/75' : 'text-gray-400']">
                                {{ visibleMessages.length }} messaggi e conferme lettura
                            </span>
                        </button>
                    </div>
                </section>

                <section v-if="canManage && activeAdminSection === 'documents'" class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
                    <form class="surface space-y-5 p-5" @submit.prevent="submitDocument">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Nuovo documento</h3>
                                <p class="mt-1 text-sm text-gray-500">Carica un PDF e scegli chi deve leggerlo.</p>
                            </div>
                            <FileText class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
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

                    <form class="surface space-y-4 p-5" @submit.prevent="submitGroup">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Gruppi documenti</h3>
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

                        <div class="space-y-2 border-t border-gray-100 pt-4">
                            <article v-for="group in groups" :key="group.id" class="flex items-center justify-between gap-3 rounded-[var(--radius-sm)] bg-white/70 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ group.name }}</p>
                                    <p class="text-xs text-gray-500">{{ group.members_count }} utenti</p>
                                </div>
                                <button type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina gruppo" @click="removeGroup(group)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </article>
                        </div>
                    </form>
                </section>

                <section v-if="canManage && activeAdminSection === 'messages'" class="surface p-5">
                    <form class="space-y-5" @submit.prevent="submitMessage">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Nuovo messaggio</h3>
                                <p class="mt-1 text-sm text-gray-500">Invia una comunicazione a tutti, a un gruppo o a persone specifiche.</p>
                            </div>
                            <MessageSquare class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
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
                </section>

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
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ canManage ? 'Tutti i documenti' : 'I miei documenti' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">Documenti {{ currentYear }} in evidenza e archivio diviso per anno.</p>
                        </div>
                    </div>

                    <div v-if="visibleDocuments.length" class="space-y-6">
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
                                <article
                                    v-for="document in visibleCurrentYearDocuments"
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
                                                <p class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :style="categoryBadgeStyle(document.category)">
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
                        Nessun documento disponibile.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
