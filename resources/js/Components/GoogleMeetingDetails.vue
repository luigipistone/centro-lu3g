<script setup>
import { CalendarDays, ExternalLink, Video } from '@lucide/vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppSelect from '@/Components/AppSelect.vue';
import ClientSelect from '@/Components/ClientSelect.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
const props = defineProps({ task: Object, users: { type: Array, default: () => [] }, clients: { type: Array, default: () => [] }, services: { type: Array, default: () => [] } });
const form = useForm({ client_id: props.task.client_id || '', service_id: props.task.service_id || '', priority: props.task.priority || 'medium', status: props.task.status || 'todo' });
watch(() => props.task.id, () => {
    form.client_id = props.task.client_id || '';
    form.service_id = props.task.service_id || '';
    form.priority = props.task.priority || 'medium';
    form.status = props.task.status || 'todo';
    form.clearErrors();
});
const priorities = [{ value: 'low', label: 'Bassa' }, { value: 'medium', label: 'Media' }, { value: 'high', label: 'Alta' }, { value: 'urgent', label: 'Urgente' }];
const statuses = [{ value: 'todo', label: 'Da fare' }, { value: 'in_progress', label: 'In corso' }, { value: 'in_review', label: 'Review' }, { value: 'done', label: 'Fatte' }];
function save() { form.put(route('tasks.update', props.task.id), { preserveScroll: true, preserveState: true }); }
function time(value, allDay) { return value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('it-IT', allDay ? { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Rome' } : { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Rome' }) : ''; }
function day(value) { return value ? new Date(value.slice(0, 10) + 'T12:00:00').toLocaleDateString('it-IT') : ''; }
</script>
<template>
    <section class="space-y-4 min-w-0">
        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500"><CalendarDays class="h-4 w-4" />Google Calendar<span v-if="task.google_cancelled_at" class="text-red-600">· Annullato o fuori dal calendario sincronizzato</span></div>
        <h3 class="text-lg font-semibold text-gray-900 break-words">{{ task.title }}</h3>
        <p class="text-sm text-gray-700">{{ task.google_all_day ? `${day(task.start_date)} – ${day(task.due_date)} · Tutto il giorno` : `${time(task.google_starts_at)} – ${time(task.google_ends_at)}` }}</p>
        <p v-if="task.location" class="text-sm text-gray-700 break-words">{{ task.location }}</p>
        <div class="flex flex-wrap gap-3"><a v-if="task.google_event_url" :href="task.google_event_url" target="_blank" rel="noopener noreferrer" class="btn btn-outline"><ExternalLink class="h-4 w-4" />Apri in Google</a><a v-if="task.google_meet_url" :href="task.google_meet_url" target="_blank" rel="noopener noreferrer" class="btn btn-primary"><Video class="h-4 w-4" />Partecipa</a></div>
        <div class="flex flex-wrap gap-2"><span v-for="user in users.filter(u => (task.assignee_ids || []).includes(u.id))" :key="user.id" class="flex items-center gap-2 text-sm text-gray-700"><UserAvatar :user="user" size="sm" />{{ user.name }}</span></div>
        <div v-if="task.description" class="text-sm text-gray-700 break-words" v-html="task.description"></div>
        <fieldset :disabled="form.processing" class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Stato</label>
                <AppSelect v-model="form.status" :options="statuses" @change="save" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Cliente</label>
                <ClientSelect v-model="form.client_id" :clients="clients" @change="save" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Servizio</label>
                <AppSelect v-model="form.service_id" :options="[{ value: '', label: 'Nessun servizio' }, ...services.map(s => ({ value: s.id, label: s.name }))]" searchable @change="save" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Priorità</label>
                <AppSelect v-model="form.priority" :options="priorities" @change="save" />
            </div>
        </fieldset>
        <p v-if="form.processing" role="status" class="text-sm text-gray-500">Salvataggio in corso...</p>
        <p v-else-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-600">{{ Object.values(form.errors)[0] }}</p>
        <p v-else-if="form.recentlySuccessful" role="status" class="text-sm text-gray-500">Salvato</p>
    </section>
</template>
