<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    modelValue: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);
const hex = ref(props.modelValue.toUpperCase());

watch(() => props.modelValue, value => { hex.value = value.toUpperCase(); });

function updateHex(event) {
    hex.value = event.target.value.toUpperCase();
    if (/^#?[0-9A-F]{6}$/.test(hex.value)) {
        emit('update:modelValue', `#${hex.value.replace('#', '').toLowerCase()}`);
    }
}

function restoreInvalidHex() {
    if (!/^#?[0-9A-F]{6}$/.test(hex.value)) hex.value = props.modelValue.toUpperCase();
}
</script>

<template>
    <div class="min-w-0">
        <label class="block text-sm font-medium text-gray-700">{{ label }}
            <input :value="modelValue" type="color" class="qr-color mt-2 block h-11 w-full cursor-pointer rounded-[var(--radius-sm)] border border-gray-200" @input="emit('update:modelValue', $event.target.value)" />
        </label>
        <input :value="hex" type="text" inputmode="text" maxlength="7" spellcheck="false" :aria-label="`Codice HEX ${label.toLowerCase()}`" placeholder="#172033" class="mt-2 block w-full min-w-0 rounded-[var(--radius-sm)] border-gray-200 font-mono text-xs uppercase text-gray-700 focus:border-[hsl(var(--primary-app))] focus:ring-[hsl(var(--primary-app)/0.18)]" @input="updateHex" @blur="restoreInvalidHex" />
    </div>
</template>

<style scoped>
.qr-color { padding: 0; background: transparent; overflow: hidden; }
.qr-color::-webkit-color-swatch-wrapper { padding: 0; }
.qr-color::-webkit-color-swatch { border: 0; }
.qr-color::-moz-color-swatch { border: 0; }
</style>
