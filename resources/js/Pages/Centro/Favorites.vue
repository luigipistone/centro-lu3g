<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import ClearableSearchInput from '@/Components/ClearableSearchInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Bookmark, ExternalLink, Folder, FolderPlus, Pencil, Plus, Trash2, Users, X } from '@lucide/vue';
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
</script>

<template>
    <Head title="Preferiti" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Preferiti</h2>
                <p class="text-sm text-gray-500">Link personali e condivisi, organizzati per cartella.</p>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ page.props.flash.success }}</div>
                <div class="surface p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <ClearableSearchInput v-model="search" class="w-full max-w-sm" input-class="h-[38px]" placeholder="Cerca preferiti" />
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-outline h-[38px]" @click="openFolder()"><FolderPlus class="h-4 w-4" :stroke-width="1.7" /> Cartella</button>
                            <button type="button" class="btn btn-primary h-[38px]" :disabled="!writableFolders.length" @click="openItem()"><Plus class="h-4 w-4" :stroke-width="1.7" /> Preferito</button>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="favorite-filter-pill" :class="{ 'is-active': selected === 'all' }" @click="selected = 'all'">Tutti <span>{{ items.length }}</span></button>
                    <button v-for="folder in folderList" :key="folder.id" type="button" class="favorite-filter-pill" :class="{ 'is-active': selected === folder.id }" @click="selected = folder.id">
                        <Folder class="h-4 w-4" :stroke-width="1.7" /> {{ folder.name }} <Users v-if="folder.visibility === 'shared'" class="h-3.5 w-3.5 opacity-70" /> <span>{{ items.filter(item => item.folder_id === folder.id).length }}</span>
                    </button>
                </div>
                <div v-if="activeFolder?.can_edit" class="flex justify-end gap-1">
                    <button type="button" class="icon-btn h-8 w-8" title="Modifica cartella" @click="openFolder(activeFolder)"><Pencil class="h-4 w-4" :stroke-width="1.7" /></button>
                    <button type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina cartella" @click="remove('folder', activeFolder.id)"><Trash2 class="h-4 w-4" :stroke-width="1.7" /></button>
                </div>
                <div v-if="!folderList.length" class="surface px-6 py-12 text-center text-sm text-gray-500">Nessuna cartella disponibile. <button type="button" class="font-semibold text-[hsl(var(--primary-app))]" @click="openFolder()">Crea una cartella</button></div>
                <div v-else-if="!visibleItems.length" class="surface px-6 py-12 text-center text-sm text-gray-500">Nessun preferito disponibile.</div>
                <div v-else class="grid items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="item in visibleItems" :key="item.id" class="surface flex h-full min-h-[138px] flex-col justify-between p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Bookmark class="h-5 w-5" :stroke-width="1.7" /></span>
                                <div class="min-w-0"><a :href="item.url" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-sm font-semibold text-gray-900 hover:text-[hsl(var(--primary-app))]"><span class="line-clamp-2">{{ item.title }}</span><ExternalLink class="h-3.5 w-3.5 shrink-0" :stroke-width="1.7" /></a><p class="truncate text-xs text-gray-500">{{ item.url }}</p></div>
                            </div>
                            <div v-if="folderById(item.folder_id)?.can_edit" class="flex shrink-0 gap-1"><button type="button" class="icon-btn h-8 w-8" title="Modifica" @click="openItem(item)"><Pencil class="h-4 w-4" :stroke-width="1.7" /></button><button type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina" @click="remove('item', item.id)"><Trash2 class="h-4 w-4" :stroke-width="1.7" /></button></div>
                        </div>
                        <p v-if="item.description" class="mt-3 line-clamp-2 text-xs text-gray-500">{{ item.description }}</p>
                        <div class="mt-4"><span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-200"><Folder class="h-3.5 w-3.5" :stroke-width="1.7" /> {{ folderById(item.folder_id)?.name }}</span></div>
                    </article>
                </div>
            </div>
        </div>

        <div v-if="editor" class="fixed inset-0 z-[5200] grid h-dvh w-dvw place-items-center bg-gray-950/20 px-4 backdrop-blur-[2px]" @click.self="editor = null">
            <form class="surface w-full max-w-xl overflow-hidden" @submit.prevent="save">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5"><h2 class="text-lg font-semibold text-gray-900">{{ editing ? 'Modifica' : 'Nuovo' }} {{ editor === 'folder' ? 'cartella' : 'preferito' }}</h2><button type="button" class="icon-btn h-8 w-8" title="Chiudi" @click="editor = null"><X class="h-4 w-4" :stroke-width="1.7" /></button></div>
                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">
                    <template v-if="editor === 'folder'">
                        <label class="block text-sm font-medium text-gray-700">Nome cartella<input v-model="form.name" class="form-control mt-2" maxlength="120" required /></label><p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                        <div><span class="block text-sm font-medium text-gray-700">Visibilità</span><div class="mt-2 inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1"><button type="button" class="rounded-md px-4 py-2 text-sm font-semibold" :class="form.visibility === 'personal' ? 'bg-white text-[hsl(var(--primary-app))] shadow-sm' : 'text-gray-500'" @click="form.visibility = 'personal'">Personale</button><button v-if="canManage" type="button" class="rounded-md px-4 py-2 text-sm font-semibold" :class="form.visibility === 'shared' ? 'bg-white text-[hsl(var(--primary-app))] shadow-sm' : 'text-gray-500'" @click="form.visibility = 'shared'">Condivisa / team</button></div><p v-if="form.errors.visibility" class="text-xs text-red-600">{{ form.errors.visibility }}</p></div>
                        <template v-if="form.visibility === 'shared'"><div><label class="mb-2 block text-sm font-medium text-gray-700">Utenti autorizzati</label><AppSelect v-model="form.user_ids" :options="users.map(user => ({ value: user.id, label: user.name }))" placeholder="Seleziona utenti" multiple searchable /></div><div><label class="mb-2 block text-sm font-medium text-gray-700">Gruppi autorizzati</label><AppSelect v-model="form.group_ids" :options="groups.map(group => ({ value: group.id, label: group.name }))" placeholder="Seleziona gruppi" multiple searchable /></div><p v-if="form.errors.user_ids" class="text-xs text-red-600">{{ form.errors.user_ids }}</p></template>
                    </template>
                    <template v-else><label class="block text-sm font-medium text-gray-700">Titolo<input v-model="form.title" class="form-control mt-2" maxlength="180" required /></label><p v-if="form.errors.title" class="text-xs text-red-600">{{ form.errors.title }}</p><label class="block text-sm font-medium text-gray-700">Indirizzo web<input v-model="form.url" class="form-control mt-2" type="url" placeholder="https://" required /></label><p v-if="form.errors.url" class="text-xs text-red-600">{{ form.errors.url }}</p><div><label class="mb-2 block text-sm font-medium text-gray-700">Cartella</label><AppSelect v-model="form.folder_id" :options="writableFolders.map(folder => ({ value: folder.id, label: folder.name }))" placeholder="Seleziona una cartella" /></div><p v-if="form.errors.folder_id" class="text-xs text-red-600">{{ form.errors.folder_id }}</p><label class="block text-sm font-medium text-gray-700">Note<textarea v-model="form.description" class="form-control mt-2 min-h-24" maxlength="2000" /></label></template>
                </div><div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4"><button type="button" class="btn btn-outline" @click="editor = null">Annulla</button><button type="submit" class="btn btn-primary" :disabled="form.processing || (editor === 'item' && !form.folder_id)">Salva</button></div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.favorite-filter-pill{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:.55rem;border:1px solid #dfe5ef;border-radius:12px;background:white;padding:0 14px;font-size:14px;font-weight:700;color:#344054;box-shadow:0 6px 20px #1c2a4910;transition:transform .18s,box-shadow .18s}
.favorite-filter-pill:hover{transform:translateY(-2px);box-shadow:0 12px 26px #1c2a491c}
.favorite-filter-pill.is-active{border-color:#8db8ff;background:linear-gradient(180deg,#348bff,#116eea);color:white;box-shadow:0 10px 24px #1874ed30}
.favorite-filter-pill span{display:inline-flex;min-width:24px;justify-content:center;border-radius:999px;background:#ffffff40;padding:2px 6px;font-size:12px}
</style>
