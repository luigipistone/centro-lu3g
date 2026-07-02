<script setup>
import { X } from '@lucide/vue';
import { useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Cerca...',
    },
    inputClass: {
        type: [String, Array, Object],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
const attrs = useAttrs();

function updateValue(event) {
    emit('update:modelValue', event.target.value);
}

function clearValue() {
    emit('update:modelValue', '');
}
</script>

<template>
    <div class="relative">
        <input
            v-bind="attrs"
            type="search"
            :value="modelValue"
            :placeholder="placeholder"
            :class="['form-control pr-10 [&::-webkit-search-cancel-button]:appearance-none', inputClass]"
            @input="updateValue"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute right-2 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-[hsl(var(--primary-app)/0.22)]"
            aria-label="Cancella ricerca"
            @click="clearValue"
        >
            <X class="h-4 w-4" :stroke-width="1.8" />
        </button>
    </div>
</template>
