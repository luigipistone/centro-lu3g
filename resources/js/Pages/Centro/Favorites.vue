<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Bookmark, ExternalLink, Folder, FolderPlus, Pencil, Plus, Search, Trash2, Users, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ folders: Array, items: Array, users: Array, groups: Array, canManage: Boolean });
const page = usePage();
const selected = ref('all');
const search = ref('');
const editor = ref(null);
const editing = ref(null);
const form = useForm({ name: '', visibility: 'personal', user_ids: [], group_ids: [], folder_id: '', title: '', url: '', description: '' });
const folderList = computed(() => props.folders || []);
const visibleItems = computed(() => (props.items || []).filter(item =>
    (selected.value === 'all' || item.folder_id === selected.value)
    && `${item.title} ${item.url} ${item.description || ''}`.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()),
));
const folderById = id => folderList.value.find(folder => folder.id === id);
const writableFolders = computed(() => folderList.value.filter(folder => folder.can_edit));
const activeFolder = computed(() => folderById(selected.value));

function openFolder(folder = null) {
    form.reset(); form.clearErrors();
    editing.value = folder;
    if (folder) {
        form.name = folder.name;
        form.visibility = folder.visibility;
        form.user_ids = [...folder.user_ids];
        form.group_ids = [...folder.group_ids];
    }
    editor.value = 'folder';
}
function openItem(item = null) {
    form.reset(); form.clearErrors();
    editing.value = item;
    form.folder_id = item?.folder_id || (activeFolder.value?.can_edit ? activeFolder.value.id : writableFolders.value[0]?.id || '');
    if (item) {
        form.title = item.title; form.url = item.url; form.description = item.description || '';
    }
    editor.value = 'item';
}
function save() {
    const options = { preserveScroll: true, onSuccess: () => { editor.value = null; form.reset(); } };
    if (editor.value === 'folder') {
        const url = editing.value ? route('favorites.folders.update', editing.value.id) : route('favorites.folders.store');
        form.transform(data => ({ name: data.name, visibility: data.visibility, user_ids: data.visibility === 'shared' ? data.user_ids : [], group_ids: data.visibility === 'shared' ? data.group_ids : [] }));
        editing.value ? form.put(url, options) : form.post(url, options);
    } else {
        const url = editing.value ? route('favorites.items.update', editing.value.id) : route('favorites.items.store');
        form.transform(data => ({ folder_id: data.folder_id, title: data.title, url: data.url, description: data.description }));
        editing.value ? form.put(url, options) : form.post(url, options);
    }
}
function remove(kind, id) {
    const message = kind === 'folder' ? 'Eliminare la cartella e tutti i preferiti contenuti?' : 'Eliminare questo preferito?';
    if (!window.confirm(message)) return;
    router.delete(route(kind === 'folder' ? 'favorites.folders.destroy' : 'favorites.items.destroy', id), {
        preserveScroll: true, onSuccess: () => { if (kind === 'folder' && selected.value === id) selected.value = 'all'; },
    });
}
function toggleId(field, id) {
    form[field] = form[field].includes(id) ? form[field].filter(value => value !== id) : [...form[field], id];
}
</script>

