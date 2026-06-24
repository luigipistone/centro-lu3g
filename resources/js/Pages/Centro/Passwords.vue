<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Copy, Eye, KeyRound, Plus, Search, ShieldAlert, Trash2, Users, Vault, X } from '@lucide/vue';
import { computed, ref } from 'vue';

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
});

const page = usePage();
const search = ref('');
const drawerOpen = ref(false);
const editingItem = ref(null);
const revealItem = ref(null);
const revealedPassword = ref('');
const accountPassword = ref('');
const revealError = ref('');
const deleteTarget = ref(null);
const deleteText = ref('');
const vaultEditor = ref(null);
const groupEditor = ref(null);

const currentView = computed(() => props.view || 'items');
const manageable = computed(() => props.canManage);
const canCreateVaults = computed(() => props.canCreateVaults);
const activeVaultId = ref('all');

const itemForm = useForm(defaultItemForm());
const vaultForm = useForm({ name: '', description: '', color: '#0B6EF3', visibility: 'personal', user_ids: [], group_ids: [] });
const groupForm = useForm({ name: '', description: '', user_ids: [] });

const visibleItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    return (props.items || []).filter((item) => {
        const vaultMatch = activeVaultId.value === 'all' || item.password_vault_id === activeVaultId.value;
        const textMatch = !q || [item.title, item.username, item.url, item.client_name, item.vault_name]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(q));

        return vaultMatch && textMatch;
    });
});

const compromisedItems = computed(() => (props.items || []).filter((item) => item.risk_flags?.length));

function defaultItemForm() {
    return {
        password_vault_id: '',
        title: '',
        username: '',
        password: '',
        url: '',
        notes: '',
        client_id: '',
        tags_text: '',
        expires_at: '',
        favorite: false,
        project_id: '',
        share_permission: 'view',
        user_ids: [],
        group_ids: [],
        custom_fields: [],
    };
}

function resetItemForm() {
    itemForm.defaults(defaultItemForm());
    itemForm.reset();
    itemForm.clearErrors();
    editingItem.value = null;
}

function openCreateItem() {
    resetItemForm();
    itemForm.password_vault_id = activeVaultId.value !== 'all' ? activeVaultId.value : (props.vaults?.[0]?.id || '');
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
    drawerOpen.value = true;
}

function saveItem() {
    itemForm.title = itemForm.url || itemForm.username || 'Password';
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            drawerOpen.value = false;
            resetItemForm();
        },
    };

    if (editingItem.value) {
        itemForm.put(route('passwords.items.update', editingItem.value.id), options);
    } else {
        itemForm.post(route('passwords.items.store'), options);
    }
}

function openReveal(item) {
    revealItem.value = item;
    revealedPassword.value = '';
    accountPassword.value = '';
    revealError.value = '';
}

async function revealPassword() {
    try {
        const response = await window.axios.post(route('passwords.items.reveal', revealItem.value.id), {
            account_password: accountPassword.value,
        });
        revealedPassword.value = response.data.password || '';
    } catch (error) {
        revealError.value = 'Password account non corretta o accesso non consentito.';
    }
}

