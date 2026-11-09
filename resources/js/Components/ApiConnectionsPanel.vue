<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AppSelect from '@/Components/AppSelect.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import Modal from '@/Components/Modal.vue';
import ApiConnectionPermissions from '@/Components/ApiConnectionPermissions.vue';
import { Plus, Copy, Check, KeyRound, BookOpen, Download, RefreshCw, Unplug, Save, X, ChevronDown } from '@lucide/vue';

const data = ref({ connections: [], clients: [], projects: [], deliveries: [], logs: [], webhooks: [] });
const saved = ref('');
const expanded = ref(null), draft = ref(null);
function edit(c) {
    if (expanded.value === c.id) { closeEditor(); return; }
    expanded.value = c.id; draft.value = JSON.parse(JSON.stringify(c)); error.value = '';
}
function closeEditor() { expanded.value = null; draft.value = null; error.value = ''; }
function status(c) { return c.revoked_at ? 'Revocato' : active(c) ? 'Attivo' : 'Scaduto'; }
const tab = ref('connections'), busy = ref(false), error = ref(''), token = ref(''), copied = ref(false), show = ref(false), revoke = ref(null), docs = ref('');
const form = ref({ name: '', site: '', client_id: '', project_id: '', scopes: ['clients:read', 'projects:read', 'tasks:read', 'tasks:write', 'comments:write', 'webhooks:write'], expires_at: new Date(Date.now() + 90 * 86400000).toISOString().slice(0, 10) });
const labels = { 'clients:read': 'Leggi cliente', 'projects:read': 'Leggi progetto', 'tasks:read': 'Leggi attività collegate', 'tasks:write': 'Crea e modifica attività', 'comments:write': 'Aggiungi commenti', 'webhooks:write': 'Gestisci webhook' };
const generalLabels = { 'clients:read': 'Leggi clienti', 'clients:write': 'Crea e modifica clienti', 'projects:read': 'Leggi progetti', 'projects:write': 'Crea e modifica progetti', 'tasks:read': 'Leggi task', 'tasks:write': 'Crea e modifica task', 'comments:read': 'Leggi commenti', 'comments:write': 'Aggiungi commenti', 'users:read': 'Utenti', 'absences:read': 'Assenze approvate', 'availability:read': 'Disponibilità e smart working', 'services:read': 'Catalogo servizi', 'webhooks:write': 'Gestisci webhook' };
const policyLabels = { clients: 'Clienti autorizzati', projects: 'Progetti e relative task', users: 'Persone e relative assenze' };
form.value.kind = 'general';
form.value.scopes = Object.keys(generalLabels).filter(scope => scope.endsWith(':read'));
form.value.access_policy = Object.fromEntries(Object.keys(policyLabels).map(key => [key, { all: false, ids: [] }]));
watch(() => form.value.kind, kind => { form.value.scopes = kind === 'general' ? Object.keys(generalLabels).filter(scope => scope.endsWith(':read')) : Object.keys(labels); });
const projects = computed(() => data.value.projects.filter(p => p.client_id === form.value.client_id));
watch(() => form.value.client_id, () => { form.value.project_id = ''; });
function name(list, id) { return list.find(x => x.id === id)?.name || id; }
function date(value) { return value ? new Date(value).toLocaleString('it-IT') : 'Mai'; }
function active(c) { return !c.revoked_at && new Date(c.expires_at) > new Date(); }
function deliveryName(d) { const hook = data.value.webhooks.find(h => h.id === d.webhook_id); return hook ? name(data.value.connections, hook.connection_id) : 'Collegamento rimosso'; }
const deliveryStatuses = { pending: 'In attesa', delivered: 'Consegnato', failed: 'Non riuscito', cancelled: 'Annullato' };
async function savePermissions(c) { await run(async () => { await axios.patch(route('settings.api-connections.update', c.id), { scopes: c.scopes, ...(c.kind === 'general' ? { access_policy: c.access_policy, site: c.site } : {}) }); await load(); closeEditor(); saved.value = 'Modifiche salvate per ' + c.name + '.'; }); }
async function load() { data.value = (await axios.get(route('settings.api-connections.index'))).data; }
async function run(action) {
    busy.value = true; error.value = ''; saved.value = '';
    try { await action(); } catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Operazione non riuscita.'; }
    finally { busy.value = false; }
}
async function create() { await run(async () => { token.value = (await axios.post(route('settings.api-connections.store'), form.value)).data.token; show.value = false; await load(); }); }
async function copy() { await run(async () => { await navigator.clipboard.writeText(token.value); copied.value = true; }); }
async function documentation() { tab.value = 'docs'; if (!docs.value) await run(async () => { docs.value = (await axios.get(route('settings.api-documentation'))).data.html; }); }
onMounted(() => run(load));
</script>