<template>
    <Head title="Preferiti" />
    <AuthenticatedLayout>
        <div class="favorites-page mx-auto w-full max-w-[1600px] px-5 pb-16 pt-7 sm:px-8">
            <header class="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div><h1 class="text-3xl font-semibold text-gray-900">Preferiti</h1><p class="mt-1 text-gray-500">Link personali e condivisi, organizzati per cartella.</p></div>
                <div class="flex gap-2">
                    <button type="button" class="fav-secondary" @click="openFolder()"><FolderPlus :size="18" /> Cartella</button>
                    <button type="button" class="fav-primary" :disabled="!writableFolders.length" @click="openItem()"><Plus :size="18" /> Preferito</button>
                </div>
            </header>

            <div v-if="page.props.flash?.success" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ page.props.flash.success }}</div>
            <div class="mb-6 flex flex-wrap items-center gap-2">
                <button type="button" class="fav-folder" :class="{ active: selected === 'all' }" @click="selected = 'all'"><Bookmark :size="17" /> Tutti <span>{{ items.length }}</span></button>
                <button v-for="folder in folderList" :key="folder.id" type="button" class="fav-folder" :class="{ active: selected === folder.id }" @click="selected = folder.id">
                    <Folder :size="17" /> {{ folder.name }} <Users v-if="folder.visibility === 'shared'" :size="14" class="opacity-60" /> <span>{{ items.filter(item => item.folder_id === folder.id).length }}</span>
                </button>
            </div>

            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div class="relative w-full max-w-sm"><Search :size="17" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" /><input v-model="search" class="fav-input pl-10" type="search" placeholder="Cerca preferiti" aria-label="Cerca preferiti" /></div>
                <div v-if="activeFolder?.can_edit" class="flex gap-2"><button class="fav-icon" title="Modifica cartella" @click="openFolder(activeFolder)"><Pencil :size="17" /></button><button class="fav-icon text-red-500" title="Elimina cartella" @click="remove('folder', activeFolder.id)"><Trash2 :size="17" /></button></div>
            </div>

            <div v-if="!folderList.length" class="fav-empty"><Folder :size="34" class="text-blue-400" /><h2 class="mt-3 text-lg font-semibold">Crea la prima cartella</h2><p class="mt-1 text-gray-500">Poi potrai aggiungere i link da ritrovare rapidamente.</p><button class="fav-primary mt-5" @click="openFolder()"><Plus :size="18" /> Nuova cartella</button></div>
            <div v-else-if="!visibleItems.length" class="fav-empty"><Bookmark :size="34" class="text-blue-400" /><h2 class="mt-3 text-lg font-semibold">Nessun preferito qui</h2><p class="mt-1 text-gray-500">{{ search ? 'Prova un’altra ricerca.' : 'Aggiungi un link a una cartella per iniziare.' }}</p></div>
            <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="item in visibleItems" :key="item.id" class="fav-item">
                    <div class="flex min-w-0 items-start justify-between gap-2"><div class="fav-item-icon"><Bookmark :size="19" /></div><div v-if="folderById(item.folder_id)?.can_edit" class="flex"><button class="fav-icon" title="Modifica preferito" @click="openItem(item)"><Pencil :size="16" /></button><button class="fav-icon text-red-500" title="Elimina preferito" @click="remove('item', item.id)"><Trash2 :size="16" /></button></div></div>
                    <a :href="item.url" target="_blank" rel="noopener noreferrer" class="mt-4 flex items-center gap-2 text-base font-semibold text-gray-900 hover:text-blue-600"><span class="min-w-0 truncate">{{ item.title }}</span><ExternalLink :size="15" class="shrink-0" /></a>
                    <p class="mt-1 truncate text-sm text-gray-500">{{ item.url }}</p>
                    <p v-if="item.description" class="mt-3 line-clamp-2 min-h-10 text-sm text-gray-600">{{ item.description }}</p><div v-else class="min-h-10" />
                    <div class="mt-4 border-t border-gray-100 pt-3 text-xs font-medium text-gray-500">{{ folderById(item.folder_id)?.name }}</div>
                </article>
            </div>
        </div>

        <div v-if="editor" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/45 p-4" @click.self="editor = null">
            <form class="fav-modal w-full max-w-xl" @submit.prevent="save">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5"><h2 class="text-xl font-semibold">{{ editing ? 'Modifica' : 'Nuovo' }} {{ editor === 'folder' ? 'cartella' : 'preferito' }}</h2><button type="button" class="fav-icon" title="Chiudi" @click="editor = null"><X :size="20" /></button></div>
                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">
                    <template v-if="editor === 'folder'">
                        <label class="fav-label">Nome cartella<input v-model="form.name" class="fav-input mt-2" maxlength="120" required /></label><p v-if="form.errors.name" class="fav-error">{{ form.errors.name }}</p>
                        <div><span class="fav-label">Visibilità</span><div class="mt-2 flex gap-2"><button type="button" class="fav-choice" :class="{ active: form.visibility === 'personal' }" @click="form.visibility = 'personal'">Personale</button><button v-if="canManage" type="button" class="fav-choice" :class="{ active: form.visibility === 'shared' }" @click="form.visibility = 'shared'">Condivisa / team</button></div><p v-if="form.errors.visibility" class="fav-error">{{ form.errors.visibility }}</p></div>
                        <template v-if="form.visibility === 'shared'"><div><span class="fav-label">Utenti autorizzati</span><div class="fav-options"><label v-for="user in users" :key="user.id"><input type="checkbox" :checked="form.user_ids.includes(user.id)" @change="toggleId('user_ids', user.id)" /> {{ user.name }}</label></div></div><div><span class="fav-label">Gruppi autorizzati</span><div class="fav-options"><label v-for="group in groups" :key="group.id"><input type="checkbox" :checked="form.group_ids.includes(group.id)" @change="toggleId('group_ids', group.id)" /> {{ group.name }}</label><span v-if="!groups.length" class="text-sm text-gray-500">Nessun gruppo disponibile.</span></div></div><p v-if="form.errors.user_ids" class="fav-error">{{ form.errors.user_ids }}</p></template>
                    </template>
                    <template v-else><label class="fav-label">Titolo<input v-model="form.title" class="fav-input mt-2" maxlength="180" required /></label><p v-if="form.errors.title" class="fav-error">{{ form.errors.title }}</p><label class="fav-label">Indirizzo web<input v-model="form.url" class="fav-input mt-2" type="url" placeholder="https://" required /></label><p v-if="form.errors.url" class="fav-error">{{ form.errors.url }}</p><label class="fav-label">Cartella<select v-model="form.folder_id" class="fav-input mt-2" required><option value="" disabled>Seleziona una cartella</option><option v-for="folder in writableFolders" :key="folder.id" :value="folder.id">{{ folder.name }}</option></select></label><p v-if="form.errors.folder_id" class="fav-error">{{ form.errors.folder_id }}</p><label class="fav-label">Note<textarea v-model="form.description" class="fav-input mt-2 min-h-24" maxlength="2000" /></label></template>
                </div><div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4"><button type="button" class="fav-secondary" @click="editor = null">Annulla</button><button type="submit" class="fav-primary" :disabled="form.processing">Salva</button></div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.fav-primary,.fav-secondary,.fav-choice,.fav-folder,.fav-icon{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:8px;font-weight:600;transition:background .18s,box-shadow .18s,color .18s}
