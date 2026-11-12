<script setup>
import { ref, watch, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import GmailRequestsPanel from '@/Components/GmailRequestsPanel.vue';
import MailRequestReview from '@/Components/MailRequestReview.vue';
import { ArrowLeft, Mail, ChevronRight, RefreshCw, Search } from '@lucide/vue';
const props=defineProps({requests:Object,filters:Object});
const search=ref(props.filters.search || ''); const status=ref(props.filters.status || 'pending'); const mine=ref(Boolean(props.filters.mine));
const connections=ref(new URLSearchParams(location.search).has('connections')); const selected=ref(null); const error=ref(''); const loading=ref(false);
let timeout;
function reload(){router.get('/requests',{search:search.value,status:status.value,...(mine.value?{mine:1}:{})},{preserveState:true,preserveScroll:true,replace:true});}
watch([search,status,mine],()=>{clearTimeout(timeout);timeout=setTimeout(reload,300);}); onUnmounted(()=>clearTimeout(timeout));
async function open(id){loading.value=true;error.value='';try{selected.value=(await axios.get(`/requests/${id}`)).data;}catch(e){error.value=e.response?.data?.message || 'Richiesta non disponibile.';}finally{loading.value=false;}}
function resolved(){selected.value=null;reload();}
const labels={pending:'Da verificare',converted:'Elaborata',discarded:'Scartata'};
</script>
<template>
    <Head title="Richieste clienti" />
    <AuthenticatedLayout>
        <template #header><div class="flex flex-col gap-1"><button v-if="selected" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500" @click="selected=null"><ArrowLeft class="h-4 w-4" />Torna alle richieste</button><h2 class="text-xl font-semibold leading-tight text-gray-800">{{selected?selected.record.subject:'Richieste clienti'}}</h2></div></template>
        <div class="mx-auto max-w-[1600px] space-y-5 px-4 py-8 sm:px-6 lg:px-8">
            <p v-if="error" role="alert" class="text-sm text-red-600">{{error}}</p>
            <MailRequestReview v-if="selected" :key="`${selected.record.id}-${selected.record.version}-${selected.record.reviewer_id}`" :data="selected" @refresh="open(selected.record.id)" @resolved="resolved" />
            <template v-else>
                <div class="flex flex-wrap items-center gap-3"><label class="relative min-w-0 flex-1"><Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" /><input v-model="search" class="form-control" style="padding-left:2.5rem;margin-top:0" placeholder="Cerca richieste" aria-label="Cerca richieste" /></label><div class="w-44"><AppSelect v-model="status" :options="[{value:'pending',label:'Da verificare'},{value:'converted',label:'Elaborate'},{value:'discarded',label:'Scartate'},{value:'all',label:'Tutte'}]" /></div><label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="mine" type="checkbox" />Le mie</label><button class="icon-btn" title="Aggiorna elenco" aria-label="Aggiorna elenco" @click="reload"><RefreshCw class="h-4 w-4" :class="{'animate-spin':loading}" /></button><button class="btn btn-outline" @click="connections=!connections"><Mail class="h-4 w-4" />La mia casella</button></div>
                <GmailRequestsPanel v-if="connections" />
                <section class="surface divide-y divide-gray-200">
                    <button v-for="item in requests.data" :key="item.id" class="flex w-full items-center gap-4 p-4 text-left transition hover:bg-gray-50 sm:p-5" :disabled="loading" @click="open(item.id)"><span class="min-w-0 flex-1"><span class="block truncate font-semibold text-gray-900">{{item.subject}}</span><span class="mt-1 block truncate text-sm text-gray-500">{{item.sender || 'Mittente da verificare'}} · {{item.manager_name}}</span><span class="mt-1 block text-xs text-gray-500">{{labels[item.status]}}<template v-if="item.reviewer_name"> · In carico a {{item.reviewer_name}}</template><span v-if="item.has_updates" class="text-amber-700"> · Nuove email</span><span v-if="item.duplicate_of" class="text-amber-700"> · Possibile duplicato</span></span></span><ChevronRight class="h-4 w-4 shrink-0 text-gray-500" /></button>
                    <p v-if="!requests.data.length" class="p-6 text-sm text-gray-500">Nessuna richiesta {{status==='pending'?'da verificare':''}}.</p>
                </section>
                <nav class="flex items-center justify-between gap-3 text-sm text-gray-600"><Link v-if="requests.prev_page_url" :href="requests.prev_page_url" class="btn btn-outline" preserve-scroll>Precedente</Link><span>Pagina {{requests.current_page}} di {{requests.last_page}} · {{requests.total}} richieste</span><Link v-if="requests.next_page_url" :href="requests.next_page_url" class="btn btn-outline" preserve-scroll>Successiva</Link></nav>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
