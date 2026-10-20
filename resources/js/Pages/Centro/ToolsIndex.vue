<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileImage, FileText, Files, Film, ImageMinus, Images, Link2, QrCode, Scissors, TextCursorInput, Type } from '@lucide/vue';
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
                    <Link :href="route('tools.short-links')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Link2 class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3"><span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Link brevi</span><span class="mt-1 block text-sm text-gray-500">Accorcia e monitora i tuoi link.</span></span><ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" /></span>
                    </Link>
                    <Link :href="route('tools.video-to-webm')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Film class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3"><span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Video in WebM</span><span class="mt-1 block text-sm text-gray-500">Converti video MOV e MP4.</span></span><ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" /></span>
                    </Link>
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
                    <Link :href="route('tools.merge-pdf')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Files class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3"><span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Unisci PDF</span><span class="mt-1 block text-sm text-gray-500">Combina più PDF nell'ordine scelto.</span></span><ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" /></span>
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
                    <Link :href="route('tools.images-to-webp')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Images class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Converti in WebP</span><span class="mt-1 block text-sm text-gray-500">Converti più immagini insieme.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                    <Link :href="route('tools.remove-background')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><ImageMinus class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3"><span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Rimuovi sfondo</span><span class="mt-1 block text-sm text-gray-500">Ottieni un'immagine trasparente.</span></span><ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" /></span>
                    </Link>
                    <Link :href="route('tools.text-cleaner')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><TextCursorInput class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Pulisci testo</span><span class="mt-1 block text-sm text-gray-500">Sistema testo copiato e formattazione.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                    <Link :href="route('tools.styled-text')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Type class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Testo stilizzato</span><span class="mt-1 block text-sm text-gray-500">Crea varianti da copiare e incollare.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                    <Link :href="route('tools.qr-code')" class="surface group flex min-h-[166px] flex-col justify-between p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]">
                        <span class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><QrCode class="h-6 w-6" :stroke-width="1.7" /></span>
                        <span class="flex items-end justify-between gap-3">
                            <span class="min-w-0"><span class="block text-base font-semibold text-gray-900">Crea QR Code</span><span class="mt-1 block text-sm text-gray-500">Genera codici statici per link e contenuti.</span></span>
                            <ArrowRight class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
