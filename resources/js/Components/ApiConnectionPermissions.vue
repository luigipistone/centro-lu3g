<script setup>
import { computed } from 'vue';
import AppSelect from '@/Components/AppSelect.vue';

const props = defineProps({ connection: Object, data: Object, disabled: Boolean });
const resources = { clients: 'Clienti', projects: 'Progetti', tasks: 'Task', comments: 'Commenti', users: 'Utenti', absences: 'Assenze approvate', availability: 'Disponibilità e smart working', services: 'Servizi' };
const policies = { clients: 'Clienti', projects: 'Progetti e task', users: 'Persone e assenze' };
const rows = computed(() => Object.entries(resources).filter(([key]) => props.connection.kind === 'general' || ['clients', 'projects', 'tasks', 'comments'].includes(key)));
function supported(key, action) {
    if (props.connection.kind !== 'general') return action === 'read' ? key !== 'comments' : ['tasks', 'comments'].includes(key);
    return action === 'read' || ['clients', 'projects', 'tasks', 'comments'].includes(key);
}
</script>

<template>
    <div class="api-permissions">
        <fieldset class="min-w-0">
            <legend class="mb-2 text-sm font-semibold">Permessi</legend>
            <table class="w-full text-sm">
                <thead><tr><th class="text-left font-medium">Risorsa</th><th class="font-medium">Lettura</th><th class="font-medium">Scrittura</th></tr></thead>
                <tbody><tr v-for="[key, label] in rows" :key="key">
                    <th scope="row" class="text-left font-normal">{{ label }}</th>
                    <td v-for="action in ['read', 'write']" :key="action" class="text-center"><input v-if="supported(key, action)" v-model="connection.scopes" type="checkbox" :value="`${key}:${action}`" :aria-label="`${label}: ${action === 'read' ? 'lettura' : 'scrittura'}`" :disabled="disabled" /><span v-else aria-label="Non disponibile">—</span></td>
                </tr></tbody>
            </table>
            <label class="mt-3 flex items-center gap-2 text-sm"><input v-model="connection.scopes" type="checkbox" value="webhooks:write" :disabled="disabled" />Gestisci webhook</label>
        </fieldset>
        <fieldset v-if="connection.kind === 'general'" class="min-w-0">
            <legend class="mb-2 text-sm font-semibold">Ambiti autorizzati</legend>
            <div class="space-y-4">
                <div v-for="(label, key) in policies" :key="key" class="min-w-0">
                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2"><span class="text-sm font-medium">{{ label }}</span><label class="inline-flex items-center gap-1.5 text-xs"><input v-model="connection.access_policy[key].all" type="checkbox" :disabled="disabled" :aria-label="`${label}: tutti, anche futuri`" />Tutti, anche futuri</label></div>
                    <AppSelect v-if="!connection.access_policy[key].all" v-model="connection.access_policy[key].ids" multiple searchable :options="data[key] || []" :disabled="disabled" placeholder="Nessuno autorizzato" />
                    <p v-else class="py-2 text-xs opacity-70">Tutti gli elementi</p>
                </div>
            </div>
        </fieldset>
    </div>
</template>

<style scoped>
.api-permissions { display: grid; gap: 24px; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
th, td { padding: 6px 4px; border-bottom: 1px solid rgb(127 127 127 / .15); }
thead th { font-size: 11px; opacity: .7; }
input[type=checkbox] { width: 15px; height: 15px; flex-shrink: 0; border-radius: 4px; cursor: pointer; }
@media(max-width: 600px) { .api-permissions { grid-template-columns: minmax(0, 1fr); gap: 20px; } }
</style>
