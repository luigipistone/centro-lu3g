<script setup>
import { CalendarDays, ExternalLink, Video } from '@lucide/vue';
import UserAvatar from '@/Components/UserAvatar.vue';
defineProps({ task: Object, users: { type: Array, default: () => [] } });
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
    </section>
</template>
