<script setup>
import AppDateInput from '@/Components/AppDateInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    report: Object,
    users: Array,
    teams: Array,
    isSuperadmin: Boolean,
});

const reportYear = ref(props.report?.year || new Date().getFullYear());
const reportMonth = ref(props.report?.month || new Date().getMonth() + 1);
const reportUserId = ref(props.report?.selected_user_id || 'all');
const reportTeamId = ref(props.report?.selected_team_id || 'all');
const reportRangeMode = ref('month');
const reportFrom = ref(props.report?.from || '');
const reportTo = ref(props.report?.to || '');
const reportYearOptions = Array.from({ length: 6 }, (_, index) => {
    const year = new Date().getFullYear() - 4 + index;
    return { value: year, label: String(year) };
});
const reportMonthOptions = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
].map((label, index) => ({ value: index + 1, label }));
const reportUserOptions = computed(() => [
    { value: 'all', label: props.isSuperadmin ? 'Tutta l’azienda' : 'Tutto il mio team' },
    ...(props.users || []).map((user) => ({ value: user.id, label: user.name })),
]);
const reportSummaryFields = [
    ['planned', 'Previste'], ['actual', 'Ore lavorate'], ['vacation', 'Ferie'], ['permissions', 'Permessi'],
    ['sickness', 'Malattia'], ['late', 'Ritardi'], ['smart_working', 'Smart working'],
    ['extra', 'Straordinari'], ['time_bank', 'Banca ore'], ['recovery', 'Recuperi'], ['travel', 'Trasferte'],
];
const reportTableFields = [
    ['planned', 'Previste'], ['actual', 'Ore lavorate'], ['ordinary', 'Ore ordinarie'],
    ['vacation', 'Ferie'], ['permissions', 'Permessi'], ['sickness', 'Malattia'], ['late', 'Ritardi'],
    ['smart_working', 'Smart working'], ['extra', 'Straordinari'], ['time_bank', 'Banca ore'],
    ['recovery', 'Recuperi'], ['travel', 'Trasferte'], ['other', 'Altre assenze'],
];

function reportParams() {
    const params = { year: reportYear.value, month: reportMonth.value };
    if (reportUserId.value !== 'all') params.user_id = reportUserId.value;
    if (reportTeamId.value !== 'all') params.team_id = reportTeamId.value;
    if (reportRangeMode.value === 'range' && reportFrom.value && reportTo.value) {
        params.from = reportFrom.value;
        params.to = reportTo.value;
    }
    return params;
}

function loadReport() {
    router.get(route('absences.index'), { tab: 'reports', ...reportParams() }, {
        preserveScroll: true,
        preserveState: true,
        only: ['attendanceReport'],
    });
}

function reportExportHref(format) {
    return route('absences.reports.export', { ...reportParams(), format });
}

onMounted(() => { if (!props.report) loadReport(); });
</script>

<template>
    <section class="space-y-6">
        <div class="surface p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Report e dati</h3>
                    <p class="mt-1 text-sm text-gray-500">Presenze previste, effettive e causali per il consulente del lavoro.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a v-for="format in ['xlsx', 'csv', 'pdf']" :key="format" :href="reportExportHref(format)" class="btn btn-outline">
                        <Download class="h-4 w-4" :stroke-width="1.7" />{{ format.toUpperCase() }}
                    </a>
                </div>
            </div>
            <div class="mt-5 flex gap-2">
                <button type="button" :class="['settings-tab', reportRangeMode === 'month' ? 'settings-tab-active' : '']" @click="reportRangeMode = 'month'; loadReport()">Mese</button>
                <button type="button" :class="['settings-tab', reportRangeMode === 'range' ? 'settings-tab-active' : '']" @click="reportRangeMode = 'range'; loadReport()">Intervallo</button>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div v-if="reportRangeMode === 'month'"><label class="block text-sm font-medium text-gray-700">Mese</label><AppSelect v-model="reportMonth" :options="reportMonthOptions" @update:model-value="loadReport" /></div>
                <div v-if="reportRangeMode === 'month'"><label class="block text-sm font-medium text-gray-700">Anno</label><AppSelect v-model="reportYear" :options="reportYearOptions" @update:model-value="loadReport" /></div>
                <div v-if="reportRangeMode === 'range'"><label class="block text-sm font-medium text-gray-700">Dal</label><AppDateInput v-model="reportFrom" @change="loadReport" /></div>
                <div v-if="reportRangeMode === 'range'"><label class="block text-sm font-medium text-gray-700">Al</label><AppDateInput v-model="reportTo" @change="loadReport" /></div>
                <div><label class="block text-sm font-medium text-gray-700">Persona</label><AppSelect v-model="reportUserId" :options="reportUserOptions" @update:model-value="loadReport" /></div>
                <div v-if="isSuperadmin"><label class="block text-sm font-medium text-gray-700">Team</label><AppSelect v-model="reportTeamId" :options="[{ value: 'all', label: 'Tutti i team' }, ...(teams || []).map((team) => ({ value: team.id, label: team.name }))]" @update:model-value="loadReport" /></div>
            </div>
            <p v-if="report?.scope_label" class="mt-4 text-sm font-medium text-gray-500">Report: <span class="text-gray-900">{{ report.scope_label }}</span></p>
            <div v-if="report" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[var(--radius-sm)] bg-white/70 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Persone</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ report.summary.users }}</p>
                </div>
                <div v-for="[key, label] in reportSummaryFields" :key="key" class="rounded-[var(--radius-sm)] bg-white/70 px-4 py-3">
                    <p class="text-xs font-semibold uppercase text-gray-400">{{ label }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ report.summary[key] }}</p>
                </div>
            </div>
        </div>

        <div v-if="report" class="surface overflow-hidden">
            <div class="border-b border-white/70 px-5 py-4">
                <h3 class="text-base font-semibold text-gray-900">{{ report.month_label }}</h3>
                <p class="mt-1 text-sm text-gray-500">Generato il {{ report.generated_at }}. XLSX e CSV includono il dettaglio giornaliero.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50/80"><tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Cognome Nome</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Matricola</th>
                        <th v-for="[key, label] in reportTableFields" :key="key" class="px-4 py-3 text-left font-semibold text-gray-600">{{ label }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 bg-white/50">
                        <tr v-for="row in report.rows" :key="row.user_id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ row.name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ row.employee_code }}</td>
                            <td v-for="[key] in reportTableFields" :key="key" class="px-4 py-3 text-gray-700">{{ row.total_labels[key] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