async function copyPassword() {
    if (revealedPassword.value) {
        await navigator.clipboard?.writeText(revealedPassword.value);
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

function saveVault() {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetVaultForm(null),
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
        onSuccess: () => resetGroupForm(null),
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
                        :class="['surface flex items-center justify-between gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]', currentView === item.view ? 'ring-1 ring-[hsl(var(--primary-app)/0.25)]' : '']"
                    >
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">{{ item.label }}</span>
                            <span class="mt-1 block text-xs text-gray-500">Apri pagina</span>
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
                            <div class="grid flex-1 gap-3 md:grid-cols-[minmax(0,1fr)_240px]">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" :stroke-width="1.7" />
                                    <input v-model="search" class="form-control pl-9" placeholder="Cerca password" />
                                </div>
                                <AppSelect
                                    v-model="activeVaultId"
                                    :options="[{ value: 'all', label: 'Tutte le casseforti' }, ...vaults.map((vault) => ({ value: vault.id, label: vault.name }))]"
                                />
                            </div>
                            <button type="button" class="btn btn-primary" @click="openCreateItem">
                                <Plus class="h-4 w-4" :stroke-width="1.7" />
                                Password
                            </button>
                        </div>
                    </div>

                    <div v-if="visibleItems.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="item in visibleItems" :key="item.id" class="surface p-4 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                                        <KeyRound class="h-5 w-5" :stroke-width="1.7" />
                                    </span>
                                    <div class="min-w-0">
                                        <button type="button" class="truncate text-left text-sm font-semibold text-gray-900 hover:text-[hsl(var(--primary-app))]" @click="item.can_edit ? openEditItem(item) : openReveal(item)">
                                            {{ item.url || item.title }}
                                        </button>
                                        <p class="truncate text-xs text-gray-500">{{ item.username || 'Nessun nome utente' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="icon-btn h-8 w-8" title="Mostra" @click="openReveal(item)">
                                        <Eye class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button v-if="item.can_delete" type="button" class="icon-btn h-8 w-8 text-red-600 hover:bg-red-50" title="Elimina" @click="openDelete(item, 'item')">
                                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span v-if="item.vault_name" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ item.vault_name }}</span>
                                <span v-if="item.client_name" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ item.client_name }}</span>
                            </div>
                        </article>
                    </div>
                    <div v-else class="surface px-6 py-12 text-center text-sm text-gray-500">Nessuna password disponibile.</div>
                </section>

                <section v-if="currentView === 'vaults'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <div class="grid gap-4 md:grid-cols-2">
                        <article v-for="vault in vaults" :key="vault.id" class="surface p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                        <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: vault.color || '#0B6EF3' }"></span>
                                        {{ vault.name }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">{{ vault.items_count }} password</p>
                                </div>
                                <div v-if="vault.can_edit" class="flex gap-1">
                                    <button type="button" class="text-sm font-semibold text-[hsl(var(--primary-app))]" @click="resetVaultForm(vault)">Modifica</button>
                                    <button v-if="manageable" type="button" class="icon-btn h-8 w-8 text-red-600" @click="openDelete(vault, 'vault')"><Trash2 class="h-4 w-4" /></button>
                                </div>
                            </div>
                            <p v-if="vault.description" class="mt-3 text-sm text-gray-500">{{ vault.description }}</p>
                            <span class="mt-3 inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ vault.visibility === 'shared' ? 'Condivisa' : 'Personale' }}</span>
                            <div v-if="vault.user_ids?.length" class="mt-3 flex flex-wrap gap-1">
                                <span v-for="id in vault.user_ids" :key="id" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ userName(id) }}</span>
                            </div>
                        </article>
                    </div>

                    <aside v-if="canCreateVaults" class="surface p-5">
                        <h3 class="text-base font-semibold text-gray-900">{{ vaultEditor ? 'Modifica cassaforte' : 'Nuova cassaforte' }}</h3>
                        <div class="mt-4 space-y-3">
                            <input v-model="vaultForm.name" class="form-control" placeholder="Nome cassaforte" />
                            <textarea v-model="vaultForm.description" class="form-control" rows="3" placeholder="Descrizione"></textarea>
                            <input v-model="vaultForm.color" type="color" class="h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200 bg-white p-1" />
                            <AppSelect
                                v-if="manageable"
                                v-model="vaultForm.visibility"
                                :options="[{ value: 'personal', label: 'Personale' }, { value: 'shared', label: 'Condivisa' }]"
                            />
                            <div v-if="manageable && vaultForm.visibility === 'shared'" class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                                <p class="text-sm font-semibold text-gray-900">Utenti</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button v-for="user in users" :key="user.id" type="button" :class="['inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition', vaultForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleIn(vaultForm, 'user_ids', user.id)">
                                        <UserAvatar :user="user" size="xs" />
                                        {{ user.name }}
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary w-full justify-center" @click="saveVault">Salva cassaforte</button>
                            <button v-if="vaultEditor" type="button" class="btn btn-outline w-full justify-center" @click="resetVaultForm(null)">Nuova cassaforte</button>
                        </div>
                    </aside>
                </section>

                <section v-if="currentView === 'groups'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <div class="grid gap-4 md:grid-cols-2">
                        <article v-for="group in groups" :key="group.id" class="surface p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ group.name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ group.members_count }} membri</p>
                                </div>
                                <div v-if="manageable" class="flex gap-1">
                                    <button type="button" class="text-sm font-semibold text-[hsl(var(--primary-app))]" @click="resetGroupForm(group)">Modifica</button>
                                    <button type="button" class="icon-btn h-8 w-8 text-red-600" @click="openDelete(group, 'group')"><Trash2 class="h-4 w-4" /></button>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1">
                                <span v-for="id in group.user_ids" :key="id" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">{{ userName(id) }}</span>
                            </div>
                        </article>
                    </div>

                    <aside v-if="manageable" class="surface p-5">
                        <h3 class="text-base font-semibold text-gray-900">{{ groupEditor ? 'Modifica gruppo' : 'Nuovo gruppo' }}</h3>
                        <div class="mt-4 space-y-3">
                            <input v-model="groupForm.name" class="form-control" placeholder="Nome gruppo" />
                            <textarea v-model="groupForm.description" class="form-control" rows="3" placeholder="Descrizione"></textarea>
                            <div class="rounded-[var(--radius)] bg-gray-50/80 p-3">
                                <p class="text-sm font-semibold text-gray-900">Utenti</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button v-for="user in users" :key="user.id" type="button" :class="['inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-xs font-semibold transition', groupForm.user_ids.includes(user.id) ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app-dark))]' : 'border-white bg-white text-gray-600 hover:border-gray-200']" @click="toggleIn(groupForm, 'user_ids', user.id)">
                                        <UserAvatar :user="user" size="xs" />
                                        {{ user.name }}
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary w-full justify-center" @click="saveGroup">Salva gruppo</button>
                            <button v-if="groupEditor" type="button" class="btn btn-outline w-full justify-center" @click="resetGroupForm(null)">Nuovo gruppo</button>
                        </div>
                    </aside>
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
            <section class="h-full w-full max-w-xl overflow-y-auto rounded-l-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.22)] transition">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingItem ? 'Modifica password' : 'Nuova password' }}</h3>
                    <button type="button" class="icon-btn" @click="drawerOpen = false"><X class="h-4 w-4" /></button>
                </div>

                <div class="mt-5 space-y-4">
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Nome utente</span>
                        <input v-model="itemForm.username" class="form-control" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Password</span>
                        <input v-model="itemForm.password" class="form-control" :placeholder="editingItem ? 'Lascia vuoto per non cambiarla' : ''" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Sito web</span>
                        <input v-model="itemForm.url" class="form-control" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Cliente</span>
                        <AppSelect v-model="itemForm.client_id" :options="[{ value: '', label: 'Nessuno' }, ...clients.map((client) => ({ value: client.id, label: client.name }))]" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-400">Note</span>
                        <textarea v-model="itemForm.notes" rows="6" class="form-control"></textarea>
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="drawerOpen = false">Annulla</button>
                    <button type="button" class="btn btn-primary" :disabled="itemForm.processing" @click="saveItem">Salva</button>
                </div>
            </section>
        </div>

        <div v-if="revealItem" class="fixed inset-0 z-[5200] flex items-center justify-center bg-black/10 px-4" @click.self="revealItem = null">
            <section class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900">Mostra password</h3>
                    <button type="button" class="icon-btn" @click="revealItem = null"><X class="h-4 w-4" /></button>
                </div>
                <p class="mt-2 text-sm text-gray-500">Conferma la password del tuo account.</p>
                <input v-model="accountPassword" type="password" class="form-control mt-4" placeholder="Password account" @keydown.enter.prevent="revealPassword" />
                <button type="button" class="btn btn-primary mt-3 w-full justify-center" @click="revealPassword">
                    <Eye class="h-4 w-4" :stroke-width="1.7" />
                    Mostra
                </button>
                <p v-if="revealError" class="mt-3 text-sm text-red-600">{{ revealError }}</p>
                <div v-if="revealedPassword" class="mt-4 rounded-[var(--radius)] bg-gray-50 p-3">
                    <p class="break-all font-mono text-sm text-gray-900">{{ revealedPassword }}</p>
                    <button type="button" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[hsl(var(--primary-app))]" @click="copyPassword">
                        <Copy class="h-4 w-4" :stroke-width="1.7" />
                        Copia
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
