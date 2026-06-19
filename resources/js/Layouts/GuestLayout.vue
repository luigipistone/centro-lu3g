<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-[hsl(var(--background))] px-4 pt-8 sm:justify-center sm:pt-0"
    >
        <div class="text-center">
            <Link href="/" class="inline-flex flex-col items-center gap-3">
                <span class="brand-mark h-20 w-20 rounded-[1.75rem]">
                    <ApplicationLogo class="h-14 w-14" />
                </span>
                <span class="text-xl font-bold tracking-tight text-gray-950">Il Centro</span>
            </Link>
        </div>

        <div
            class="surface mt-6 w-full overflow-hidden px-6 py-5 sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
