<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { ArrowLeft, Check, ChevronRight, Download, FileText, Folder, FolderPlus, LoaderCircle, Paperclip, Trash2, UploadCloud, X } from '@lucide/vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({ scope: { type: String, required: true }, entityId: { type: String, required: true } });
const files = ref([]);
const loading = ref(false);
const error = ref('');
const ready = ref(false);
const canUpload = ref(false);
const canRemove = ref(false);
const folder = ref(null);
const root = ref('');
const picker = ref(false);
const folders = ref([]);
const trail = ref([]);
const cursor = ref(null);
const browsing = ref(false);
const folderError = ref('');
const input = ref(null);
const dragging = ref(false);
const busy = ref(false);
const progress = ref(0);
const currentName = ref('');
const success = ref('');
const removing = ref(null);
const deleting = ref(false);
const newFolderName = ref('');
const newFolderOpen = ref(false);
const creatingFolder = ref(false);
let version = 0;
const base = () => `/${props.scope}/${encodeURIComponent(props.entityId)}`;
const message = (e) => Object.values(e.response?.data?.errors || {}).flat()[0] || 'Operazione non riuscita. Riprova.';
const size = (bytes) => bytes < 1048576 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1048576).toFixed(1)} MB`;

async function load() {
    const seq = ++version;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`${base()}/drive-attachments`);
        if (seq !== version) return;
        files.value = data.files;
        ready.value = data.configured;
        canUpload.value = data.can_upload;
        canRemove.value = data.can_remove;
        root.value = data.root;
        folder.value ||= { id: data.root, name: 'Drive aziendale' };
    } catch (e) {
        if (seq === version) error.value = message(e);
    } finally {
        if (seq === version) loading.value = false;
    }
}
watch(() => [props.scope, props.entityId], () => {
    files.value = []; folder.value = null; success.value = ''; picker.value = false;
    load();
}, { immediate: true });

async function browse(target, more = false) {
    if (browsing.value || creatingFolder.value) return;
    browsing.value = true;
    folderError.value = '';
    try {
        const { data } = await axios.get(`${base()}/drive-folders`, { params: { parent: target.id, cursor: more ? cursor.value : undefined } });
        folders.value = more ? [...folders.value, ...data.folders] : data.folders;
        cursor.value = data.cursor;
        if (!more) {
            const index = trail.value.findIndex(item => item.id === target.id);
            trail.value = index >= 0 ? trail.value.slice(0, index + 1) : [...trail.value, data.folder];
        }
    } catch (e) { folderError.value = message(e); }
    finally { browsing.value = false; }
}
function openPicker() {
    newFolderName.value = ''; newFolderOpen.value = false;
    trail.value = []; folders.value = []; cursor.value = null;
    picker.value = true;
    browse({ id: root.value });
}
async function createFolder() {
    const name = newFolderName.value.trim();
    if (!name || creatingFolder.value || !trail.value.length) return;
    creatingFolder.value = true; folderError.value = '';
    try {
        const { data } = await axios.post(`${base()}/drive-folders`, { parent: trail.value[trail.value.length - 1].id, name });
        folder.value = data;
        picker.value = false;
        newFolderName.value = ''; newFolderOpen.value = false;
    } catch (e) { folderError.value = message(e); }
    finally { creatingFolder.value = false; }
}
async function upload(list) {
    if (busy.value || !ready.value || !canUpload.value) return;
    const queue = Array.from(list || []);
    if (!queue.length) return;
    if (queue.some(file => file.size > 20 * 1024 * 1024 || !file.size)) {
        error.value = 'Seleziona file non vuoti, fino a 20 MB ciascuno.';
        return;
    }
    const url = `${base()}/drive-attachments`;
    const destination = folder.value.id;
    const entity = props.entityId;
    busy.value = true; error.value = ''; success.value = '';
    let completed = 0;
    try {
        for (const file of queue) {
            currentName.value = file.name; progress.value = 0;
            const form = new FormData(); form.append('file', file); form.append('folder', destination);
            await axios.post(url, form, { onUploadProgress: event => {
                progress.value = event.total ? Math.min(100, Math.round(event.loaded * 100 / event.total)) : 0;
            } });
            completed++;
        }
        if (entity === props.entityId) success.value = completed === 1 ? 'File salvato su Drive.' : `${completed} file salvati su Drive.`;
    } catch (e) {
        if (entity === props.entityId) error.value = `${completed ? `${completed} file salvati. ` : ''}${message(e)}`;
    } finally {
        busy.value = false; currentName.value = '';
        if (input.value) input.value.value = '';
        if (entity === props.entityId) {
            const savedError = error.value;
            await load();
            if (savedError) error.value = savedError;
        }
    }
}
function drop(event) { dragging.value = false; upload(event.dataTransfer.files); }
async function deleteFile() {
    deleting.value = true;
    try {
        await axios.delete(`${base()}/drive-attachments/${removing.value.id}`);
        removing.value = null;
        await load();
    } catch (e) { error.value = message(e); removing.value = null; }
    finally { deleting.value = false; }
}
</script>

<template>
    <section class="min-w-0 space-y-3" aria-label="Allegati Drive">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900"><Paperclip class="h-4 w-4" />Allegati</h3>
            <button v-if="canUpload && ready" type="button" class="btn btn-outline max-w-full text-sm" :disabled="busy" @click="openPicker">
                <Folder class="h-4 w-4 shrink-0" /><span class="truncate">{{ folder?.name }}</span><ChevronRight class="h-4 w-4 shrink-0" />
            </button>
        </div>
        <div v-if="canUpload && ready" class="rounded-[var(--radius-sm)] border border-dashed p-4 text-center transition" :class="dragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-gray-50/70'" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop">
            <button type="button" class="inline-flex max-w-full items-center justify-center gap-2 text-sm font-medium text-gray-700" :disabled="busy" @click="input?.click()">
                <UploadCloud class="h-5 w-5 shrink-0" />Trascina i file o selezionali
            </button>
            <p class="mt-1 text-xs text-gray-500">Google Drive · Fino a 20 MB per file</p>
            <input ref="input" type="file" multiple class="hidden" :disabled="busy" @change="upload($event.target.files)" />
        </div>
        <div v-if="busy" class="space-y-2" role="status" aria-live="polite">
            <div class="flex min-w-0 items-center gap-2 text-sm text-gray-600"><LoaderCircle class="h-4 w-4 shrink-0 animate-spin" /><span class="truncate">{{ currentName }}</span></div>
            <progress class="h-2 w-full accent-indigo-600" :value="progress" max="100" />
            <p class="text-xs text-gray-500">{{ progress < 100 ? `Caricamento ${progress}%` : 'Salvataggio su Google Drive…' }}</p>
        </div>
        <p v-if="loading" class="text-sm text-gray-500" role="status">Caricamento allegati…</p>
        <p v-else-if="!ready" class="text-sm text-gray-500">Collegamento Google Drive non disponibile.</p>
        <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
        <p v-if="success" class="flex items-center gap-2 text-sm text-emerald-600" role="status"><Check class="h-4 w-4" />{{ success }}</p>
        <div class="divide-y divide-gray-100">
            <div v-for="file in files" :key="file.id" class="flex min-w-0 items-center gap-3 py-3">
                <FileText class="h-5 w-5 shrink-0 text-gray-400" />
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-gray-900" :title="file.name">{{ file.name }}</p><p class="truncate text-xs text-gray-500">{{ size(file.size) }} · {{ file.folder_name }}</p></div>
                <a :href="`${base()}/drive-attachments/${file.id}`" class="icon-btn h-9 w-9 shrink-0" title="Scarica allegato" aria-label="Scarica allegato"><Download class="h-4 w-4" /></a>
                <button v-if="canRemove" type="button" class="icon-btn h-9 w-9 shrink-0 text-red-600" title="Elimina file" aria-label="Elimina file" @click="removing = file"><Trash2 class="h-4 w-4" /></button>
            </div>
        </div>
        <Modal :show="picker" :closeable="!creatingFolder" @close="picker = false">
            <div class="space-y-4 p-6">
                <div class="flex items-center justify-between gap-3"><h3 class="text-lg font-semibold text-gray-900">Cartella Google Drive</h3><button type="button" class="icon-btn h-9 w-9" aria-label="Chiudi" :disabled="creatingFolder" @click="picker = false"><X class="h-5 w-5" /></button></div>
                <button type="button" class="btn btn-outline text-sm" :disabled="browsing || creatingFolder || !trail.length" @click="newFolderOpen = !newFolderOpen"><FolderPlus class="h-4 w-4" />Nuova cartella</button>
                <form v-if="newFolderOpen" class="space-y-2" @submit.prevent="createFolder">
                    <label class="block text-sm font-medium text-gray-700">Nome cartella<input v-model="newFolderName" class="form-control mt-2" maxlength="255" required :disabled="creatingFolder" /></label>
                    <button type="submit" class="btn btn-primary" :disabled="creatingFolder || !newFolderName.trim()"><LoaderCircle v-if="creatingFolder" class="h-4 w-4 animate-spin" />{{ creatingFolder ? 'Creazione…' : 'Crea e seleziona' }}</button>
                </form>
                <div class="flex min-w-0 items-center gap-2"><button v-if="trail.length > 1" type="button" class="icon-btn h-9 w-9 shrink-0" :disabled="browsing" title="Cartella superiore" @click="browse(trail[trail.length - 2])"><ArrowLeft class="h-4 w-4" /></button><p class="truncate text-sm font-medium text-gray-700" :title="trail.map(f => f.name).join(' / ')">{{ trail.map(f => f.name).join(' / ') }}</p></div>
                <p v-if="folderError" class="text-sm text-red-600" role="alert">{{ folderError }}</p>
                <div class="max-h-72 overflow-y-auto">
                    <button v-for="item in folders" :key="item.id" type="button" class="flex w-full items-center gap-3 rounded-[var(--radius-sm)] p-3 text-left text-sm text-gray-700 hover:bg-gray-100" :disabled="browsing" @click="browse(item)"><Folder class="h-5 w-5 shrink-0" /><span class="min-w-0 flex-1 truncate">{{ item.name }}</span><ChevronRight class="h-4 w-4 shrink-0" /></button>
                    <p v-if="browsing" class="p-3 text-sm text-gray-500">Caricamento cartelle…</p>
                    <p v-else-if="!folders.length && !folderError" class="p-3 text-sm text-gray-500">Nessuna sottocartella.</p>
                    <button v-if="cursor" type="button" class="btn btn-outline mt-2" :disabled="browsing" @click="browse(trail[trail.length - 1], true)">Carica altre cartelle</button>
                </div>
                <div class="flex justify-end gap-3"><button type="button" class="btn btn-outline" :disabled="creatingFolder" @click="picker = false">Annulla</button><button type="button" class="btn btn-primary" :disabled="creatingFolder || browsing || !trail.length || !!folderError" @click="folder = trail[trail.length - 1]; picker = false">Seleziona cartella</button></div>
            </div>
        </Modal>
        <Modal :show="!!removing" max-width="md" :closeable="!deleting" @close="removing = null">
            <div class="space-y-4 p-6"><h3 class="text-lg font-semibold text-gray-900">Eliminare il file?</h3><p class="break-words text-sm text-gray-600">{{ removing?.name }} verrà rimosso dagli allegati e spostato nel cestino di Google Drive.</p><div class="flex justify-end gap-3"><button type="button" class="btn btn-outline" :disabled="deleting" @click="removing = null">Annulla</button><button type="button" class="btn btn-primary" :disabled="deleting" @click="deleteFile">{{ deleting ? 'Eliminazione…' : 'Elimina' }}</button></div></div>
        </Modal>
    </section>
</template>
