<script setup>
import ToolDropzone from '@/Components/ToolDropzone.vue';
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Link2, LoaderCircle, Mail, Send, Trash2, X } from '@lucide/vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    configured: { type: Boolean, required: true },
    isSuperadmin: { type: Boolean, required: true },
    transfers: { type: Array, default: () => [] },
});

const chunkBytes = 16 * 1024 * 1024;
const maxBytes = 3 * 1024 * 1024 * 1024;
const input = ref(null);
const files = ref([]);
const mode = ref('send');
const recipient = ref('');
const message = ref('');
const expiresDays = ref(3);
const sending = ref(false);
const progress = ref(0);
const uploadStage = ref('');
const uploadFileNumber = ref(0);
const error = ref('');
const result = ref('');
const createdLink = ref('');
const copiedTransferId = ref(null);
const deletingId = ref(null);
const deleteTarget = ref(null);
const deleteError = ref('');
const resumeId = ref(null);
const abortController = ref(null);
const transferList = ref(props.transfers);
watch(() => props.transfers, (value) => { transferList.value = value; });
const visibleTransfers = computed(() => transferList.value.filter((transfer) =>
    ['uploading', 'ready'].includes(transfer.status) && new Date(transfer.expires_at).getTime() > Date.now()
));

const totalBytes = computed(() => files.value.reduce((sum, file) => sum + file.size, 0));
const totalLabel = computed(() => formatBytes(totalBytes.value));

function formatBytes(bytes) {
    if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
    if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    return `${(bytes / 1024).toFixed(0)} KB`;
}

function dateLabel(value) {
    return new Intl.DateTimeFormat('it-IT', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}

function selectFiles(selected) {
    error.value = '';
    const added = Array.from(selected || []);
    const next = [...files.value, ...added];
    if (next.length > 20 || next.reduce((sum, file) => sum + file.size, 0) > maxBytes || next.some((file) => !file.size)) {
        error.value = 'Seleziona fino a 20 file, per un massimo complessivo di 3 GB.';
        return;
    }
    files.value = next;
    if (input.value) input.value.value = '';
}

function removeFile(index) {
    files.value.splice(index, 1);
}

function messageFor(errorObject) {
    const errors = errorObject?.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0];
    if ([502, 503, 504].includes(errorObject?.response?.status)) return 'Il server o lo storage non risponde. Il caricamento resta salvato: puoi riprenderlo.';
    return errorObject?.response?.data?.message || 'Operazione non riuscita. Riprova.';
}

function isTemporaryUploadError(exception) {
    return !exception.response || [408, 429, 500, 502, 503, 504].includes(exception.response.status);
}

async function uploadChunkWithRetry(transferId, remoteId, index, file, start, uploaded) {
    for (let attempt = 1; attempt <= 3; attempt++) {
        const form = new FormData();
        form.append('index', String(index));
        form.append('chunk', file.slice(start, Math.min(start + chunkBytes, file.size)), file.name);
        try {
            uploadStage.value = attempt > 1 ? `Connessione instabile, nuovo tentativo ${attempt}/3` : 'Invio al server';
            await axios.post(route('tools.airmail.chunk', [transferId, remoteId]), form, {
                signal: abortController.value.signal,
                timeout: 240000,
                onUploadProgress: (event) => {
                    const current = uploaded + Math.min(event.loaded || 0, file.size - start);
                    progress.value = Math.min(99, Math.round((current / totalBytes.value) * 100));
                    if (event.total && event.loaded >= event.total) uploadStage.value = 'Salvataggio nello storage';
                },
            });
            return;
        } catch (exception) {
            if (exception?.code === 'ERR_CANCELED' || attempt === 3 || !isTemporaryUploadError(exception)) throw exception;
            progress.value = Math.min(99, Math.round((uploaded / totalBytes.value) * 100));
            uploadStage.value = `Connessione instabile, nuovo tentativo ${attempt + 1}/3`;
            await new Promise((resolve) => setTimeout(resolve, attempt * 1500));
            if (abortController.value?.signal.aborted) throw exception;
        }
    }
}

