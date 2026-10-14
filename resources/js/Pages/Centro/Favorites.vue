<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import ClearableSearchInput from '@/Components/ClearableSearchInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bookmark, ExternalLink, Folder, Pencil, Plus, Star, Tag, Trash2, Users, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({ folders: Array, items: Array, categories: Array, users: Array, groups: Array, canManage: Boolean, view: String, selectedFolderId: String, selectedGroupId: String });
const page = usePage();
const selected = ref('all');
const search = ref('');
const categoryFilters = ref([]);
const categoryEditorOpen = ref(false);
const categoryForm = useForm({ name: '' });
const editor = ref(null);
const editing = ref(null);
const form = useForm({ name: '', color: '#0B6EF3', visibility: 'personal', user_ids: [], group_ids: [], folder_id: '', title: '', url: '', description: '', category_ids: [], is_featured: false });
const groupForm = useForm({ name: '', description: '', user_ids: [] });
const folderList = computed(() => props.folders || []);
const currentView = computed(() => props.view || 'items');
const selectedFolder = computed(() => folderList.value.find(folder => folder.id === props.selectedFolderId));
const selectedGroup = computed(() => (props.groups || []).find(group => group.id === props.selectedGroupId));
const folderColors = ['#0B6EF3', '#22C55E', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#111827'];
const nav = [{ view: 'items', route: 'favorites.index', label: 'Preferiti', description: 'Link salvati' }, { view: 'folders', route: 'favorites.folders', label: 'Cartelle', description: 'Spazi personali e condivisi' }, { view: 'groups', route: 'favorites.groups', label: 'Gruppi', description: 'Accessi condivisi' }];
function light(color, threshold = 0.62) {
    const hex = /^#[0-9a-fA-F]{6}$/.test(color || '') ? color : '#0B6EF3';
    const rgb = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
    return (rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722) / 255 > threshold;
}
function folderStyle(folder) {
    const color = folder?.color || '#0B6EF3';
    return { backgroundColor: color, color: light(color) ? '#111827' : '#ffffff', borderColor: light(color) ? 'rgba(17,24,39,.14)' : 'rgba(255,255,255,.24)' };
}
function folderChipStyle(folder) {
    return { color: light(folder?.color) ? '#111827' : '#ffffff', borderColor: light(folder?.color) ? 'rgba(17,24,39,.14)' : 'rgba(255,255,255,.28)', backgroundColor: light(folder?.color) ? 'rgba(255,255,255,.46)' : 'rgba(255,255,255,.16)' };
}
function toggleShare(field, id) {
    form[field] = form[field].includes(id) ? form[field].filter(value => value !== id) : [...form[field], id];
}
const visibleItems = computed(() => (props.items || []).filter(item =>
    (selected.value === 'all' || item.folder_id === selected.value)
    && (!categoryFilters.value.length || categoryFilters.value.some(id => item.category_ids?.includes(id)))
    && `${item.title} ${item.url} ${item.description || ''}`.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()),
));
const featuredItems = computed(() => visibleItems.value.filter(item => item.is_featured));
const regularItems = computed(() => visibleItems.value.filter(item => !item.is_featured));
const itemSections = computed(() => [
    ...(featuredItems.value.length ? [{ key: 'featured', title: 'In evidenza', items: featuredItems.value }] : []),
    ...(regularItems.value.length ? [{ key: 'regular', title: featuredItems.value.length ? 'Tutti i preferiti' : '', items: regularItems.value }] : []),
]);
const categoryName = id => (props.categories || []).find(category => category.id === id)?.name || '';
const folderById = id => folderList.value.find(folder => folder.id === id);
const writableFolders = computed(() => folderList.value.filter(folder => folder.can_edit));
const activeFolder = computed(() => folderById(selected.value));
watch(() => [props.view, props.selectedFolderId], () => {
    if (!['folder-create', 'folder-detail'].includes(currentView.value)) return;
    form.reset(); form.clearErrors();
    editing.value = selectedFolder.value || null;
    form.name = selectedFolder.value?.name || '';
    form.color = selectedFolder.value?.color || '#0B6EF3';
    form.visibility = selectedFolder.value?.visibility || 'personal';
    form.user_ids = [...(selectedFolder.value?.user_ids || [])];
    form.group_ids = [...(selectedFolder.value?.group_ids || [])];
    editor.value = 'folder';
}, { immediate: true });
watch(() => [props.view, props.selectedGroupId], () => {
    if (!['group-create', 'group-detail'].includes(currentView.value)) return;
    groupForm.reset(); groupForm.clearErrors();
    groupForm.name = selectedGroup.value?.name || '';
    groupForm.description = selectedGroup.value?.description || '';
    groupForm.user_ids = [...(selectedGroup.value?.user_ids || [])];
}, { immediate: true });

