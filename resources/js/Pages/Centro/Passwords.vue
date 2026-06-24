<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bold, Building2, Copy, Italic, KeyRound, List, ListOrdered, Pencil, Plus, Quote, ShieldAlert, Trash2, Underline, Users, Vault, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    view: String,
    canManage: Boolean,
    canCreateVaults: Boolean,
    vaults: Array,
    groups: Array,
    items: Array,
    users: Array,
    clients: Array,
    nav: Array,
    selectedVault: Object,
    selectedGroup: Object,
});

const page = usePage();
const search = ref('');
const drawerOpen = ref(false);
const editingItem = ref(null);
const revealItem = ref(null);
const revealedPassword = ref('');
const revealedUsername = ref('');
const revealError = ref('');
const revealMode = ref('view');
const revealCopied = ref('');
const deleteTarget = ref(null);
const deleteText = ref('');
const vaultEditor = ref(null);
const groupEditor = ref(null);
const activeClientId = ref('all');
const noteEditor = ref(null);
const generatorOpen = ref(false);
const generatedPassword = ref('');
const editPasswordLoaded = ref(false);
const editPasswordVisible = ref(false);
const editPasswordError = ref('');
const generator = ref({
    length: 20,
    uppercase: true,
    lowercase: true,
    numbers: true,
    symbols: true,
});

const currentView = computed(() => props.view || 'items');
const manageable = computed(() => props.canManage);
const canCreateVaults = computed(() => props.canCreateVaults);
const activeVaultId = ref('all');
const passwordVaultColors = ['#0B6EF3', '#22C55E', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#111827'];
const navDescriptions = {
    items: 'Credenziali salvate',
    vaults: 'Spazi personali',
    groups: 'Accessi condivisi',
    compromised: 'Da controllare',
};

const navViewMatches = (itemView) => currentView.value === itemView
    || (currentView.value === 'vault-detail' && itemView === 'vaults')
    || (currentView.value === 'vault-create' && itemView === 'vaults')
    || (currentView.value === 'group-detail' && itemView === 'groups')
    || (currentView.value === 'group-create' && itemView === 'groups');

const itemForm = useForm(defaultItemForm());
const vaultForm = useForm({ name: '', description: '', color: '#0B6EF3', visibility: 'personal', user_ids: [], group_ids: [] });
const groupForm = useForm({ name: '', description: '', user_ids: [] });

const visibleItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    return (props.items || []).filter((item) => {
        const vaultMatch = activeVaultId.value === 'all' || item.password_vault_id === activeVaultId.value;
        const clientMatch = activeClientId.value === 'all' || item.client_id === activeClientId.value;
        const textMatch = !q || [item.title, item.username, item.url, item.client_name, item.vault_name]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(q));

        return vaultMatch && clientMatch && textMatch;
    });
});

const compromisedItems = computed(() => (props.items || []).filter((item) => item.risk_flags?.length));
const itemFormErrorMessages = computed(() => Object.values(itemForm.errors || {}).filter(Boolean));

