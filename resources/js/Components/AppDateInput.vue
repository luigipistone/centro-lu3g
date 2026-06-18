<script setup>
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: {
        type: [String, null],
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Seleziona data',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'input']);
const attrs = useAttrs();
const root = ref(null);
const menu = ref(null);
const open = ref(false);
const menuStyle = ref({});
const viewDate = ref(props.modelValue ? new Date(`${props.modelValue}T00:00:00`) : new Date());
const weekdays = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

const selectedLabel = computed(() => {
    if (!props.modelValue) return props.placeholder;
    return new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${props.modelValue}T00:00:00`));
});

const monthLabel = computed(() => new Intl.DateTimeFormat('it-IT', { month: 'long', year: 'numeric' }).format(viewDate.value));

const days = computed(() => {
    const year = viewDate.value.getFullYear();
    const month = viewDate.value.getMonth();
    const first = new Date(year, month, 1);
    const offset = (first.getDay() + 6) % 7;
    const start = new Date(year, month, 1 - offset);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        return {
            date,
            value: formatDate(date),
            inMonth: date.getMonth() === month,
            label: date.getDate(),
        };
    });
});

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) updateMenuPosition();
}

function selectDay(day) {
    emit('update:modelValue', day.value);
    emit('change', day.value);
    emit('input', day.value);
    open.value = false;
}

function moveMonth(amount) {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + amount, 1);
}

function updateMenuPosition() {
    nextTick(() => {
        const rect = root.value?.getBoundingClientRect();
        if (!rect) return;
        menuStyle.value = {
            left: `${rect.left}px`,
            top: `${rect.bottom + 8}px`,
            width: `${Math.max(rect.width, 304)}px`,
        };
    });
}

function closeOnOutside(event) {
    if (!open.value) return;
    if (root.value?.contains(event.target)) return;
    if (menu.value?.contains(event.target)) return;
    open.value = false;
}

watch(() => props.modelValue, (value) => {
    if (value) viewDate.value = new Date(`${value}T00:00:00`);
});

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
            class="form-control mt-0 flex h-[38px] items-center justify-between gap-3 text-left"
            :class="open ? 'border-indigo-300 ring-4 ring-indigo-500/10' : ''"
            :disabled="disabled"
            @click.stop="toggle"
        >
            <span :class="['truncate', modelValue ? 'text-gray-800' : 'text-gray-400']">{{ selectedLabel }}</span>
            <CalendarDays class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menu"
                class="app-popover field-dropdown-menu fixed z-[7000] p-3"
                :style="menuStyle"
                @click.stop
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <button type="button" class="icon-btn h-8 w-8" @click="moveMonth(-1)">
                        <ChevronLeft class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                    <div class="text-sm font-semibold capitalize text-gray-900">{{ monthLabel }}</div>
                    <button type="button" class="icon-btn h-8 w-8" @click="moveMonth(1)">
                        <ChevronRight class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-gray-400">
                    <span v-for="day in weekdays" :key="day">{{ day }}</span>
                </div>
                <div class="mt-1 grid grid-cols-7 gap-1">
                    <button
                        v-for="day in days"
                        :key="day.value"
                        type="button"
                        :class="[
                            'field-dropdown-option h-9 rounded-[var(--radius-sm)] text-sm font-medium transition hover:bg-indigo-50',
                            day.inMonth ? 'text-gray-700' : 'text-gray-300',
                            modelValue === day.value ? 'bg-indigo-50 text-indigo-700 font-semibold' : '',
                        ]"
                        @click="selectDay(day)"
                    >
                        {{ day.label }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
