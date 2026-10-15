<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileImage, FileText, Images, Scissors } from '@lucide/vue';
</script>

<template>
    <Head title="Strumenti" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Strumenti</h2>
                <p class="text-sm text-gray-500">Utilità per i file e il lavoro quotidiano.</p>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <div class="grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link :href="route('tools.compress-pdf')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><FileText class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Comprimi PDF</span><span class="mt-1 block text-sm text-gray-500">Riduci le dimensioni dei PDF.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                    <Link :href="route('tools.split-pdf')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Scissors class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Dividi PDF</span><span class="mt-1 block text-sm text-gray-500">Separa intervalli o estrai pagine.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                    <Link :href="route('tools.images-to-pdf')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><FileImage class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Immagini in PDF</span><span class="mt-1 block text-sm text-gray-500">Converti e ordina le immagini.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                    <Link :href="route('tools.extract-images')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Images class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Estrai immagini</span><span class="mt-1 block text-sm text-gray-500">Salva le immagini contenute nei PDF.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