.fav-primary{min-height:42px;padding:0 16px;background:#1874ed;color:white;box-shadow:0 8px 20px #1874ed26}.fav-primary:hover{background:#095fcf}.fav-primary:disabled{opacity:.5;cursor:not-allowed}
.fav-secondary{min-height:42px;padding:0 16px;background:white;color:#344054;border:1px solid #d9e0e9}.fav-secondary:hover,.fav-icon:hover{background:#f2f6fb}
.fav-folder{min-height:42px;padding:0 13px;background:white;color:#475467;border:1px solid #e3e8ef}.fav-folder.active{background:#eaf3ff;color:#0869db;border-color:#9fc7ff}.fav-folder span{font-size:12px;opacity:.65}
.fav-icon{width:34px;height:34px}.fav-input{width:100%;min-height:44px;border:1px solid #d6dde8;border-radius:8px;background:white;padding:10px 12px;color:#172033;box-shadow:0 1px 2px #1e293b0a}.fav-input:focus{border-color:#3488ff;outline:2px solid #3488ff24}.fav-item{min-width:0;border:1px solid #e6ebf2;border-radius:8px;background:white;padding:18px;box-shadow:0 8px 22px #2636500a}.fav-item-icon{display:grid;width:38px;height:38px;place-items:center;border-radius:8px;background:#e9f2ff;color:#1474ed}.fav-empty{padding:60px 20px;text-align:center;color:#1f2937}.fav-empty>svg{margin:auto}.fav-modal{border-radius:10px;background:white;box-shadow:0 24px 80px #10182833}.fav-label{display:block;font-size:14px;font-weight:600;color:#344054}.fav-choice{padding:9px 14px;border:1px solid #d9e0e9;color:#475467}.fav-choice.active{border-color:#8cbcff;background:#eaf3ff;color:#0869db}.fav-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:9px;max-height:160px;overflow:auto}.fav-options label{display:flex;align-items:center;gap:8px;font-size:14px;color:#344054}.fav-error{margin-top:5px;color:#c73232;font-size:13px}
</style>