function normalizeHexColor(value, fallback = '#0B6EF3') {
    if (!value) return fallback;
    const color = String(value).trim();
    if (/^#[0-9a-f]{6}$/i.test(color)) return color;
    if (/^#[0-9a-f]{3}$/i.test(color)) {
        return `#${color.slice(1).split('').map((char) => `${char}${char}`).join('')}`;
    }

    return fallback;
}

function isLightColor(value) {
    const color = normalizeHexColor(value);
    const r = parseInt(color.slice(1, 3), 16);
    const g = parseInt(color.slice(3, 5), 16);
    const b = parseInt(color.slice(5, 7), 16);
    const luminance = ((0.2126 * r) + (0.7152 * g) + (0.0722 * b)) / 255;

    return luminance > 0.62;
}

function vaultCardStyle(vault) {
    const backgroundColor = normalizeHexColor(vault?.color, '#0B6EF3');
    const light = isLightColor(backgroundColor);

    return {
        backgroundColor,
        color: light ? '#111827' : '#ffffff',
        borderColor: light ? 'rgba(17, 24, 39, 0.14)' : 'rgba(255, 255, 255, 0.24)',
        boxShadow: light ? '0 18px 40px rgba(28, 42, 73, 0.12)' : '0 18px 40px rgba(15, 23, 42, 0.22)',
    };
}

function vaultMutedStyle(vault) {
    return {
        color: isLightColor(vault?.color || '#0B6EF3') ? 'rgba(17, 24, 39, 0.68)' : 'rgba(255, 255, 255, 0.78)',
    };
}

function vaultChipStyle(vault) {
    const light = isLightColor(vault?.color || '#0B6EF3');

    return {
        color: light ? '#111827' : '#ffffff',
        borderColor: light ? 'rgba(17, 24, 39, 0.14)' : 'rgba(255, 255, 255, 0.28)',
        backgroundColor: light ? 'rgba(255, 255, 255, 0.46)' : 'rgba(255, 255, 255, 0.16)',
    };
}

function passwordVaultBadgeStyle(item) {
    const backgroundColor = normalizeHexColor(item?.vault_color, '#0B6EF3');

    return {
        backgroundColor,
        borderColor: isLightColor(backgroundColor) ? 'rgba(17, 24, 39, 0.14)' : 'rgba(255, 255, 255, 0.20)',
        color: isLightColor(backgroundColor) ? '#111827' : '#ffffff',
    };
}

function defaultItemForm() {
    return {
        password_vault_id: '',
        title: '',
        username: '',
        password: '',
        url: '',
        notes: '',
        client_id: '',
    };
}

function resetItemForm() {
    itemForm.defaults(defaultItemForm());
    itemForm.reset();
    itemForm.clearErrors();
    editingItem.value = null;
    generatedPassword.value = '';
    editPasswordLoaded.value = false;
    editPasswordVisible.value = false;
    editPasswordError.value = '';
}

function openCreateItem() {
    resetItemForm();
    itemForm.password_vault_id = activeVaultId.value !== 'all' ? activeVaultId.value : (props.vaults?.[0]?.id || '');
    if (noteEditor.value) noteEditor.value.innerHTML = '';
    drawerOpen.value = true;
}

function openEditItem(item) {
    editingItem.value = item;
    itemForm.defaults({
        ...defaultItemForm(),
        password_vault_id: item.password_vault_id || '',
        title: item.title || '',
        username: item.username || '',
        url: item.url || '',
        notes: item.notes || '',
        client_id: item.client_id || '',
    });
    itemForm.reset();
    itemForm.has_password = item.has_password;
    editPasswordLoaded.value = false;
    editPasswordVisible.value = false;
    editPasswordError.value = '';
    drawerOpen.value = true;
    setTimeout(() => {
        if (noteEditor.value) noteEditor.value.innerHTML = item.notes || '';
    }, 0);
    loadEditingPassword();
}

function displayPasswordInputValue() {
    if (editingItem.value && !editPasswordLoaded.value && itemForm.has_password !== false) {
        return '••••••••••••';
    }

    return itemForm.password;
}

function handlePasswordFieldFocus(event) {
    if (editingItem.value && !editPasswordLoaded.value) {
        itemForm.password = '';
        editPasswordLoaded.value = true;
        event.target.value = '';
    }
}

async function loadEditingPassword() {
    if (!editingItem.value || !editingItem.value.has_password) {
        editPasswordLoaded.value = true;
        return;
    }

    editPasswordError.value = '';
    try {
        const response = await window.axios.post(route('passwords.items.reveal', editingItem.value.id));
        itemForm.password = response.data.password || '';
        editPasswordLoaded.value = true;
    } catch (error) {
        editPasswordError.value = 'Password non caricata. Verifica di avere accesso a questa credenziale.';
    }
}

function handlePasswordFieldInput(event) {
    if (editingItem.value && !editPasswordLoaded.value) {
        editPasswordLoaded.value = true;
    }
    itemForm.password = event.target.value;
}

function saveItem() {
    itemForm.title = itemForm.title || itemForm.url || itemForm.username || 'Password';
    itemForm.notes = noteEditor.value?.innerHTML || '';
    itemForm.password_vault_id = itemForm.password_vault_id || props.vaults?.[0]?.id || '';
    itemForm.clearErrors();
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            drawerOpen.value = false;
            resetItemForm();
        },
        onError: () => {
            drawerOpen.value = true;
        },
    };

    if (editingItem.value) {
        itemForm.put(route('passwords.items.update', editingItem.value.id), options);
    } else {
        itemForm.post(route('passwords.items.store'), options);
    }
}

function runNoteCommand(command, value = null) {
    noteEditor.value?.focus();
    document.execCommand(command, false, value);
    itemForm.notes = noteEditor.value?.innerHTML || '';
}