function saveGroup() {
    const options = { onSuccess: () => router.visit(route('favorites.groups')) };
    if (selectedGroup.value) groupForm.put(route('favorites.groups.update', selectedGroup.value.id), options);
    else groupForm.post(route('favorites.groups.store'), options);
}

function toggleGroupUser(id) {
    groupForm.user_ids = groupForm.user_ids.includes(id) ? groupForm.user_ids.filter(value => value !== id) : [...groupForm.user_ids, id];
}

function openFolder(folder = null) {
    router.visit(route(folder ? 'favorites.folders.show' : 'favorites.folders.create', ...(folder ? [folder.id] : [])));
}
function openItem(item = null) {
    form.reset(); form.clearErrors();
    editing.value = item;
    form.folder_id = item?.folder_id || (selectedFolder.value?.can_edit ? selectedFolder.value.id : activeFolder.value?.can_edit ? activeFolder.value.id : writableFolders.value[0]?.id || '');
    if (item) {
        form.title = item.title; form.url = item.url; form.description = item.description || '';
        form.category_ids = [...(item.category_ids || [])];
        form.is_featured = Boolean(item.is_featured);
    }
    editor.value = 'item';
}
function save() {
    const options = { preserveScroll: true, onSuccess: () => {
        if (editor.value === 'folder') router.visit(route('favorites.folders'));
        else { editor.value = null; form.reset(); }
    } };
    if (editor.value === 'folder') {
        const url = editing.value ? route('favorites.folders.update', editing.value.id) : route('favorites.folders.store');
        form.transform(data => ({ name: data.name, color: data.color, visibility: data.visibility, user_ids: data.visibility === 'shared' ? data.user_ids : [], group_ids: data.visibility === 'shared' ? data.group_ids : [] }));
        editing.value ? form.put(url, options) : form.post(url, options);
    } else {
        const url = editing.value ? route('favorites.items.update', editing.value.id) : route('favorites.items.store');
        form.transform(data => ({ folder_id: data.folder_id, title: data.title, url: data.url, description: data.description, category_ids: data.category_ids, is_featured: data.is_featured }));
        editing.value ? form.put(url, options) : form.post(url, options);
    }
}
function saveCategory() {
    const name = categoryForm.name.trim();
    if (!name) return;
    categoryForm.post(route('favorites.categories.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: response => {
            const category = response.props.categories?.find(entry => entry.name.toLocaleLowerCase() === name.toLocaleLowerCase());
            if (category) form.category_ids = [...new Set([...form.category_ids, category.id])];
            categoryForm.reset();
            categoryEditorOpen.value = false;
        },
    });
}
function remove(kind, id) {
    const message = kind === 'folder' ? 'Eliminare la cartella e tutti i preferiti contenuti?' : kind === 'group' ? 'Eliminare il gruppo? Le cartelle condivise con questo gruppo non saranno più accessibili ai suoi membri.' : 'Eliminare questo preferito?';
    if (!window.confirm(message)) return;
    router.delete(route(kind === 'folder' ? 'favorites.folders.destroy' : kind === 'group' ? 'favorites.groups.destroy' : 'favorites.items.destroy', id), {
        preserveScroll: true, onSuccess: () => { if (kind === 'folder') router.visit(route('favorites.folders')); if (kind === 'group') router.visit(route('favorites.groups')); },
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
                <nav class="grid gap-3 md:grid-cols-3">
                    <Link v-for="entry in nav" :key="entry.view" :href="route(entry.route)" :class="['surface flex items-center justify-between gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]', (currentView === entry.view || (entry.view === 'folders' && currentView.startsWith('folder-')) || (entry.view === 'groups' && currentView.startsWith('group-'))) ? 'ring-1 ring-[hsl(var(--primary-app)/0.25)]' : '']">
                        <span><span class="block text-sm font-semibold text-gray-900">{{ entry.label }}</span><span class="mt-1 block text-xs text-gray-500">{{ entry.description }}</span></span>
                        <Folder v-if="entry.view === 'folders'" class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" /><Users v-else-if="entry.view === 'groups'" class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" /><Bookmark v-else class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                    </Link>
                </nav>
                <section v-if="currentView === 'items'" class="space-y-5">
                <div class="surface p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="grid w-full flex-1 gap-3 md:grid-cols-2">
                            <ClearableSearchInput v-model="search" input-class="h-[38px]" placeholder="Cerca preferiti" />
                            <AppSelect v-model="categoryFilters" :options="categories.map(category => ({ value: category.id, label: category.name }))" placeholder="Categorie" multiple searchable />
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-primary h-[38px]" :disabled="!writableFolders.length" @click="openItem()"><Plus class="h-4 w-4" :stroke-width="1.7" /> Preferito</button>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="favorite-filter-pill" :class="{ 'is-active': selected === 'all' }" :style="{ backgroundColor: '#ffffff', color: '#111827' }" @click="selected = 'all'">Tutte <span>{{ items.length }}</span></button>
                    <button v-for="folder in folderList" :key="folder.id" type="button" class="favorite-filter-pill" :class="{ 'is-active': selected === folder.id }" :style="folderStyle(folder)" @click="selected = folder.id">
                        {{ folder.name }} <span>{{ items.filter(item => item.folder_id === folder.id).length }}</span>
                    </button>
                </div>
                <div v-if="!folderList.length" class="surface px-6 py-12 text-center text-sm text-gray-500">Nessuna cartella disponibile. <Link :href="route('favorites.folders.create')" class="font-semibold text-[hsl(var(--primary-app))]">Crea una cartella</Link></div>
                <div v-else-if="!visibleItems.length" class="surface px-6 py-12 text-center text-sm text-gray-500">Nessun preferito disponibile.</div>
                <div v-for="section in itemSections" :key="section.key" :class="section.key === 'featured' ? 'rounded-[var(--radius)] border border-[hsl(var(--primary-app)/0.22)] bg-[hsl(var(--primary-app)/0.045)] p-4 sm:p-5' : ''">
                    <h3 v-if="section.title" class="mb-4 flex items-center gap-2 text-base font-semibold text-gray-900"><Star v-if="section.key === 'featured'" class="h-4 w-4 fill-[hsl(var(--primary-app))] text-[hsl(var(--primary-app))]" :stroke-width="1.7" /> {{ section.title }}</h3>
                    <div class="grid items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="item in section.items" :key="item.id" class="surface flex h-full min-h-[138px] flex-col justify-between p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Bookmark class="h-5 w-5" :stroke-width="1.7" /></span>
                                <div class="min-w-0"><a :href="item.url" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 text-sm font-semibold text-gray-900 hover:text-[hsl(var(--primary-app))]"><span class="line-clamp-2">{{ item.title }}</span><ExternalLink class="h-3.5 w-3.5 shrink-0" :stroke-width="1.7" /></a><p class="truncate text-xs text-gray-500">{{ item.url }}</p></div>
                            </div>
                            <div v-if="folderById(item.folder_id)?.can_edit" class="flex shrink-0 gap-1"><button type="button" class="icon-btn h-8 w-8" title="Modifica" @click="openItem(item)"><Pencil class="h-4 w-4" :stroke-width="1.7" /></button><button type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina" @click="remove('item', item.id)"><Trash2 class="h-4 w-4" :stroke-width="1.7" /></button></div>
                        </div>
                        <p v-if="item.description" class="mt-3 line-clamp-2 text-xs text-gray-500">{{ item.description }}</p>
                        <div class="mt-4 flex flex-wrap gap-1.5"><span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-xs font-semibold" :style="folderStyle(folderById(item.folder_id))"><Folder class="h-3.5 w-3.5" :stroke-width="1.7" /> {{ folderById(item.folder_id)?.name }}</span><span v-for="id in item.category_ids" :key="id" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600"><Tag class="h-3 w-3" :stroke-width="1.7" /> {{ categoryName(id) }}</span></div>
                    </article>
                    </div>
                </div>
                </section>
                <section v-if="currentView === 'folders'" class="space-y-6">
                    <div class="flex justify-end"><Link :href="route('favorites.folders.create')" class="btn btn-primary"><Plus class="h-4 w-4" :stroke-width="1.7" /> Aggiungi cartella</Link></div>
                    <div v-if="folderList.length" class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <article v-for="folder in folderList" :key="folder.id" class="content-card project-preview-card group relative flex min-h-[190px] cursor-pointer flex-col border p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg" :style="folderStyle(folder)">
                            <Link :href="route('favorites.folders.show', folder.id)" class="absolute inset-0 z-0 rounded-[inherit]" :aria-label="`Apri cartella ${folder.name}`" />
                            <div class="pointer-events-none relative z-10 flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ folder.name }}</p><p class="mt-1 text-xs opacity-75">{{ items.filter(item => item.folder_id === folder.id).length }} preferiti</p></div><button v-if="folder.can_edit" type="button" class="pointer-events-auto relative z-20 icon-btn h-8 w-8" title="Elimina cartella" @click.stop="remove('folder', folder.id)"><Trash2 class="h-4 w-4" :stroke-width="1.7" /></button></div>
                            <div class="pointer-events-none relative z-10 mt-auto flex flex-wrap items-end gap-1 pt-4"><span class="rounded-full border px-2 py-0.5 text-xs font-medium" :style="folderChipStyle(folder)">{{ folder.visibility === 'shared' ? 'Condivisa' : 'Personale' }}</span><span v-if="folder.group_ids?.length" class="rounded-full border px-2 py-0.5 text-xs font-medium" :style="folderChipStyle(folder)">{{ folder.group_ids.length }} gruppi</span><span v-if="folder.user_ids?.length" class="rounded-full border px-2 py-0.5 text-xs font-medium" :style="folderChipStyle(folder)">{{ folder.user_ids.length }} utenti</span></div>
                        </article>
                    </div>
                    <div v-else class="surface px-6 py-12 text-center text-sm text-gray-500">Nessuna cartella disponibile.</div>
                </section>
                <section v-if="['folder-create', 'folder-detail'].includes(currentView)" class="surface p-5">
                    <div class="mb-6 flex items-center justify-between gap-3"><Link :href="route('favorites.folders')" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-900"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Cartelle</Link><button v-if="selectedFolder?.can_edit" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina cartella" @click="remove('folder', selectedFolder.id)"><Trash2 class="h-4 w-4" :stroke-width="1.7" /></button></div>
                    <h3 v-if="currentView === 'folder-create' || selectedFolder?.can_edit" class="mb-5 text-base font-semibold text-gray-900">{{ currentView === 'folder-create' ? 'Nuova cartella' : 'Modifica cartella' }}</h3>
                    <div v-if="currentView === 'folder-create' || selectedFolder?.can_edit" class="max-w-3xl space-y-5">
                        <div><label class="block text-sm font-medium text-gray-700">Nome</label><input v-model="form.name" class="form-control" maxlength="120" /><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div>
                        <div><label class="block text-sm font-medium text-gray-700">Colore</label><div class="mt-2 flex flex-wrap gap-2"><button v-for="color in folderColors" :key="color" type="button" :class="['h-8 w-8 rounded-full border-2', form.color === color ? 'border-gray-900 ring-2 ring-gray-300' : 'border-white']" :style="{ backgroundColor: color }" :aria-label="`Colore ${color}`" @click="form.color = color" /><label class="relative inline-flex h-8 w-8 cursor-pointer items-center justify-center overflow-hidden rounded-full border-2 border-white shadow-sm ring-1 ring-gray-200" :style="{ backgroundColor: form.color }"><span class="sr-only">Scegli colore personalizzato</span><input v-model="form.color" type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" /></label></div><p v-if="form.errors.color" class="mt-1 text-xs text-red-600">{{ form.errors.color }}</p></div>
                        <div><label class="block text-sm font-medium text-gray-700">Visibilità</label><AppSelect v-if="canManage" v-model="form.visibility" :options="[{ value: 'personal', label: 'Personale' }, { value: 'shared', label: 'Condivisa' }]" /><p v-else class="mt-1 text-sm font-semibold text-gray-700">Personale</p></div>
                        <div v-if="canManage && form.visibility === 'shared'" class="rounded-[var(--radius)] bg-gray-50/80 p-4"><p class="text-sm font-semibold text-gray-900">Condivisione</p><div v-if="groups.length" class="mt-3"><p class="text-xs font-semibold uppercase text-gray-400">Gruppi</p><div class="mt-2 flex flex-wrap gap-2"><button v-for="group in groups" :key="group.id" type="button" :class="['rounded-full border px-3 py-1.5 text-xs font-semibold transition', form.group_ids.includes(group.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleShare('group_ids', group.id)">{{ group.name }}</button></div></div><div class="mt-3"><p class="text-xs font-semibold uppercase text-gray-400">Utenti singoli</p><div class="mt-2 flex flex-wrap gap-2"><button v-for="user in users" :key="user.id" type="button" :class="['rounded-full border px-3 py-1.5 text-xs font-semibold transition', form.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleShare('user_ids', user.id)">{{ user.name }}</button></div></div><p v-if="form.errors.user_ids" class="mt-2 text-xs text-red-600">{{ form.errors.user_ids }}</p></div>
                        <button type="button" class="btn btn-primary" :disabled="form.processing || !form.name.trim()" @click="save">Salva cartella</button>
                    </div>
                    <div v-if="selectedFolder" class="mt-8 border-t border-gray-100 pt-6"><div class="mb-4 flex items-center justify-between"><h3 class="text-base font-semibold text-gray-900">Preferiti</h3><button v-if="selectedFolder.can_edit" type="button" class="btn btn-primary" @click="openItem()"><Plus class="h-4 w-4" :stroke-width="1.7" /> Preferito</button></div><div v-if="items.some(item => item.folder_id === selectedFolder.id)" class="divide-y divide-gray-100"><div v-for="item in items.filter(item => item.folder_id === selectedFolder.id)" :key="item.id" class="flex items-center justify-between gap-3 py-3"><div class="min-w-0"><a :href="item.url" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-gray-900 hover:text-[hsl(var(--primary-app))]">{{ item.title }} <ExternalLink class="inline h-3.5 w-3.5" /></a><p class="mt-1 truncate text-xs text-gray-500">{{ item.url }}</p><div v-if="item.is_featured || item.category_ids?.length" class="mt-2 flex flex-wrap gap-1.5"><span v-if="item.is_featured" class="inline-flex items-center gap-1 rounded-full bg-[hsl(var(--primary-app)/0.10)] px-2 py-0.5 text-xs font-semibold text-[hsl(var(--primary-app-dark))]"><Star class="h-3 w-3 fill-current" /> In evidenza</span><span v-for="id in item.category_ids" :key="id" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ categoryName(id) }}</span></div></div><button v-if="selectedFolder.can_edit" type="button" class="icon-btn h-8 w-8 shrink-0" title="Modifica" @click="openItem(item)"><Pencil class="h-4 w-4" /></button></div></div><p v-else class="text-sm text-gray-500">Nessun preferito in questa cartella.</p></div>
                </section>
                <section v-if="currentView === 'groups'" class="space-y-6">
                    <div v-if="canManage" class="flex justify-end"><Link :href="route('favorites.groups.create')" class="btn btn-primary"><Plus class="h-4 w-4" :stroke-width="1.7" /> Aggiungi gruppo</Link></div>
                    <div v-if="groups.length" class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <article v-for="group in groups" :key="group.id" class="surface relative min-h-[190px] p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]" :class="canManage ? 'cursor-pointer' : ''">
                            <Link v-if="canManage" :href="route('favorites.groups.show', group.id)" class="absolute inset-0 z-0 rounded-[inherit]" :aria-label="`Apri gruppo ${group.name}`" />
                            <div class="pointer-events-none relative z-10 flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900">{{ group.name }}</p><p class="mt-1 text-xs text-gray-500">{{ group.members_count }} membri</p></div><button v-if="canManage" type="button" class="pointer-events-auto icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina gruppo" @click.stop="remove('group', group.id)"><Trash2 class="h-4 w-4" :stroke-width="1.7" /></button></div>
                            <p v-if="group.description" class="relative z-10 mt-4 line-clamp-2 text-sm text-gray-500">{{ group.description }}</p>
                            <div v-if="canManage" class="pointer-events-none relative z-10 mt-4 flex flex-wrap gap-1"><span v-for="id in group.user_ids.slice(0, 3)" :key="id" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ users.find(user => user.id === id)?.name }}</span><span v-if="group.user_ids.length > 3" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">+{{ group.user_ids.length - 3 }}</span></div>
                        </article>
                    </div>
                    <div v-else class="surface px-6 py-12 text-center text-sm text-gray-500">Nessun gruppo disponibile.</div>
                </section>
                <section v-if="['group-create', 'group-detail'].includes(currentView)" class="surface p-5">
                    <div class="mb-6 flex items-center justify-between gap-3"><Link :href="route('favorites.groups')" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-900"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" /> Gruppi</Link><button v-if="selectedGroup" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina gruppo" @click="remove('group', selectedGroup.id)"><Trash2 class="h-4 w-4" :stroke-width="1.7" /></button></div>
                    <h3 class="mb-5 text-base font-semibold text-gray-900">{{ currentView === 'group-create' ? 'Nuovo gruppo' : 'Modifica gruppo' }}</h3>
                    <div class="max-w-3xl space-y-5">
                        <div><label class="block text-sm font-medium text-gray-700">Nome</label><input v-model="groupForm.name" class="form-control" maxlength="120" /><p v-if="groupForm.errors.name" class="mt-1 text-xs text-red-600">{{ groupForm.errors.name }}</p></div>
                        <div><label class="block text-sm font-medium text-gray-700">Descrizione</label><textarea v-model="groupForm.description" class="form-control" rows="4" maxlength="2000" /><p v-if="groupForm.errors.description" class="mt-1 text-xs text-red-600">{{ groupForm.errors.description }}</p></div>
                        <div class="rounded-[var(--radius)] bg-gray-50/80 p-4"><p class="text-sm font-semibold text-gray-900">Utenti</p><div class="mt-3 flex flex-wrap gap-2"><button v-for="user in users" :key="user.id" type="button" :class="['rounded-full border px-3 py-1.5 text-xs font-semibold transition', groupForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleGroupUser(user.id)">{{ user.name }}</button></div><p v-if="groupForm.errors.user_ids" class="mt-2 text-xs text-red-600">{{ groupForm.errors.user_ids }}</p></div>
                        <button type="button" class="btn btn-primary" :disabled="groupForm.processing || !groupForm.name.trim()" @click="saveGroup">Salva gruppo</button>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="editor === 'item'" class="fixed inset-0 z-[5200] grid h-dvh w-dvw place-items-center bg-gray-950/20 px-4 backdrop-blur-[2px]" @click.self="editor = null">
            <form class="surface w-full max-w-xl overflow-hidden" @submit.prevent="save">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5"><h2 class="text-lg font-semibold text-gray-900">{{ editing ? 'Modifica' : 'Nuovo' }} {{ editor === 'folder' ? 'cartella' : 'preferito' }}</h2><button type="button" class="icon-btn h-8 w-8" title="Chiudi" @click="editor = null"><X class="h-4 w-4" :stroke-width="1.7" /></button></div>
                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">
                    <label class="block text-sm font-medium text-gray-700">Titolo<input v-model="form.title" class="form-control mt-2" maxlength="180" required /></label><p v-if="form.errors.title" class="text-xs text-red-600">{{ form.errors.title }}</p><label class="block text-sm font-medium text-gray-700">Indirizzo web<input v-model="form.url" class="form-control mt-2" type="url" placeholder="https://" required /></label><p v-if="form.errors.url" class="text-xs text-red-600">{{ form.errors.url }}</p><div><label class="mb-2 block text-sm font-medium text-gray-700">Cartella</label><AppSelect v-model="form.folder_id" :options="writableFolders.map(folder => ({ value: folder.id, label: folder.name }))" placeholder="Seleziona una cartella" /></div><p v-if="form.errors.folder_id" class="text-xs text-red-600">{{ form.errors.folder_id }}</p>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700">Categorie</label><AppSelect v-model="form.category_ids" :options="categories.map(category => ({ value: category.id, label: category.name }))" placeholder="Seleziona categorie" multiple searchable /><p v-if="form.errors.category_ids" class="mt-1 text-xs text-red-600">{{ form.errors.category_ids }}</p><button v-if="!categoryEditorOpen" type="button" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[hsl(var(--primary-app))]" @click="categoryEditorOpen = true"><Plus class="h-3.5 w-3.5" /> Nuova categoria</button><div v-else class="mt-2 flex items-start gap-2"><div class="min-w-0 flex-1"><input v-model="categoryForm.name" class="form-control mt-0" maxlength="120" placeholder="Nome categoria" @keydown.enter.prevent="saveCategory" /><p v-if="categoryForm.errors.name" class="mt-1 text-xs text-red-600">{{ categoryForm.errors.name }}</p></div><button type="button" class="btn btn-outline" :disabled="categoryForm.processing || !categoryForm.name.trim()" @click="saveCategory">Aggiungi</button><button type="button" class="icon-btn h-9 w-9" title="Annulla" @click="categoryEditorOpen = false; categoryForm.reset(); categoryForm.clearErrors()"><X class="h-4 w-4" /></button></div></div>
                    <label class="flex items-center justify-between gap-4 rounded-[var(--radius-sm)] border border-gray-100 px-3 py-2.5"><span class="flex items-center gap-2 text-sm font-medium text-gray-700"><Star class="h-4 w-4 text-[hsl(var(--primary-app))]" :stroke-width="1.7" /> In evidenza</span><input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" /></label>
                    <label class="block text-sm font-medium text-gray-700">Note<textarea v-model="form.description" class="form-control mt-2 min-h-24" maxlength="2000" /></label>
                </div><div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4"><button type="button" class="btn btn-outline" @click="editor = null">Annulla</button><button type="submit" class="btn btn-primary" :disabled="form.processing || (editor === 'item' && !form.folder_id)">Salva</button></div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.favorite-filter-pill{display:inline-flex;min-height:34px;align-items:center;gap:.45rem;border-radius:var(--radius-sm);border:1px solid rgb(229 231 235 / .9);padding:.45rem .72rem;font-size:.8rem;font-weight:700;box-shadow:inset 0 1px 0 rgb(255 255 255 / .74),0 8px 20px rgb(15 23 42 / .04);transition:transform .18s ease,border-color .18s ease,background-color .18s ease,color .18s ease,box-shadow .18s ease}
.favorite-filter-pill:hover{transform:translateY(-1px)}
.favorite-filter-pill.is-active{box-shadow:inset 0 1px 0 rgb(255 255 255 / .68),0 10px 24px hsl(var(--primary-app) / .14),0 0 0 2px hsl(var(--primary-app) / .18)}
.favorite-filter-pill span{display:inline-flex;min-width:1.35rem;height:1.35rem;align-items:center;justify-content:center;border-radius:999px;background:rgb(255 255 255 / .22);padding:0 .35rem;font-size:.68rem;font-weight:800;color:currentColor;box-shadow:inset 0 0 0 1px rgb(255 255 255 / .22)}
</style>
