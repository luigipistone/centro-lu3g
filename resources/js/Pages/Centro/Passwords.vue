<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { dateTimeIt } from '@/utils/formatters';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Eye,
    EyeOff,
    KeyRound,
    Lock,
    Plus,
    RefreshCw,
    Search,
    ShieldCheck,
    Star,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    canManage: Boolean,
    vaults: Array,
    groups: Array,
    items: Array,
    users: Array,
    clients: Array,
    projects: Array,
});

const page = usePage();
const search = ref('');
const selectedVault = ref('all');
const selectedItem = ref(null);
const itemPanelOpen = ref(false);
const revealItem = ref(null);
const revealPassword = ref('');
const revealAccountPassword = ref('');
const revealError = ref('');
const deleteTarget = ref(null);
const deleteText = ref('');
const groupPanelOpen = ref(false);
const vaultPanelOpen = ref(false);

const itemForm = useForm(defaultItemForm());
const vaultForm = useForm({ name: '', description: '', color: '#0B6EF3' });
const groupForm = useForm({ name: '', description: '', user_ids: [] });

const filteredItems = computed(() => {
    const query = search.value.trim().toLowerCase();
    return (props.items || []).filter((item) => {
        const inVault = selectedVault.value === 'all' || item.password_vault_id === selectedVault.value;
        const inSearch = !query
            || [item.title, item.url, item.username, item.client_name, item.project_name, item.vault_name]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(query));

        return inVault && inSearch;
    });
});

const weakCount = computed(() => (props.items || []).filter((item) => !item.has_password).length);
const expiringCount = computed(() => (props.items || []).filter((item) => item.expires_at && new Date(item.expires_at) <= new Date(Date.now() + 1000 * 60 * 60 * 24 * 30)).length);
const sharedCount = computed(() => (props.items || []).filter((item) => (item.user_ids || []).length || (item.group_ids || []).length).length);

function defaultItemForm() {
    return {
        password_vault_id: '',
        title: '',
        url: '',
        username: '',
        password: '',
        notes: '',
        tags_text: '',
        expires_at: '',
        favorite: false,
        client_id: '',
        project_id: '',
        share_permission: 'view',
        user_ids: [],
        group_ids: [],
        custom_fields: [{ label: '', value: '' }],
    };
}

function resetItemForm() {
    itemForm.defaults(defaultItemForm());
    itemForm.reset();
    itemForm.clearErrors();
    selectedItem.value = null;
}

function createItem() {
    resetItemForm();
    itemPanelOpen.value = true;
}

function editItem(item) {
    selectedItem.value = item;
    itemForm.defaults({
        password_vault_id: item.password_vault_id || '',
        title: item.title || '',
        url: item.url || '',
        username: item.username || '',
        password: '',
        notes: item.notes || '',
        tags_text: (item.tags || []).join(', '),
        expires_at: item.expires_at ? String(item.expires_at).slice(0, 10) : '',
        favorite: Boolean(item.favorite),
        client_id: item.client_id || '',
        project_id: item.project_id || '',
        share_permission: item.share_permission || 'view',
        user_ids: item.user_ids || [],
        group_ids: item.group_ids || [],
        custom_fields: item.custom_fields?.length ? item.custom_fields : [{ label: '', value: '' }],
    });
    itemForm.reset();
    itemForm.clearErrors();
    itemPanelOpen.value = true;
}

function saveItem() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            itemPanelOpen.value = false;
            resetItemForm();
        },
    };

    if (selectedItem.value) {
        itemForm.put(route('passwords.items.update', selectedItem.value.id), options);
    } else {
        itemForm.post(route('passwords.items.store'), options);
    }
}

function toggleUser(id) {
    itemForm.user_ids = itemForm.user_ids.includes(id)
        ? itemForm.user_ids.filter((userId) => userId !== id)
        : [...itemForm.user_ids, id];
}

