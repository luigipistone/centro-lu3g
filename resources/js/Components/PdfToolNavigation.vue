<script setup>
import { Link } from '@inertiajs/vue3';
import { FileImage, FileText, Files, Images, Scissors } from '@lucide/vue';

const tools = [
    { route: 'tools.compress-pdf', label: 'Comprimi', icon: FileText },
    { route: 'tools.merge-pdf', label: 'Unisci', icon: Files },
    { route: 'tools.split-pdf', label: 'Dividi', icon: Scissors },
    { route: 'tools.images-to-pdf', label: 'Immagini in PDF', icon: FileImage },
    { route: 'tools.extract-images', label: 'Estrai immagini', icon: Images },
];
</script>

<template>
    <nav aria-label="Altri strumenti PDF" class="min-w-0 overflow-x-auto border-b border-gray-200">
        <div class="flex min-w-max items-center gap-5 sm:gap-7">
            <Link
                v-for="tool in tools"
                :key="tool.route"
                :href="route(tool.route)"
                :aria-current="route().current(tool.route) ? 'page' : undefined"
                :class="[
                    'inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-1 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]',
                    route().current(tool.route)
                        ? 'border-[hsl(var(--primary-app))] text-[hsl(var(--primary-app))]'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800',
                ]"
            ><component :is="tool.icon" class="h-4 w-4" :stroke-width="1.7" />{{ tool.label }}</Link>
        </div>
    </nav>
</template>
