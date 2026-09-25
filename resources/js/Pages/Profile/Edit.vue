<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link as InertiaLink, router, useForm } from '@inertiajs/vue3';
import { Bold, FileText, Heading3, Italic, Link2, List, ListOrdered, Paperclip, Quote, Underline } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    profile: {
        type: Object,
        default: () => ({}),
    },
    notificationPreferences: {
        type: Array,
        default: () => [],
    },
    absences: {
        type: Array,
        default: () => [],
    },
    attendanceBalances: { type: Object, default: () => ({}) },
    attendanceYear: Number,
    attendanceCauses: { type: Array, default: () => [] },
    archiveRequest: Object,
    dossierDocuments: { type: Array, default: () => [] },
    employeeDossier: { type: Object, default: () => ({ items: [], summary: [] }) },
});

const profileTab = ref('personal');
const profileTabs = [
    { value: 'personal', label: 'Dati personali' },
    { value: 'employment', label: 'Rapporto di lavoro' },
    { value: 'schedule', label: 'Orario e smart working' },
    { value: 'notifications', label: 'Notifiche' },
    { value: 'dossier', label: 'Fascicolo digitale' },
    { value: 'absences', label: 'Assenze' },
    { value: 'security', label: 'Sicurezza' },
];
const employmentStatusLabels = { active: 'Attivo', suspended: 'Sospeso', ended: 'Terminato' };
const documentCategoryLabels = { compensation: 'Compensi', contracts: 'Contratti', courses: 'Corsi e attestati', identity: "Documenti d'identità", other: 'Documenti vari' };
const dossierStatusLabels = { missing: 'Mancante', valid: 'Valido', expiring: 'In scadenza', expired: 'Scaduto', replaced: 'Sostituito' };
const dossierStatusClasses = { missing: 'bg-red-50 text-red-700', expired: 'bg-red-50 text-red-700', expiring: 'bg-amber-50 text-amber-700', valid: 'bg-emerald-50 text-emerald-700', replaced: 'bg-gray-100 text-gray-500' };
const activeEmployeeDossierItems = computed(() => props.employeeDossier.items.filter((item) => !item.replaced_at));

const absenceTypes = [
    { value: 'vacation', label: 'Ferie' },
    { value: 'permission', label: 'Permesso' },
    { value: 'sickness', label: 'Malattia' },
    { value: 'late', label: 'Ritardo' },
    { value: 'smart_working', label: 'Smart working' },
    { value: 'travel', label: 'Trasferta' },
    { value: 'recovery', label: 'Recupero' },
    { value: 'other', label: 'Altra assenza' },
];
const absenceStatusLabels = {
    pending: 'In attesa',
    approved: 'Approvata',
    rejected: 'Rifiutata',
    needs_info: 'Integrazione richiesta',
};
const smartworkingLabels = {
    monday: 'Lunedì',
    tuesday: 'Martedì',
    wednesday: 'Mercoledì',
    thursday: 'Giovedì',
    friday: 'Venerdì',
};
const today = new Date().toISOString().slice(0, 10);
const absenceForm = useForm({
    type: 'vacation',
    start_date: today,
    end_date: today,
    start_time: '',
    end_time: '',
    inps_code: '',
    cause_code: '',
    medical_document: null,
    notes: '',
});
const absenceNotesEditor = ref(null);
const absenceMedicalDocumentInput = ref(null);
const smartworkingLabel = computed(() => smartworkingLabels[props.profile?.smartworking_day] || 'Non impostato');
const absenceRows = computed(() => props.absences || []);
const absenceNeedsEndDate = computed(() => ['vacation', 'sickness', 'other', 'smart_working', 'travel', 'recovery'].includes(absenceForm.type));
const absenceNeedsTime = computed(() => ['permission', 'late', 'other'].includes(absenceForm.type));
const hourOptions = computed(() => Array.from({ length: 14 }, (_, index) => {
    const hour = String(index + 7).padStart(2, '0');
    return { value: `${hour}:00`, label: `${hour}:00` };
}));
const balanceLabels = { vacation: 'Ferie', permission: 'Permessi' };
const formatHours = (minutes) => minutes === null || minutes === undefined ? 'Da configurare' : `${Math.floor(minutes / 60)}h${minutes % 60 ? ` ${minutes % 60}m` : ''}`;
const supplementText = ref({});
function submitSupplement(absence) {
    router.patch(route('profile.absences.supplement', absence.id), { notes: supplementText.value[absence.id] || '' }, {
        preserveScroll: true,
        onSuccess: () => { supplementText.value[absence.id] = ''; },
    });
}

