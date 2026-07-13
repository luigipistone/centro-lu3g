<script setup>
import AppSelect from '@/Components/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Check, ChevronDown, ChevronLeft, Folder, PackageOpen, Pencil, Plus, Trash2, X } from '@lucide/vue';

const props = defineProps({
    folders: Array,
    modules: Array,
    agentOptions: Array,
    moduleStatusOptions: Array,
});

const selectedFolderId = ref(null);
const folderModalOpen = ref(false);
const moduleDrawerOpen = ref(false);
const editingFolder = ref(null);
const editingModule = ref(null);
const deleteTarget = ref(null);
const deleteText = ref('');
const requiredInputsText = ref('');
const expandedModuleIds = ref([]);

const folderForm = useForm({
    name: '',
    description: '',
    color: '#2563eb',
});

const moduleForm = useForm({
    admin_module_folder_id: '',
    parent_module_id: '',
    name: '',
    category: 'Decisione',
    version: '1.0',
    status: 'draft',
    description: '',
    available_components: '',
    decision_criteria: '',
    required_inputs: [],
    dependency_module_ids: [],
    rules: '',
    output: '',
    allowed_agents: [],
    active: true,
});

const categoryOptions = [
    { value: 'Standard', label: 'Standard' },
    { value: 'Decisione', label: 'Decisione' },
    { value: 'Procedura', label: 'Procedura' },
    { value: 'Analisi', label: 'Analisi' },
    { value: 'Checklist', label: 'Checklist' },
    { value: 'Operativo', label: 'Operativo' },
];

const folderOptions = computed(() => (props.folders || []).map((folder) => ({
    value: folder.id,
    label: folder.name,
})));

const moduleDependencyOptions = computed(() => (props.modules || [])
    .filter((module) => module.id !== editingModule.value?.id)
    .map((module) => ({
        value: module.id,
        label: `${module.name}${module.folder_name ? ` · ${module.folder_name}` : ''}`,
    })));

const moduleParentOptions = computed(() => [
    { value: '', label: 'Nessun modulo padre' },
    ...(props.modules || [])
        .filter((module) => module.id !== editingModule.value?.id)
        .map((module) => ({
            value: module.id,
            label: `${module.name}${module.folder_name ? ` · ${module.folder_name}` : ''}`,
        })),
]);

const selectedFolder = computed(() => (props.folders || []).find((folder) => folder.id === selectedFolderId.value) || null);
const filteredModules = computed(() => {
    if (!selectedFolder.value) return [];

    return (props.modules || []).filter((module) => module.admin_module_folder_id === selectedFolderId.value);
});

const childModulesByParent = computed(() => {
    const grouped = {};
    const visibleModuleIds = new Set(filteredModules.value.map((module) => module.id));

    filteredModules.value.forEach((module) => {
        if (!module.parent_module_id || !visibleModuleIds.has(module.parent_module_id)) return;
        grouped[module.parent_module_id] = [...(grouped[module.parent_module_id] || []), module];
    });

    Object.keys(grouped).forEach((parentId) => {
        grouped[parentId] = grouped[parentId].sort((first, second) => new Date(first.created_at || 0) - new Date(second.created_at || 0));
    });

    return grouped;
});

const visibleModules = computed(() => {
    const childIds = new Set();
    Object.values(childModulesByParent.value).forEach((children) => {
        children.forEach((module) => childIds.add(module.id));
    });

    return filteredModules.value.filter((module) => !childIds.has(module.id));
});

watch(
    () => props.folders,
    (folders) => {
        if (selectedFolderId.value && !folders?.some((folder) => folder.id === selectedFolderId.value)) {
            selectedFolderId.value = null;
        }
    },
);

function contrastColor(hex) {
    const value = String(hex || '#2563eb').replace('#', '');
    const full = value.length === 3 ? value.split('').map((part) => part + part).join('') : value;
    const int = Number.parseInt(full, 16);
    if (Number.isNaN(int)) return '#ffffff';

    const red = (int >> 16) & 255;
    const green = (int >> 8) & 255;
    const blue = int & 255;
    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

    return luminance > 0.62 ? '#111827' : '#ffffff';
}