function makeGeneratedPassword() {
    const sets = [
        generator.value.uppercase ? 'ABCDEFGHJKLMNPQRSTUVWXYZ' : '',
        generator.value.lowercase ? 'abcdefghijkmnopqrstuvwxyz' : '',
        generator.value.numbers ? '23456789' : '',
        generator.value.symbols ? '!?#$%&*-_' : '',
    ].filter(Boolean);
    const alphabet = sets.join('') || 'abcdefghijkmnopqrstuvwxyz23456789';
    const length = Math.max(8, Math.min(64, Number(generator.value.length) || 20));
    const bytes = new Uint32Array(length);
    window.crypto.getRandomValues(bytes);

    return Array.from(bytes).map((value) => alphabet[value % alphabet.length]).join('');
}

function refreshGeneratedPassword() {
    generatedPassword.value = makeGeneratedPassword();
}

function openGenerator() {
    generatorOpen.value = !generatorOpen.value;
    if (generatorOpen.value) refreshGeneratedPassword();
}

function useGeneratedPassword() {
    if (!generatedPassword.value) refreshGeneratedPassword();
    itemForm.password = generatedPassword.value;
    generatorOpen.value = false;
}

watch(generator, () => {
    if (generatorOpen.value) refreshGeneratedPassword();
}, { deep: true });

function openReveal(item, mode = 'view') {
    revealItem.value = item;
    revealMode.value = mode;
    revealedPassword.value = '';
    revealedUsername.value = '';
    revealError.value = '';
    revealCopied.value = '';
    revealPassword();
}

async function revealPassword() {
    try {
        const response = await window.axios.post(route('passwords.items.reveal', revealItem.value.id));
        revealedPassword.value = response.data.password || '';
        revealedUsername.value = response.data.username || revealItem.value?.username || '';
        if (revealMode.value === 'copy-password') {
            await navigator.clipboard?.writeText(revealedPassword.value);
            revealCopied.value = 'Password copiata.';
        }
    } catch (error) {
        revealError.value = 'Accesso non consentito o password non disponibile.';
    }
}

async function copyPassword() {
    if (revealedPassword.value) {
        await navigator.clipboard?.writeText(revealedPassword.value);
        revealCopied.value = 'Password copiata.';
    }
}

async function copyUsername() {
    if (revealedUsername.value) {
        await navigator.clipboard?.writeText(revealedUsername.value);
        revealCopied.value = 'Nome utente copiato.';
    }
}

function openDelete(target, type) {
    if (page.props.auth?.user?.role === 'superadmin') {
        runDelete(target, type);
        return;
    }
    deleteTarget.value = { target, type };
    deleteText.value = '';
}

function runDelete(target = deleteTarget.value?.target, type = deleteTarget.value?.type) {
    if (!target || !type) return;
    const routeName = type === 'item' ? 'passwords.items.destroy' : (type === 'vault' ? 'passwords.vaults.destroy' : 'passwords.groups.destroy');
    router.delete(route(routeName, target.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
            deleteText.value = '';
        },
    });
}

function resetVaultForm(vault = null) {
    vaultEditor.value = vault;
    vaultForm.defaults({
        name: vault?.name || '',
        description: vault?.description || '',
        color: vault?.color || '#0B6EF3',
        visibility: vault?.visibility || 'personal',
        user_ids: vault?.user_ids || [],
        group_ids: vault?.group_ids || [],
    });
    vaultForm.reset();
    vaultForm.clearErrors();
}

function toggleVaultShare(field, id) {
    const oppositeField = field === 'user_ids' ? 'group_ids' : 'user_ids';
    vaultForm[oppositeField] = [];
    toggleIn(vaultForm, field, id);
}

function saveVault() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!vaultEditor.value?.id) resetVaultForm(null);
        },
    };
    if (vaultEditor.value?.id) {
        vaultForm.put(route('passwords.vaults.update', vaultEditor.value.id), options);
    } else {
        vaultForm.post(route('passwords.vaults.store'), options);
    }
}

function resetGroupForm(group = null) {
    groupEditor.value = group;
    groupForm.defaults({
        name: group?.name || '',
        description: group?.description || '',
        user_ids: group?.user_ids || [],
    });
    groupForm.reset();
    groupForm.clearErrors();
}

function saveGroup() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!groupEditor.value?.id) resetGroupForm(null);
        },
    };
    if (groupEditor.value?.id) {
        groupForm.put(route('passwords.groups.update', groupEditor.value.id), options);
    } else {
        groupForm.post(route('passwords.groups.store'), options);
    }
}

function toggleIn(form, field, id) {
    form[field] = form[field].includes(id)
        ? form[field].filter((itemId) => itemId !== id)
        : [...form[field], id];
}

