<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import DocumentPdfDropzone from '@/Components/DocumentPdfDropzone.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, FileText, Plus, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    source: Object,
    users: Array,
    groups: Array,
    documentCategories: Object,
    isSuperadmin: Boolean,
});

const review = ref(false);
const form = useForm({
    title: props.source.title || '',
    description: props.source.description || '',
    category: props.source.category || 'documenti_vari',
    document_year: Number(props.source.document_year) || new Date().getFullYear(),
    audience: props.source.audience || '',
    user_ids: [...(props.source.user_ids || [])],
    group_ids: [...(props.source.group_ids || [])],
    file: null,
    publication_confirmed: false,
});

const sensitiveCategories = ['compensi', 'contratti', 'documenti_identita'];
const categoryOptions = computed(() => Object.entries(props.documentCategories || {})
    .filter(([value]) => props.isSuperadmin || !sensitiveCategories.includes(value))
    .map(([value, label]) => ({ value, label })));
const audienceOptions = computed(() => sensitiveCategories.includes(form.category)
    ? [{ value: 'users', label: 'Utenti specifici' }]
    : [{ value: 'all', label: 'Tutti' }, { value: 'users', label: 'Utenti specifici' }, { value: 'groups', label: 'Gruppi' }]);
const yearOptions = computed(() => {
    const latest = Math.min(2100, Math.max(new Date().getFullYear() + 1, Number(props.source.document_year) || 2000));
    return Array.from({ length: latest - 1999 }, (_, index) => ({ value: latest - index, label: String(latest - index) }));
});
const recipientNames = computed(() => form.audience === 'all' ? ['Tutti gli utenti']
    : form.audience === 'users' ? (props.users || []).filter((user) => form.user_ids.includes(user.id)).map((user) => user.name)
        : (props.groups || []).filter((group) => form.group_ids.includes(group.id)).map((group) => `${group.name} (${group.members_count} persone)`));

watch(() => form.category, (category) => {
    if (sensitiveCategories.includes(category) && form.audience !== 'users') {
        form.audience = 'users';
    }
    review.value = false;
});
watch(() => [form.title, form.description, form.document_year, form.audience, form.file, ...form.user_ids, ...form.group_ids], () => {
    review.value = false;
});

function toggleId(field, id) {
    form[field] = form[field].includes(id) ? form[field].filter((value) => value !== id) : [...form[field], id];
}

function submit() {
    if (!review.value) {
        form.clearErrors();
        if (!form.title.trim() || !form.file || !form.audience ||
            (form.audience === 'users' && (!form.user_ids.length || (sensitiveCategories.includes(form.category) && form.user_ids.length !== 1))) ||
            (form.audience === 'groups' && !form.group_ids.length)) {
            if (!form.title.trim()) form.setError('title', 'Inserisci un titolo.');
            if (!form.file) form.setError('file', 'Seleziona un nuovo PDF.');
            if (!form.audience) form.setError('audience', 'Seleziona i destinatari.');
            if (form.audience === 'users' && !form.user_ids.length) form.setError('user_ids', 'Seleziona almeno una persona.');
            if (sensitiveCategories.includes(form.category) && form.user_ids.length !== 1) form.setError('user_ids', 'Per un documento riservato seleziona una sola persona.');
            if (form.audience === 'groups' && !form.group_ids.length) form.setError('group_ids', 'Seleziona almeno un gruppo.');
            return;
        }
        review.value = true;
        return;
    }
    form.publication_confirmed = true;
    form.post(route('documents.store'));
}
</script>

<template>
    <Head title="Clona documento" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('documents.show', source.id)" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" /> Documento originale
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Clona documento</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-8">
                <form class="surface space-y-5 p-5 sm:p-6" @submit.prevent="submit">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                            <FileText class="h-5 w-5" :stroke-width="1.7" />
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Nuovo documento</h3>
                            <p class="mt-1 text-sm text-gray-500">Dati copiati da {{ source.title }}. Seleziona un nuovo PDF prima di pubblicare.</p>
                        </div>
                    </div>

                    <template v-if="!review">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="clone-title" class="block text-sm font-medium text-gray-700">Titolo</label>
                                <input id="clone-title" v-model="form.title" class="form-control" maxlength="255" required />
                                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Categoria</label>
                                <AppSelect v-model="form.category" :options="categoryOptions" />
                                <p v-if="form.errors.category" class="mt-1 text-sm text-red-600">{{ form.errors.category }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Anno</label>
                                <AppSelect v-model="form.document_year" :options="yearOptions" searchable />
                                <p v-if="form.errors.document_year" class="mt-1 text-sm text-red-600">{{ form.errors.document_year }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Destinatari</label>
                                <AppSelect v-model="form.audience" :options="audienceOptions" placeholder="Seleziona destinatari" />
                                <p v-if="form.errors.audience" class="mt-1 text-sm text-red-600">{{ form.errors.audience }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label for="clone-description" class="block text-sm font-medium text-gray-700">Descrizione</label>
                                <textarea id="clone-description" v-model="form.description" class="form-control" rows="3" maxlength="5000"></textarea>
                                <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <span class="block text-sm font-medium text-gray-700">PDF</span>
                                <DocumentPdfDropzone v-model="form.file" :error="form.errors.file" @update:model-value="form.clearErrors('file')" />
                            </div>
                        </div>

                        <div v-if="form.audience === 'users'" class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                            <p class="mb-3 text-sm font-semibold text-gray-700">Seleziona utenti</p>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="user in users" :key="user.id" type="button" :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition', form.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleId('user_ids', user.id)">
                                    <UserAvatar :user="user" size="xs" /> {{ user.name }}
                                </button>
                            </div>
                            <p v-if="form.errors.user_ids" class="mt-2 text-sm text-red-600">{{ form.errors.user_ids }}</p>
                        </div>
                        <div v-if="form.audience === 'groups'" class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                            <p class="mb-3 text-sm font-semibold text-gray-700">Seleziona gruppi</p>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="group in groups" :key="group.id" type="button" :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition', form.group_ids.includes(group.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleId('group_ids', group.id)">
                                    <Users class="h-4 w-4" :stroke-width="1.7" /> {{ group.name }} <span class="text-xs text-gray-400">{{ group.members_count }}</span>
                                </button>
                            </div>
                            <p v-if="form.errors.group_ids" class="mt-2 text-sm text-red-600">{{ form.errors.group_ids }}</p>
                        </div>
                    </template>
                    <div v-else class="rounded-[var(--radius-sm)] border border-blue-100 bg-blue-50/60 p-4">
                        <p class="text-sm font-semibold text-gray-900">Conferma pubblicazione</p>
                        <p class="mt-1 text-sm text-gray-600">{{ form.title }} · {{ documentCategories[form.category] }} · {{ form.file?.name }}</p>
                        <p class="mt-2 text-xs font-semibold uppercase text-gray-500">Destinatari</p>
                        <p class="mt-1 text-sm text-gray-800">{{ recipientNames.join(', ') }}</p>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button v-if="review" type="button" class="btn btn-outline" @click="review = false">Indietro</button>
                        <Link v-else :href="route('documents.show', source.id)" class="btn btn-outline">Annulla</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing"><Plus class="h-4 w-4" :stroke-width="1.7" /> {{ review ? 'Conferma e pubblica' : 'Continua' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
