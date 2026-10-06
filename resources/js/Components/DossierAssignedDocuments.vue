<script setup>
import AppSelect from '@/Components/AppSelect.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    documents: { type: Array, default: () => [] },
    readField: { type: String, default: 'user_read_at' },
});

const categoryLabels = {
    compensi: 'Compensi',
    contratti: 'Contratti',
    corsi_attestati: 'Corsi e attestati',
    documenti_identita: "Documenti d'identità",
    documenti_vari: 'Documenti vari',
};
const selectedCategory = ref('all');
const selectedYear = ref('all');
const visibleCount = ref(5);
const sortedDocuments = computed(() => [...props.documents].sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0)));
const documentYear = (document) => Number(document.document_year) || new Date(document.created_at).getFullYear();
const categoryOptions = computed(() => [
    { value: 'all', label: 'Tutte le categorie' },
    ...[...new Set(sortedDocuments.value.map((document) => document.category || 'documenti_vari'))]
        .map((category) => ({ value: category, label: categoryLabels[category] || category }))
        .sort((a, b) => a.label.localeCompare(b.label, 'it')),
]);
const yearOptions = computed(() => [
    { value: 'all', label: 'Tutti gli anni' },
    ...[...new Set(sortedDocuments.value.map(documentYear))]
        .filter(Number.isFinite)
        .sort((a, b) => b - a)
        .map((year) => ({ value: String(year), label: String(year) })),
]);
const isFiltered = computed(() => selectedCategory.value !== 'all' || selectedYear.value !== 'all');
const filteredDocuments = computed(() => sortedDocuments.value.filter((document) =>
    (selectedCategory.value === 'all' || (document.category || 'documenti_vari') === selectedCategory.value)
    && (selectedYear.value === 'all' || String(documentYear(document)) === selectedYear.value)
));
const visibleDocuments = computed(() => isFiltered.value ? filteredDocuments.value : filteredDocuments.value.slice(0, visibleCount.value));
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Documenti aziendali assegnati</h3>
        <div v-if="documents.length" class="grid w-full gap-3 sm:w-auto sm:grid-cols-2">
            <div class="sm:min-w-44"><AppSelect v-model="selectedCategory" :options="categoryOptions" aria-label="Filtra per categoria" /></div>
            <div class="sm:min-w-36"><AppSelect v-model="selectedYear" :options="yearOptions" aria-label="Filtra per anno" /></div>
        </div>
    </div>
    <div v-if="visibleDocuments.length" class="mt-4 divide-y divide-gray-100 rounded-[var(--radius-sm)] border border-gray-100">
        <Link v-for="document in visibleDocuments" :key="document.id" :href="route('documents.show', document.id)" class="flex items-center justify-between gap-4 px-4 py-3 transition hover:bg-gray-50">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-900">{{ document.title }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ categoryLabels[document.category] || 'Documenti vari' }} · {{ documentYear(document) }}</p>
            </div>
            <span :class="['shrink-0 rounded-full px-3 py-1 text-xs font-semibold', document[readField] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">{{ document[readField] ? 'Letto' : 'Da leggere' }}</span>
        </Link>
    </div>
    <p v-else class="mt-4 text-sm text-gray-500">{{ isFiltered ? 'Nessun documento con i filtri selezionati.' : 'Nessun documento aziendale assegnato.' }}</p>
    <button v-if="!isFiltered && filteredDocuments.length > visibleCount" type="button" class="mt-4 text-sm font-semibold text-[hsl(var(--primary-app))] hover:underline" @click="visibleCount += 5">Carica altri</button>
</template>
