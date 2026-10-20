<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ToolDropzone from '@/Components/ToolDropzone.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileUp, LoaderCircle, X } from '@lucide/vue';
import axios from 'axios';
import { ref } from 'vue';

const input = ref(null);
const form = useForm({ file: null });
const preview = ref(null);
const previewing = ref(false);
const error = ref('');

async function selectFile(files) {
    const file = files?.[0];
    if (!file) return;
    form.file = null;
    preview.value = null;
    error.value = '';
    if (!/\.(csv|txt)$/i.test(file.name) || file.size > 10 * 1024 * 1024) {
        error.value = 'Seleziona un CSV fino a 10 MB.';
        return;
    }
    form.file = file;
    previewing.value = true;
    try {
        const data = new FormData();
        data.append('file', file);
        const response = await axios.post(route('clients.import.preview'), data);
        if (form.file === file) preview.value = response.data;
    } catch (failure) {
        error.value = failure.response?.data?.errors?.file?.[0] || 'Impossibile verificare il CSV.';
    } finally {
        previewing.value = false;
    }
}

function onInput(event) {
    selectFile(event.target.files);
    event.target.value = '';
}

function clearFile() {
    form.file = null;
    preview.value = null;
    error.value = '';
}

function downloadTemplate() {
    const headers = ['Nome', 'Ragione sociale', 'Email', 'Telefono', 'Partita IVA', 'Codice fiscale', 'Natura giuridica', 'Settore', 'Sorgente', 'Paese', 'Via', 'Numero', 'CAP', 'Citta', 'Provincia', 'PEC', 'Codice SDI', 'Sito web', 'IBAN', 'BIC/SWIFT', 'Trattamento IVA', 'Termini pagamento', 'Pubblica amministrazione', 'Note'];
    const url = URL.createObjectURL(new Blob(['\uFEFF' + headers.join(';') + '\r\n'], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'modello-importazione-clienti.csv';
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function submit() {
    if (!form.file || !preview.value?.count || previewing.value) return;
    form.post(route('clients.import.store'));
}
</script>

<template>
    <Head title="Importa clienti" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('clients.index')" class="inline-flex w-fit items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" />Torna ai clienti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Importa clienti</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="surface p-4 sm:p-6">
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-5">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Importa clienti da CSV</h3>
                            <p class="mt-1 text-sm text-gray-500">Usa il modello per compilare i dati dei clienti.</p>
                        </div>
                        <button type="button" class="btn btn-outline" @click="downloadTemplate"><Download class="h-4 w-4" />Scarica modello CSV</button>
                    </div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">File CSV</label>
                    <input ref="input" class="sr-only" type="file" accept=".csv,.txt,text/csv" @change="onInput" />
                    <ToolDropzone v-if="!form.file" label="Trascina qui il CSV oppure selezionalo" hint="CSV fino a 10 MB · massimo 2.000 righe" aria-label="Seleziona file CSV" class="min-h-[150px]" @select="input?.click()" @drop="selectFile($event.dataTransfer.files)" />
                    <div v-else class="flex min-w-0 items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 px-4 py-3">
                        <span class="min-w-0 truncate text-sm font-medium text-gray-800">{{ form.file.name }}</span>
                        <button type="button" class="icon-btn shrink-0" title="Rimuovi file" aria-label="Rimuovi file" :disabled="form.processing" @click="clearFile"><X class="h-4 w-4" /></button>
                    </div>
                    <p v-if="previewing" class="mt-4 flex items-center gap-2 text-sm text-gray-600"><LoaderCircle class="h-4 w-4 animate-spin" />Verifica del file...</p>
                    <p v-if="error || form.errors.file" role="alert" class="mt-4 text-sm text-red-600">{{ error || form.errors.file }}</p>
                    <div v-if="preview" class="mt-6 border-t border-gray-100 pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-base font-semibold text-gray-900">Anteprima importazione</h3>
                            <span class="text-sm font-semibold text-gray-700">{{ preview.count }} nuovi · {{ preview.issues.length }} saltati</span>
                        </div>
                        <div v-if="preview.records.length" class="mt-4 overflow-x-auto rounded-[var(--radius-sm)] border border-gray-200">
                            <table class="w-full min-w-[600px] text-left text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold text-gray-600"><tr><th class="px-3 py-2">Riga</th><th class="px-3 py-2">Nome</th><th class="px-3 py-2">Ragione sociale</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">P. IVA</th></tr></thead>
                                <tbody class="divide-y divide-gray-100"><tr v-for="row in preview.records" :key="row.line"><td class="px-3 py-2 text-gray-500">{{ row.line }}</td><td class="px-3 py-2 font-medium text-gray-900">{{ row.name }}</td><td class="px-3 py-2">{{ row.legal_name || '—' }}</td><td class="px-3 py-2">{{ row.email || '—' }}</td><td class="px-3 py-2">{{ row.vat_number || '—' }}</td></tr></tbody>
                            </table>
                        </div>
                        <p v-if="preview.count > preview.records.length" class="mt-2 text-xs text-gray-500">Mostrate le prime {{ preview.records.length }} righe valide.</p>
                        <details v-if="preview.issues.length" class="mt-4 border-t border-gray-100 pt-4 text-sm"><summary class="cursor-pointer font-medium text-amber-800">Righe saltate ({{ preview.issues.length }})</summary><div class="mt-2 max-h-48 space-y-1 overflow-auto text-gray-600"><p v-for="issue in preview.issues" :key="issue.line">Riga {{ issue.line }}: {{ issue.reason }}</p></div></details>
                        <div class="mt-5 flex justify-end border-t border-gray-100 pt-5"><button type="button" class="btn btn-primary" :disabled="!preview.count || form.processing || previewing" @click="submit"><LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" /><FileUp v-else class="h-4 w-4" />{{ form.processing ? 'Importazione...' : `Importa ${preview.count} clienti` }}</button></div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