function folderStyle(folder) {
    const background = folder?.color || '#2563eb';
    return {
        backgroundColor: background,
        color: contrastColor(background),
    };
}

function folderCssVars(folder) {
    const background = folder?.color || '#2563eb';

    return {
        '--folder-color': background,
        '--folder-text': contrastColor(background),
    };
}

function statusLabel(status) {
    return (props.moduleStatusOptions || []).find((option) => option.value === status)?.label || 'Bozza';
}

function parseLines(value) {
    return String(value || '')
        .split(/\r\n|\r|\n/)
        .map((line) => line.trim().replace(/^[-•]\s*/, ''))
        .filter(Boolean);
}

function openFolder(folder) {
    selectedFolderId.value = folder.id;
}

function closeFolder() {
    selectedFolderId.value = null;
    expandedModuleIds.value = [];
}

function openFolderModal(folder = null) {
    editingFolder.value = folder;
    folderForm.clearErrors();
    folderForm.defaults({
        name: folder?.name || '',
        description: folder?.description || '',
        color: folder?.color || '#2563eb',
    });
    folderForm.reset();
    folderModalOpen.value = true;
}

function closeFolderModal() {
    folderModalOpen.value = false;
    editingFolder.value = null;
    folderForm.reset();
}

function saveFolder() {
    const options = {
        preserveScroll: true,
        onSuccess: closeFolderModal,
    };

    if (editingFolder.value) {
        folderForm.put(route('modules.folders.update', editingFolder.value.id), options);
        return;
    }

    folderForm.post(route('modules.folders.store'), options);
}

function openModuleDrawer(module = null) {
    editingModule.value = module;
    moduleForm.clearErrors();
    const fallbackFolderId = selectedFolderId.value
        ? selectedFolderId.value
        : props.folders?.[0]?.id || '';

    moduleForm.defaults({
        admin_module_folder_id: module?.admin_module_folder_id || fallbackFolderId,
        parent_module_id: module?.parent_module_id || '',
        name: module?.name || '',
        category: module?.category || 'Decisione',
        version: module?.version || '1.0',
        status: module?.status || 'draft',
        description: module?.description || '',
        available_components: module?.available_components || '',
        decision_criteria: module?.decision_criteria || '',
        required_inputs: module?.required_inputs || [],
        dependency_module_ids: module?.dependency_module_ids || [],
        rules: module?.rules || '',
        output: module?.output || '',
        allowed_agents: module?.allowed_agents || [],
        active: true,
    });
    moduleForm.reset();
    requiredInputsText.value = (module?.required_inputs || []).join('\n');
    moduleDrawerOpen.value = true;
}

function closeModuleDrawer() {
    moduleDrawerOpen.value = false;
    editingModule.value = null;
    moduleForm.reset();
    requiredInputsText.value = '';
}

function toggleAgent(agent) {
    const next = new Set(moduleForm.allowed_agents || []);
    if (next.has(agent)) next.delete(agent);
    else next.add(agent);
    moduleForm.allowed_agents = Array.from(next);
}

function childModulesFor(moduleId) {
    return childModulesByParent.value[moduleId] || [];
}

function isModuleExpanded(moduleId) {
    return expandedModuleIds.value.includes(moduleId);
}

function toggleModuleChildren(moduleId) {
    if (isModuleExpanded(moduleId)) {
        expandedModuleIds.value = expandedModuleIds.value.filter((id) => id !== moduleId);
        return;
    }

    expandedModuleIds.value = [...expandedModuleIds.value, moduleId];
}