function userName(id) {
    return (props.users || []).find((user) => user.id === id)?.name || 'Utente';
}

function groupName(id) {
    return (props.groups || []).find((group) => group.id === id)?.name || 'Gruppo';
}

watch(() => vaultForm.visibility, (value) => {
    if (value !== 'shared') {
        vaultForm.user_ids = [];
        vaultForm.group_ids = [];
    }
});

if (props.selectedVault) {
    resetVaultForm(props.selectedVault);
}

if (props.selectedGroup) {
    resetGroupForm(props.selectedGroup);
}
</script>

<template>
    <Head title="Password" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Password</h2>
                <p class="text-sm text-gray-500">Credenziali condivise, casseforti e accessi protetti.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <nav class="grid gap-3 md:grid-cols-4">
                    <Link
                        v-for="item in nav"
                        :key="item.view"
                        :href="route(item.route)"
                        :class="['surface flex items-center justify-between gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]', navViewMatches(item.view) ? 'ring-1 ring-[hsl(var(--primary-app)/0.25)]' : '']"
                    >
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">{{ item.label }}</span>
                            <span class="mt-1 block text-xs text-gray-500">{{ navDescriptions[item.view] }}</span>
                        </span>
                        <Vault v-if="item.view === 'vaults'" class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        <Users v-else-if="item.view === 'groups'" class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        <ShieldAlert v-else-if="item.view === 'compromised'" class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        <KeyRound v-else class="h-5 w-5 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                    </Link>
                </nav>

                <section v-if="currentView === 'items'" class="space-y-5">
                    <div class="surface p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="grid flex-1 items-center gap-3 md:grid-cols-[minmax(0,1fr)_230px_230px]">
                                <input v-model="search" class="form-control h-[38px]" placeholder="Cerca password" />
                                <AppSelect
                                    v-model="activeVaultId"
                                    class="password-filter-control"
                                    :options="[{ value: 'all', label: 'Tutte le casseforti' }, ...vaults.map((vault) => ({ value: vault.id, label: vault.name }))]"
                                />
                                <AppSelect
                                    v-model="activeClientId"
                                    class="password-filter-control"
                                    :options="[{ value: 'all', label: 'Tutti i clienti' }, ...clients.map((client) => ({ value: client.id, label: client.name }))]"
                                    searchable
                                />
                            </div>
                            <button type="button" class="btn btn-primary h-[38px]" @click="openCreateItem">
                                <Plus class="h-4 w-4" :stroke-width="1.7" />
                                Password
                            </button>
                        </div>
                    </div>

                    <div v-if="visibleItems.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="item in visibleItems" :key="item.id" class="surface group/password-card cursor-pointer p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]" @click="openReveal(item)">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                        <KeyRound class="h-5 w-5" :stroke-width="1.7" />
                                    </span>
                                    <div class="min-w-0">
                                        <button type="button" class="truncate text-left text-sm font-semibold text-gray-900 hover:text-[hsl(var(--primary-app))]" @click.stop="openReveal(item)">
                                            {{ item.title || item.url || 'Password' }}
                                        </button>
                                        <p v-if="item.url" class="truncate text-xs text-gray-500">{{ item.url }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button v-if="item.can_edit" type="button" class="icon-btn h-8 w-8" title="Modifica" @click.stop="openEditItem(item)">
                                        <Pencil class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button v-if="item.can_delete" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina" @click.stop="openDelete(item, 'item')">
                                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-xs font-semibold" :style="passwordVaultBadgeStyle(item)">
                                    <Vault class="h-3.5 w-3.5" :stroke-width="1.8" />
                                    {{ item.vault_name || 'Nessuna cassaforte' }}
                                </span>
                                <span v-if="item.client_name" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-200">
                                    <Building2 class="h-3.5 w-3.5" :stroke-width="1.8" />
                                    {{ item.client_name }}
                                </span>
                            </div>
                        </article>
                    </div>
                    <div v-else class="surface px-6 py-12 text-center text-sm text-gray-500">Nessuna password disponibile.</div>
                </section>

                <section v-if="currentView === 'vaults'" class="space-y-6">
                    <div v-if="canCreateVaults" class="flex justify-end">
                        <Link :href="route('passwords.vaults.create')" class="btn btn-primary">
                            <Plus class="h-4 w-4" :stroke-width="1.7" />
                            Aggiungi cassaforte
                        </Link>
                    </div>
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <article
                            v-for="vault in vaults"
                            :key="vault.id"
                            :class="['content-card project-preview-card group relative flex min-h-[190px] flex-col border p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg', vault.can_edit ? 'cursor-pointer' : '']"
                            :style="vaultCardStyle(vault)"
                        >
                            <Link v-if="vault.can_edit" :href="route('passwords.vaults.show', vault.id)" class="absolute inset-0 z-0 rounded-[inherit]" :aria-label="`Apri cassaforte ${vault.name}`" />
                            <div class="pointer-events-none relative z-10 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">
                                        {{ vault.name }}
                                    </p>
                                    <p class="mt-1 text-xs" :style="vaultMutedStyle(vault)">{{ vault.items_count }} password</p>
                                </div>
                                <div v-if="vault.can_edit" class="pointer-events-auto flex gap-1">
                                    <button v-if="manageable" type="button" class="vault-action-button h-8 w-8" title="Elimina cassaforte" @click.stop.prevent="openDelete(vault, 'vault')">
                                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                            </div>
                            <p v-if="vault.description" class="pointer-events-none relative z-10 mt-3 line-clamp-2 text-sm" :style="vaultMutedStyle(vault)">{{ vault.description }}</p>
                            <div class="pointer-events-none relative z-10 mt-auto flex flex-wrap items-end gap-1 pt-4">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-medium" :style="vaultChipStyle(vault)">{{ vault.visibility === 'shared' ? 'Condivisa' : 'Personale' }}</span>
                                <span v-if="vault.group_ids?.length" class="rounded-full border px-2 py-0.5 text-xs font-medium" :style="vaultChipStyle(vault)">{{ vault.group_ids.length }} gruppi</span>
                                <span v-if="vault.user_ids?.length" class="rounded-full border px-2 py-0.5 text-xs font-medium" :style="vaultChipStyle(vault)">{{ vault.user_ids.length }} utenti</span>
                            </div>
                        </article>
                    </div>
                </section>

                <section v-if="['vault-detail', 'vault-create'].includes(currentView)" class="surface p-5">
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <Link :href="route('passwords.vaults')" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-900">
                            <ArrowLeft class="h-4 w-4" :stroke-width="1.7" />
                            Casseforti
                        </Link>
                        <button v-if="manageable && selectedVault" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50 hover:text-red-700" title="Elimina cassaforte" @click="openDelete(selectedVault, 'vault')">
                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                        </button>
                    </div>
                    <h3 class="mb-5 text-base font-semibold text-gray-900">{{ currentView === 'vault-create' ? 'Nuova cassaforte' : 'Modifica cassaforte' }}</h3>
                    <div class="max-w-3xl space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input v-model="vaultForm.name" class="form-control" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                            <textarea v-model="vaultForm.description" class="form-control" rows="4"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Colore</label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="color in passwordVaultColors"
                                    :key="`vault-edit-color-${color}`"
                                    type="button"
                                    :class="['h-8 w-8 rounded-full border-2', vaultForm.color === color ? 'border-gray-900 ring-2 ring-gray-300' : 'border-white']"
                                    :style="{ backgroundColor: color }"
                                    :aria-label="`Colore ${color}`"
                                    @click="vaultForm.color = color"
                                ></button>
                                <label class="relative inline-flex h-8 w-8 cursor-pointer items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white shadow-sm ring-1 ring-gray-200 transition hover:ring-gray-300" :style="{ backgroundColor: vaultForm.color || '#0B6EF3' }">
                                    <span class="sr-only">Scegli colore custom</span>
                                    <input v-model="vaultForm.color" type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                                </label>
                                <input v-model="vaultForm.color" type="text" class="form-control mt-0 w-28 font-mono text-xs" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Visibilità</label>
                            <AppSelect
                                v-if="manageable"
                                v-model="vaultForm.visibility"
                                :options="[{ value: 'personal', label: 'Personale' }, { value: 'shared', label: 'Condivisa' }]"
                            />
                            <p v-else class="mt-1 text-sm font-semibold text-gray-700">{{ vaultForm.visibility === 'shared' ? 'Condivisa' : 'Personale' }}</p>
                        </div>
                        <div v-if="manageable && vaultForm.visibility === 'shared'" class="rounded-[var(--radius)] bg-gray-50/80 p-4">
                            <p class="text-sm font-semibold text-gray-900">Condivisione</p>
                            <div v-if="groups.length" class="mt-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Gruppi</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button v-for="group in groups" :key="group.id" type="button" :class="['rounded-full border px-3 py-1.5 text-xs font-semibold transition', vaultForm.group_ids.includes(group.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleVaultShare('group_ids', group.id)">
                                        {{ group.name }}
                                    </button>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Utenti singoli</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button v-for="user in users" :key="user.id" type="button" :class="['inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition', vaultForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleVaultShare('user_ids', user.id)">
                                        <UserAvatar :user="user" size="xs" />
                                        {{ user.name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" :disabled="vaultForm.processing" @click="saveVault">Salva cassaforte</button>
                    </div>
                </section>

                <section v-if="currentView === 'groups'" class="space-y-6">
                    <div v-if="manageable" class="flex justify-end">
                        <Link :href="route('passwords.groups.create')" class="btn btn-primary">
                            <Plus class="h-4 w-4" :stroke-width="1.7" />
                            Aggiungi gruppo
                        </Link>
                    </div>
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <article
                            v-for="group in groups"
                            :key="group.id"
                            :class="['surface relative min-h-[190px] p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]', manageable ? 'cursor-pointer' : '']"
                        >
                            <Link v-if="manageable" :href="route('passwords.groups.show', group.id)" class="absolute inset-0 z-0 rounded-[inherit]" :aria-label="`Apri gruppo ${group.name}`" />
                            <div class="pointer-events-none relative z-10 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ group.name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ group.members_count }} membri</p>
                                </div>
                                <div v-if="manageable" class="pointer-events-auto flex gap-1">
                                    <button type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50 hover:text-red-700" title="Elimina gruppo" @click.stop="openDelete(group, 'group')">
                                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                            </div>
                            <div class="pointer-events-none relative z-10 mt-4 flex flex-wrap gap-1">
                                <span v-for="id in (group.user_ids || []).slice(0, 3)" :key="id" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ userName(id) }}</span>
                                <span v-if="(group.user_ids || []).length > 3" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">+{{ group.user_ids.length - 3 }}</span>
                            </div>
                        </article>
                    </div>
                </section>

                <section v-if="['group-detail', 'group-create'].includes(currentView)" class="surface p-5">
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <Link :href="route('passwords.groups')" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-900">
                            <ArrowLeft class="h-4 w-4" :stroke-width="1.7" />
                            Gruppi
                        </Link>
                        <button v-if="manageable && selectedGroup" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50 hover:text-red-700" title="Elimina gruppo" @click="openDelete(selectedGroup, 'group')">
                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                        </button>
                    </div>
                    <h3 class="mb-5 text-base font-semibold text-gray-900">{{ currentView === 'group-create' ? 'Nuovo gruppo' : 'Modifica gruppo' }}</h3>
                    <div class="max-w-3xl space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input v-model="groupForm.name" class="form-control" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                            <textarea v-model="groupForm.description" class="form-control" rows="4"></textarea>
                        </div>
                        <div class="rounded-[var(--radius)] bg-gray-50/80 p-4">
                            <p class="text-sm font-semibold text-gray-900">Utenti</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button v-for="user in users" :key="user.id" type="button" :class="['inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition', groupForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleIn(groupForm, 'user_ids', user.id)">
                                    <UserAvatar :user="user" size="xs" />
                                    {{ user.name }}
                                </button>
                            </div>
                            <p v-if="groupForm.errors.user_ids" class="mt-2 text-sm text-red-600">{{ groupForm.errors.user_ids }}</p>
                        </div>
                        <button type="button" class="btn btn-primary" :disabled="groupForm.processing" @click="saveGroup">Salva gruppo</button>
                    </div>
                </section>

                <section v-if="currentView === 'compromised'" class="space-y-4">
                    <article v-for="item in compromisedItems" :key="item.id" class="surface flex items-center justify-between gap-4 p-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-red-50 text-red-600">
                                <ShieldAlert class="h-5 w-5" :stroke-width="1.7" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ item.url || item.title }}</p>
                                <p class="truncate text-xs text-gray-500">{{ item.risk_flags.join(', ') }}</p>
                            </div>
                        </div>
                        <button v-if="item.can_edit" type="button" class="text-sm font-semibold text-[hsl(var(--primary-app))]" @click="openEditItem(item)">Sistema</button>
                    </article>
                    <div v-if="!compromisedItems.length" class="surface px-6 py-12 text-center text-sm text-gray-500">Nessuna password da controllare.</div>
                </section>
            </div>
        </div>

        <div v-if="drawerOpen" class="fixed inset-0 z-[5000] flex justify-end bg-black/10" @click.self="drawerOpen = false">
            <section class="h-full w-full max-w-xl overflow-y-auto rounded-l-[var(--radius)] bg-white p-6 shadow-[0_24px_80px_rgba(15,23,42,0.22)] transition">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingItem ? 'Modifica password' : 'Nuova password' }}</h3>
                    <button type="button" class="icon-btn" @click="drawerOpen = false"><X class="h-4 w-4" /></button>
                </div>

                <div class="mt-7 space-y-5">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Titolo</span>
                        <input v-model="itemForm.title" class="form-control" name="centro_password_item_title" autocomplete="off" placeholder="Es. Accesso Aruba, Gmail cliente..." />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Nome utente</span>
                        <input v-model="itemForm.username" class="form-control" name="centro_password_item_username" autocomplete="off" />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Password</span>
                        <div class="flex gap-2">
                            <input
                                :value="displayPasswordInputValue()"
                                :type="editPasswordVisible ? 'text' : 'password'"
                                class="form-control"
                                name="centro_password_item_secret"
                                autocomplete="new-password"
                                :placeholder="editingItem ? 'Password salvata' : ''"
                                @focus="handlePasswordFieldFocus"
                                @input="handlePasswordFieldInput"
                            />
                            <button v-if="editingItem" type="button" class="btn btn-outline h-[38px] shrink-0 px-3" @click="editPasswordVisible = !editPasswordVisible">
                                {{ editPasswordVisible ? 'Nascondi' : 'Vedi' }}
                            </button>
                            <button type="button" class="btn btn-outline h-[38px] shrink-0" @click="openGenerator">Genera</button>
                        </div>
                        <p v-if="editPasswordError" class="mt-2 text-sm text-red-600">{{ editPasswordError }}</p>
                        <div v-if="generatorOpen" class="rounded-[var(--radius)] bg-gray-50/80 p-4">
                            <div class="mb-4 flex items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 bg-white px-3 py-2">
                                <code class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-800">{{ generatedPassword }}</code>
                                <button type="button" class="btn btn-primary h-[34px] shrink-0 px-4" @click="useGeneratedPassword">Usa</button>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-gray-800">Lunghezza</span>
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 shadow-sm">{{ generator.length }} caratteri</span>
                                </div>
                                <input
                                    v-model.number="generator.length"
                                    type="range"
                                    min="8"
                                    max="64"
                                    step="1"
                                    class="password-length-slider w-full"
                                />
                            </div>
                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input v-model="generator.uppercase" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                                    Maiuscole
                                </label>
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input v-model="generator.lowercase" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                                    Minuscole
                                </label>
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input v-model="generator.numbers" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                                    Numeri
                                </label>
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <input v-model="generator.symbols" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                                    Simboli
                                </label>
                            </div>
                        </div>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Cassaforte</span>
                        <AppSelect
                            v-model="itemForm.password_vault_id"
                            :options="vaults.map((vault) => ({ value: vault.id, label: vault.name }))"
                            searchable
                        />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Sito web</span>
                        <input v-model="itemForm.url" class="form-control" name="centro_password_item_url" autocomplete="off" />
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Cliente</span>
                        <AppSelect v-model="itemForm.client_id" :options="[{ value: '', label: 'Nessuno' }, ...clients.map((client) => ({ value: client.id, label: client.name }))]" searchable />
                    </label>
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Note</span>
                        <div class="overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white">
                            <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 px-2 py-2">
                                <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runNoteCommand('bold')">
                                    <Bold class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runNoteCommand('italic')">
                                    <Italic class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runNoteCommand('underline')">
                                    <Underline class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                                <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runNoteCommand('insertUnorderedList')">
                                    <List class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runNoteCommand('insertOrderedList')">
                                    <ListOrdered class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Citazione" @mousedown.prevent @click="runNoteCommand('formatBlock', 'blockquote')">
                                    <Quote class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                            </div>
                            <div
                                ref="noteEditor"
                                contenteditable="true"
                                class="min-h-40 px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)] wysiwyg-content"
                                data-placeholder="Scrivi note..."
                                @input="itemForm.notes = noteEditor?.innerHTML || ''"
                            ></div>
                        </div>
                    </div>
                </div>

                <div v-if="itemFormErrorMessages.length" class="mt-5 rounded-[var(--radius-sm)] border border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    <p v-for="message in itemFormErrorMessages" :key="message">{{ message }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="drawerOpen = false">Annulla</button>
                    <button type="button" class="btn btn-primary" :disabled="itemForm.processing" @click="saveItem">Salva</button>
                </div>
            </section>
        </div>

        <div v-if="revealItem" class="fixed left-0 top-0 z-[5200] grid h-dvh w-dvw place-items-center bg-black/10 px-4" @click.self="revealItem = null">
            <section class="max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900">{{ revealMode === 'copy-password' ? 'Copia password' : 'Dettaglio password' }}</h3>
                    <button type="button" class="icon-btn" @click="revealItem = null"><X class="h-4 w-4" /></button>
                </div>
                <div class="mt-4 rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/80 p-3">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ revealItem.title || revealItem.url || 'Password' }}</p>
                    <p v-if="revealItem.vault_name || revealItem.client_name" class="mt-1 text-xs text-gray-500">
                        <span v-if="revealItem.vault_name">{{ revealItem.vault_name }}</span>
                        <span v-if="revealItem.vault_name && revealItem.client_name"> · </span>
                        <span v-if="revealItem.client_name">{{ revealItem.client_name }}</span>
                    </p>
                </div>
                <p v-if="!revealedPassword && !revealError" class="mt-4 text-sm text-gray-500">Caricamento credenziale...</p>
                <p v-if="revealError" class="mt-3 text-sm text-red-600">{{ revealError }}</p>
                <p v-if="revealCopied" class="mt-3 text-sm font-semibold text-[hsl(var(--primary-app))]">{{ revealCopied }}</p>
                <div v-if="revealedPassword" class="mt-4 space-y-3">
                    <button type="button" class="password-reveal-row group/reveal-field w-full text-left" :disabled="!revealedUsername" @click="copyUsername">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Nome utente</span>
                        <div class="mt-1 flex items-center gap-2">
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900">{{ revealedUsername || 'Non inserito' }}</p>
                            <span v-if="revealedUsername" class="field-copy-button" title="Copia nome utente">
                                <Copy class="h-4 w-4" :stroke-width="1.7" />
                            </span>
                        </div>
                    </button>
                    <button type="button" class="password-reveal-row group/reveal-field w-full text-left" @click="copyPassword">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Password</span>
                        <div class="mt-1 flex items-center gap-2">
                            <p class="min-w-0 flex-1 break-all font-mono text-sm text-gray-900">{{ revealedPassword }}</p>
                            <span class="field-copy-button" title="Copia password">
                                <Copy class="h-4 w-4" :stroke-width="1.7" />
                            </span>
                        </div>
                    </button>
                </div>
            </section>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 z-[5300] flex items-center justify-center bg-transparent px-4" @click.self="deleteTarget = null">
            <section class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <h3 class="text-base font-semibold text-gray-900">Eliminare?</h3>
                <p class="mt-2 text-sm text-gray-500">Digita <span class="font-mono font-semibold">ELIMINA</span> per confermare.</p>
                <input v-model="deleteText" class="form-control mt-4 font-mono" placeholder="ELIMINA" />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="deleteTarget = null">Annulla</button>
                    <button type="button" class="btn btn-danger" :disabled="deleteText !== 'ELIMINA'" @click="runDelete">Elimina</button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.password-filter-control :deep(.form-control) {
    height: 38px;
    min-height: 38px;
}