async function send() {
    if (sending.value || !files.value.length) return;
    error.value = '';
    result.value = '';
    createdLink.value = '';
    sending.value = true;
    uploadStage.value = 'Preparazione del caricamento';
    uploadFileNumber.value = 0;
    abortController.value = new AbortController();
    try {
        let transfer;
        let remoteFiles;
        if (resumeId.value) {
            const { data } = await axios.get(route('tools.airmail.show', resumeId.value));
            transfer = data.transfer;
            remoteFiles = data.files;
            if (files.value.length !== remoteFiles.length || files.value.some((file, index) => file.name !== remoteFiles[index].name || file.size !== remoteFiles[index].size)) {
                throw new Error('Per riprendere, seleziona gli stessi file nello stesso ordine.');
            }
        } else {
            const { data } = await axios.post(route('tools.airmail.create'), {
                mode: mode.value,
                recipient_email: mode.value === 'send' ? recipient.value : null,
                message: message.value,
                expires_days: expiresDays.value,
                files: files.value.map((file) => ({ name: file.name, size: file.size })),
            });
            transfer = { id: data.id };
            remoteFiles = data.files;
            resumeId.value = transfer.id;
        }

        let uploaded = remoteFiles.reduce((sum, file, index) => sum + Math.min(file.uploaded_chunks * chunkBytes, files.value[index].size), 0);
        progress.value = Math.round((uploaded / totalBytes.value) * 100);
        for (let fileIndex = 0; fileIndex < files.value.length; fileIndex++) {
            const file = files.value[fileIndex];
            const remote = remoteFiles[fileIndex];
            if (remote.status === 'done') continue;
            uploadFileNumber.value = fileIndex + 1;
            const count = Math.ceil(file.size / chunkBytes);
            for (let index = remote.uploaded_chunks + 1; index <= count; index++) {
                const start = (index - 1) * chunkBytes;
                await uploadChunkWithRetry(transfer.id, remote.id, index, file, start, uploaded);
                uploaded += Math.min(chunkBytes, file.size - start);
                progress.value = Math.min(99, Math.round((uploaded / totalBytes.value) * 100));
            }
        }
        uploadStage.value = 'Creazione del link';
        const { data } = await axios.post(route('tools.airmail.publish', transfer.id));
        progress.value = 100;
        createdLink.value = data.transfer.url || '';
        if (!createdLink.value) {
            error.value = 'Il link è già scaduto. Crea un nuovo trasferimento per condividerlo.';
        } else {
            result.value = mode.value === 'link'
                ? 'Link pronto. Copialo e condividilo.'
                : data.email_sent ? 'Invio completato. Il destinatario ha ricevuto il link.' : 'File pronti. L’email non è stata inviata: copia il link qui sotto.';
        }
        files.value = [];
        resumeId.value = null;
        transferList.value.unshift(data.transfer);
    } catch (exception) {
        error.value = exception?.code === 'ERR_CANCELED'
            ? 'Caricamento interrotto. Puoi riprenderlo dalla lista.'
            : exception?.response
                ? messageFor(exception)
                : 'Connessione interrotta o troppo lenta. Il caricamento resta salvato: puoi riprenderlo.';
        if (resumeId.value) router.reload({ only: ['transfers'] });
    } finally {
        sending.value = false;
        uploadStage.value = '';
        uploadFileNumber.value = 0;
        abortController.value = null;
    }
}