<template>
    <section class="space-y-6 min-w-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <button class="btn" :class="tab === 'connections' ? 'btn-primary' : 'btn-outline'" @click="tab = 'connections'"><KeyRound class="h-4 w-4" />Collegamenti</button>
                <button class="btn" :class="tab === 'deliveries' ? 'btn-primary' : 'btn-outline'" @click="tab = 'deliveries'"><RefreshCw class="h-4 w-4" />Webhook e accessi</button>
                <a class="btn btn-outline" href="/developers" target="_blank" rel="noopener"><BookOpen class="h-4 w-4" />Documentazione API</a>
            </div>
            <button class="btn btn-primary" :disabled="busy" @click="show = true"><Plus class="h-4 w-4" />Nuovo collegamento</button>
        </div>
        <p v-if="error && !expanded && !show" role="alert" class="text-sm text-red-600">{{ error }}</p>
        <p v-if="saved" role="status" class="text-sm font-medium text-gray-700">{{ saved }}</p>
        <div v-if="token" class="app-card space-y-3">
            <h3 class="section-title">Token del collegamento</h3>
            <p class="text-sm">Visibile solo ora. Conservalo nel backend del software collegato.</p>
            <div class="flex items-center gap-3"><code class="min-w-0 flex-1 break-all">{{ token }}</code><button class="btn btn-outline" title="Copia token" aria-label="Copia token" @click="copy"><component :is="copied ? Check : Copy" class="h-4 w-4" /></button><button class="btn btn-outline" title="Nascondi token" aria-label="Nascondi token" @click="token = ''; copied = false"><X class="h-4 w-4" /></button></div>
        </div>
        <div v-if="tab === 'connections'" class="api-connections divide-y divide-gray-200/30">
            <p v-if="!data.connections.length" class="py-5 text-sm text-gray-500">Nessun collegamento.</p>
            <article v-for="c in data.connections" :key="c.id">
                <div class="connection-row">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-semibold break-words">{{ c.name }}</h3><span class="connection-status" :class="{ 'is-active': active(c) }"><span></span>{{ status(c) }}</span></div>
                        <p class="mt-1 text-xs opacity-70 break-words">{{ c.kind === 'general' ? 'API comuni' : 'Page Notes' }} · {{ c.scopes.length }} {{ c.scopes.length === 1 ? 'permesso' : 'permessi' }}<template v-if="c.kind !== 'general'"> · {{ name(data.clients, c.client_id) }}</template></p>
                    </div>
                    <div class="connection-dates text-xs"><div><span class="opacity-60">Scadenza</span><p class="mt-1">{{ date(c.expires_at) }}</p></div><div><span class="opacity-60">Ultimo accesso</span><p class="mt-1">{{ date(c.last_used_at) }}</p></div></div>
                    <div class="flex gap-2">
                        <button class="btn btn-outline" :disabled="busy" :aria-expanded="expanded === c.id" :aria-controls="'connection-' + c.id" :title="'Dettagli di ' + c.name" :aria-label="'Dettagli di ' + c.name" @click="edit(c)"><ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expanded === c.id }" /></button>
                        <button v-if="active(c)" class="btn btn-outline" :disabled="busy" :title="'Revoca ' + c.name" :aria-label="'Revoca ' + c.name" @click="revoke = c"><Unplug class="h-4 w-4" /></button>
                    </div>
                </div>
                <form v-if="expanded === c.id && draft" :id="'connection-' + c.id" class="connection-editor" @submit.prevent="savePermissions(draft)">
                    <ApiConnectionPermissions :connection="draft" :data="data" :disabled="!active(c) || busy" />
                    <div v-if="c.kind === 'general'" class="mt-5"><label :for="'api-site-' + c.id" class="form-label">Sito HTTPS per webhook{{ draft.scopes.includes('webhooks:write') ? '' : ' (facoltativo)' }}</label><input :id="'api-site-' + c.id" v-model="draft.site" type="url" class="form-control" :required="draft.scopes.includes('webhooks:write')" :disabled="!active(c) || busy" /></div>
                    <p v-else class="mt-4 text-sm break-all">{{ c.site }} · {{ name(data.projects, c.project_id) }}</p>
                    <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
                    <footer class="editor-footer"><button type="button" class="btn btn-outline" :disabled="busy" @click="closeEditor">{{ active(c) ? 'Annulla' : 'Chiudi' }}</button><button v-if="active(c)" class="btn btn-primary" :disabled="busy"><Save class="h-4 w-4" />{{ busy ? 'Salvataggio…' : 'Salva modifiche' }}</button></footer>
                </form>
            </article>
        </div>
        <div v-else-if="tab === 'deliveries'" class="space-y-6">
            <div class="app-card"><div class="flex items-center justify-between"><h3 class="section-title">Ultimi 50 tentativi webhook</h3><button class="btn btn-outline" title="Aggiorna" aria-label="Aggiorna" :disabled="busy" @click="run(load)"><RefreshCw class="h-4 w-4" /></button></div>
                <p v-if="!data.deliveries.length" class="mt-4 text-sm text-gray-500">Nessun invio.</p>
                <div v-for="d in data.deliveries" :key="d.id" class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200/30 py-3 text-sm"><div><p class="font-semibold">{{ deliveryName(d) }}</p><p>{{ deliveryStatuses[d.status] || d.status }} · {{ d.attempts }} tentativi · HTTP {{ d.http_status || '—' }}</p><p class="text-xs text-gray-500">{{ date(d.updated_at) }}</p></div><button v-if="d.status === 'failed'" class="btn btn-outline" title="Riprova invio" aria-label="Riprova invio" :disabled="busy" @click="run(async () => { await axios.post(route('settings.api-deliveries.retry', d.id)); await load(); saved = 'Invio rimesso in coda.'; })"><RefreshCw class="h-4 w-4" /></button></div>
            </div>
            <div class="app-card"><h3 class="section-title">Ultime 50 richieste API</h3><div v-for="l in data.logs" :key="l.id" class="flex flex-wrap gap-3 py-2 text-sm"><span>{{ l.status }}</span><span class="break-all">{{ l.method }} /{{ l.path }}</span><span class="text-gray-500">{{ date(l.created_at) }}</span></div><p v-if="!data.logs.length" class="mt-4 text-sm text-gray-500">Nessuna richiesta.</p></div>
        </div>
        <div v-else class="app-card min-w-0"><div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h3 class="section-title">API v1 · Page Notes</h3><a class="btn btn-outline" :href="route('settings.api-openapi')"><Download class="h-4 w-4" />OpenAPI JSON</a></div><div class="api-docs" v-html="docs" /></div>
        <Modal :show="show" :closeable="!busy" @close="show = false"><form class="api-create" @submit.prevent="create">
            <header class="flex items-center justify-between gap-3"><h3 class="section-title">Nuovo collegamento API</h3><button type="button" class="btn btn-outline" title="Chiudi" aria-label="Chiudi" :disabled="busy" @click="show = false"><X class="h-4 w-4" /></button></header>
            <div class="api-create-body">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="form-label" for="api-name">Nome collegamento</label><input id="api-name" v-model="form.name" class="form-control" required maxlength="120" /></div>
                    <div><label class="form-label">Tipo</label><AppSelect v-model="form.kind" :options="[{ value: 'general', label: 'API comuni' }, { value: 'page_notes', label: 'Page Notes (specifico)' }]" /></div>
                    <div><label class="form-label">Scadenza</label><AppDateInput v-model="form.expires_at" /></div>
                    <div><label class="form-label" for="api-site">{{ form.kind === 'general' ? 'Sito webhook' : 'Sito HTTPS' }}{{ form.kind === 'general' && !form.scopes.includes('webhooks:write') ? ' (facoltativo)' : '' }}</label><input id="api-site" v-model="form.site" class="form-control" type="url" :required="form.kind !== 'general' || form.scopes.includes('webhooks:write')" /></div>
                    <template v-if="form.kind === 'page_notes'"><div><label class="form-label">Cliente</label><AppSelect v-model="form.client_id" :options="data.clients" placeholder="Seleziona cliente" /></div><div><label class="form-label">Progetto</label><AppSelect v-model="form.project_id" :options="projects" placeholder="Seleziona progetto" /></div></template>
                </div>
                <div class="mt-5 border-t border-gray-200/30 pt-4"><ApiConnectionPermissions :connection="form" :data="data" :disabled="busy" /></div>
                <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
            </div>
            <footer class="editor-footer"><button type="button" class="btn btn-outline" :disabled="busy" @click="show = false">Annulla</button><button class="btn btn-primary" :disabled="busy || (form.kind === 'page_notes' && !form.project_id)">{{ busy ? 'Creazione…' : 'Crea collegamento' }}</button></footer>
        </form></Modal>
        <Modal :show="Boolean(revoke)" @close="revoke = null"><div class="p-6 space-y-5"><h3 class="section-title">Revoca {{ revoke?.name }}?</h3><p>Il sito non potrà più accedere alle API. Le attività già create resteranno nel Centro.</p><div class="flex justify-end gap-3"><button class="btn btn-outline" @click="revoke = null">Annulla</button><button class="btn btn-primary" :disabled="busy" @click="run(async () => { await axios.delete(route('settings.api-connections.destroy', revoke.id)); revoke = null; await load(); })">Revoca</button></div></div></Modal>
    </section>
