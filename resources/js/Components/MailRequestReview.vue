<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppSelect from '@/Components/AppSelect.vue';
import ClientSelect from '@/Components/ClientSelect.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import { Check, Download, Link, UserCheck, X, RefreshCw } from '@lucide/vue';
const props=defineProps({data:Object}); const emit=defineEmits(['refresh','resolved']);
const page=usePage(); const busy=ref(false); const error=ref(''); const action=ref('create'); const discardConfirm=ref(false);
const form=ref({title:props.data.record.subject,description:props.data.messages.map(m=>m.body).join('\n\n---\n\n'),client_id:props.data.record.suggested_client_id || '',project_id:props.data.record.suggested_project_id || '',service_id:'',priority:'medium',start_date:'',due_date:'',assignee_ids:[page.props.auth.user.id],task_id:''});
const mine=computed(()=>props.data.record.reviewer_id===page.props.auth.user.id);
const projects=computed(()=>{const c=props.data.clients.find(c=>c.id===form.value.client_id);return props.data.projects.filter(p=>p.client_id===c?.id || (c?.parent_client_id && p.client_id===c.parent_client_id));});
const options=(items)=>items.map(i=>({value:i.id,label:i.name || i.title}));
async function run(fn){busy.value=true;error.value='';try{await fn();}catch(e){error.value=Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Operazione non riuscita.';}finally{busy.value=false;}}
function claim(release=false){run(async()=>{await axios.post(`/requests/${props.data.record.id}/claim`,{release});emit('refresh');});}
function resolve(kind){run(async()=>{await axios.post(`/requests/${props.data.record.id}/resolve`,{...form.value,description:form.value.description ? `<p>${form.value.description.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('\n','<br>')}</p>` : '',action:kind,version:props.data.record.version});emit('resolved');});}
function chooseClient(){form.value.project_id='';form.value.service_id='';}
function chooseProject(){form.value.service_id=props.data.projects.find(p=>p.id===form.value.project_id)?.service_id || '';}
</script>
<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3"><button v-if="!data.record.reviewer_id" class="btn btn-primary" :disabled="busy" @click="claim()"><UserCheck class="h-4 w-4" />Prendi in carico</button><button v-else-if="mine" class="btn btn-outline" :disabled="busy" @click="claim(true)">Rilascia richiesta</button><span v-else class="text-sm text-gray-500">In revisione da un altro manager</span><button class="icon-btn" title="Aggiorna richiesta" aria-label="Aggiorna richiesta" @click="emit('refresh')"><RefreshCw class="h-4 w-4" /></button><a v-if="data.record.task_id" :href="`/tasks/${data.record.task_id}`" class="btn btn-outline">Apri task collegata</a></div>
        <p v-if="data.record.duplicate_of" class="text-sm text-amber-700">Possibile duplicato: questa email è già presente in un’altra richiesta. Verifica prima di creare una task.</p>
        <p v-if="data.record.has_updates" class="text-sm text-amber-700">Sono arrivate nuove email. La task eventualmente collegata non è stata modificata.</p>
        <p v-if="error" role="alert" class="text-sm text-red-600">{{error}}</p>
        <div class="grid items-start gap-6 xl:grid-cols-2">
            <section class="surface min-w-0 p-5"><h3 class="mb-4 font-semibold text-gray-900">Email originali</h3><article v-for="m in data.messages" :key="m.id" class="border-b border-gray-200 py-4 first:pt-0 last:border-0"><div class="break-words text-sm font-semibold text-gray-900">{{m.subject}}</div><div class="mt-1 break-all text-xs text-gray-500">{{m.sender}} · {{m.received_at}}</div><pre class="mt-3 whitespace-pre-wrap break-words font-sans text-sm text-gray-700" style="overflow-wrap:anywhere">{{m.body}}</pre><div class="mt-3 flex flex-wrap gap-2"><a v-for="(a,i) in m.attachments" :key="i" :href="`/requests/${data.record.id}/messages/${m.id}/attachments/${i}`" class="btn btn-outline max-w-full text-sm"><Download class="h-4 w-4 shrink-0" /><span class="truncate">{{a.name}}</span></a></div></article></section>
            <section v-if="data.record.status==='pending'" class="surface min-w-0 p-5">
                <h3 class="mb-4 font-semibold text-gray-900">Proposta da verificare</h3>
                <div class="mb-5"><AppSelect v-model="action" :options="[{value:'create',label:'Crea nuova task'},{value:'link',label:'Collega a task esistente'}]" /></div>
                <form class="space-y-4" @submit.prevent="resolve(action)">
                    <template v-if="action==='create'">
                        <label class="block text-sm text-gray-700">Titolo<input v-model="form.title" class="form-control" required maxlength="255" :disabled="!mine" /></label>
                        <div class="grid gap-4 sm:grid-cols-2"><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Cliente</span><ClientSelect v-model="form.client_id" :clients="data.clients" :disabled="!mine" @change="chooseClient" /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Progetto</span><AppSelect v-model="form.project_id" :options="options(projects)" :disabled="!mine" searchable @change="chooseProject" /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Servizio</span><AppSelect v-model="form.service_id" :options="[{value:'',label:'Nessun servizio'},...options(data.services)]" :disabled="!mine" searchable /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Priorità</span><AppSelect v-model="form.priority" :options="[{value:'low',label:'Bassa'},{value:'medium',label:'Media'},{value:'high',label:'Alta'},{value:'urgent',label:'Urgente'}]" :disabled="!mine" /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Inizio</span><AppDateInput v-model="form.start_date" :disabled="!mine" /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Scadenza</span><AppDateInput v-model="form.due_date" :disabled="!mine" /></label></div>
                        <label class="block text-sm text-gray-700"><span class="mb-1 block">Assegnatari</span><AppSelect v-model="form.assignee_ids" :options="options(data.users)" multiple searchable :disabled="!mine" /></label>
                        <label class="block text-sm text-gray-700">Descrizione<textarea v-model="form.description" rows="10" class="form-control" :disabled="!mine" /></label>
                    </template>
                    <label v-else class="block text-sm text-gray-700"><span class="mb-1 block">Task</span><AppSelect v-model="form.task_id" :options="options(data.tasks)" searchable :disabled="!mine" /></label>
                    <div class="flex flex-wrap gap-3"><button class="btn btn-primary" :disabled="busy || !mine"><Check v-if="action==='create'" class="h-4 w-4" /><Link v-else class="h-4 w-4" />{{action==='create'?'Approva e crea task':'Collega task'}}</button><button type="button" class="btn btn-outline" :disabled="busy || !mine" @click="discardConfirm=!discardConfirm"><X class="h-4 w-4" />Scarta</button></div>
                    <div v-if="discardConfirm" class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 text-sm text-gray-700">Scartare questa richiesta?<button type="button" class="btn btn-outline text-red-600" :disabled="busy" @click="resolve('discard')">Conferma scarto</button></div>
                </form>
            </section>
            <section v-else class="surface p-5"><h3 class="font-semibold text-gray-900">{{data.record.status==='converted'?'Richiesta elaborata':'Richiesta scartata'}}</h3><button v-if="data.record.has_updates" class="btn btn-outline mt-4" :disabled="!mine || busy" @click="resolve('reviewed')"><Check class="h-4 w-4" />Segna aggiornamenti come verificati</button></section>
        </div>
    </div>
</template>
