<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateIt, dateTimeIt } from '@/utils/formatters';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronLeft, FileText } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    canManage: Boolean,
    document: Object,
    readers: Array,
    documentCategories: Object,
});

const page = usePage();
const selectedCategory = ref(props.document.category || 'documenti_vari');
const savingCategory = ref(false);

const documentCategoryOptions = computed(() => Object.entries(props.documentCategories || {}).map(([value, label]) => ({ value, label })));

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

function markRead() {
    router.post(route('documents.read', props.document.id), {}, {
        preserveScroll: true,
    });
}

function updateCategory(value) {
    selectedCategory.value = value;
    savingCategory.value = true;

    router.patch(route('documents.category.update', props.document.id), { category: value }, {
        preserveScroll: true,
        onFinish: () => {
            savingCategory.value = false;
        },
    });
}
</script>

<template>
    <Head :title="document.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('documents.list')" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                    Documenti
                </Link>
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ document.title }}</h2>
                    <p class="text-sm text-gray-500">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :style="categoryBadgeStyle(document.category)">
                            {{ categoryLabel(document.category) }}
                        </span>
                        <span class="ml-1">· Anno {{ document.document_year }} · PDF pubblicato il {{ dateIt(document.created_at) }}</span>
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="rounded-[var(--radius-sm)] border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div class="surface overflow-hidden">
                        <div class="flex items-center justify-between gap-4 border-b border-white/70 px-5 py-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                    <FileText class="h-5 w-5" :stroke-width="1.7" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ document.file_name }}</p>
                                    <p class="text-xs text-gray-500">Anteprima documento</p>
                                </div>
                            </div>
                            <a :href="route('documents.file', document.id)" target="_blank" class="btn btn-outline">
                                Apri PDF
                            </a>
                        </div>

                        <iframe
                            :src="route('documents.file', document.id)"
                            class="h-[72vh] min-h-[560px] w-full bg-white"
                            title="Anteprima PDF"
                        ></iframe>
                    </div>

                    <aside class="space-y-6">
                        <section v-if="canManage" class="surface p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">Categoria</h3>
                                    <p class="mt-1 text-sm text-gray-500">Modifica la categoria del documento.</p>
                                </div>
                                <span v-if="savingCategory" class="text-xs font-semibold text-gray-400">Salvataggio...</span>
                            </div>
                            <div class="mt-4">
                                <AppSelect :model-value="selectedCategory" :options="documentCategoryOptions" @update:model-value="updateCategory" />
                            </div>
                        </section>

                        <section v-if="document.user_is_recipient || document.description" class="surface p-5">
                            <h3 class="text-base font-semibold text-gray-900">{{ document.user_is_recipient ? 'Lettura' : 'Descrizione' }}</h3>
                            <div v-if="document.description" class="mt-2 text-sm leading-6 text-gray-500" v-html="document.description"></div>
                            <div v-if="document.user_is_recipient" class="mt-4 rounded-[var(--radius)] bg-gray-50/80 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Stato personale</p>
                                <p :class="['mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold', document.user_read_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                                    <Check v-if="document.user_read_at" class="h-4 w-4" :stroke-width="1.8" />
                                    {{ document.user_read_at ? `Letto il ${dateTimeIt(document.user_read_at)}` : 'Da confermare come letto' }}
                                </p>
                            </div>
                            <button
                                v-if="document.user_is_recipient && !document.user_read_at"
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
                                <p class="mt-1 text-sm text-gray-500">{{ document.read_count }} letti su {{ document.recipient_count }} destinatari</p>
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