function absenceTypeLabel(type) {
    return absenceTypes.find((option) => option.value === type)?.label || 'Assenza';
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`));
}

function submitAbsence() {
    updateAbsenceNotesFromEditor();
    if (!absenceNeedsEndDate.value) {
        absenceForm.end_date = absenceForm.start_date;
    }
    if (!absenceNeedsTime.value) {
        absenceForm.start_time = '';
        absenceForm.end_time = '';
    }
    if (absenceForm.type !== 'sickness') {
        absenceForm.inps_code = '';
        absenceForm.medical_document = null;
    }
    if (absenceForm.type !== 'other') absenceForm.cause_code = '';
    absenceForm.post(route('profile.absences.store'), {
        preserveScroll: true,
        onSuccess: () => {
            absenceForm.reset('notes', 'start_time', 'end_time', 'inps_code', 'medical_document');
            absenceForm.start_date = today;
            absenceForm.end_date = today;
            if (absenceMedicalDocumentInput.value) absenceMedicalDocumentInput.value.value = '';
            refreshAbsenceNotesEditor();
        },
    });
}

function chooseAbsenceMedicalDocument() {
    absenceMedicalDocumentInput.value?.click();
}

function uploadAbsenceMedicalDocument(event) {
    absenceForm.medical_document = event.target.files?.[0] || null;
}

function cancelAbsence(absence) {
    router.delete(route('profile.absences.destroy', absence.id), { preserveScroll: true });
}

function updateAbsenceNotesFromEditor() {
    absenceForm.notes = absenceNotesEditor.value?.innerHTML || '';
}

function refreshAbsenceNotesEditor() {
    nextTick(() => {
        if (absenceNotesEditor.value && absenceNotesEditor.value.innerHTML !== (absenceForm.notes || '')) {
            absenceNotesEditor.value.innerHTML = absenceForm.notes || '';
        }
    });
}

function runAbsenceNotesCommand(command, value = null) {
    absenceNotesEditor.value?.focus();
    document.execCommand(command, false, value);
    updateAbsenceNotesFromEditor();
}

function addAbsenceNotesLink() {
    const url = window.prompt('URL del link');
    if (!url) return;
    runAbsenceNotesCommand('createLink', url);
}

watch(() => absenceForm.type, () => {
    if (!absenceNeedsEndDate.value) {
        absenceForm.end_date = absenceForm.start_date;
    }
    if (!absenceNeedsTime.value) {
        absenceForm.start_time = '';
        absenceForm.end_time = '';
    }
    if (absenceForm.type !== 'sickness') {
        absenceForm.inps_code = '';
        absenceForm.medical_document = null;
        if (absenceMedicalDocumentInput.value) absenceMedicalDocumentInput.value.value = '';
    }
});
</script>

<template>
    <Head title="Profilo" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Profilo
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 sm:px-6 lg:px-8">
                <nav class="surface flex w-full gap-2 overflow-x-auto p-2" aria-label="Sezioni profilo">
                    <button v-for="tab in profileTabs" :key="tab.value" type="button" :class="['settings-tab shrink-0', profileTab === tab.value ? 'settings-tab-active' : '']" @click="profileTab = tab.value">
                        {{ tab.label }}
                    </button>
                </nav>
                <div
                    v-if="profileTab === 'personal' || profileTab === 'notifications'"
                    class="surface p-4 sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        :section="profileTab"
                        class="max-w-3xl"
                    />
                </div>

                <section v-if="profileTab === 'employment'" class="surface p-4 sm:p-8">
                    <h2 class="text-lg font-medium text-gray-900">Rapporto di lavoro</h2>
                    <p class="mt-1 text-sm text-gray-600">Dati organizzativi gestiti dall’amministrazione.</p>
                    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="item in [
                            ['Matricola', profile?.employee_code], ['Qualifica', profile?.job_title], ['Reparto', profile?.department],
                            ['Responsabile', profile?.manager_name], ['Sede', profile?.office], ['Stato del rapporto', employmentStatusLabels[profile?.employment_status] || profile?.employment_status],
                            ['Data di assunzione', formatDate(profile?.hire_date)], ['Data di licenziamento', formatDate(profile?.termination_date)],
                        ]" :key="item[0]" class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase text-gray-400">{{ item[0] }}</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ item[1] || 'Non impostato' }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="profileTab === 'schedule'" class="surface p-4 sm:p-8">
                    <h2 class="text-lg font-medium text-gray-900">Orario e smart working</h2>
                    <p class="mt-1 text-sm text-gray-600">Configurazione definita dall’amministrazione.</p>
                    <div class="mt-6 grid gap-5 lg:grid-cols-2">
                        <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                            <span class="text-xs font-semibold uppercase text-gray-400">Orario settimanale</span>
                            <p class="mt-2 text-xl font-bold text-gray-900">{{ profile?.weekly_hours || 40 }} ore</p>
                            <p class="mt-1 text-sm text-gray-500">{{ profile?.part_time ? `Part-time ${profile?.part_time_percentage || ''}%` : 'Tempo pieno' }}</p>
                        </div>
                        <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                            <span class="text-xs font-semibold uppercase text-gray-400">Giorni di smart working</span>
                            <div class="mt-3 flex flex-wrap gap-2"><span v-for="day in (profile?.smartworking_days || [])" :key="day" class="rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">{{ smartworkingLabels[day] || day }}</span><span v-if="!profile?.smartworking_days?.length" class="text-sm text-gray-500">Non impostati</span></div>
                            <p v-if="profile?.smartworking_rules" class="mt-3 text-sm text-gray-600">{{ profile.smartworking_rules }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="profileTab === 'dossier'" class="surface p-4 sm:p-8">
                    <h2 class="text-lg font-medium text-gray-900">Fascicolo digitale</h2>
                    <p class="mt-1 text-sm text-gray-600">Stato dei documenti personali, delle scadenze e degli attestati.</p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="item in employeeDossier.summary" :key="item.type" class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 px-4 py-3">
                            <p class="text-sm font-semibold text-gray-900">{{ item.label }}</p>
                            <span :class="['mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', dossierStatusClasses[item.status]]">{{ dossierStatusLabels[item.status] }}</span>
                        </div>
                    </div>
                    <div v-if="activeEmployeeDossierItems.length" class="mt-6 divide-y divide-gray-100 border-y border-gray-100">
                        <div v-for="item in activeEmployeeDossierItems" :key="item.id" class="flex items-center gap-4 py-4">
                            <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><p class="truncate text-sm font-semibold text-gray-900">{{ item.title }}</p><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', dossierStatusClasses[item.status]]">{{ dossierStatusLabels[item.status] }}</span></div><p class="mt-1 text-xs text-gray-500">{{ item.type_label }}<template v-if="item.expires_at"> · scade {{ formatDate(item.expires_at) }}</template></p></div>
                            <a v-if="item.file_path" :href="route('users.dossier-items.file', [profile.user_id, item.id])" target="_blank" class="icon-btn h-9 w-9" title="Apri allegato"><FileText class="h-4 w-4" /></a>
                        </div>
                    </div>
                    <p v-else class="mt-6 text-sm text-gray-500">Il fascicolo personale non contiene ancora documenti strutturati.</p>
                    <h3 class="mt-8 border-t border-gray-100 pt-6 text-sm font-semibold text-gray-900">Documenti aziendali assegnati</h3>
                    <div v-if="dossierDocuments.length" class="mt-6 divide-y divide-gray-100 rounded-[var(--radius-sm)] border border-gray-100">
                        <InertiaLink v-for="document in dossierDocuments" :key="document.id" :href="route('documents.show', document.id)" class="flex items-center justify-between gap-4 px-4 py-3 transition hover:bg-gray-50">
                            <div><p class="text-sm font-semibold text-gray-900">{{ document.title }}</p><p class="mt-1 text-xs text-gray-500">{{ documentCategoryLabels[document.category] || 'Documento' }} · {{ formatDate(String(document.created_at).slice(0, 10)) }}</p></div>
                            <span :class="['rounded-full px-3 py-1 text-xs font-semibold', document.read_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">{{ document.read_at ? 'Letto' : 'Da leggere' }}</span>
                        </InertiaLink>
                    </div>
                    <p v-else class="mt-6 text-sm text-gray-500">Nessun documento assegnato.</p>
                </section>

                <section v-if="profileTab === 'absences'" class="surface p-4 sm:p-8">
                    <div class="mb-6 grid gap-3 sm:grid-cols-2">
                        <div v-for="(balance, type) in attendanceBalances" :key="type" class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                            <p class="text-xs font-semibold uppercase text-gray-500">{{ balanceLabels[type] }} {{ attendanceYear }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ formatHours(balance.remaining_minutes) }} <span v-if="balance.remaining_minutes !== null" class="text-sm font-normal text-gray-500">disponibili</span></p>
                            <p class="text-xs text-gray-500">Usate {{ formatHours(balance.used_minutes) }} · Assegnate {{ formatHours(balance.allocated_minutes) }}</p>
                        </div>
                    </div>
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900">Assenze e disponibilità</h2>
                            <p class="mt-1 text-sm text-gray-600">Richiedi ferie, permessi, malattie, ritardi o altre assenze.</p>
                        </div>
                        <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 px-4 py-2 text-sm">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Smart working</span>
                            <span class="font-semibold text-gray-900">{{ smartworkingLabel }}</span>
                        </div>
                    </div>

                    <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submitAbsence">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo richiesta</label>
                            <AppSelect v-model="absenceForm.type" :options="absenceTypes" />
                            <div v-if="absenceForm.errors.type" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.type }}</div>
                        </div>
                        <div v-if="absenceForm.type === 'other'">
                            <label class="block text-sm font-medium text-gray-700">Causale</label>
                            <AppSelect v-model="absenceForm.cause_code" :options="attendanceCauses.map((cause) => ({ value: cause.code, label: cause.name }))" placeholder="Seleziona causale" />
                            <div v-if="absenceForm.errors.cause_code" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.cause_code }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ absenceNeedsEndDate ? 'Dal' : 'Giorno' }}</label>
                            <AppDateInput v-model="absenceForm.start_date" />
                            <div v-if="absenceForm.errors.start_date" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.start_date }}</div>
                        </div>
                        <div v-if="absenceNeedsEndDate">
                            <label class="block text-sm font-medium text-gray-700">Al</label>
                            <AppDateInput v-model="absenceForm.end_date" />
                            <div v-if="absenceForm.errors.end_date" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.end_date }}</div>
                        </div>
                        <div v-if="absenceNeedsTime">
                            <label class="block text-sm font-medium text-gray-700">Ora inizio</label>
                            <AppSelect v-model="absenceForm.start_time" :options="hourOptions" placeholder="Seleziona ora" />
                            <div v-if="absenceForm.errors.start_time" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.start_time }}</div>
                        </div>
                        <div v-if="absenceNeedsTime">
                            <label class="block text-sm font-medium text-gray-700">Ora fine</label>
                            <AppSelect v-model="absenceForm.end_time" :options="hourOptions" placeholder="Seleziona ora" />
                            <div v-if="absenceForm.errors.end_time" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.end_time }}</div>
                        </div>
                        <div v-if="absenceForm.type === 'sickness'">
                            <label class="block text-sm font-medium text-gray-700">Codice INPS</label>
                            <input v-model="absenceForm.inps_code" class="form-control" placeholder="Inserisci Codice INPS" required />
                            <div v-if="absenceForm.errors.inps_code" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.inps_code }}</div>
                        </div>
                        <div v-if="absenceForm.type === 'sickness'" class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Documento medico</label>
                            <input ref="absenceMedicalDocumentInput" type="file" accept=".pdf,image/jpeg,image/png,image/webp" class="hidden" @change="uploadAbsenceMedicalDocument" />
                            <button
                                type="button"
                                class="mt-1 flex min-h-[38px] w-full items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-white/70 bg-white/58 px-3 text-left text-sm font-medium text-gray-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.62)] transition hover:border-indigo-100 hover:bg-white"
                                @click="chooseAbsenceMedicalDocument"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <Paperclip class="h-4 w-4 shrink-0 text-indigo-500" :stroke-width="1.7" />
                                    <span class="truncate">{{ absenceForm.medical_document?.name || 'Allega documento del medico' }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-gray-400">PDF/JPG/PNG</span>
                            </button>
                            <div v-if="absenceForm.errors.medical_document" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.medical_document }}</div>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium text-gray-700">Note</label>
                            <div class="mt-1 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                    <button type="button" class="icon-btn h-8 w-8" title="Titolo" @mousedown.prevent @click="runAbsenceNotesCommand('formatBlock', 'h3')">
                                        <Heading3 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8 text-xs font-bold" title="Testo normale" @mousedown.prevent @click="runAbsenceNotesCommand('formatBlock', 'p')">T</button>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runAbsenceNotesCommand('bold')">
                                        <Bold class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runAbsenceNotesCommand('italic')">
                                        <Italic class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runAbsenceNotesCommand('underline')">
                                        <Underline class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runAbsenceNotesCommand('insertUnorderedList')">
                                        <List class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runAbsenceNotesCommand('insertOrderedList')">
                                        <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Citazione" @mousedown.prevent @click="runAbsenceNotesCommand('formatBlock', 'blockquote')">
                                        <Quote class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addAbsenceNotesLink">
                                        <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                                <div
                                    ref="absenceNotesEditor"
                                    class="min-h-[120px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                    contenteditable="true"
                                    data-placeholder="Aggiungi eventuali dettagli..."
                                    @input="updateAbsenceNotesFromEditor"
                                    @blur="updateAbsenceNotesFromEditor"
                                ></div>
                            </div>
                            <div v-if="absenceForm.errors.notes" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.notes }}</div>
                        </div>
                        <div class="md:col-span-3 flex justify-end">
                            <button type="submit" class="btn btn-primary" :disabled="absenceForm.processing">Invia richiesta</button>
                        </div>
                    </form>

                    <div class="mt-8">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Le tue richieste</h3>
                        <div v-if="absenceRows.length" class="mt-3 divide-y divide-gray-100 rounded-[var(--radius-sm)] border border-gray-100">
                            <div v-for="absence in absenceRows" :key="absence.id" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900">{{ absenceTypeLabel(absence.type) }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ formatDate(absence.start_date) }}
                                        <span v-if="absence.end_date && absence.end_date !== absence.start_date"> - {{ formatDate(absence.end_date) }}</span>
                                        <span v-if="absence.start_time || absence.end_time"> · {{ absence.start_time || '--:--' }} - {{ absence.end_time || '--:--' }}</span>
                                    </div>
                                    <div v-if="absence.inps_code" class="mt-1 text-xs font-semibold text-gray-500">Codice INPS: {{ absence.inps_code }}</div>
                                    <a
                                        v-if="absence.medical_document_path"
                                        :href="route('absences.medical-document.download', absence.id)"
                                        class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 transition hover:text-indigo-500"
                                    >
                                        <FileText class="h-3.5 w-3.5" :stroke-width="1.7" />
                                        {{ absence.medical_document_name || 'Documento medico' }}
                                    </a>
                                    <div v-if="absence.notes" class="mt-1 text-sm text-gray-600" v-html="absence.notes"></div>
                                    <p v-if="absence.decision_reason" class="mt-2 text-sm text-red-700">Motivo del rifiuto: {{ absence.decision_reason }}</p>
                                    <div v-if="absence.status === 'needs_info'" class="mt-3 max-w-xl space-y-2">
                                        <p class="text-sm text-amber-700">Integrazione richiesta: {{ absence.integration_request }}</p>
                                        <textarea v-model="supplementText[absence.id]" class="form-control" rows="2" placeholder="Scrivi le informazioni richieste"></textarea>
                                        <button type="button" class="btn btn-primary" :disabled="!supplementText[absence.id]?.trim()" @click="submitSupplement(absence)">Invia integrazione</button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ absenceStatusLabels[absence.status] || absence.status }}</span>
                                    <button v-if="absence.status === 'pending'" type="button" class="danger-link" @click="cancelAbsence(absence)">Annulla</button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm text-gray-500">Non hai ancora inviato richieste.</p>
                    </div>
                </section>

                <div v-if="profileTab === 'security'" class="surface p-4 sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div v-if="profileTab === 'security'" class="surface p-4 sm:p-8">
                    <DeleteUserForm :request="archiveRequest" class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