function toggleGroup(id) {
    itemForm.group_ids = itemForm.group_ids.includes(id)
        ? itemForm.group_ids.filter((groupId) => groupId !== id)
        : [...itemForm.group_ids, id];
}

function toggleGroupMember(id) {
    groupForm.user_ids = groupForm.user_ids.includes(id)
        ? groupForm.user_ids.filter((userId) => userId !== id)
        : [...groupForm.user_ids, id];
}

function addCustomField() {
    itemForm.custom_fields = [...itemForm.custom_fields, { label: '', value: '' }];
}

function removeCustomField(index) {
    itemForm.custom_fields = itemForm.custom_fields.filter((_, fieldIndex) => fieldIndex !== index);
}

function generatePassword() {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!?#$%&*-_';
    const bytes = new Uint32Array(22);
    window.crypto.getRandomValues(bytes);
    itemForm.password = Array.from(bytes).map((value) => alphabet[value % alphabet.length]).join('');
}

function storeVault() {
    vaultForm.post(route('passwords.vaults.store'), {
        preserveScroll: true,
        onSuccess: () => {
            vaultForm.reset();
            vaultPanelOpen.value = false;
        },
    });
}

function storeGroup() {
    groupForm.post(route('passwords.groups.store'), {
        preserveScroll: true,
        onSuccess: () => {
            groupForm.reset();
            groupPanelOpen.value = false;
        },
    });
}

function requestReveal(item) {
    revealItem.value = item;
    revealPassword.value = '';
    revealAccountPassword.value = '';
    revealError.value = '';
}

async function revealSelectedPassword() {
    if (!revealItem.value) return;
    revealError.value = '';

    try {
        const response = await window.axios.post(route('passwords.items.reveal', revealItem.value.id), {
            account_password: revealAccountPassword.value,
        });
        revealPassword.value = response.data.password || '';
    } catch (error) {
        revealError.value = 'Password account non corretta o accesso non consentito.';
    }
}

