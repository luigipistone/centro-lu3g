<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-2xl border border-white/70 bg-white/70 px-4 py-2 text-xs font-semibold uppercase tracking-normal text-gray-700 shadow-[0_8px_22px_rgba(28,42,73,0.08),inset_0_1px_0_rgba(255,255,255,0.64)] backdrop-blur-xl transition duration-150 ease-in-out hover:bg-white/90 hover:text-gray-950 focus:outline-none focus:ring-4 focus:ring-indigo-500/15 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
