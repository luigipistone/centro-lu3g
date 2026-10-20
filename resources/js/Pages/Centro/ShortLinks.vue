<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Link2, LoaderCircle, Plus, Trash2 } from '@lucide/vue';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({ links: { type: Array, default: () => [] } });
const links = ref([...props.links]);
const url = ref('');
const busy = ref(false);
const deleting = ref(null);
const error = ref('');
const copied = ref(null);

async function createLink() {
    if (busy.value || !url.value.trim()) return;
    busy.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('tools.short-links.store'), { url: url.value.trim() });
        links.value.unshift(data.link);
        url.value = '';
        await copyLink(data.link);
    } catch (failure) {
        error.value = failure.response?.data?.errors?.url?.[0] || 'Impossibile creare il link. Riprova.';
    } finally {
        busy.value = false;
    }
}

async function copyLink(link) {
    try {
        await navigator.clipboard.writeText(link.short_url);
        copied.value = link.id;
        window.setTimeout(() => { if (copied.value === link.id) copied.value = null; }, 2200);
    } catch {
        error.value = 'Copia non disponibile. Seleziona il link per copiarlo.';
    }
}

async function deleteLink(link) {
    if (deleting.value || !window.confirm('Eliminare questo link breve? Non funzionerà più.')) return;
    deleting.value = link.id;
    error.value = '';
    try {
        await axios.delete(route('tools.short-links.destroy', link.id));
        links.value = links.value.filter((item) => item.id !== link.id);
    } catch {
        error.value = 'Impossibile eliminare il link. Riprova.';
    } finally {
        deleting.value = null;
    }
}
</script>

<template>
    <Head title="Link brevi" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" />Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Link brevi</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="surface p-5 sm:p-6">
                    <div class="flex items-center gap-3 text-gray-900"><span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Link2 class="h-6 w-6" /></span><h3 class="text-base font-semibold">Nuovo link</h3></div>
                    <form class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="createLink">
                        <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Indirizzo web
                            <input v-model="url" type="url" required maxlength="2048" placeholder="https://esempio.it/pagina" class="mt-2 block w-full min-w-0 rounded-[var(--radius-sm)] border-gray-200 text-sm focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" />
                        </label>
                        <button type="submit" :disabled="busy || !url.trim()" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app))] px-5 text-sm font-semibold text-white disabled:opacity-50"><LoaderCircle v-if="busy" class="h-4 w-4 animate-spin" /><Plus v-else class="h-4 w-4" />Crea link</button>
                    </form>
                    <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
                </div>

                <div class="surface overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 sm:px-6"><h3 class="text-base font-semibold text-gray-900">Link creati</h3></div>
                    <p v-if="!links.length" class="px-5 py-10 text-center text-sm text-gray-500">Nessun link breve creato.</p>
                    <div v-for="link in links" :key="link.id" class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0 flex-1">
                            <a :href="link.short_url" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-semibold text-[hsl(var(--primary-app))] hover:underline">{{ link.short_url }}</a>
                            <p class="mt-1 truncate text-sm text-gray-500" :title="link.url">{{ link.url }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="min-w-16 text-right text-sm text-gray-500">{{ link.clicks }} clic</span>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-sm)] text-gray-500 hover:bg-gray-100" :title="copied === link.id ? 'Copiato' : 'Copia link'" :aria-label="copied === link.id ? 'Copiato' : 'Copia link'" @click="copyLink(link)"><Check v-if="copied === link.id" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" /></button>
                            <button type="button" :disabled="deleting === link.id" class="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-sm)] text-red-500 hover:bg-red-50 disabled:opacity-50" title="Elimina link" aria-label="Elimina link" @click="deleteLink(link)"><Trash2 class="h-4 w-4" /></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