async function copyRevealPassword() {
    if (!revealPassword.value) return;
    await navigator.clipboard?.writeText(revealPassword.value);
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

function userName(id) {
    return (props.users || []).find((user) => user.id === id)?.name || 'Utente';
}

function groupName(id) {
    return (props.groups || []).find((group) => group.id === id)?.name || 'Gruppo';
}
</script>

<template>
    <Head title="Password" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Password</h2>
                <p class="text-sm text-gray-500">Casseforti, credenziali condivise, accessi protetti e audit.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="rounded-[var(--radius-sm)] border border-green-100 bg-green-50 px-3 py-2 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <section class="grid gap-4 md:grid-cols-3">
                    <div class="surface p-5">
                        <p class="text-xs font-semibold uppercase text-gray-400">Credenziali</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ items.length }}</p>
                    </div>
                    <div class="surface p-5">
                        <p class="text-xs font-semibold uppercase text-gray-400">Condivise</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ sharedCount }}</p>
                    </div>
                    <div class="surface p-5">
                        <p class="text-xs font-semibold uppercase text-gray-400">Da controllare</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ weakCount + expiringCount }}</p>
                    </div>
                </section>

                <section class="surface p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="grid flex-1 gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" :stroke-width="1.7" />
                                <input v-model="search" class="form-control pl-9" placeholder="Cerca per titolo, URL, username, cliente o progetto" />
                            </div>
                            <AppSelect
                                v-model="selectedVault"
                                :options="[{ value: 'all', label: 'Tutte le casseforti' }, ...vaults.map((vault) => ({ value: vault.id, label: vault.name }))]"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-if="canManage" type="button" class="btn btn-outline" @click="vaultPanelOpen = true">
                                <Plus class="h-4 w-4" :stroke-width="1.7" />
                                Cassaforte
                            </button>
                            <button v-if="canManage" type="button" class="btn btn-outline" @click="groupPanelOpen = true">
                                <Users class="h-4 w-4" :stroke-width="1.7" />
                                Gruppo
                            </button>
                            <button type="button" class="btn btn-primary" @click="createItem">
                                <Plus class="h-4 w-4" :stroke-width="1.7" />
                                Password
                            </button>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
                    <aside class="space-y-4">
                        <section class="surface p-4">
                            <h3 class="text-sm font-semibold text-gray-900">Casseforti</h3>
                            <div class="mt-3 space-y-2">
                                <button
                                    v-for="vault in vaults"
                                    :key="vault.id"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-[var(--radius-sm)] px-3 py-2 text-left transition hover:bg-gray-50"
                                    @click="selectedVault = vault.id"
                                >
                                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: vault.color || '#0B6EF3' }"></span>
                                        {{ vault.name }}
                                    </span>
                                    <span class="text-xs text-gray-400">{{ vault.items_count }}</span>
                                </button>
                            </div>
                        </section>

                        <section class="surface p-4">
                            <h3 class="text-sm font-semibold text-gray-900">Gruppi</h3>
                            <div class="mt-3 space-y-2">
                                <div v-for="group in groups" :key="group.id" class="rounded-[var(--radius-sm)] bg-gray-50 px-3 py-2">
                                    <p class="text-sm font-semibold text-gray-800">{{ group.name }}</p>
                                    <p class="text-xs text-gray-500">{{ group.members_count }} membri</p>
                                </div>
                            </div>
                        </section>
                    </aside>

                    <section v-if="filteredItems.length" class="grid gap-4 lg:grid-cols-2">
                        <article
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="surface group p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                            <KeyRound class="h-5 w-5" :stroke-width="1.7" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ item.title }}</p>
                                            <p class="truncate text-xs text-gray-500">{{ item.username || 'Nessun username' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="icon-btn h-8 w-8" title="Rivela password" @click="requestReveal(item)">
                                        <Eye class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button v-if="item.can_edit" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina" @click="openDelete(item, 'item')">
                                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 text-sm">
                                <p v-if="item.url" class="truncate text-gray-500">{{ item.url }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <span v-if="item.vault_name" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ item.vault_name }}</span>
                                    <span v-if="item.client_name" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ item.client_name }}</span>
                                    <span v-if="item.project_name" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ item.project_name }}</span>
                                </div>
                                <div v-if="item.user_ids?.length || item.group_ids?.length" class="flex flex-wrap gap-1 text-xs text-gray-500">
                                    <span v-for="id in item.user_ids" :key="id" class="rounded-full bg-[hsl(var(--primary-app)/0.08)] px-2 py-1 font-semibold text-[hsl(var(--primary-app-dark))]">{{ userName(id) }}</span>
                                    <span v-for="id in item.group_ids" :key="id" class="rounded-full bg-gray-100 px-2 py-1 font-semibold text-gray-600">{{ groupName(id) }}</span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3">
                                <button v-if="item.can_edit" type="button" class="text-sm font-semibold text-[hsl(var(--primary-app))]" @click="editItem(item)">Modifica</button>
                                <span v-else class="inline-flex items-center gap-1 text-xs font-semibold text-gray-400">
                                    <Lock class="h-3.5 w-3.5" :stroke-width="1.7" />
                                    Sola lettura
                                </span>
                                <span v-if="item.favorite" class="text-amber-500"><Star class="h-4 w-4 fill-current" :stroke-width="1.7" /></span>
                            </div>
                        </article>
                    </section>
                    <section v-else class="surface px-6 py-12 text-center">
                        <ShieldCheck class="mx-auto h-10 w-10 text-[hsl(var(--primary-app))]" :stroke-width="1.5" />
                        <p class="mt-3 text-sm font-semibold text-gray-900">Nessuna password trovata</p>
                        <p class="mt-1 text-sm text-gray-500">Crea una nuova credenziale o cambia i filtri.</p>
                    </section>
                </section>
            </div>
        </div>

        <div v-if="itemPanelOpen" class="fixed inset-0 z-[5000] flex items-center justify-end bg-black/10 px-4 py-4" @click.self="itemPanelOpen = false">
            <section class="h-full w-full max-w-3xl overflow-y-auto rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem ? 'Modifica password' : 'Nuova password' }}</h3>
                    <button type="button" class="icon-btn" @click="itemPanelOpen = false"><X class="h-4 w-4" /></button>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-semibold uppercase text-gray-400">Titolo</span>
                        <input v-model="itemForm.title" class="form-control" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Cassaforte</span>
                        <AppSelect v-model="itemForm.password_vault_id" :options="[{ value: '', label: 'Nessuna' }, ...vaults.map((vault) => ({ value: vault.id, label: vault.name }))]" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">URL</span>
                        <input v-model="itemForm.url" class="form-control" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Username</span>
                        <input v-model="itemForm.username" class="form-control" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Password</span>
                        <div class="flex gap-2">
                            <input v-model="itemForm.password" class="form-control" :placeholder="selectedItem ? 'Lascia vuoto per non cambiarla' : ''" />
                            <button type="button" class="icon-btn h-11 w-11" title="Genera" @click="generatePassword">
                                <RefreshCw class="h-4 w-4" :stroke-width="1.7" />
                            </button>
                        </div>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Cliente</span>
                        <AppSelect v-model="itemForm.client_id" :options="[{ value: '', label: 'Nessuno' }, ...clients.map((client) => ({ value: client.id, label: client.name }))]" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Progetto</span>
                        <AppSelect v-model="itemForm.project_id" :options="[{ value: '', label: 'Nessuno' }, ...projects.map((project) => ({ value: project.id, label: project.name }))]" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Scadenza</span>
                        <input v-model="itemForm.expires_at" type="date" class="form-control cursor-pointer" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Tag</span>
                        <input v-model="itemForm.tags_text" class="form-control" placeholder="social, cliente, produzione" />
                    </label>
                    <label class="flex items-center gap-2 rounded-[var(--radius-sm)] bg-gray-50 px-3 py-2">
                        <input v-model="itemForm.favorite" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                        <span class="text-sm font-semibold text-gray-700">Preferita</span>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Permesso condivisioni</span>
                        <AppSelect v-model="itemForm.share_permission" :options="[{ value: 'view', label: 'Solo visualizzazione' }, { value: 'edit', label: 'Visualizza e modifica' }]" />
                    </label>
                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-semibold uppercase text-gray-400">Note</span>
                        <textarea v-model="itemForm.notes" rows="4" class="form-control"></textarea>
                    </label>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <section class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                        <p class="text-sm font-semibold text-gray-900">Utenti</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="user in users"
                                :key="user.id"
                                type="button"
                                :class="['inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition', itemForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                                @click="toggleUser(user.id)"
                            >
                                <UserAvatar :user="user" size="xs" />
                                {{ user.name }}
                            </button>
                        </div>
                    </section>
                    <section class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                        <p class="text-sm font-semibold text-gray-900">Gruppi</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="group in groups"
                                :key="group.id"
                                type="button"
                                :class="['rounded-full border px-3 py-2 text-xs font-semibold transition', itemForm.group_ids.includes(group.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                                @click="toggleGroup(group.id)"
                            >
                                {{ group.name }}
                            </button>
                        </div>
                    </section>
                </div>

                <section class="mt-5 rounded-[var(--radius)] bg-gray-50/80 p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-900">Campi personalizzati</p>
                        <button type="button" class="text-sm font-semibold text-[hsl(var(--primary-app))]" @click="addCustomField">Aggiungi</button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(field, index) in itemForm.custom_fields" :key="index" class="grid gap-2 md:grid-cols-[1fr_1fr_auto]">
                            <input v-model="field.label" class="form-control" placeholder="Etichetta" />
                            <input v-model="field.value" class="form-control" placeholder="Valore" />
                            <button type="button" class="icon-btn h-11 w-11 text-red-600" @click="removeCustomField(index)">
                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                            </button>
                        </div>
                    </div>
                </section>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="itemPanelOpen = false">Annulla</button>
                    <button type="button" class="btn btn-primary" :disabled="itemForm.processing" @click="saveItem">
                        Salva
                    </button>
                </div>
            </section>
        </div>

        <div v-if="vaultPanelOpen" class="fixed inset-0 z-[5100] flex items-center justify-center bg-black/10 px-4" @click.self="vaultPanelOpen = false">
            <section class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <h3 class="text-base font-semibold text-gray-900">Nuova cassaforte</h3>
                <div class="mt-4 space-y-3">
                    <input v-model="vaultForm.name" class="form-control" placeholder="Nome" />
                    <textarea v-model="vaultForm.description" class="form-control" rows="3" placeholder="Descrizione"></textarea>
                    <input v-model="vaultForm.color" type="color" class="h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200 bg-white p-1" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="vaultPanelOpen = false">Annulla</button>
                    <button type="button" class="btn btn-primary" @click="storeVault">Salva</button>
                </div>
            </section>
        </div>

        <div v-if="groupPanelOpen" class="fixed inset-0 z-[5100] flex items-center justify-center bg-black/10 px-4" @click.self="groupPanelOpen = false">
            <section class="w-full max-w-xl rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <h3 class="text-base font-semibold text-gray-900">Nuovo gruppo password</h3>
                <div class="mt-4 space-y-3">
                    <input v-model="groupForm.name" class="form-control" placeholder="Nome" />
                    <textarea v-model="groupForm.description" class="form-control" rows="3" placeholder="Descrizione"></textarea>
                    <div class="flex flex-wrap gap-2 rounded-[var(--radius)] bg-gray-50/80 p-3">
                        <button
                            v-for="user in users"
                            :key="user.id"
                            type="button"
                            :class="['inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition', groupForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']"
                            @click="toggleGroupMember(user.id)"
                        >
                            <UserAvatar :user="user" size="xs" />
                            {{ user.name }}
                        </button>
                    </div>
                    <p v-if="groupForm.errors.user_ids" class="text-sm text-red-600">{{ groupForm.errors.user_ids }}</p>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="groupPanelOpen = false">Annulla</button>
                    <button type="button" class="btn btn-primary" @click="storeGroup">Salva</button>
                </div>
            </section>
        </div>

        <div v-if="revealItem" class="fixed inset-0 z-[5200] flex items-center justify-center bg-black/10 px-4" @click.self="revealItem = null">
            <section class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Rivela password</h3>
                    <button type="button" class="icon-btn" @click="revealItem = null"><X class="h-4 w-4" /></button>
                </div>
                <p class="mt-2 text-sm text-gray-500">Conferma la password del tuo account per visualizzare “{{ revealItem.title }}”.</p>
                <div class="mt-4 space-y-3">
                    <input v-model="revealAccountPassword" type="password" class="form-control" placeholder="Password account" @keydown.enter.prevent="revealSelectedPassword" />
                    <button type="button" class="btn btn-primary w-full justify-center" @click="revealSelectedPassword">
                        <Eye class="h-4 w-4" :stroke-width="1.7" />
                        Rivela
                    </button>
                    <p v-if="revealError" class="text-sm text-red-600">{{ revealError }}</p>
                    <div v-if="revealPassword" class="rounded-[var(--radius)] bg-gray-50 p-3">
                        <p class="break-all font-mono text-sm text-gray-900">{{ revealPassword }}</p>
                        <button type="button" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[hsl(var(--primary-app))]" @click="copyRevealPassword">
                            <Copy class="h-4 w-4" :stroke-width="1.7" />
                            Copia
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 z-[5300] flex items-center justify-center bg-transparent px-4" @click.self="deleteTarget = null">
            <section class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <h3 class="text-base font-semibold text-gray-900">Eliminare elemento?</h3>
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
