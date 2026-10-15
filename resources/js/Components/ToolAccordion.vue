<script setup>
import { ChevronDown } from '@lucide/vue';

defineProps({
    title: { type: String, required: true },
    summary: { type: String, default: '' },
    open: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    panelId: { type: String, required: true },
});

defineEmits(['toggle']);
</script>

<template>
    <div :class="open ? 'bg-[hsl(var(--primary-app)/0.04)]' : 'bg-white'">
        <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-[hsl(var(--primary-app)/0.04)] focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]" :disabled="disabled" :aria-expanded="open" :aria-controls="panelId" @click="$emit('toggle')">
            <span class="min-w-0"><span :class="['block text-sm font-semibold', open ? 'text-[hsl(var(--primary-app))]' : 'text-gray-900']">{{ title }}</span><span v-if="summary" class="block text-xs text-gray-500">{{ summary }}</span></span>
            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200', open ? 'rotate-180 text-[hsl(var(--primary-app))]' : '']" :stroke-width="1.7" />
        </button>
        <div v-if="open" :id="panelId" class="border-t border-[hsl(var(--primary-app)/0.12)] px-4 pb-4 pt-3"><slot /></div>
    </div>
</template>
