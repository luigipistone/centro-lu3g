<script setup>
import { Upload, X } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
    modelValue: { type: Object, default: null },
    error: { type: String, default: '' },
    allowImages: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const input = ref(null);
const dragDepth = ref(0);
const localError = ref('');
const maxBytes = 20 * 1024 * 1024;

function setFile(file) {
    localError.value = '';
    if (!file) return;
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    const isImage = props.allowImages && (
        ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
        || /\.(jpe?g|png|webp)$/i.test(file.name)
    );
    if (!isPdf && !isImage) {
        localError.value = props.allowImages ? 'Seleziona un PDF o un’immagine JPG, PNG o WebP.' : 'Seleziona un file PDF.';
        emit('update:modelValue', null);
        return;
    }
    if (file.size > maxBytes) {
        localError.value = 'Il file non può superare 20 MB.';
        emit('update:modelValue', null);
        return;
    }
    emit('update:modelValue', file);
}

function onInput(event) {
    setFile(event.target.files?.[0]);
    event.target.value = '';
}

function onDrop(event) {
    dragDepth.value = 0;
    setFile(event.dataTransfer?.files?.[0]);
}

function clearFile() {
    localError.value = '';
    emit('update:modelValue', null);
}
</script>

<template>
    <div>
        <div class="relative mt-2">
        <div
            role="button"
            tabindex="0"
            :aria-label="modelValue ? `File selezionato: ${modelValue.name}. Scegli un altro file` : allowImages ? 'Seleziona o trascina un PDF o un’immagine' : 'Seleziona o trascina un PDF'"
            :class="['flex min-h-20 cursor-pointer items-center gap-3 rounded-[var(--radius)] border border-dashed px-4 py-3 text-sm transition focus-visible:outline-2 focus-visible:outline-[hsl(var(--primary-app))]', modelValue ? 'pr-12' : '', dragDepth ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app)/0.08)]' : 'border-gray-200 bg-white/70 hover:border-[hsl(var(--primary-app))] hover:bg-[hsl(var(--primary-app)/0.04)]']"
            @click="input?.click()"
            @keydown.enter.prevent="input?.click()"
            @keydown.space.prevent="input?.click()"
            @dragenter.prevent="dragDepth++"
            @dragover.prevent
            @dragleave.prevent="dragDepth = Math.max(0, dragDepth - 1)"
            @drop.prevent="onDrop"
        >
            <span class="flex min-w-0 items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
                    <Upload class="h-4 w-4" :stroke-width="1.7" />
                </span>
                <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-700">{{ modelValue?.name || (allowImages ? 'Trascina qui un PDF o un’immagine' : 'Trascina qui il PDF o selezionalo') }}</span>
                    <span class="block text-xs text-gray-500">{{ modelValue ? `${(modelValue.size / 1024 / 1024).toFixed(1)} MB` : (allowImages ? 'PDF, JPG, PNG o WebP · max 20 MB' : 'PDF · max 20 MB') }}</span>
                </span>
            </span>
        </div>
        <button v-if="modelValue" type="button" class="icon-btn absolute right-3 top-1/2 h-7 w-7 -translate-y-1/2" title="Rimuovi file" @click="clearFile"><X class="h-4 w-4" :stroke-width="1.7" /></button>
        </div>
        <input ref="input" type="file" :accept="allowImages ? 'application/pdf,.pdf,image/jpeg,.jpg,.jpeg,image/png,.png,image/webp,.webp' : 'application/pdf,.pdf'" class="hidden" @change="onInput" />
        <p v-if="localError || error" class="mt-1 text-sm text-red-600">{{ localError || error }}</p>
    </div>
</template>
