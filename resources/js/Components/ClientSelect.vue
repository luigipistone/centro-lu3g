<script setup>
import AppSelect from '@/Components/AppSelect.vue';
import { clientOptions } from '@/utils/clientOptions';

defineProps({ modelValue: { default: '' }, clients: { type: Array, default: () => [] } });
defineEmits(['update:modelValue', 'change']);
</script>

<template>
    <AppSelect
        class="app-filter-control"
        :model-value="modelValue"
        :options="clientOptions(clients, { value: '', label: 'Nessun cliente' })"
        placeholder="Cliente"
        searchable
        @update:model-value="$emit('update:modelValue', $event)"
        @change="$emit('change', $event)"
    />
</template>
