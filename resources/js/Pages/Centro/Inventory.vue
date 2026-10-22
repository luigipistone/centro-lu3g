<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import InventoryScene from '@/Components/InventoryScene.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Boxes, Check, Laptop, Monitor, Pencil, Plus, Search, Trash2, X } from '@lucide/vue';

const props = defineProps({ items: { type: Array, default: () => [] }, users: { type: Array, default: () => [] }, canManage: Boolean });
const type = ref('physical');
const search = ref('');
const selectedId = ref(null);
const itemDialog = ref(false);
const unitDialog = ref(false);
const editingItem = ref(null);
const editingUnit = ref(null);
const confirmation = ref(null);
const notice = ref('');

const selectedItems = computed(() => props.items.filter((item) => item.type === type.value));
const filteredItems = computed(() => selectedItems.value.filter((item) => `${item.name} ${item.category || ''} ${item.brand || ''}`.toLocaleLowerCase('it').includes(search.value.toLocaleLowerCase('it'))));
const selected = computed(() => filteredItems.value.find((item) => item.id === selectedId.value)
    || (type.value === 'physical' && filteredItems.value.find((item) => item.name.startsWith('iMac 23')))
    || filteredItems.value[0] || null);
const physicalCount = computed(() => props.items.filter((item) => item.type === 'physical').reduce((sum, item) => sum + Number(item.quantity || 0), 0));
const digitalCount = computed(() => props.items.filter((item) => item.type === 'digital').length);
const userOptions = computed(() => [{ value: '', label: 'Non assegnato' }, ...props.users.map((user) => ({ value: user.id, label: user.name }))]);
const conditionOptions = [
    { value: 'available', label: 'Disponibile' },
    { value: 'assigned', label: 'Assegnato' },
    { value: 'repair', label: 'In riparazione' },
    { value: 'retired', label: 'Dismesso' },
];
const conditionLabels = Object.fromEntries(conditionOptions.map((option) => [option.value, option.label]));
const itemForm = useForm({
    type: 'physical', name: '', category: '', brand: '', model: '', model_year: '',
    license_type: '', capacity_total: '', used_count: '', renewal_date: '',
    access_url: '', notes: '', quantity: 1,
});
const unitForm = useForm({
    serial_number: '', assigned_user_id: '', office: '', condition: 'available',
    purchased_at: '', warranty_until: '', notes: '',
});

function changeType(value) {
    type.value = value;
    selectedId.value = null;
    search.value = '';
}

function openItem(item = null) {
    editingItem.value = item;
    itemForm.clearErrors();
    itemForm.defaults({
        type: item?.type || type.value, name: item?.name || '', category: item?.category || '',
        brand: item?.brand || '', model: item?.model || '', model_year: item?.model_year || '',
        license_type: item?.license_type || '', capacity_total: item?.capacity_total ?? '',
        used_count: item?.used_count ?? '', renewal_date: item?.renewal_date || '',
        access_url: item?.access_url || '', notes: item?.notes || '', quantity: 1,
    });
    itemForm.reset();
    itemDialog.value = true;
}

function saveItem() {
    const target = editingItem.value;
    const options = { preserveScroll: true, onSuccess: () => { itemDialog.value = false; notice.value = target ? 'Scheda aggiornata.' : 'Elemento aggiunto.'; } };
    if (target) itemForm.put(route('inventory.update', target.id), options);
    else itemForm.post(route('inventory.store'), options);
}

function openUnit(unit) {
    editingUnit.value = unit;
    unitForm.clearErrors();
    unitForm.defaults({
        serial_number: unit.serial_number || '', assigned_user_id: unit.assigned_user_id || '',
        office: unit.office || '', condition: unit.condition || 'available',
        purchased_at: unit.purchased_at || '', warranty_until: unit.warranty_until || '', notes: unit.notes || '',
    });
    unitForm.reset();
    unitDialog.value = true;
}

function saveUnit() {
    if (!selected.value || !editingUnit.value) return;
    unitForm.put(route('inventory.units.update', [selected.value.id, editingUnit.value.id]), {
        preserveScroll: true,
        onSuccess: () => { unitDialog.value = false; notice.value = 'Unità aggiornata.'; },
    });
}

