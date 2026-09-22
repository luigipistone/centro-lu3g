<script setup>
import ClearableSearchInput from '@/Components/ClearableSearchInput.vue';
import { Check, ChevronDown, ChevronRight } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean, Array, null],
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
    multiple: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);
const attrs = useAttrs();
const root = ref(null);
const menu = ref(null);
const open = ref(false);
const query = ref('');
const menuStyle = ref({});
const expandedGroups = ref([]);
const floatingUiCloseEvent = 'centro:close-floating-ui';

function normalizeOption(option, depth = 0, parentValue = null) {
    if (typeof option === 'object' && option !== null) {
        const value = Object.prototype.hasOwnProperty.call(option, 'value') ? option.value : option.id;
        return {
            value,
            label: option.label ?? option.name ?? option.title ?? value ?? '',
            disabled: Boolean(option.disabled),
            depth,
            parentValue,
            children: (option.children || []).map((child) => normalizeOption(child, depth + 1, value)),
        };
    }

    return { value: option, label: option, disabled: false, depth, parentValue, children: [] };
}

const normalizedTree = computed(() => props.options.map((option) => normalizeOption(option)));
const normalizedOptions = computed(() => normalizedTree.value.flatMap((option) => [option, ...option.children]));

const selectedValues = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));
const selectedOption = computed(() => normalizedOptions.value.find((option) => String(option.value) === String(props.modelValue)));
const selectedOptions = computed(() => normalizedOptions.value.filter((option) => selectedValues.value.some((value) => String(value) === String(option.value))));
const selectedLabel = computed(() => {
    if (!props.multiple) return selectedOption.value?.label || props.placeholder;
    if (!selectedOptions.value.length) return props.placeholder;
    if (selectedOptions.value.length === 1) return selectedOptions.value[0].label;

    return `${selectedOptions.value.length} selezionati`;
});
const canSearch = computed(() => props.searchable || normalizedOptions.value.length >= props.searchThreshold);
const filteredOptions = computed(() => {
    const needle = query.value.trim().toLowerCase();
    if (needle) return normalizedOptions.value.filter((option) => String(option.label).toLowerCase().includes(needle));

    return normalizedTree.value.flatMap((option) => [
        option,
        ...(expandedGroups.value.some((value) => String(value) === String(option.value)) ? option.children : []),
    ]);
});

function toggle() {
    if (props.disabled) return;

    const nextOpen = !open.value;
    if (nextOpen) window.dispatchEvent(new CustomEvent(floatingUiCloseEvent));
    open.value = nextOpen;
    if (open.value) {
        query.value = '';
        updateMenuPosition();
    }
}

function selectOption(option) {
    if (option.disabled) return;

    if (props.multiple) {
        const next = new Set(selectedValues.value);
        const current = selectedValues.value.find((value) => String(value) === String(option.value));
        if (current !== undefined) next.delete(current);
        else next.add(option.value);

        const value = Array.from(next);
        emit('update:modelValue', value);
        emit('change', value);
        return;
    }

    emit('update:modelValue', option.value);
    emit('change', option.value);
    open.value = false;
    query.value = '';
}

function toggleGroup(option) {
    const isExpanded = expandedGroups.value.some((value) => String(value) === String(option.value));
    expandedGroups.value = isExpanded
        ? expandedGroups.value.filter((value) => String(value) !== String(option.value))
        : [...expandedGroups.value, option.value];
    updateMenuPosition();
}

function isGroupExpanded(option) {
    return expandedGroups.value.some((value) => String(value) === String(option.value));
}

function isSelected(option) {
    if (props.multiple) {
        return selectedValues.value.some((value) => String(value) === String(option.value));
    }

    return String(props.modelValue) === String(option.value);
}

function closeOnOutside(event) {
    if (!open.value) return;
    if (root.value?.contains(event.target)) return;
    if (menu.value?.contains(event.target)) return;

    open.value = false;
}

function closeFromFloatingUiEvent() {
    open.value = false;
}

function updateMenuPosition() {
    nextTick(() => {
        const rect = root.value?.getBoundingClientRect();
        if (!rect) return;
        const viewportPadding = 12;
        const width = Math.min(Math.max(rect.width, 220), window.innerWidth - (viewportPadding * 2));
        const menuHeight = menu.value?.offsetHeight || 340;
        const left = Math.min(Math.max(viewportPadding, rect.right - width), window.innerWidth - width - viewportPadding);
        const hasSpaceBelow = rect.bottom + 8 + menuHeight <= window.innerHeight - viewportPadding;
        const top = hasSpaceBelow
            ? rect.bottom + 8
            : Math.max(viewportPadding, rect.top - menuHeight - 8);

        menuStyle.value = {
            left: `${left}px`,
            top: `${top}px`,
            width: `${width}px`,
        };
    });
}

function closeOnViewportChange() {
    if (!open.value) return;

    updateMenuPosition();
}

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutside, true);
    window.addEventListener(floatingUiCloseEvent, closeFromFloatingUiEvent);
    window.addEventListener('resize', closeOnViewportChange);
    window.addEventListener('scroll', closeOnViewportChange, true);
});
onUnmounted(() => {
    document.removeEventListener('pointerdown', closeOnOutside, true);
    window.removeEventListener(floatingUiCloseEvent, closeFromFloatingUiEvent);
    window.removeEventListener('resize', closeOnViewportChange);
    window.removeEventListener('scroll', closeOnViewportChange, true);
});
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
            <span :class="['truncate', props.multiple ? (selectedOptions.length ? 'text-gray-800' : 'text-gray-400') : (selectedOption ? 'text-gray-800' : 'text-gray-400')]">{{ selectedLabel }}</span>
            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', open ? 'rotate-180 text-indigo-500' : '']" :stroke-width="1.7" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menu"
                class="app-popover field-dropdown-menu fixed z-[7000] p-3"
                :style="menuStyle"
                @click.stop
            >
                <div v-if="canSearch">
                    <ClearableSearchInput
                        v-model="query"
                        input-class="mt-0"
                        :placeholder="`Cerca...`"
                        autocomplete="off"
                    />
                </div>

                <div class="mt-2 max-h-56 overflow-y-auto pr-1">
                    <div
                        v-for="option in filteredOptions"
                        :key="`${option.value}-${option.label}`"
                        :class="[
                            'field-dropdown-option flex w-full items-center justify-between gap-1 px-2 py-1 text-left text-sm transition hover:bg-indigo-50',
                            isSelected(option) ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700',
                        ]"
                    >
                        <button
                            type="button"
                            :disabled="option.disabled"
                            class="flex min-w-0 flex-1 items-center px-1 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                            :style="{ paddingLeft: `${0.25 + (option.depth * 1.25)}rem` }"
                            @click="selectOption(option)"
                        >
                            <span class="truncate">{{ option.label }}</span>
                        </button>
                        <button v-if="option.children.length && !query" type="button" class="icon-btn h-7 w-7 shrink-0" :title="isGroupExpanded(option) ? 'Comprimi' : 'Espandi'" @click.stop="toggleGroup(option)">
                            <ChevronRight :class="['h-4 w-4 transition-transform', isGroupExpanded(option) ? 'rotate-90' : '']" :stroke-width="1.8" />
                        </button>
                        <Check v-if="isSelected(option)" class="h-4 w-4 shrink-0" :stroke-width="1.8" />
                    </div>
                    <p v-if="!filteredOptions.length" class="px-3 py-2 text-sm text-gray-500">Nessun risultato</p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
