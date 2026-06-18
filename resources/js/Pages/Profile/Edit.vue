<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    absences: {
        type: Array,
        default: () => [],
    },
});

const absenceTypes = [
    { value: 'vacation', label: 'Ferie' },
    { value: 'permission', label: 'Permesso' },
    { value: 'sickness', label: 'Malattia' },
    { value: 'late', label: 'Ritardo' },
    { value: 'other', label: 'Altra assenza' },
];
const absenceStatusLabels = {
    pending: 'In attesa',
    approved: 'Approvata',
    rejected: 'Rifiutata',
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
    notes: '',
});
const smartworkingLabel = computed(() => smartworkingLabels[props.profile?.smartworking_day] || 'Non impostato');
const absenceRows = computed(() => props.absences || []);

function absenceTypeLabel(type) {
    return absenceTypes.find((option) => option.value === type)?.label || 'Assenza';
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`));
}

function submitAbsence() {
    absenceForm.post(route('profile.absences.store'), {
        preserveScroll: true,
        onSuccess: () => {
            absenceForm.reset('notes', 'start_time', 'end_time');
            absenceForm.start_date = today;
            absenceForm.end_date = today;
        },
    });
}

function cancelAbsence(absence) {
    router.delete(route('profile.absences.destroy', absence.id), { preserveScroll: true });
}
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
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="surface p-4 sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <section class="surface p-4 sm:p-8">
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
                            <select v-model="absenceForm.type" class="form-control">
                                <option v-for="option in absenceTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <div v-if="absenceForm.errors.type" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.type }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Dal</label>
                            <input v-model="absenceForm.start_date" type="date" class="form-control" />
                            <div v-if="absenceForm.errors.start_date" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.start_date }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Al</label>
                            <input v-model="absenceForm.end_date" type="date" class="form-control" />
                            <div v-if="absenceForm.errors.end_date" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.end_date }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Ora inizio</label>
                            <input v-model="absenceForm.start_time" type="time" class="form-control" />
                            <div v-if="absenceForm.errors.start_time" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.start_time }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Ora fine</label>
                            <input v-model="absenceForm.end_time" type="time" class="form-control" />
                            <div v-if="absenceForm.errors.end_time" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.end_time }}</div>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium text-gray-700">Note</label>
                            <textarea v-model="absenceForm.notes" rows="3" class="form-control" placeholder="Aggiungi eventuali dettagli..."></textarea>
                            <div v-if="absenceForm.errors.notes" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.notes }}</div>
                        </div>
                        <div class="md:col-span-3 flex justify-end">
                            <button type="submit" class="btn btn-primary" :disabled="absenceForm.processing">Invia richiesta</button>
                        </div>
                    </form>

                    <div class="mt-8">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Le tue richieste</h3>
                        <div v-if="absenceRows.length" class="mt-3 divide-y divide-gray-100 rounded-[var(--radius-sm)] border border-gray-100">
                            <div v-for="absence in absenceRows" :key="absence.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">{{ absenceTypeLabel(absence.type) }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ formatDate(absence.start_date) }}
                                        <span v-if="absence.end_date && absence.end_date !== absence.start_date"> - {{ formatDate(absence.end_date) }}</span>
                                        <span v-if="absence.start_time || absence.end_time"> · {{ absence.start_time || '--:--' }} - {{ absence.end_time || '--:--' }}</span>
                                    </div>
                                    <p v-if="absence.notes" class="mt-1 text-sm text-gray-600">{{ absence.notes }}</p>
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

                <div class="surface p-4 sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="surface p-4 sm:p-8">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
