<script setup>
import { Check, ChevronDown, Search } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean, null],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Seleziona',
    },
    searchable: {
        type: Boolean,
        default: false,
    },
    searchThreshold: {
        type: Number,
        default: 8,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);
const attrs = useAttrs();
const root = ref(null);
const open = ref(false);
const query = ref('');

const normalizedOptions = computed(() => props.options.map((option) => {
    if (typeof option === 'object' && option !== null) {
        return {
            value: Object.prototype.hasOwnProperty.call(option, 'value') ? option.value : option.id,
            label: option.label ?? option.name ?? option.title ?? option.value ?? option.id ?? '',
            disabled: Boolean(option.disabled),
        };
    }

    return { value: option, label: option, disabled: false };
}));

const selectedOption = computed(() => normalizedOptions.value.find((option) => String(option.value) === String(props.modelValue)));
const selectedLabel = computed(() => selectedOption.value?.label || props.placeholder);
const canSearch = computed(() => props.searchable || normalizedOptions.value.length >= props.searchThreshold);
const filteredOptions = computed(() => {
    const needle = query.value.trim().toLowerCase();
    if (!needle) return normalizedOptions.value;

    return normalizedOptions.value.filter((option) => String(option.label).toLowerCase().includes(needle));
});

function toggle() {
    if (props.disabled) return;

    open.value = !open.value;
    if (open.value) query.value = '';
}

function selectOption(option) {
    if (option.disabled) return;

    emit('update:modelValue', option.value);
    emit('change', option.value);
    open.value = false;
    query.value = '';
}

function closeOnOutside(event) {
    if (!open.value) return;
    if (root.value?.contains(event.target)) return;

    open.value = false;
}

onMounted(() => document.addEventListener('click', closeOnOutside));
onUnmounted(() => document.removeEventListener('click', closeOnOutside));
</script>

<template>
    <div ref="root" :class="['relative w-full', attrs.class]">
        <button
            type="button"
            class="form-control mt-0 flex h-[38px] items-center justify-between gap-3 text-left"
            :class="open ? 'border-indigo-300 ring-4 ring-indigo-500/10' : ''"
            :disabled="disabled"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <span :class="['truncate', selectedOption ? 'text-gray-800' : 'text-gray-400']">{{ selectedLabel }}</span>
            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', open ? 'rotate-180 text-indigo-500' : '']" :stroke-width="1.7" />
        </button>

        <div
            v-if="open"
            class="app-popover field-dropdown-menu absolute left-0 right-0 top-full z-[5300] mt-2 p-3"
            @click.stop
        >
            <div v-if="canSearch" class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" :stroke-width="1.7" />
                <input
                    v-model="query"
                    type="search"
                    class="form-control mt-0 pl-9"
                    :placeholder="`Cerca...`"
                    autocomplete="off"
                />
            </div>

            <div class="mt-2 max-h-56 overflow-y-auto pr-1">
                <button
                    v-for="option in filteredOptions"
                    :key="`${option.value}-${option.label}`"
                    type="button"
                    :disabled="option.disabled"
                    :class="[
                        'field-dropdown-option flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50',
                        String(modelValue) === String(option.value) ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700',
                    ]"
                    @click="selectOption(option)"
                >
                    <span class="truncate">{{ option.label }}</span>
                    <Check v-if="String(modelValue) === String(option.value)" class="h-4 w-4 shrink-0" :stroke-width="1.8" />
                </button>
                <p v-if="!filteredOptions.length" class="px-3 py-2 text-sm text-gray-500">Nessun risultato</p>
            </div>
        </div>
    </div>
</template>