function addUnit() {
    if (!selected.value) return;
    router.post(route('inventory.units.store', selected.value.id), {}, { preserveScroll: true, onSuccess: () => { notice.value = 'Unità aggiunta.'; } });
}

function askDelete(kind, id, label) {
    confirmation.value = { kind, id, label };
}

function deleteConfirmed() {
    if (!confirmation.value || !selected.value) return;
    const { kind, id } = confirmation.value;
    const url = kind === 'item' ? route('inventory.destroy', id) : route('inventory.units.destroy', [selected.value.id, id]);
    router.delete(url, { preserveScroll: true, onSuccess: () => { confirmation.value = null; notice.value = kind === 'item' ? 'Elemento eliminato.' : 'Unità eliminata.'; } });
}

function safeUrl(value) {
    try {
        const url = new URL(value);
        return ['https:', 'http:'].includes(url.protocol) ? url.href : null;
    } catch { return null; }
}
</script>

<template>
    <Head title="Inventario" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventario</h2>
                <button v-if="canManage" class="btn btn-primary" type="button" @click="openItem()"><Plus class="h-4 w-4" />Nuovo elemento</button>
            </div>
        </template>

        <div class="mx-auto max-w-[1800px] px-4 py-6 sm:px-6 lg:px-8">
            <div v-if="notice" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800" role="status"><span class="flex items-center gap-2"><Check class="h-4 w-4" />{{ notice }}</span><button type="button" aria-label="Chiudi conferma" @click="notice = ''"><X class="h-4 w-4" /></button></div>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-[var(--radius-sm)] border border-gray-200 bg-white p-1 shadow-sm" role="tablist" aria-label="Tipo di inventario">
                    <button v-for="option in [{ key: 'physical', label: 'Fisico', count: physicalCount, icon: Laptop }, { key: 'digital', label: 'Digitale', count: digitalCount, icon: Boxes }]" :key="option.key" type="button" role="tab" :aria-selected="type === option.key" class="flex min-h-9 items-center gap-2 rounded-[calc(var(--radius-sm)-4px)] px-3 text-sm font-medium transition" :class="type === option.key ? 'bg-[hsl(var(--primary-app))] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'" @click="changeType(option.key)"><component :is="option.icon" class="h-4 w-4" />{{ option.label }} <span class="text-xs opacity-75">{{ option.count }}</span></button>
                </div>
                <label class="relative block w-full min-w-0 sm:w-64"><Search class="pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-gray-400" /><span class="sr-only">Cerca nell'inventario</span><input v-model="search" type="search" class="form-control !mt-0 !pl-10 w-full" placeholder="Cerca nell'inventario" /></label>
            </div>

            <div class="inventory-workspace grid min-w-0 overflow-hidden border-y border-gray-200 bg-white lg:grid-cols-[minmax(0,1fr)_340px]">
                <InventoryScene :items="filteredItems" :type="type" :selected-id="selected?.id" @select="selectedId = $event" />
                <aside class="min-w-0 border-t border-gray-100 bg-white p-5 lg:border-l lg:border-t-0" aria-live="polite">
                    <template v-if="selected">
                        <div class="flex items-start justify-between gap-2"><div class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-blue-50 text-blue-600"><Monitor v-if="type === 'physical'" class="h-5 w-5" /><Boxes v-else class="h-5 w-5" /></div><div v-if="canManage" class="flex gap-1"><button type="button" class="icon-btn h-9 w-9" title="Modifica elemento" aria-label="Modifica elemento" @click="openItem(selected)"><Pencil class="h-4 w-4" /></button><button type="button" class="icon-btn h-9 w-9 text-red-500" title="Elimina elemento" aria-label="Elimina elemento" @click="askDelete('item', selected.id, selected.name)"><Trash2 class="h-4 w-4" /></button></div></div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ selected.name }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ selected.category || (type === 'physical' ? 'Materiale fisico' : 'Licenza digitale') }}</p>
                        <dl class="mt-5 divide-y divide-gray-100 text-sm">
                            <div v-if="type === 'physical'" class="flex justify-between gap-4 py-2"><dt class="text-gray-500">Unità</dt><dd class="font-medium text-gray-900">{{ selected.quantity }}</dd></div>
                            <div v-if="type === 'physical'" class="flex justify-between gap-4 py-2"><dt class="text-gray-500">Assegnate</dt><dd class="font-medium text-gray-900">{{ selected.units.filter(unit => unit.assigned_user_id).length }}</dd></div>
                            <div v-if="selected.model" class="flex justify-between gap-4 py-2"><dt class="text-gray-500">Modello</dt><dd class="text-right font-medium text-gray-900">{{ selected.model }}</dd></div>
                            <div v-if="selected.license_type" class="flex justify-between gap-4 py-2"><dt class="text-gray-500">Licenza</dt><dd class="font-medium text-gray-900">{{ selected.license_type }}</dd></div>
                            <div v-if="selected.capacity_total !== null" class="flex justify-between gap-4 py-2"><dt class="text-gray-500">Capienza</dt><dd class="font-medium text-gray-900">{{ selected.used_count ?? '–' }} / {{ selected.capacity_total }}</dd></div>
                            <div v-if="selected.renewal_date" class="flex justify-between gap-4 py-2"><dt class="text-gray-500">Rinnovo</dt><dd class="font-medium text-gray-900">{{ new Date(`${selected.renewal_date}T12:00:00`).toLocaleDateString('it-IT') }}</dd></div>
                        </dl>
                        <p v-if="selected.notes" class="mt-4 border-t border-gray-100 pt-3 text-sm text-gray-600">{{ selected.notes }}</p>
                        <a v-if="safeUrl(selected.access_url)" :href="safeUrl(selected.access_url)" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline">Apri accesso ↗</a>
                        <div v-if="type === 'physical'" class="mt-6 border-t border-gray-100 pt-4">
                            <div class="mb-2 flex items-center justify-between"><h4 class="text-sm font-semibold text-gray-900">Singole unità</h4><button v-if="canManage" type="button" class="icon-btn h-8 w-8" title="Aggiungi unità" aria-label="Aggiungi unità" @click="addUnit"><Plus class="h-4 w-4" /></button></div>
                            <div class="max-h-44 space-y-1 overflow-y-auto pr-1"><button v-for="(unit, index) in selected.units" :key="unit.id" type="button" class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-100 px-3 py-2 text-left text-xs transition hover:bg-blue-50" :disabled="!canManage" @click="openUnit(unit)"><span class="min-w-0 truncate font-medium text-gray-800">{{ unit.serial_number || `Unità ${index + 1}` }}</span><span class="shrink-0 text-gray-500">{{ unit.assigned_user_name || conditionLabels[unit.condition] }}</span></button></div>
                        </div>
                    </template>
                    <div v-else class="flex h-full min-h-40 flex-col items-center justify-center text-center text-gray-500"><Boxes class="mb-3 h-8 w-8 text-gray-300" /><p class="text-sm">Nessun elemento corrisponde alla ricerca.</p></div>
                </aside>
            </div>

            <div class="mt-7">
                <div class="mb-3 flex items-center justify-between"><h3 class="text-base font-semibold text-gray-900">{{ type === 'physical' ? 'Materiali fisici' : 'Licenze e strumenti digitali' }}</h3><span class="text-sm text-gray-500">{{ filteredItems.length }} voci</span></div>
                <div class="overflow-x-auto rounded-[var(--radius-sm)] border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-100 text-sm"><thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500"><tr><th class="px-4 py-3">Nome</th><th class="px-4 py-3">Categoria</th><th class="px-4 py-3">{{ type === 'physical' ? 'Unità' : 'Licenza' }}</th><th class="px-4 py-3">{{ type === 'physical' ? 'Assegnate' : 'Rinnovo' }}</th><th class="px-4 py-3 text-right">Dettaglio</th></tr></thead>
                        <tbody class="divide-y divide-gray-100"><tr v-for="item in filteredItems" :key="item.id" :class="selected?.id === item.id ? 'bg-blue-50/60' : ''"><td class="px-4 py-3 font-medium text-gray-900">{{ item.name }}</td><td class="px-4 py-3 text-gray-500">{{ item.category || '–' }}</td><td class="px-4 py-3 text-gray-700">{{ type === 'physical' ? item.quantity : item.license_type || '–' }}</td><td class="px-4 py-3 text-gray-700">{{ type === 'physical' ? item.units.filter(unit => unit.assigned_user_id).length : item.renewal_date || '–' }}</td><td class="px-4 py-3 text-right"><button type="button" class="text-sm font-semibold text-blue-600 hover:underline" @click="selectedId = item.id">Apri</button></td></tr><tr v-if="!filteredItems.length"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Nessuna voce trovata.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <Teleport to="body">
        <div v-if="itemDialog" class="fixed inset-0 z-[3000] flex items-center justify-center bg-gray-900/35 p-4" @click.self="itemDialog = false">
            <form class="surface max-h-[90vh] w-full max-w-2xl overflow-y-auto p-6" role="dialog" aria-modal="true" aria-labelledby="inventory-item-title" @submit.prevent="saveItem">
                <div class="mb-5 flex items-center justify-between gap-3 border-b border-gray-100 pb-4">
                    <h3 id="inventory-item-title" class="text-lg font-semibold text-gray-900">{{ editingItem ? 'Modifica elemento' : 'Nuovo elemento' }}</h3>
                    <button type="button" class="icon-btn h-9 w-9" title="Chiudi" aria-label="Chiudi" @click="itemDialog = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div><span class="block text-sm font-medium text-gray-700">Tipologia</span><AppSelect v-model="itemForm.type" class="mt-1" :disabled="!!editingItem" :options="[{ value: 'physical', label: 'Fisico' }, { value: 'digital', label: 'Digitale' }]" /></div>
                    <div><label for="inventory-name" class="block text-sm font-medium text-gray-700">Nome</label><input id="inventory-name" v-model="itemForm.name" required class="form-control" /><p v-if="itemForm.errors.name" class="mt-1 text-xs text-red-600">{{ itemForm.errors.name }}</p></div>
                    <div><label for="inventory-category" class="block text-sm font-medium text-gray-700">Categoria</label><input id="inventory-category" v-model="itemForm.category" class="form-control" /></div>
                    <div v-if="itemForm.type === 'physical' && !editingItem"><label for="inventory-quantity" class="block text-sm font-medium text-gray-700">Quantità iniziale</label><input id="inventory-quantity" v-model.number="itemForm.quantity" type="number" min="1" max="100" class="form-control" /></div>
                    <template v-if="itemForm.type === 'physical'">
                        <div><label for="inventory-brand" class="block text-sm font-medium text-gray-700">Marca</label><input id="inventory-brand" v-model="itemForm.brand" class="form-control" /></div>
                        <div><label for="inventory-model" class="block text-sm font-medium text-gray-700">Modello</label><input id="inventory-model" v-model="itemForm.model" class="form-control" /></div>
                        <div><label for="inventory-year" class="block text-sm font-medium text-gray-700">Anno modello</label><input id="inventory-year" v-model="itemForm.model_year" type="number" min="1900" max="2100" class="form-control" /></div>
                    </template>
                    <template v-else>
                        <div><label for="inventory-license" class="block text-sm font-medium text-gray-700">Tipo di licenza</label><input id="inventory-license" v-model="itemForm.license_type" class="form-control" /></div>
                        <div><label for="inventory-capacity" class="block text-sm font-medium text-gray-700">Capienza</label><input id="inventory-capacity" v-model="itemForm.capacity_total" type="number" min="0" class="form-control" /></div>
                        <div><label for="inventory-used" class="block text-sm font-medium text-gray-700">Utilizzate</label><input id="inventory-used" v-model="itemForm.used_count" type="number" min="0" class="form-control" /><p v-if="itemForm.errors.used_count" class="mt-1 text-xs text-red-600">{{ itemForm.errors.used_count }}</p></div>
                        <div><span class="block text-sm font-medium text-gray-700">Rinnovo</span><AppDateInput v-model="itemForm.renewal_date" class="mt-1" /></div>
                        <div class="sm:col-span-2"><label for="inventory-url" class="block text-sm font-medium text-gray-700">Link accesso</label><input id="inventory-url" v-model="itemForm.access_url" type="url" class="form-control" /><p v-if="itemForm.errors.access_url" class="mt-1 text-xs text-red-600">{{ itemForm.errors.access_url }}</p></div>
                    </template>
                    <div class="sm:col-span-2"><label for="inventory-notes" class="block text-sm font-medium text-gray-700">Note</label><textarea id="inventory-notes" v-model="itemForm.notes" rows="3" class="form-control" /></div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4"><button type="button" class="btn btn-outline" @click="itemDialog = false">Annulla</button><button type="submit" class="btn btn-primary" :disabled="itemForm.processing"><Check class="h-4 w-4" />Salva</button></div>
            </form>
        </div>

        <div v-if="unitDialog" class="fixed inset-0 z-[3000] flex items-center justify-center bg-gray-900/35 p-4" @click.self="unitDialog = false">
            <form class="surface max-h-[90vh] w-full max-w-xl overflow-y-auto p-6" role="dialog" aria-modal="true" aria-labelledby="inventory-unit-title" @submit.prevent="saveUnit">
                <div class="mb-5 flex items-center justify-between gap-3 border-b border-gray-100 pb-4"><h3 id="inventory-unit-title" class="text-lg font-semibold text-gray-900">Unità fisica</h3><button type="button" class="icon-btn h-9 w-9" title="Chiudi" aria-label="Chiudi" @click="unitDialog = false"><X class="h-4 w-4" /></button></div>
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div><label for="inventory-serial" class="block text-sm font-medium text-gray-700">Seriale</label><input id="inventory-serial" v-model="unitForm.serial_number" class="form-control" /></div>
                    <div><span class="block text-sm font-medium text-gray-700">Assegnato a</span><AppSelect v-model="unitForm.assigned_user_id" class="mt-1" :options="userOptions" /></div>
                    <div><label for="inventory-office" class="block text-sm font-medium text-gray-700">Sede</label><input id="inventory-office" v-model="unitForm.office" class="form-control" /></div>
                    <div><span class="block text-sm font-medium text-gray-700">Stato</span><AppSelect v-model="unitForm.condition" class="mt-1" :options="conditionOptions" /></div>
                    <div><span class="block text-sm font-medium text-gray-700">Acquistato il</span><AppDateInput v-model="unitForm.purchased_at" class="mt-1" /></div>
                    <div><span class="block text-sm font-medium text-gray-700">Garanzia fino al</span><AppDateInput v-model="unitForm.warranty_until" class="mt-1" /></div>
                    <div class="sm:col-span-2"><label for="inventory-unit-notes" class="block text-sm font-medium text-gray-700">Note</label><textarea id="inventory-unit-notes" v-model="unitForm.notes" rows="3" class="form-control" /></div>
                </div>
                <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-4"><button type="button" class="btn btn-outline text-red-600" @click="unitDialog = false; askDelete('unit', editingUnit.id, editingUnit.serial_number || 'questa unità')"><Trash2 class="h-4 w-4" />Elimina unità</button><button type="submit" class="btn btn-primary" :disabled="unitForm.processing"><Check class="h-4 w-4" />Salva</button></div>
            </form>
        </div>

        <div v-if="confirmation" class="fixed inset-0 z-[3100] flex items-center justify-center bg-gray-900/35 p-4" @click.self="confirmation = null"><div class="surface w-full max-w-md p-6" role="alertdialog" aria-modal="true"><h3 class="text-lg font-semibold">Eliminare {{ confirmation.label }}?</h3><p class="mt-2 text-sm text-gray-600">Questa operazione non può essere annullata. Gli oggetti assegnati devono essere restituiti prima.</p><div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4"><button type="button" class="btn btn-outline" @click="confirmation = null">Annulla</button><button type="button" class="btn btn-danger" @click="deleteConfirmed"><Trash2 class="h-4 w-4" />Elimina</button></div></div></div>
        </Teleport>
    </AuthenticatedLayout>
</template>
