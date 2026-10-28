<script setup>
import { Upload } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    hint: { type: String, required: true },
    ariaLabel: { type: String, required: true },
    disabled: { type: Boolean, default: false },
    showcase: { type: Boolean, default: false },
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
            'tool-dropzone relative flex min-h-44 flex-1 cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-[var(--radius)] border-2 border-dashed px-5 py-8 text-center transition focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]',
            dragDepth ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.08)]' : 'border-gray-200 bg-gray-50/70 hover:border-[hsl(var(--primary-app)/0.6)] hover:bg-[hsl(var(--primary-app)/0.04)]',
            disabled ? 'pointer-events-none opacity-60' : '',
            showcase ? 'tool-dropzone--showcase' : '',
            showcase && dragDepth ? 'tool-dropzone--dragging' : '',
        ]"
        @click="select"
        @keydown.enter.prevent="select"
        @keydown.space.prevent="select"
        @dragenter.prevent="dragDepth++"
        @dragover.prevent
        @dragleave.prevent="dragDepth = Math.max(0, dragDepth - 1)"
        @drop.prevent="onDrop"
    >
        <span v-if="showcase" aria-hidden="true" class="dropzone-orbit"><i /><i /><i /></span>
        <span class="dropzone-icon relative z-10 flex h-12 w-12 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]"><Upload class="h-6 w-6" :stroke-width="1.7" /></span>
        <span class="relative z-10 text-sm font-semibold text-gray-800">{{ showcase && dragDepth ? 'Rilascia i file qui' : label }}</span>
        <span class="relative z-10 text-xs text-gray-500">{{ hint }}</span>
    </div>
</template>

<style scoped>
.tool-dropzone--showcase {
    isolation: isolate;
}

.tool-dropzone--showcase::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 50%, hsl(var(--primary-app) / 0.16), transparent 68%);
    opacity: 0;
    transform: scale(0.75);
    transition: opacity 300ms ease, transform 500ms ease;
    pointer-events: none;
}

.tool-dropzone--dragging::before {
    opacity: 1;
    transform: scale(1.2);
}

.tool-dropzone--dragging {
    border-style: solid;
    box-shadow: inset 0 0 0 1px hsl(var(--primary-app) / 0.25), 0 0 0 5px hsl(var(--primary-app) / 0.08);
}

.tool-dropzone--dragging .dropzone-icon {
    animation: dropzone-pulse 1.4s ease-in-out infinite;
}

.dropzone-orbit {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}

.dropzone-orbit i {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 25px;
    height: 32px;
    border: 2px solid hsl(var(--primary-app) / 0.55);
    border-radius: 5px;
    background: hsl(var(--primary-app) / 0.12);
    opacity: 0;
}

.tool-dropzone--dragging .dropzone-orbit i {
    animation: dropzone-arrive 1.8s ease-in-out infinite;
}

.dropzone-orbit i:nth-child(1) { --from-x: -150px; --from-y: -55px; --angle: -25deg; }
.dropzone-orbit i:nth-child(2) { --from-x: 145px; --from-y: -40px; --angle: 22deg; animation-delay: 180ms; }
.dropzone-orbit i:nth-child(3) { --from-x: 35px; --from-y: 90px; --angle: 16deg; animation-delay: 360ms; }

@keyframes dropzone-arrive {
    0% { opacity: 0; transform: translate(var(--from-x), var(--from-y)) rotate(var(--angle)) scale(0.7); }
    30% { opacity: 0.9; }
    75%, 100% { opacity: 0; transform: translate(-50%, -50%) rotate(0deg) scale(0.25); }
}

@keyframes dropzone-pulse {
    50% { transform: translateY(-5px) scale(1.1); box-shadow: 0 0 0 12px hsl(var(--primary-app) / 0.08); }
}

@media (prefers-reduced-motion: reduce) {
    .tool-dropzone--showcase::before,
    .tool-dropzone--dragging .dropzone-icon,
    .tool-dropzone--dragging .dropzone-orbit i {
        animation: none;
        transition: none;
    }
    .tool-dropzone--dragging .dropzone-orbit i { display: none; }
}
</style>