function resume(transfer) {
    resumeId.value = transfer.id;
    mode.value = transfer.mode || 'send';
    recipient.value = transfer.recipient_email || '';
    files.value = [];
    result.value = '';
    createdLink.value = '';
    error.value = 'Seleziona nuovamente tutti i file originali nello stesso ordine, poi premi Riprendi invio.';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelResume() {
    resumeId.value = null;
    files.value = [];
    error.value = '';
}

function changeMode(value) {
    if (sending.value || resumeId.value) return;
    mode.value = value;
    error.value = '';
    result.value = '';
    createdLink.value = '';
}

async function copyLink(url, transferId = null) {
    try {
        await navigator.clipboard.writeText(url);
        if (!transferId) result.value = 'Link copiato.';
        copiedTransferId.value = transferId;
        if (transferId) window.setTimeout(() => {
            if (copiedTransferId.value === transferId) copiedTransferId.value = null;
        }, 2000);
    } catch {
        error.value = 'Impossibile copiare il link.';
    }
}

function askDelete(transfer) {
    if (deletingId.value) return;
    deleteError.value = '';
    deleteTarget.value = transfer;
}

function closeDelete() {
    if (deletingId.value) return;
    deleteTarget.value = null;
    deleteError.value = '';
}

async function removeTransfer() {
    const transfer = deleteTarget.value;
    if (!transfer || deletingId.value) return;
    deletingId.value = transfer.id;
    deleteError.value = '';
    try {
        await axios.delete(route('tools.airmail.destroy', transfer.id));
        transferList.value = transferList.value.filter((item) => item.id !== transfer.id);
        deleteTarget.value = null;
    } catch (exception) {
        deleteError.value = messageFor(exception);
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <Head title="Airmail" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" />Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Airmail</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="!configured" class="app-card">
                    <p class="font-semibold text-gray-900">Airmail non è ancora collegato</p>
                    <p class="mt-1 text-sm text-gray-500">Il superadmin deve configurare l'accesso allo storage.</p>
                    <Link v-if="isSuperadmin" :href="route('settings.index', { tab: 'airmail' })" class="btn btn-primary mt-4">Configura Airmail</Link>
                </div>
                <template v-else>
                    <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                        <section class="app-card flex min-w-0 flex-col gap-5">
                            <h3 class="section-title"><span class="section-icon"><Send class="h-4 w-4" :stroke-width="1.7" /></span>File da condividere</h3>
                            <input ref="input" type="file" multiple class="sr-only" @change="selectFiles($event.target.files)" />
                            <ToolDropzone label="Trascina qui i file oppure selezionali" hint="Fino a 20 file, 3 GB complessivi" aria-label="Seleziona i file da inviare" :disabled="sending" showcase @select="input?.click()" @drop="selectFiles($event.dataTransfer.files)" />
                            <TransitionGroup v-if="files.length" name="airmail-file" tag="div" class="divide-y divide-gray-100 rounded-[var(--radius-sm)] border border-gray-200">
                                <div v-for="(file, index) in files" :key="`${file.name}-${file.size}-${file.lastModified}-${index}`" class="flex min-w-0 items-center gap-3 px-3 py-2.5 text-sm">
                                    <span class="min-w-0 flex-1 truncate text-gray-800" :title="file.name">{{ file.name }}</span>
                                    <span class="shrink-0 text-gray-500">{{ formatBytes(file.size) }}</span>
                                    <button type="button" class="icon-btn" :disabled="sending" :aria-label="`Rimuovi ${file.name}`" :title="`Rimuovi ${file.name}`" @click="removeFile(index)"><X class="h-4 w-4" /></button>
                                </div>
                            </TransitionGroup>
                            <p v-if="files.length" class="text-right text-sm font-medium text-gray-600">Totale: {{ totalLabel }}</p>
                        </section>
                        <section class="app-card flex min-w-0 flex-col gap-4">
                            <h3 class="section-title">Condivisione</h3>
                            <div class="grid grid-cols-2 gap-1 rounded-[var(--radius-sm)] border border-gray-200 bg-gray-100 p-1" role="group" aria-label="Modalità di condivisione">
                                <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-[var(--radius-sm)] px-3 text-sm font-semibold transition" :class="mode === 'send' ? 'bg-white text-[hsl(var(--primary-app))] shadow-sm' : 'text-gray-600 hover:text-gray-900'" :aria-pressed="mode === 'send'" :disabled="sending || Boolean(resumeId)" @click="changeMode('send')"><Mail class="h-4 w-4" />Invia</button>
                                <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-[var(--radius-sm)] px-3 text-sm font-semibold transition" :class="mode === 'link' ? 'bg-white text-[hsl(var(--primary-app))] shadow-sm' : 'text-gray-600 hover:text-gray-900'" :aria-pressed="mode === 'link'" :disabled="sending || Boolean(resumeId)" @click="changeMode('link')"><Link2 class="h-4 w-4" />Crea link</button>
                            </div>
                            <div v-if="mode === 'send'"><label for="airmail-recipient" class="block text-sm font-medium text-gray-700">Email destinatario</label><input id="airmail-recipient" v-model.trim="recipient" type="email" class="form-control mt-1" autocomplete="email" :disabled="sending || Boolean(resumeId)" /></div>
                            <div><label for="airmail-message" class="block text-sm font-medium text-gray-700">Messaggio</label><textarea id="airmail-message" v-model="message" rows="3" class="form-control mt-1" :disabled="sending || Boolean(resumeId)" /></div>
                            <div><label for="airmail-expiry" class="block text-sm font-medium text-gray-700">Scadenza</label><select id="airmail-expiry" v-model.number="expiresDays" class="form-control mt-1" :disabled="sending || Boolean(resumeId)"><option :value="1">1 giorno</option><option :value="3">3 giorni</option><option :value="7">7 giorni</option></select></div>
                            <div v-if="sending" class="space-y-2" role="status" aria-live="polite"><div class="h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full bg-[hsl(var(--primary-app))] transition-all" :style="{ width: `${progress}%` }" /></div><p class="text-sm text-gray-600">{{ uploadStage }}<template v-if="uploadFileNumber"> · File {{ uploadFileNumber }} di {{ files.length }}</template> · {{ progress }}%</p></div>
                            <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                            <p v-if="result" role="status" class="text-sm text-emerald-700">{{ result }}</p>
                            <div v-if="createdLink" class="flex min-w-0 gap-2"><input :value="createdLink" class="form-control min-w-0 flex-1" readonly aria-label="Link creato" @focus="$event.target.select()" /><button type="button" class="btn btn-outline shrink-0" title="Copia link" aria-label="Copia link" @click="copyLink(createdLink)"><Copy class="h-4 w-4" /></button></div>
                            <button type="button" class="btn btn-primary mt-auto w-full justify-center" :disabled="sending || !files.length || (mode === 'send' && !recipient)" @click="send"><LoaderCircle v-if="sending" class="h-4 w-4 animate-spin" /><Link2 v-else-if="mode === 'link'" class="h-4 w-4" /><Send v-else class="h-4 w-4" />{{ sending ? 'Caricamento in corso...' : resumeId ? 'Riprendi caricamento' : mode === 'link' ? 'Crea link' : 'Invia file' }}</button>
                            <button v-if="sending" type="button" class="btn btn-outline w-full justify-center" @click="abortController?.abort()">Interrompi</button>
                            <button v-if="resumeId && !sending" type="button" class="btn btn-outline w-full justify-center" @click="cancelResume">Annulla ripresa</button>
                        </section>
                    </div>

                    <section class="app-card">
                        <h3 class="section-title">I tuoi invii</h3>
                        <p v-if="!visibleTransfers.length" class="mt-5 text-sm text-gray-500">Nessun invio attivo.</p>
                        <div v-else class="mt-4 divide-y divide-gray-100">
                            <div v-for="transfer in visibleTransfers" :key="transfer.id" class="flex flex-wrap items-center gap-3 py-3 text-sm">
                                <div class="min-w-0 flex-1"><p class="truncate font-medium text-gray-900">{{ transfer.mode === 'link' ? 'Link creato' : transfer.recipient_email }}</p><a v-if="transfer.url" :href="transfer.url" target="_blank" rel="noopener noreferrer" class="mt-0.5 block w-fit max-w-full truncate text-xs font-medium text-[hsl(var(--primary-app))] hover:underline" :title="transfer.url">{{ transfer.url }}</a><p class="text-xs text-gray-500">{{ transfer.status === 'ready' ? `Disponibile fino al ${dateLabel(transfer.expires_at)}` : transfer.status === 'uploading' ? 'Caricamento da completare' : transfer.status === 'expired' ? 'Scaduto' : 'Eliminato' }}</p></div>
                                <button v-if="transfer.status === 'uploading'" type="button" class="btn btn-outline" @click="resume(transfer)">Riprendi</button>
                                <button v-if="transfer.url" type="button" class="btn btn-outline" :aria-label="`Copia link del trasferimento`" @click="copyLink(transfer.url, transfer.id)"><Copy class="h-4 w-4" />{{ copiedTransferId === transfer.id ? 'Copiato' : 'Copia link' }}</button>
                                <button v-if="['uploading', 'ready'].includes(transfer.status)" type="button" class="icon-btn text-red-600" title="Elimina invio" aria-label="Elimina invio" :disabled="Boolean(deletingId)" @click="askDelete(transfer)"><Trash2 class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </div>
        <Modal :show="Boolean(deleteTarget)" max-width="md" :closeable="!deletingId" @close="closeDelete">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">Eliminare il trasferimento?</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleteTarget?.mode === 'link' ? 'Il link creato' : `L’invio a ${deleteTarget?.recipient_email}` }} e tutti i suoi file verranno eliminati. Il link non sarà più utilizzabile.
                </p>
                <p v-if="deleteError" role="alert" class="mt-4 text-sm text-red-600">{{ deleteError }}</p>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn btn-outline" :disabled="Boolean(deletingId)" @click="closeDelete">Annulla</button>
                    <button type="button" class="btn btn-danger" :disabled="Boolean(deletingId)" @click="removeTransfer"><LoaderCircle v-if="deletingId" class="h-4 w-4 animate-spin" /><Trash2 v-else class="h-4 w-4" />Elimina</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.airmail-file-enter-active,
.airmail-file-leave-active {
    transition: opacity 240ms ease, transform 300ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.airmail-file-enter-from { opacity: 0; transform: translateY(-12px) scale(0.98); }
.airmail-file-leave-to { opacity: 0; transform: translateX(14px); }
@media (prefers-reduced-motion: reduce) {
    .airmail-file-enter-active,
    .airmail-file-leave-active { transition: none; }
}
</style>
