<script setup>
import { Upload } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    hint: { type: String, required: true },
    ariaLabel: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'drop']);
const dragDepth = ref(0);

function onDrop(event) {
    dragDepth.value = 0;
    if (!props.disabled) emit('drop', event);
}

function select() {
    if (!props.disabled) emit('select');
}
</script>

<template>
    <div
        role="button"
        tabindex="0"
        :aria-label="ariaLabel"
        :aria-disabled="disabled"
        :class="[
            'flex min-h-44 flex-1 cursor-pointer flex-col items-center justify-center gap-2 rounded-[var(--radius)] border-2 border-dashed px-5 py-8 text-center transition focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]',
            dragDepth ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.08)]' : 'border-gray-200 bg-gray-50/70 hover:border-[hsl(var(--primary-app)/0.6)] hover:bg-[hsl(var(--primary-app)/0.04)]',
            disabled ? 'pointer-events-none opacity-60' : '',
        ]"
        @click="select"
        @keydown.enter.prevent="select"
        @keydown.space.prevent="select"
        @dragenter.prevent="dragDepth++"
        @dragover.prevent
        @dragleave.prevent="dragDepth = Math.max(0, dragDepth - 1)"
        @drop.prevent="onDrop"
    >
        <span class="flex h-12 w-12 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Upload class="h-6 w-6" :stroke-width="1.7" /></span>
        <span class="text-sm font-semibold text-gray-800">{{ label }}</span>
        <span class="text-xs text-gray-500">{{ hint }}</span>
    </div>
</template>
