<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppSelect from '@/Components/AppSelect.vue';
import ClientSelect from '@/Components/ClientSelect.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Check, Download, Link, UserCheck, RefreshCw, Sparkles, LoaderCircle, ChevronDown, Plus, Trash2 } from '@lucide/vue';
const props=defineProps({data:Object}); const emit=defineEmits(['refresh','resolved']);
const page=usePage(); const busy=ref(false); const error=ref(''); const action=ref('create'); const discardConfirm=ref(false);
const pending=computed(()=>props.data.messages.filter(m=>!m.disposition));
const incoming=pending.value.filter(m=>!(props.data.internal_emails || []).some(e=>e.toLowerCase()===m.sender.toLowerCase()));
const selected=ref((incoming.length ? incoming : pending.value).slice(-1).map(m=>m.id));
const prepared=ref(false); const candidates=ref([]);
const aiGenerated=ref(false); const aiLoading=ref(false); const warnings=ref([]); const replaceMode=ref(null);
const form=ref({title:props.data.record.subject,description:'',task_type:'ongoing',subtasks:[],client_id:'',project_id:'',service_id:'',priority:'medium',start_date:'',due_date:'',assignee_ids:[page.props.auth.user.id],task_id:props.data.record.task_id || ''});
const peopleOpen=ref(false); const peopleRoot=ref(null);
const selectedUsers=computed(()=>props.data.users.filter(u=>form.value.assignee_ids.includes(u.id)));
function togglePerson(id){form.value.assignee_ids=form.value.assignee_ids.includes(id)?form.value.assignee_ids.filter(v=>v!==id):[...form.value.assignee_ids,id];}
function closePeople(e){if(e.key==='Escape' || (e.type==='pointerdown' && !peopleRoot.value?.contains(e.target))) peopleOpen.value=false;}
onMounted(()=>{document.addEventListener('pointerdown',closePeople);document.addEventListener('keydown',closePeople);});
onBeforeUnmount(()=>{document.removeEventListener('pointerdown',closePeople);document.removeEventListener('keydown',closePeople);});
const mine=computed(()=>props.data.record.reviewer_id===page.props.auth.user.id);
const projects=computed(()=>{const c=props.data.clients.find(c=>c.id===form.value.client_id);return props.data.projects.filter(p=>p.client_id===c?.id || (c?.parent_client_id && p.client_id===c.parent_client_id));});
const options=(items)=>items.map(i=>({value:i.id,label:i.name || i.title}));
async function run(fn){busy.value=true;error.value='';try{await fn();}catch(e){error.value=Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Operazione non riuscita.';}finally{busy.value=false;}}
function claim(release=false){run(async()=>{await axios.post(`/requests/${props.data.record.id}/claim`,{release});emit('refresh');});}
function resolve(kind){run(async()=>{await axios.post(`/requests/${props.data.record.id}/resolve`,{...form.value,message_ids:selected.value,description:form.value.description ? `<p>${form.value.description.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('\n','<br>')}</p>` : '',action:kind,version:props.data.record.version});emit('resolved');});}
function prepare(ai=false,confirmed=false){
    if(prepared.value && !confirmed){replaceMode.value=ai;return;}
    replaceMode.value=null;
    run(async()=>{
        aiLoading.value=ai;
        try {
            const {data}=await axios.post(`/requests/${props.data.record.id}/prepare`,{message_ids:selected.value,ai,version:props.data.record.version});
            Object.assign(form.value,{title:data.title,description:data.description,subtasks:data.subtasks || []});
            if(!prepared.value){Object.assign(form.value,{client_id:data.client_id || '',project_id:form.value.task_type==='task' ? data.project_id || '' : ''});chooseProject();}
            candidates.value=data.candidates;warnings.value=data.warnings || [];aiGenerated.value=Boolean(data.ai_generated);prepared.value=true;
        } finally {aiLoading.value=false;}
    });
}
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
            <section class="surface min-w-0 p-5">
                <h3 class="mb-4 flex flex-wrap items-center gap-2 font-semibold text-gray-900">Messaggi <span class="text-sm text-gray-500">{{pending.length}} da verificare</span></h3>
                <article v-for="m in data.messages" :key="m.id" class="border-b border-gray-200 py-4 first:pt-0 last:border-0">
                    <label v-if="!m.disposition" class="flex items-start gap-3 text-sm font-semibold text-gray-900"><input v-model="selected" type="checkbox" :value="m.id" :disabled="!mine || busy" class="mt-1" @change="prepared=false" /><span class="break-words min-w-0">{{m.subject}}</span></label>
                    <div v-else class="text-sm text-gray-500">{{m.disposition==='ignored' ? 'Ignorato' : 'Elaborato'}} <a v-if="m.task_id" :href="`/tasks/${m.task_id}`" class="text-blue-600 hover:underline">Apri task</a></div>
                    <div class="mt-1 break-all text-xs text-gray-500">{{m.sender}} · {{m.received_at}}</div>
                    <details class="mt-3" :open="selected.includes(m.id)"><summary class="cursor-pointer text-sm text-gray-500">Contenuto originale</summary><pre class="mt-3 whitespace-pre-wrap break-words font-sans text-sm text-gray-700" style="overflow-wrap:anywhere">{{m.body}}</pre>
                        <div class="mt-3 flex flex-wrap gap-2"><a v-for="(a,i) in m.attachments" :key="i" :href="`/requests/${data.record.id}/messages/${m.id}/attachments/${i}`" class="btn btn-outline max-w-full text-sm"><Download class="h-4 w-4 shrink-0" /><span class="truncate">{{a.name}}</span></a></div>
                    </details>
                </article>
            </section>
            <section v-if="pending.length" class="surface min-w-0 p-5">
                <h3 class="mb-4 font-semibold text-gray-900">Proposta da verificare</h3>
                <div class="mb-5 flex flex-wrap items-center gap-3">
                    <button v-if="data.ai_configured" class="btn btn-primary" :disabled="!mine || busy || !selected.length" @click="prepare(true)"><LoaderCircle v-if="aiLoading" class="h-4 w-4 animate-spin" /><Sparkles v-else class="h-4 w-4" />{{aiLoading ? 'Preparazione AI...' : 'Prepara con AI'}}</button>
                    <button :class="['btn',data.ai_configured ? 'btn-outline' : 'btn-primary']" :disabled="!mine || busy || !selected.length" @click="prepare(false)">Prepara proposta ({{selected.length}})</button><button class="btn btn-outline" :disabled="!mine || busy || !selected.length" @click="discardConfirm=!discardConfirm">Ignora selezionati</button>
                </div>
                <div v-if="replaceMode!==null" class="mb-4 flex flex-wrap items-center gap-3 text-sm text-gray-700">Sostituire titolo, descrizione e sottoattività attuali?<button class="btn btn-outline" :disabled="busy" @click="prepare(replaceMode,true)">Conferma</button><button class="btn btn-outline" @click="replaceMode=null">Annulla</button></div>
                <p v-if="aiLoading" role="status" class="mb-4 text-sm text-gray-500">Elaborazione dei messaggi selezionati...</p>
                <div v-if="discardConfirm" class="mb-4 flex flex-wrap items-center gap-3 text-sm text-gray-700">Ignorare i messaggi selezionati?<button class="btn btn-outline text-red-600" :disabled="busy" @click="resolve('discard')">Conferma</button></div>
                <template v-if="prepared">
                <p v-if="aiGenerated" class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700"><Sparkles class="h-4 w-4" />Bozza AI · Da verificare</p>
                <ul v-if="warnings.length" class="mb-4 list-disc space-y-1 pl-5 text-sm text-amber-700"><li v-for="(warning,i) in warnings" :key="i">{{warning}}</li></ul>
                <div v-if="candidates.length" class="mb-4 text-sm text-gray-700"><p v-for="c in candidates" :key="c.id">{{c.name}} · {{c.reason}}</p><p v-if="candidates.length>1" class="mt-2 text-amber-700">Più corrispondenze: seleziona il cliente.</p></div>
                <p v-else class="mb-4 text-sm text-gray-500">Nessuna corrispondenza certa: seleziona il cliente.</p>
                <div class="mb-5"><AppSelect v-model="action" :options="[{value:'create',label:'Crea nuova task'},{value:'link',label:'Aggiungi come commento a una task'}]" /></div>
                <form class="space-y-4" @submit.prevent="resolve(action)">
                    <template v-if="action==='create'">
                        <label class="block text-sm text-gray-700"><span class="mb-1 block">Tipo</span><AppSelect v-model="form.task_type" :options="[{value:'ongoing',label:'Continuativa'},{value:'task',label:'Task di progetto'}]" :disabled="!mine || busy" @change="form.project_id=''" /></label>
                        <label class="block text-sm text-gray-700">Titolo<input v-model="form.title" class="form-control" required maxlength="255" :disabled="!mine" /></label>
                        <div class="grid gap-4 sm:grid-cols-2"><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Cliente</span><ClientSelect v-model="form.client_id" :clients="data.clients" :disabled="!mine" @change="chooseClient" /></label><label v-if="form.task_type==='task'" class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Progetto</span><AppSelect v-model="form.project_id" :options="options(projects)" :disabled="!mine" searchable @change="chooseProject" /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Servizio</span><AppSelect v-model="form.service_id" :options="[{value:'',label:'Nessun servizio'},...options(data.services)]" :disabled="!mine" searchable /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Priorità</span><AppSelect v-model="form.priority" :options="[{value:'low',label:'Bassa'},{value:'medium',label:'Media'},{value:'high',label:'Alta'},{value:'urgent',label:'Urgente'}]" :disabled="!mine" /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Inizio</span><AppDateInput v-model="form.start_date" :disabled="!mine" /></label><label class="block min-w-0 text-sm text-gray-700"><span class="mb-1 block">Scadenza</span><AppDateInput v-model="form.due_date" :disabled="!mine" /></label></div>
                        <div ref="peopleRoot" class="relative">
                            <label class="mb-1 block text-sm text-gray-700">Assegnatari</label>
                            <button type="button" class="form-control task-people-control flex items-center justify-between gap-3 text-left" :disabled="!mine || busy" :aria-expanded="peopleOpen" aria-label="Assegnatari" @click="peopleOpen=!peopleOpen">
                                <span class="flex min-w-0 items-center gap-2"><span class="flex -space-x-2"><UserAvatar v-for="u in selectedUsers.slice(0,3)" :key="u.id" :user="u" size="xs" class="ring-2 ring-white" /></span><span class="truncate">{{selectedUsers.length===1 ? selectedUsers[0].name : `${selectedUsers.length} assegnatari`}}</span></span><ChevronDown class="h-4 w-4 shrink-0" />
                            </button>
                            <div v-if="peopleOpen" class="app-popover field-dropdown-menu absolute left-0 right-0 top-full z-[5300] mt-2 p-3">
                                <div class="people-avatar-picker max-h-44"><button v-for="u in data.users" :key="u.id" type="button" :disabled="!mine || busy" :class="['person-picker relative inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full transition',form.assignee_ids.includes(u.id)?'person-picker-selected bg-indigo-50 ring-2 ring-indigo-500 ring-offset-2 ring-offset-white':'bg-white/70 ring-1 ring-gray-200 hover:ring-indigo-200']" :aria-pressed="form.assignee_ids.includes(u.id)" :aria-label="u.name" :title="u.name" @click="togglePerson(u.id)"><UserAvatar :user="u" size="md" /></button></div>
                            </div>
                        </div>
                        <label class="block text-sm text-gray-700">Descrizione<textarea v-model="form.description" rows="10" class="form-control" :disabled="!mine" /></label>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm text-gray-700"><span>Sottoattività ({{form.subtasks.length}})</span><button type="button" class="icon-btn" title="Aggiungi sottoattività" aria-label="Aggiungi sottoattività" :disabled="!mine || busy" @click="form.subtasks.push('')"><Plus class="h-4 w-4" /></button></div>
                            <div v-for="(_,i) in form.subtasks" :key="i" class="flex items-center gap-2"><input v-model="form.subtasks[i]" :aria-label="`Sottoattività ${i+1}`" class="form-control min-w-0 flex-1" maxlength="255" required :disabled="!mine || busy" /><button type="button" class="icon-btn shrink-0 text-red-600" title="Rimuovi sottoattività" :aria-label="`Rimuovi sottoattività ${i+1}`" :disabled="!mine || busy" @click="form.subtasks.splice(i,1)"><Trash2 class="h-4 w-4" /></button></div>
                        </div>
                    </template>
                    <label v-else class="block text-sm text-gray-700"><span class="mb-1 block">Task</span><AppSelect v-model="form.task_id" :options="options(data.tasks)" searchable :disabled="!mine" /></label>
                    <div class="flex flex-wrap gap-3"><button class="btn btn-primary" :disabled="busy || !mine"><Check v-if="action==='create'" class="h-4 w-4" /><Link v-else class="h-4 w-4" />{{action==='create'?'Approva e crea task':'Aggiungi commento'}}</button></div>
                </form>
                </template>
            </section>
            <section v-else class="surface p-5"><h3 class="font-semibold text-gray-900">Tutti i messaggi sono stati verificati</h3></section>
        </div>
    </div>
</template>