.password-length-slider {
    accent-color: hsl(var(--primary-app));
    cursor: pointer;
}

.vault-action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    color: currentColor;
    opacity: 0.78;
    transition: background-color 0.18s ease, opacity 0.18s ease, transform 0.18s ease;
}

.vault-action-button:hover {
    background: rgba(255, 255, 255, 0.18);
    opacity: 1;
    transform: translateY(-1px);
}

.password-reveal-row {
    border: 1px solid rgb(229 231 235 / 0.9);
    border-radius: var(--radius-sm);
    background: rgb(249 250 251 / 0.85);
    padding: 0.85rem 0.95rem;
    cursor: pointer;
    transition: border-color 0.18s ease, background-color 0.18s ease, transform 0.18s ease;
}

.password-reveal-row:hover:not(:disabled) {
    border-color: hsl(var(--primary-app) / 0.28);
    background: hsl(var(--primary-app) / 0.055);
    transform: translateY(-1px);
}

.password-reveal-row:disabled {
    cursor: default;
}

.field-copy-button {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    color: hsl(var(--primary-app));
    opacity: 0;
    transition: opacity 0.18s ease, background-color 0.18s ease, transform 0.18s ease;
}

.password-reveal-row:hover .field-copy-button,
.field-copy-button:focus-visible {
    opacity: 1;
}

.field-copy-button:hover {
    background: hsl(var(--primary-app) / 0.10);
    transform: translateY(-1px);
}
</style>
