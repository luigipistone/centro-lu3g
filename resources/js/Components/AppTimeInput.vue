<script setup>
import { Check, Clock } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, useAttrs, watch } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: {
        type: [String, null],
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Seleziona ora',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    startHour: {
        type: Number,
        default: 7,
    },
    endHour: {
        type: Number,
        default: 23,
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'input']);
const attrs = useAttrs();
const root = ref(null);
const menu = ref(null);
const open = ref(false);
const menuStyle = ref({});
const selectedHour = ref('09');
const selectedMinute = ref('00');
const minutes = ['00', '30'];

const hours = computed(() => Array.from({ length: props.endHour - props.startHour + 1 }, (_, index) => String(props.startHour + index).padStart(2, '0')));
const selectedLabel = computed(() => props.modelValue || props.placeholder);

function syncFromValue(value) {
    const [hour, minute] = String(value || '').split(':');
    if (hours.value.includes(hour)) selectedHour.value = hour;
    if (minutes.includes(minute)) selectedMinute.value = minute;
}

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        syncFromValue(props.modelValue);
        updateMenuPosition();
    }
}

function selectPart(type, value) {
    if (type === 'hour') selectedHour.value = value;
    if (type === 'minute') selectedMinute.value = value;

    const nextValue = `${selectedHour.value}:${selectedMinute.value}`;
    emit('update:modelValue', nextValue);
    emit('change', nextValue);
    emit('input', nextValue);

    if (type === 'minute') open.value = false;
}

function updateMenuPosition() {
    nextTick(() => {
        const rect = root.value?.getBoundingClientRect();
        if (!rect) return;
        menuStyle.value = {
            left: `${rect.left}px`,
            top: `${rect.bottom + 8}px`,
            width: `${Math.max(rect.width, 220)}px`,
        };
    });
}

function closeOnOutside(event) {
    if (!open.value) return;
    if (root.value?.contains(event.target)) return;
    if (menu.value?.contains(event.target)) return;
    open.value = false;
}

watch(() => props.modelValue, syncFromValue, { immediate: true });

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutside, true);
    window.addEventListener('resize', updateMenuPosition);
    window.addEventListener('scroll', updateMenuPosition, true);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeOnOutside, true);
    window.removeEventListener('resize', updateMenuPosition);
    window.removeEventListener('scroll', updateMenuPosition, true);
});
</script>

<template>
    <div ref="root" :class="['relative w-full', attrs.class]">
        <button
            type="button"
            :class="[
                'form-control mt-0 flex h-[38px] items-center justify-between gap-3 text-left',
                open ? 'border-indigo-300 ring-4 ring-indigo-500/10' : '',
            ]"
            :disabled="disabled"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <span :class="['truncate', modelValue ? 'text-gray-800' : 'text-gray-400']">{{ selectedLabel }}</span>
            <Clock class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menu"
                class="app-popover field-dropdown-menu fixed z-[7000] grid grid-cols-[1fr_72px] gap-2 p-3"
                :style="menuStyle"
                @click.stop
            >
                <div>
                    <div class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Ore</div>
                    <div class="max-h-56 overflow-y-auto pr-1">
                        <button
                            v-for="hour in hours"
                            :key="hour"
                            type="button"
                            :class="[
                                'field-dropdown-option flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-indigo-50',
                                selectedHour === hour ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700',
                            ]"
                            @click="selectPart('hour', hour)"
                        >
                            {{ hour }}
                            <Check v-if="selectedHour === hour" class="h-4 w-4" :stroke-width="1.8" />
                        </button>
                    </div>
                </div>
                <div>
                    <div class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Min</div>
                    <div class="space-y-1">
                        <button
                            v-for="minute in minutes"
                            :key="minute"
                            type="button"
                            :class="[
                                'field-dropdown-option flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-indigo-50',
                                selectedMinute === minute ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700',
                            ]"
                            @click="selectPart('minute', minute)"
                        >
                            {{ minute }}
                            <Check v-if="selectedMinute === minute" class="h-4 w-4" :stroke-width="1.8" />
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