</template>

<style scoped>
.connection-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 24px; align-items: center; padding: 18px 0; }
.connection-dates { display: flex; gap: 28px; }
.connection-status { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; opacity: .65; }
.connection-status > span { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.connection-status.is-active { color: #15803d; opacity: 1; }
.connection-editor { padding: 12px 0 0; width: 100%; }
.connection-editor :deep(.api-permissions) { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 36px; }
.connection-editor :deep(fieldset + fieldset) { border-left: 1px solid rgb(127 127 127 / .18); padding-left: 36px; }
.editor-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 0; margin-top: 16px; border-top: 1px solid rgb(127 127 127 / .18); }
.api-create { padding: 20px; }
.api-create header { margin-bottom: 16px; }
.api-create .editor-footer { padding-bottom: 0; }
.api-create :deep(.form-control), .connection-editor :deep(.form-control) { min-height: 38px; padding-top: 8px; padding-bottom: 8px; font-size: 13px; }
.api-create :deep(.form-label), .connection-editor :deep(.form-label) { display: block; font-size: 12px; margin-bottom: 5px; }
@media(max-width: 640px) {
    .connection-editor :deep(.api-permissions) { grid-template-columns: minmax(0, 1fr); gap: 20px; }
    .connection-editor :deep(fieldset + fieldset) { border-left: 0; padding-left: 0; }
    .connection-row { grid-template-columns: minmax(0, 1fr) auto; gap: 12px; }
    .connection-dates { grid-row: 2; grid-column: 1 / -1; gap: 20px; }
    .api-create { padding: 16px; }
}

.api-docs { font-size: 14px; line-height: 1.75; overflow-wrap: anywhere; }
.api-docs :deep(h1) { font-size: 24px; font-weight: 700; }
.api-docs :deep(h2) { font-size: 19px; font-weight: 700; margin-top: 28px; }
.api-docs :deep(h3) { font-size: 16px; font-weight: 600; margin-top: 20px; }
.api-docs :deep(p), .api-docs :deep(ul), .api-docs :deep(ol) { margin: 12px 0; }
.api-docs :deep(ul) { list-style: disc; padding-left: 24px; }
.api-docs :deep(ol) { list-style: decimal; padding-left: 24px; }
.api-docs :deep(pre) { padding: 16px; background: rgba(127,127,127,.08); border-radius: 8px; overflow-x: auto; }
.api-docs :deep(table) { display: block; overflow-x: auto; border-collapse: collapse; }
.api-docs :deep(th), .api-docs :deep(td) { text-align: left; padding: 8px 12px; border-bottom: 1px solid rgba(127,127,127,.25); }
</style>
