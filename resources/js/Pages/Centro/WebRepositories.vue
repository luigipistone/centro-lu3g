<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, ExternalLink, GitBranch, LockKeyhole, RefreshCw, Search } from '@lucide/vue';

const props = defineProps({ repositories: Array, configured: Boolean, error: String });
const search = ref('');
const refreshing = ref(false);
const isSuperadmin = computed(() => usePage().props.auth?.user?.role === 'superadmin');
const filtered = computed(() => (props.repositories || []).filter(repo =>
    `${repo.name} ${repo.description || ''}`.toLocaleLowerCase('it').includes(search.value.trim().toLocaleLowerCase('it')),
));

function refresh() {
    router.reload({ data: { refresh: 1 }, only: ['repositories', 'configured', 'error'], onStart: () => { refreshing.value = true; }, onFinish: () => { refreshing.value = false; } });
}
</script>

<template>
    <Head title="Repository Web" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="route('tools.index')" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-gray-500 hover:text-[hsl(var(--primary-app))]"><ArrowLeft class="h-4 w-4" :stroke-width="1.7" />Torna agli strumenti</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Repository Web</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <div v-if="!configured" class="surface p-6">
                    <p class="font-semibold text-gray-900">GitHub non è ancora collegato</p>
                    <p class="mt-1 text-sm text-gray-500">Serve un token per mostrare le repository dell’organizzazione.</p>
                    <Link v-if="isSuperadmin" :href="route('settings.index', { tab: 'github' })" class="btn btn-primary mt-4">Configura GitHub</Link>
                </div>
                <div v-else-if="error" class="surface border border-red-200 p-5 text-sm text-red-700">{{ error }}</div>
                <template v-else>
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <label class="min-w-0 flex-1 sm:max-w-md"><span class="mb-1 block text-sm font-medium text-gray-700">Cerca repository</span><span class="relative block"><Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" /><input v-model="search" class="form-control mt-0 pl-10" type="search" placeholder="Nome o descrizione" /></span></label>
                        <button type="button" class="btn btn-outline" :disabled="refreshing" @click="refresh"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" :stroke-width="1.7" />Aggiorna</button>
                    </div>
                    <p class="text-sm text-gray-500">{{ filtered.length }} repository · lu3gofficial</p>
                    <div v-if="filtered.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <a v-for="repo in filtered" :key="repo.url" :href="repo.url" target="_blank" rel="noopener noreferrer" class="surface group flex min-h-[150px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                            <div class="flex min-w-0 items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><GitBranch class="h-5 w-5" :stroke-width="1.7" /></span><span class="min-w-0"><span class="block break-words font-semibold text-gray-900">{{ repo.name }}</span><span v-if="repo.description" class="mt-1 line-clamp-2 block text-sm text-gray-500">{{ repo.description }}</span></span></div>
                            <span class="mt-4 flex items-center justify-between gap-2 text-xs text-gray-500"><span class="flex items-center gap-1"><LockKeyhole v-if="repo.private" class="h-3.5 w-3.5" />{{ repo.private ? 'Privata' : 'Pubblica' }}<span v-if="repo.language">· {{ repo.language }}</span></span><ExternalLink class="h-4 w-4 shrink-0 transition group-hover:text-[hsl(var(--primary-app))]" /></span>
                        </a>
                    </div>
                    <div v-else class="surface p-6 text-sm text-gray-500">{{ search ? 'Nessuna repository corrisponde alla ricerca.' : 'Nessuna repository con Plugin_Wordpress = yes.' }}</div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
