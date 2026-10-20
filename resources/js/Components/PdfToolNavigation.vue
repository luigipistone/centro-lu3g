<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, ChevronDown, FileImage, FileText, Files, Images, Scissors } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const tools = [
    { route: 'tools.compress-pdf', label: 'Comprimi', icon: FileText },
    { route: 'tools.merge-pdf', label: 'Unisci', icon: Files },
    { route: 'tools.split-pdf', label: 'Dividi', icon: Scissors },
    { route: 'tools.images-to-pdf', label: 'Immagini in PDF', icon: FileImage },
    { route: 'tools.extract-images', label: 'Estrai immagini', icon: Images },
];
const menu = ref(null);

function closeOutside(event) {
    if (menu.value && !menu.value.contains(event.target)) menu.value.open = false;
}

onMounted(() => document.addEventListener('click', closeOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeOutside));
</script>

<template>
    <nav aria-label="Collegamenti agli altri strumenti PDF">
        <details ref="menu" class="group relative inline-block max-w-full" @keydown.esc="menu.open = false">
            <summary class="inline-flex min-h-10 max-w-full cursor-pointer list-none items-center gap-2 rounded-[var(--radius-sm)] border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))] [&::-webkit-details-marker]:hidden">
                <Files class="h-4 w-4 shrink-0 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                <span class="truncate">Vai a un altro strumento PDF</span>
                <ChevronDown class="h-4 w-4 shrink-0 transition group-open:rotate-180" :stroke-width="1.7" />
            </summary>
            <div class="absolute left-0 top-full z-20 mt-2 w-60 max-w-[calc(100vw-2rem)] overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white p-1 shadow-lg">
                <Link v-for="tool in tools.filter(item => !route().current(item.route))" :key="tool.route" :href="route(tool.route)" class="flex min-h-10 items-center gap-2 rounded-[var(--radius-sm)] px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-[hsl(var(--primary-app))] focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]">
                    <component :is="tool.icon" class="h-4 w-4 shrink-0" :stroke-width="1.7" />
                    <span class="min-w-0 flex-1">{{ tool.label }}</span>
                    <ArrowUpRight class="h-3.5 w-3.5 shrink-0 text-gray-400" :stroke-width="1.7" />
                </Link>
            </div>
        </details>
    </nav>
</template>
