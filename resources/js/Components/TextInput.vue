<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-2xl border-gray-300 bg-white/70 shadow-[inset_0_1px_0_rgba(255,255,255,0.82),0_1px_2px_rgba(28,42,73,0.04)] backdrop-blur-xl transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10"
        v-model="model"
        ref="input"
    />
</template>
