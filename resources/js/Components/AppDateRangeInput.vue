<script setup>
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    startDay: { type: String, default: '' },
    endDay: { type: String, default: '' },
    placeholder: { type: String, default: 'Seleziona uno o più giorni consecutivi' },
});
const emit = defineEmits(['update:startDay', 'update:endDay']);
const root = ref(null);
const menu = ref(null);
const open = ref(false);
const menuStyle = ref({});
const viewDate = ref(props.startDay ? new Date(`${props.startDay}T00:00:00`) : new Date());
const weekdays = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
const formatDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const dateLabel = (date) => new Intl.DateTimeFormat('it-IT', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${date}T00:00:00`));
const selectedLabel = computed(() => !props.startDay ? props.placeholder : props.endDay && props.endDay !== props.startDay
    ? `${dateLabel(props.startDay)} – ${dateLabel(props.endDay)}` : dateLabel(props.startDay));
const monthLabel = computed(() => new Intl.DateTimeFormat('it-IT', { month: 'long', year: 'numeric' }).format(viewDate.value));
const days = computed(() => {
    const first = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);
    const start = new Date(first.getFullYear(), first.getMonth(), 1 - ((first.getDay() + 6) % 7));
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        return { value: formatDate(date), label: date.getDate(), inMonth: date.getMonth() === first.getMonth() };
    });
});

function positionMenu() {
    nextTick(() => {
        const rect = root.value?.getBoundingClientRect();
        if (!rect) return;
        const padding = 12;
        const width = Math.min(Math.max(rect.width, 304), window.innerWidth - padding * 2);
        const height = menu.value?.offsetHeight || 390;
        menuStyle.value = {
            left: `${Math.min(Math.max(padding, rect.right - width), window.innerWidth - width - padding)}px`,
            top: `${rect.bottom + 8 + height <= window.innerHeight - padding ? rect.bottom + 8 : Math.max(padding, rect.top - height - 8)}px`,
            width: `${width}px`,
        };
    });
}
function toggle() {
    if (!open.value) window.dispatchEvent(new CustomEvent('centro:close-floating-ui'));
    open.value = !open.value;
    if (open.value) positionMenu();
}
function selectDay(value) {
    if (!props.startDay || props.endDay || value < props.startDay) {
        emit('update:startDay', value);
        emit('update:endDay', '');
        return;
    }
    emit('update:endDay', value);
    open.value = false;
}
function closeOnOutside(event) {
    if (root.value?.contains(event.target) || menu.value?.contains(event.target)) return;
    open.value = false;
}
function closeOnEscape(event) { if (event.key === 'Escape') open.value = false; }
function closeFromOtherPopover() { open.value = false; }
watch(() => props.startDay, (value) => { if (value) viewDate.value = new Date(`${value}T00:00:00`); });
onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutside, true);
    document.addEventListener('keydown', closeOnEscape);
    window.addEventListener('centro:close-floating-ui', closeFromOtherPopover);
    window.addEventListener('resize', positionMenu);
    window.addEventListener('scroll', positionMenu, true);
});
onUnmounted(() => {
    document.removeEventListener('pointerdown', closeOnOutside, true);
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('centro:close-floating-ui', closeFromOtherPopover);
    window.removeEventListener('resize', positionMenu);
    window.removeEventListener('scroll', positionMenu, true);
});
</script>

<template>
    <div ref="root" class="relative w-full">
        <button type="button" :class="['form-control mt-0 flex h-[38px] w-full items-center justify-between gap-3 text-left cursor-pointer', open ? 'border-indigo-300 ring-4 ring-indigo-500/10' : '']" @click.stop="toggle">
            <span :class="['truncate', startDay ? 'text-gray-800' : 'text-gray-400']">{{ selectedLabel }}</span>
            <CalendarDays class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
        </button>
        <Teleport to="body">
            <div v-if="open" ref="menu" class="app-popover field-dropdown-menu fixed z-[7000] p-3" :style="menuStyle" @click.stop>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <button type="button" class="icon-btn h-8 w-8" aria-label="Mese precedente" @click="viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1)"><ChevronLeft class="h-4 w-4" /></button>
                    <span class="text-sm font-semibold capitalize text-gray-900">{{ monthLabel }}</span>
                    <button type="button" class="icon-btn h-8 w-8" aria-label="Mese successivo" @click="viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1)"><ChevronRight class="h-4 w-4" /></button>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-gray-400"><span v-for="day in weekdays" :key="day">{{ day }}</span></div>
                <div class="mt-1 grid grid-cols-7 gap-1">
                    <button v-for="day in days" :key="day.value" type="button" :class="[
                        'field-dropdown-option h-9 rounded-[var(--radius-sm)] text-sm font-medium transition',
                        day.inMonth ? 'text-gray-700' : 'text-gray-300',
                        startDay && day.value >= startDay && day.value <= (endDay || startDay) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'hover:bg-indigo-50',
                        day.value === startDay || day.value === endDay ? 'ring-1 ring-inset ring-indigo-400' : '',
                    ]" @click="selectDay(day.value)">{{ day.label }}</button>
                </div>
                <p v-if="startDay && !endDay" class="mt-3 text-center text-xs text-gray-500">Seleziona il giorno finale, anche lo stesso giorno</p>
            </div>
        </Teleport>
    </div>
</template>
