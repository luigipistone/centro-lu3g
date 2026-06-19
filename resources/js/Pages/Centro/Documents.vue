<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateIt } from '@/utils/formatters';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, FileText, Plus, Trash2, Upload, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    canManage: Boolean,
    documents: Array,
    groups: Array,
    users: Array,
    selectedUserId: String,
    selectedUserDocuments: Array,
});

const page = usePage();
const selectedUser = ref(props.selectedUserId || '');

const documentForm = useForm({
    title: '',
    description: '',
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

const audienceOptions = [
    { value: 'all', label: 'Tutti' },
    { value: 'users', label: 'Utenti specifici' },
    { value: 'groups', label: 'Gruppi' },
];

const selectedUserObject = computed(() => props.users.find((user) => user.id === selectedUser.value));
const visibleDocuments = computed(() => props.documents || []);
const userDocuments = computed(() => props.selectedUserDocuments || []);

watch(selectedUser, (value) => {
    if (!props.canManage) return;
    router.get(route('documents.index'), value ? { user: value } : {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
});

function resetDocumentForm() {
    documentForm.reset();
    documentForm.clearErrors();
}

function submitDocument() {
    documentForm.post(route('documents.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: resetDocumentForm,
    });
}

function submitGroup() {
    groupForm.post(route('document-groups.store'), {
        preserveScroll: true,
        onSuccess: () => groupForm.reset(),
    });
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

function toggleGroupUser(userId) {
    groupForm.user_ids = groupForm.user_ids.includes(userId)
        ? groupForm.user_ids.filter((id) => id !== userId)
        : [...groupForm.user_ids, userId];
}

function removeDocument(document) {
    if (!window.confirm(`Eliminare il documento "${document.title}"?`)) return;
    router.delete(route('documents.destroy', document.id), { preserveScroll: true });
}

function removeGroup(group) {
    if (!window.confirm(`Eliminare il gruppo "${group.name}"?`)) return;
    router.delete(route('document-groups.destroy', group.id), { preserveScroll: true });
}

function audienceLabel(document) {
    if (document.audience === 'all') return 'Tutti';
    if (document.audience === 'groups') return 'Gruppi';
    return 'Utenti';
}

function fileSize(bytes) {
    const value = Number(bytes || 0);
    if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
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
                <div v-if="page.props.flash?.status" class="rounded-[var(--radius-sm)] border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <section v-if="canManage" class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
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
                                <label class="block text-sm font-medium text-gray-700">Destinatari</label>
                                <AppSelect v-model="documentForm.audience" :options="audienceOptions" />
                                <div v-if="documentForm.errors.audience" class="mt-1 text-sm text-red-600">{{ documentForm.errors.audience }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                                <textarea v-model="documentForm.description" rows="3" class="form-control" placeholder="Nota interna opzionale"></textarea>
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

                <section v-if="canManage" class="surface p-5">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Documenti per utente</h3>
                            <p class="mt-1 text-sm text-gray-500">Controlla cosa vede ogni persona e lo stato di lettura.</p>
                        </div>
                        <select v-model="selectedUser" class="form-control mt-0 max-w-xs">
                            <option value="">Seleziona utente</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </div>

                    <div v-if="selectedUserObject" class="mb-4 flex items-center gap-3 rounded-[var(--radius)] bg-gray-50/80 p-3">
                        <UserAvatar :user="selectedUserObject" size="md" />
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ selectedUserObject.name }}</p>
                            <p class="text-xs text-gray-500">{{ userDocuments.filter((document) => document.user_read_at).length }} letti su {{ userDocuments.length }}</p>
                        </div>
                    </div>

                    <div v-if="selectedUserObject && userDocuments.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <Link
                            v-for="document in userDocuments"
                            :key="document.id"
                            :href="route('documents.show', document.id)"
                            class="rounded-[var(--radius)] border border-white bg-white/72 p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <FileText class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                                <span :class="['rounded-full px-2 py-1 text-xs font-semibold', document.user_read_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                                    {{ document.user_read_at ? 'Letto' : 'Da leggere' }}
                                </span>
                            </div>
                            <p class="mt-3 line-clamp-2 text-sm font-semibold text-gray-900">{{ document.title }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ dateIt(document.created_at) }}</p>
                        </Link>
                    </div>
                    <p v-else-if="selectedUserObject" class="py-6 text-center text-sm text-gray-500">Nessun documento assegnato a questo utente.</p>
                    <p v-else class="py-6 text-center text-sm text-gray-500">Scegli un utente per vedere i suoi documenti.</p>
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ canManage ? 'Tutti i documenti' : 'I miei documenti' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ canManage ? 'Archivio documenti pubblicati.' : 'Apri i documenti e conferma la lettura.' }}</p>
                        </div>
                    </div>

                    <div v-if="visibleDocuments.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="document in visibleDocuments"
                            :key="document.id"
                            :class="['surface group p-4 transition', !canManage && !document.user_read_at ? 'ring-1 ring-amber-100' : '']"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                        <FileText class="h-5 w-5" :stroke-width="1.7" />
                                    </span>
                                    <div class="min-w-0">
                                        <Link :href="route('documents.show', document.id)" class="line-clamp-2 text-sm font-semibold text-gray-900 transition hover:text-[hsl(var(--primary-app))]">
                                            {{ document.title }}
                                        </Link>
                                        <p class="mt-1 text-xs text-gray-500">{{ audienceLabel(document) }} · {{ fileSize(document.file_size) }}</p>
                                    </div>
                                </div>
                                <button v-if="canManage" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina documento" @click="removeDocument(document)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>

                            <p v-if="document.description" class="mt-3 line-clamp-2 text-sm text-gray-500">{{ document.description }}</p>

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
                    <div v-else class="surface px-5 py-12 text-center text-sm text-gray-500">
                        Nessun documento disponibile.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
