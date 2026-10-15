<script setup>
import { faviconUrls } from '@/utils/faviconUrls.js';
import { Bookmark } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({ url: { type: String, required: true } });
const sources = computed(() => faviconUrls(props.url));
const sourceIndex = ref(0);
watch(() => props.url, () => { sourceIndex.value = 0; });
const source = computed(() => sources.value[sourceIndex.value]);

function tryNext() {
    sourceIndex.value += 1;
}
</script>

<template>
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[hsl(var(--primary-app)/0.10)] text-[hsl(var(--primary-app))]">
        <img v-if="source" :key="source" :src="source" alt="" aria-hidden="true" class="h-6 w-6 object-contain" loading="lazy" referrerpolicy="no-referrer" @error="tryNext" />
        <Bookmark v-else class="h-5 w-5" :stroke-width="1.7" aria-hidden="true" />
    </span>
</template>
