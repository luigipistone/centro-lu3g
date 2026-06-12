<script setup>
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    size: {
        type: String,
        default: 'md',
    },
});

const sizeClass = computed(() => ({
    xs: 'h-7 w-7 text-[10px]',
    sm: 'h-8 w-8 text-[11px]',
    md: 'h-10 w-10 text-xs',
    lg: 'h-14 w-14 text-base',
}[props.size] || 'h-10 w-10 text-xs'));

const initials = computed(() => {
    const source = props.user?.name || props.user?.email || '?';

    return source
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});
</script>

<template>
    <span
        :class="[
            'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-indigo-100/80 font-semibold text-indigo-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.76)]',
            sizeClass,
        ]"
    >
        <img
            v-if="user.avatar_url"
            :src="user.avatar_url"
            :alt="user.name || user.email || 'Avatar utente'"
            class="h-full w-full object-cover"
        />
        <span v-else>{{ initials }}</span>
    </span>
</template>
