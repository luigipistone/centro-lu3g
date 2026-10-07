<script setup>
import AppSelect from '@/Components/AppSelect.vue';
import { router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    initialUserId: { type: String, default: '' },
});
const emit = defineEmits(['close']);
const userId = ref(props.initialUserId);
const files = ref([]);
const busy = ref(false);
const uploaded = ref(0);
const error = ref('');
const alreadyPresent = ref([]);
const months = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
const rows = computed(() => files.value.map((file) => {
    const match = /(?:^|[-_ ])ced\.(0?[1-9]|1[0-2])\.(\d{2})\.pdf$/i.exec(file.name);
    if (!match || file.size > 20 * 1024 * 1024) return { file, valid: false, title: '' };
    return { file, valid: true, title: `Compenso ${months[Number(match[1]) - 1]} ${2000 + Number(match[2])}` };
}));
const validRows = computed(() => rows.value.filter((row) => row.valid));
const skippedRows = computed(() => rows.value.filter((row) => !row.valid));
const hasDuplicates = computed(() => new Set(validRows.value.map((row) => row.title)).size !== validRows.value.length);
const recipientName = computed(() => props.users.find((user) => user.id === userId.value)?.name || '');

function selectFolder(event) {
    files.value = [...(event.target.files || [])];
    uploaded.value = 0;
    error.value = '';
    alreadyPresent.value = [];
}

function close() {
    if (!busy.value) emit('close');
}

async function submit() {
    if (!userId.value || !validRows.value.length || hasDuplicates.value || busy.value) return;
    busy.value = true;
    error.value = '';
    uploaded.value = 0;
    try {
        const check = await window.axios.post(route('documents.compensi.bulk.check'), {
            user_id: userId.value,
            titles: validRows.value.map((row) => row.title),
        });
        alreadyPresent.value = check.data.existing || [];
        const pending = validRows.value.filter((row) => !alreadyPresent.value.includes(row.title));
        if (!pending.length) {
            error.value = 'Tutti i compensi selezionati sono già presenti per questa persona.';
            return;
        }
        for (const row of pending) {
            const data = new FormData();
            data.append('user_id', userId.value);
            data.append('publication_confirmed', '1');
            data.append('notify_recipient', uploaded.value === 0 ? '1' : '0');
            data.append('files[]', row.file, row.file.name);
            await window.axios.post(route('documents.compensi.bulk.store'), data);
            uploaded.value += 1;
        }
        const destination = route('documents.users.show', userId.value);
        busy.value = false;
        emit('close');
        router.visit(destination);
    } catch (failure) {
        error.value = failure.response?.data?.errors?.files?.[0]
            || failure.response?.data?.message
            || 'Caricamento interrotto. I documenti già pubblicati restano disponibili; riprova per completare i restanti.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-[8000] flex items-center justify-center bg-black/15 px-4 py-6 backdrop-blur-sm" @click.self="close">
        <form class="surface max-h-[calc(100dvh-3rem)] w-full max-w-2xl space-y-5 overflow-y-auto bg-white p-5" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-4">
                <div><h3 class="text-base font-semibold text-gray-900">Carica Compensi</h3><p class="mt-1 text-sm text-gray-500">Seleziona la persona e la cartella dei cedolini.</p></div>
                <button type="button" class="icon-btn" aria-label="Chiudi" :disabled="busy" @click="close"><X class="h-4 w-4" /></button>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Destinatario</label>
                <AppSelect v-model="userId" :options="[{ value: '', label: 'Seleziona una persona' }, ...users.map((user) => ({ value: user.id, label: user.name }))]" :disabled="busy || Boolean(initialUserId)" searchable />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Cartella</label>
                <input type="file" accept=".pdf,application/pdf" multiple webkitdirectory directory class="form-control" :disabled="busy" @change="selectFolder" />
                <p class="mt-1 text-xs text-gray-500">Sono riconosciuti i mesi da 1 a 12 nel formato ced.mese.anno.pdf, anche con un nome prima di ced. File diversi o oltre 20 MB non saranno caricati.</p>
            </div>
            <div v-if="files.length" class="space-y-3">
                <div class="flex items-center justify-between text-sm font-semibold text-gray-900"><span>Anteprima pubblicazione</span><span>{{ validRows.length }} documenti</span></div>
                <p class="text-sm text-gray-600">Destinatario: <strong>{{ recipientName || 'Da selezionare' }}</strong> · Categoria: <strong>Compensi</strong></p>
                <div v-if="validRows.length" class="max-h-52 divide-y divide-gray-100 overflow-y-auto rounded-[var(--radius-sm)] border border-gray-100">
                    <div v-for="row in validRows" :key="row.file.name" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"><span class="font-medium text-gray-900">{{ row.title }}</span><span class="text-xs text-gray-500">{{ row.file.name }}</span></div>
                </div>
                <div v-if="skippedRows.length" class="rounded-[var(--radius-sm)] border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">Da verificare, esclusi dal caricamento ({{ skippedRows.length }})</p>
                    <p class="mt-1 break-words">{{ skippedRows.map((row) => row.file.name).join(', ') }}</p>
                </div>
                <p v-if="hasDuplicates" class="text-sm text-red-600">Sono presenti due file per lo stesso mese: lascia una sola copia nella cartella.</p>
                <p v-if="alreadyPresent.length" class="text-sm text-amber-700">Già presenti per questa persona, saltati: {{ alreadyPresent.join(', ') }}.</p>
                <p v-if="busy" class="text-sm font-semibold text-gray-700">Pubblicati {{ uploaded }} di {{ validRows.length - alreadyPresent.length }} documenti...</p>
                <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" class="btn btn-outline" :disabled="busy" @click="close">Annulla</button>
                <button type="submit" class="btn btn-primary" :disabled="!userId || !validRows.length || hasDuplicates || busy">{{ busy ? 'Pubblicazione...' : `Pubblica ${validRows.length} documenti` }}</button>
            </div>
        </form>
    </div>
</template>
