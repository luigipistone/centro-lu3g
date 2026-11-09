<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AppSelect from '@/Components/AppSelect.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import Modal from '@/Components/Modal.vue';
import { Plus, Copy, Check, KeyRound, BookOpen, Download, RefreshCw, Unplug, Save, X } from '@lucide/vue';

const data = ref({ connections: [], clients: [], projects: [], deliveries: [], logs: [], webhooks: [] });
const saved = ref('');
const tab = ref('connections'), busy = ref(false), error = ref(''), token = ref(''), copied = ref(false), show = ref(false), revoke = ref(null), docs = ref('');
const form = ref({ name: '', site: '', client_id: '', project_id: '', scopes: ['clients:read', 'projects:read', 'tasks:read', 'tasks:write', 'comments:write', 'webhooks:write'], expires_at: new Date(Date.now() + 90 * 86400000).toISOString().slice(0, 10) });
const labels = { 'clients:read': 'Leggi cliente', 'projects:read': 'Leggi progetto', 'tasks:read': 'Leggi attività collegate', 'tasks:write': 'Crea e modifica attività', 'comments:write': 'Aggiungi commenti', 'webhooks:write': 'Gestisci webhook' };
const projects = computed(() => data.value.projects.filter(p => p.client_id === form.value.client_id));
watch(() => form.value.client_id, () => { form.value.project_id = ''; });
function name(list, id) { return list.find(x => x.id === id)?.name || id; }
function date(value) { return value ? new Date(value).toLocaleString('it-IT') : 'Mai'; }
function active(c) { return !c.revoked_at && new Date(c.expires_at) > new Date(); }
function deliveryName(d) { const hook = data.value.webhooks.find(h => h.id === d.webhook_id); return hook ? name(data.value.connections, hook.connection_id) : 'Collegamento rimosso'; }
const deliveryStatuses = { pending: 'In attesa', delivered: 'Consegnato', failed: 'Non riuscito', cancelled: 'Annullato' };
async function savePermissions(c) { await run(async () => { await axios.patch(route('settings.api-connections.update', c.id), { scopes: c.scopes }); await load(); saved.value = 'Permessi aggiornati per ' + c.name + '.'; }); }
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
                <button class="btn" :class="tab === 'docs' ? 'btn-primary' : 'btn-outline'" @click="documentation"><BookOpen class="h-4 w-4" />Documentazione</button>
            </div>
            <button class="btn btn-primary" :disabled="busy" @click="show = true"><Plus class="h-4 w-4" />Collega sito</button>
        </div>
        <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
        <p v-if="saved" role="status" class="text-sm font-medium text-gray-700">{{ saved }}</p>
        <div v-if="token" class="app-card space-y-3">
            <h3 class="section-title">Token del collegamento</h3>
            <p class="text-sm">Visibile solo ora. Conservalo nelle impostazioni server del plugin.</p>
            <div class="flex items-center gap-3"><code class="min-w-0 flex-1 break-all">{{ token }}</code><button class="btn btn-outline" title="Copia token" aria-label="Copia token" @click="copy"><component :is="copied ? Check : Copy" class="h-4 w-4" /></button><button class="btn btn-outline" title="Nascondi token" aria-label="Nascondi token" @click="token = ''; copied = false"><X class="h-4 w-4" /></button></div>
        </div>
        <div v-if="tab === 'connections'" class="app-card divide-y divide-gray-200/30">
            <p v-if="!data.connections.length" class="text-sm text-gray-500">Nessun sito collegato.</p>
            <article v-for="c in data.connections" :key="c.id" class="py-4 first:pt-0 last:pb-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0"><h3 class="font-semibold break-words">{{ c.name }}</h3><p class="text-sm break-all">{{ c.site }}</p><p class="text-sm text-gray-500">{{ name(data.clients, c.client_id) }} · {{ name(data.projects, c.project_id) }}</p><p class="mt-1 text-xs text-gray-500">{{ c.revoked_at ? 'Revocato' : active(c) ? 'Attivo' : 'Scaduto' }} · Scadenza {{ date(c.expires_at) }} · Ultimo utilizzo {{ date(c.last_used_at) }}</p></div>
                    <button v-if="active(c)" class="btn btn-outline" title="Revoca collegamento" aria-label="Revoca collegamento" @click="revoke = c"><Unplug class="h-4 w-4" /></button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-3"><label v-for="(label, scope) in labels" :key="scope" class="inline-flex items-center gap-2 text-sm"><input v-model="c.scopes" :value="scope" type="checkbox" :disabled="!active(c) || busy" class="rounded border-gray-300" />{{ label }}</label><button v-if="active(c)" class="btn btn-outline" :disabled="busy" title="Salva permessi" aria-label="Salva permessi" @click="savePermissions(c)"><Save class="h-4 w-4" /></button></div>
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
        <Modal :show="show" @close="show = false"><form class="p-6 space-y-5" @submit.prevent="create"><h3 class="section-title">Collega Page Notes</h3>
            <div><label class="form-label" for="api-name">Nome collegamento</label><input id="api-name" v-model="form.name" class="form-control" required maxlength="120" /></div>
            <div><label class="form-label" for="api-site">Sito HTTPS</label><input id="api-site" v-model="form.site" class="form-control" type="url" required /></div>
            <div><label class="form-label">Cliente</label><AppSelect v-model="form.client_id" :options="data.clients" placeholder="Seleziona cliente" /></div>
            <div><label class="form-label">Progetto</label><AppSelect v-model="form.project_id" :options="projects" placeholder="Seleziona progetto" /></div>
            <div><label class="form-label">Scadenza</label><AppDateInput v-model="form.expires_at" /></div>
            <div class="grid gap-3 sm:grid-cols-2"><label v-for="(label, scope) in labels" :key="scope" class="flex items-center gap-2 text-sm"><input v-model="form.scopes" type="checkbox" :value="scope" class="rounded border-gray-300" />{{ label }}</label></div>
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" class="btn btn-outline" @click="show = false">Annulla</button><button class="btn btn-primary" :disabled="busy || !form.project_id">Crea collegamento</button></div>
        </form></Modal>
        <Modal :show="Boolean(revoke)" @close="revoke = null"><div class="p-6 space-y-5"><h3 class="section-title">Revoca {{ revoke?.name }}?</h3><p>Il sito non potrà più accedere alle API. Le attività già create resteranno nel Centro.</p><div class="flex justify-end gap-3"><button class="btn btn-outline" @click="revoke = null">Annulla</button><button class="btn btn-primary" :disabled="busy" @click="run(async () => { await axios.delete(route('settings.api-connections.destroy', revoke.id)); revoke = null; await load(); })">Revoca</button></div></div></Modal>
    </section>
</template>

<style scoped>
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