function saveModule() {
    moduleForm.required_inputs = parseLines(requiredInputsText.value);

    const options = {
        preserveScroll: true,
        onSuccess: closeModuleDrawer,
    };

    if (editingModule.value) {
        moduleForm.put(route('modules.items.update', editingModule.value.id), options);
        return;
    }

    moduleForm.post(route('modules.items.store'), options);
}

function requestDelete(type, item) {
    deleteTarget.value = { type, item };
    deleteText.value = '';
}

function closeDelete() {
    deleteTarget.value = null;
    deleteText.value = '';
}

function confirmDelete() {
    if (!deleteTarget.value || deleteText.value !== 'ELIMINA') return;

    const routeName = deleteTarget.value.type === 'folder'
        ? route('modules.folders.destroy', deleteTarget.value.item.id)
        : route('modules.items.destroy', deleteTarget.value.item.id);

    router.delete(routeName, {
        preserveScroll: true,
        onSuccess: closeDelete,
    });
}
</script>

<template>
    <Head title="Moduli" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Moduli</h2>
                    <p class="mt-1 text-sm text-gray-500">Cartelle operative con regole, input richiesti, output e agenti abilitati.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline" @click="openFolderModal()">
                        <Folder class="h-4 w-4" :stroke-width="1.7" />
                        Nuova cartella
                    </button>
                    <button type="button" class="btn btn-primary" :disabled="!folders?.length" @click="openModuleDrawer()">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo modulo
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <section v-if="!selectedFolder" class="surface p-5">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Cartelle</h3>
                            <p class="mt-1 text-sm text-gray-500">Apri una cartella per vedere e gestire i moduli contenuti.</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-500">
                            {{ folders?.length || 0 }} cartelle · {{ modules?.length || 0 }} moduli
                        </span>
                    </div>

                    <div v-if="folders?.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <article
                            v-for="folder in folders"
                            :key="folder.id"
                            class="module-folder-card group"
                            @click="openFolder(folder)"
                        >
                            <div class="module-folder-shape" :style="folderCssVars(folder)">
                                <div class="module-folder-tab"></div>
                                <div class="module-folder-body">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 pr-16">
                                            <h3 class="line-clamp-2 text-base font-semibold leading-5 text-current">{{ folder.name }}</h3>
                                            <p v-if="folder.description" class="mt-2 line-clamp-2 text-sm leading-5 text-current opacity-[0.72]">{{ folder.description }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-auto flex items-center justify-between gap-3 pt-6">
                                        <span class="text-xs font-semibold text-current opacity-70">{{ folder.modules_count || 0 }} moduli</span>
                                        <span class="text-xs font-semibold text-current opacity-0 transition group-hover:opacity-80">Apri</span>
                                    </div>
                                </div>
                            </div>

                            <div class="absolute right-6 top-12 z-10 flex items-center gap-1">
                                <button type="button" class="icon-btn h-8 w-8 bg-white/70" title="Modifica" @click.stop="openFolderModal(folder)">
                                    <Pencil class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8 bg-white/70 text-red-600 hover:bg-red-50" title="Elimina" @click.stop="requestDelete('folder', folder)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="rounded-[var(--radius)] border border-dashed border-gray-200 bg-gray-50 p-8 text-center">
                        <Folder class="mx-auto h-8 w-8 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        <h3 class="mt-3 text-base font-semibold text-gray-900">Nessuna cartella</h3>
                        <p class="mt-1 text-sm text-gray-500">Crea la prima cartella per organizzare i moduli.</p>
                    </div>
                </section>

                <section v-else class="surface p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <button type="button" class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]" @click="closeFolder">
                                <ChevronLeft class="h-3.5 w-3.5" :stroke-width="1.8" />
                                Cartelle
                            </button>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                {{ selectedFolder.name }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">{{ filteredModules.length }} moduli in questa cartella.</p>
                        </div>
                    </div>

                    <div v-if="visibleModules.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="module in visibleModules"
                            :key="module.id"
                            class="content-card group relative min-h-32 cursor-pointer rounded-[var(--radius)] border border-gray-200 p-4 transition duration-200 hover:-translate-y-0.5 hover:border-[hsl(var(--primary-app)/0.28)] hover:shadow-lg"
                            @click="openModuleDrawer(module)"
                        >
                            <div class="flex h-full flex-col justify-between gap-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="line-clamp-2 text-base font-semibold leading-6 text-gray-900">{{ module.name }}</h3>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <span v-if="module.category" class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ module.category }}</span>
                                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ statusLabel(module.status) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button
                                            v-if="childModulesFor(module.id).length"
                                            type="button"
                                            class="icon-btn h-8 w-8"
                                            :title="isModuleExpanded(module.id) ? 'Nascondi moduli figli' : 'Mostra moduli figli'"
                                            @click.stop="toggleModuleChildren(module.id)"
                                        >
                                            <ChevronDown :class="['h-4 w-4 transition', isModuleExpanded(module.id) ? 'rotate-180' : '']" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina" @click.stop="requestDelete('module', module)">
                                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-xs font-semibold text-gray-400">
                                    <span v-if="module.version">Versione {{ module.version }}</span>
                                    <span v-else>Versione 1.0</span>
                                    <span v-if="childModulesFor(module.id).length">{{ childModulesFor(module.id).length }} figli</span>
                                    <span v-else-if="module.dependency_modules?.length">{{ module.dependency_modules.length }} dipendenze</span>
                                    <span v-else>Nessuna dipendenza</span>
                                </div>
                                <div v-if="isModuleExpanded(module.id) && childModulesFor(module.id).length" class="space-y-1.5 border-t border-gray-100 pt-3">
                                    <button
                                        v-for="child in childModulesFor(module.id)"
                                        :key="child.id"
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 rounded-[var(--radius-sm)] px-2 py-1.5 text-left text-sm font-semibold text-gray-700 transition hover:bg-gray-50 hover:text-[hsl(var(--primary-app))]"
                                        @click.stop="openModuleDrawer(child)"
                                    >
                                        <span class="truncate">{{ child.name }}</span>
                                        <span class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">{{ statusLabel(child.status) }}</span>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="rounded-[var(--radius)] border border-dashed border-gray-200 bg-gray-50 p-8 text-center">
                        <PackageOpen class="mx-auto h-8 w-8 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        <h3 class="mt-3 text-base font-semibold text-gray-900">Nessun modulo</h3>
                        <p class="mt-1 text-sm text-gray-500">Aggiungi un modulo con regole, output e agenti abilitati.</p>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="folderModalOpen" class="fixed inset-0 z-[5200] grid place-items-center bg-gray-950/20 px-4 backdrop-blur-[2px]" @click.self="closeFolderModal">
            <section class="w-full max-w-xl rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ editingFolder ? 'Modifica cartella' : 'Nuova cartella' }}</h3>
                        <p class="mt-1 text-sm text-gray-500">Organizza i moduli per area o processo.</p>
                    </div>
                    <button type="button" class="icon-btn h-9 w-9" @click="closeFolderModal">
                        <X class="h-5 w-5" :stroke-width="1.7" />
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome</label>
                        <input v-model="folderForm.name" class="form-control" />
                        <div v-if="folderForm.errors.name" class="mt-1 text-sm text-red-600">{{ folderForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                        <textarea v-model="folderForm.description" rows="3" class="form-control"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Colore</label>
                        <div class="mt-1 flex items-center gap-3">
                            <input v-model="folderForm.color" type="color" class="h-10 w-16 cursor-pointer rounded-[var(--radius-sm)] border border-gray-200 bg-white p-1" />
                            <input v-model="folderForm.color" class="form-control font-mono" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="closeFolderModal">Annulla</button>
                    <button type="button" class="btn btn-primary" :disabled="folderForm.processing" @click="saveFolder">
                        {{ editingFolder ? 'Salva' : 'Crea cartella' }}
                    </button>
                </div>
            </section>
        </div>

        <div v-if="moduleDrawerOpen" class="fixed inset-0 z-[5200] bg-gray-950/20 backdrop-blur-[2px]" @click.self="closeModuleDrawer">
            <aside class="ml-auto flex h-dvh w-full max-w-3xl flex-col overflow-hidden bg-white shadow-[0_24px_90px_rgba(15,23,42,0.25)]">
                <header class="flex items-start justify-between gap-4 border-b border-gray-100 p-6">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">{{ editingModule ? 'Modifica modulo' : 'Nuovo modulo' }}</h3>
                        <p class="mt-1 text-sm text-gray-500">Definisci quando usarlo, quali dati servono e quale output deve produrre.</p>
                    </div>
                    <button type="button" class="icon-btn h-10 w-10" @click="closeModuleDrawer">
                        <X class="h-5 w-5" :stroke-width="1.7" />
                    </button>
                </header>

                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input v-model="moduleForm.name" class="form-control" placeholder="Quando proporre un nuovo sito" />
                            <div v-if="moduleForm.errors.name" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cartella</label>
                            <AppSelect v-model="moduleForm.admin_module_folder_id" :options="folderOptions" placeholder="Seleziona cartella" />
                            <div v-if="moduleForm.errors.admin_module_folder_id" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.admin_module_folder_id }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Modulo padre</label>
                            <AppSelect v-model="moduleForm.parent_module_id" :options="moduleParentOptions" searchable placeholder="Nessun modulo padre" />
                            <div v-if="moduleForm.errors.parent_module_id" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.parent_module_id }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Categoria</label>
                            <AppSelect v-model="moduleForm.category" :options="categoryOptions" placeholder="Categoria" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Versione</label>
                            <input v-model="moduleForm.version" class="form-control" placeholder="1.0" />
                            <div v-if="moduleForm.errors.version" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.version }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stato</label>
                            <AppSelect v-model="moduleForm.status" :options="moduleStatusOptions" placeholder="Stato" />
                            <div v-if="moduleForm.errors.status" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.status }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                        <textarea v-model="moduleForm.description" rows="3" class="form-control" placeholder="Regole per capire quando e' necessario rifare il sito."></textarea>
                    </div>

                    <div v-if="moduleForm.category === 'Standard'" class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Componenti disponibili</label>
                            <textarea v-model="moduleForm.available_components" rows="5" class="form-control" placeholder="Elenca i componenti disponibili per questo standard."></textarea>
                            <div v-if="moduleForm.errors.available_components" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.available_components }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Criteri decisionali</label>
                            <textarea v-model="moduleForm.decision_criteria" rows="5" class="form-control" placeholder="Indica i criteri da usare per decidere."></textarea>
                            <div v-if="moduleForm.errors.decision_criteria" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.decision_criteria }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Input richiesti</label>
                        <textarea v-model="requiredInputsText" rows="5" class="form-control font-medium" placeholder="URL&#10;Settore&#10;Obiettivo&#10;Budget&#10;Competitor"></textarea>
                        <p class="mt-1 text-xs text-gray-500">Inserisci un input per riga.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dipendenze</label>
                        <AppSelect
                            v-if="moduleDependencyOptions.length"
                            v-model="moduleForm.dependency_module_ids"
                            :options="moduleDependencyOptions"
                            multiple
                            searchable
                            placeholder="Seleziona dipendenze"
                        />
                        <p v-else class="mt-2 rounded-[var(--radius-sm)] bg-gray-50 px-3 py-2 text-sm text-gray-500">
                            Nessun altro modulo disponibile.
                        </p>
                        <div v-if="moduleForm.errors.dependency_module_ids" class="mt-1 text-sm text-red-600">{{ moduleForm.errors.dependency_module_ids }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Regole</label>
                        <textarea v-model="moduleForm.rules" rows="7" class="form-control" placeholder="Se il sito ha piu' di 5 anni -> considera il rifacimento."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Output</label>
                        <textarea v-model="moduleForm.output" rows="5" class="form-control" placeholder="Consiglia: rifacimento completo, restyling oppure nessun intervento."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Agenti che possono usarlo</label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="agent in agentOptions"
                                :key="agent"
                                type="button"
                                :class="[
                                    'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition hover:-translate-y-0.5',
                                    moduleForm.allowed_agents?.includes(agent)
                                        ? 'border-[hsl(var(--primary-app)/0.35)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-[hsl(var(--primary-app)/0.25)]',
                                ]"
                                @click="toggleAgent(agent)"
                            >
                                <Check :class="['h-3.5 w-3.5', moduleForm.allowed_agents?.includes(agent) ? 'opacity-100' : 'opacity-0']" :stroke-width="2" />
                                {{ agent }}
                            </button>
                        </div>
                    </div>
                </div>

                <footer class="flex justify-end gap-2 border-t border-gray-100 p-5">
                    <button type="button" class="btn btn-outline" @click="closeModuleDrawer">Annulla</button>
                    <button type="button" class="btn btn-primary" :disabled="moduleForm.processing" @click="saveModule">
                        {{ editingModule ? 'Salva modulo' : 'Crea modulo' }}
                    </button>
                </footer>
            </aside>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 z-[5400] flex items-center justify-center bg-transparent px-4" @click.self="closeDelete">
            <section class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <h3 class="text-base font-semibold text-gray-900">Eliminare {{ deleteTarget.type === 'folder' ? 'cartella' : 'modulo' }}?</h3>
                <p class="mt-2 text-sm text-gray-500">
                    Digita <span class="font-mono font-semibold">ELIMINA</span> per confermare. L'azione non puo' essere annullata.
                </p>
                <input v-model="deleteText" class="form-control mt-4 font-mono" placeholder="ELIMINA" />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="closeDelete">Annulla</button>
                    <button type="button" class="btn btn-danger" :disabled="deleteText !== 'ELIMINA'" @click="confirmDelete">Elimina</button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.module-folder-card {
    position: relative;
    min-height: 178px;
    cursor: pointer;
}

.module-folder-shape {
    position: relative;
    min-height: 178px;
    padding-top: 18px;
    filter: drop-shadow(0 18px 34px rgba(15, 23, 42, 0.10));
    perspective: 900px;
    transition: filter 180ms ease;
}

.module-folder-card:hover .module-folder-shape {
    filter: drop-shadow(0 22px 44px rgba(37, 99, 235, 0.16));
}

.module-folder-tab {
    position: absolute;
    left: 18px;
    top: 0;
    width: 42%;
    height: 38px;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    background: var(--folder-color);
    transform-origin: left bottom;
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), filter 220ms ease;
}

.module-folder-card:hover .module-folder-tab {
    filter: brightness(1.04);
    transform: translateY(-9px) rotateX(42deg) skewX(-2deg);
}

.module-folder-body {
    position: relative;
    display: flex;
    min-height: 160px;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--folder-color) 80%, white);
    border-radius: var(--radius);
    background: var(--folder-color);
    color: var(--folder-text);
    padding: 22px;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.18),
        0 1px 0 rgba(255, 255, 255, 0.28);
    transform-origin: center bottom;
    transition: filter 220ms ease, transform 260ms cubic-bezier(0.22, 1, 0.36, 1);
}

.module-folder-card:hover .module-folder-body {
    filter: brightness(1.035);
    transform: translateY(2px);
}

.module-folder-body::before {
    position: absolute;
    inset: 0 0 auto;
    height: 14px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.18), transparent);
    content: "";
    opacity: 0;
    transition: opacity 220ms ease;
}

.module-folder-card:hover .module-folder-body::before {
    opacity: 1;
}

html.dark .module-folder-body {
    border-color: color-mix(in srgb, var(--folder-color) 72%, rgb(15, 23, 42));
    background: var(--folder-color);
}
</style>
