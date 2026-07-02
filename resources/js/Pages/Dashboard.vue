<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { APP_TIME_ZONE } from '@/utils/formatters';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    AlertTriangle,
    Bold,
    Briefcase,
    CalendarClock,
    CheckSquare,
    Heading1,
    Heading2,
    GripVertical,
    Italic,
    KeyRound,
    Link2,
    List,
    ListOrdered,
    Plus,
    Quote,
    RemoveFormatting,
    Settings2,
    StickyNote,
    Strikethrough,
    Underline,
    Users,
    X,
} from '@lucide/vue';

const props = defineProps({
    stats: Object,
    recentClients: Array,
    upcomingTasks: Array,
    urgentTasks: Array,
    myTasks: Array,
    activeProjects: Array,
    dashboardWidgets: Array,
    availableDashboardWidgets: Array,
    dashboardNote: Object,
    passwordItems: Array,
    todayAbsences: Array,
    todaySmartworking: Array,
});

const page = usePage();
const dashboardGrid = ref(null);
const widgetMenu = ref(null);
const widgetMenuOpen = ref(false);
const saving = ref(false);
const savingNote = ref(false);
const draggingType = ref(null);
const dragOverIndex = ref(null);
const noteEditor = ref(null);
const noteHtml = ref(props.dashboardNote?.html || '');
const passwordWidgetSearch = ref('');
const passwordRevealItem = ref(null);
const passwordRevealUsername = ref('');
const passwordRevealPassword = ref('');
const passwordRevealCopied = ref('');
const passwordRevealError = ref('');
let saveTimer = null;
let noteSaveTimer = null;
let resizeState = null;
let moveState = null;

const widgetMeta = {
    stat_clients: {
        label: 'Clienti',
        description: 'Anagrafiche',
        route: 'clients.index',
        icon: Users,
        iconClass: 'text-indigo-600',
        kind: 'stat',
        value: () => props.stats?.clients ?? 0,
    },
    stat_open_tasks: {
        label: 'Task Aperti',
        description: 'Attivita da chiudere',
        route: 'tasks.index',
        icon: CheckSquare,
        iconClass: 'text-emerald-600',
        kind: 'stat',
        value: () => props.stats?.openTasks ?? 0,
    },
    stat_urgent_tasks: {
        label: 'Urgenti',
        description: 'Priorita alta',
        route: 'tasks.index',
        icon: AlertTriangle,
        iconClass: 'text-red-600',
        kind: 'stat',
        value: () => props.stats?.urgentTasks ?? 0,
    },
    upcoming_tasks: {
        label: 'Task in scadenza',
        description: 'Prossime attivita',
        route: 'tasks.index',
        icon: CalendarClock,
        kind: 'list',
        empty: 'Nessun task in scadenza',
        items: () => props.upcomingTasks ?? [],
    },
    my_tasks: {
        label: 'I miei task',
        description: 'Assegnati a te',
        route: 'tasks.index',
        icon: CheckSquare,
        kind: 'list',
        empty: 'Nessun task assegnato',
        items: () => props.myTasks ?? [],
    },
    active_projects: {
        label: 'Progetti attivi',
        description: 'Assegnati a te',
        route: 'projects.index',
        icon: Briefcase,
        kind: 'list',
        empty: 'Nessun progetto attivo assegnato',
        items: () => props.activeProjects ?? [],
    },
    recent_clients: {
        label: 'Clienti recenti',
        description: 'Ultime anagrafiche',
        route: 'clients.index',
        icon: Users,
        kind: 'list',
        empty: 'Nessun cliente',
        items: () => props.recentClients ?? [],
    },
    urgent_tasks: {
        label: 'Task urgenti',
        description: 'Priorita alta',
        route: 'tasks.index',
        icon: AlertTriangle,
        iconClass: 'text-red-600',
        kind: 'list',
        empty: 'Nessun task urgente',
        items: () => props.urgentTasks ?? [],
    },
    notes: {
        label: 'Note',
        description: 'Scrittura libera',
        route: 'dashboard',
        icon: StickyNote,
        iconClass: 'text-violet-600',
        kind: 'note',
    },
    password_search: {
        label: 'Password',
        description: 'Cerca e copia credenziali',
        route: 'passwords.index',
        icon: KeyRound,
        iconClass: 'text-sky-600',
        kind: 'password',
    },
    attendance_today: {
        label: 'Presenze oggi',
        description: 'Assenze e smart working',
        route: 'absences.index',
        icon: CalendarClock,
        iconClass: 'text-teal-600',
        kind: 'attendance',
    },
};

const noteToolbar = [
    ['bold', Bold, 'Grassetto'],
    ['italic', Italic, 'Corsivo'],
    ['underline', Underline, 'Sottolineato'],
    ['strikeThrough', Strikethrough, 'Barrato'],
    ['formatBlock:h1', Heading1, 'Titolo 1'],
    ['formatBlock:h2', Heading2, 'Titolo 2'],
    ['insertUnorderedList', List, 'Lista'],
    ['insertOrderedList', ListOrdered, 'Lista numerata'],
    ['formatBlock:blockquote', Quote, 'Citazione'],
    ['justifyLeft', AlignLeft, 'Allinea a sinistra'],
    ['justifyCenter', AlignCenter, 'Allinea al centro'],
    ['justifyRight', AlignRight, 'Allinea a destra'],
    ['createLink', Link2, 'Link'],
    ['removeFormat', RemoveFormatting, 'Pulisci formato'],
];

function normalizeWidgets(source = []) {
    const saved = new Map(source.map((widget) => [widget.widget_type, widget]));
    const allowedTypes = new Set((props.availableDashboardWidgets || []).map((widget) => widget.type));

    return Object.keys(widgetMeta)
        .filter((type) => !allowedTypes.size || allowedTypes.has(type))
        .map((type, index) => {
            const widget = saved.get(type);

            return {
                widget_type: type,
                position: Number(widget?.position ?? index),
                col_span: clampSpan(widget?.col_span ?? (type === 'upcoming_tasks' ? 2 : 1)),
                visible: Boolean(widget?.visible ?? false),
            };
        })
        .sort((a, b) => Number(b.visible) - Number(a.visible) || a.position - b.position)
        .map((widget, index) => ({ ...widget, position: index }));
}

const widgets = ref(normalizeWidgets(props.dashboardWidgets ?? []));

const visibleWidgets = computed(() => widgets.value.filter((widget) => widget.visible).sort((a, b) => a.position - b.position));
const hiddenWidgets = computed(() => widgets.value.filter((widget) => !widget.visible).sort((a, b) => a.position - b.position));
const filteredPasswordItems = computed(() => {
    const q = passwordWidgetSearch.value.trim().toLowerCase();
    if (!q) return [];

    return (props.passwordItems || [])
        .filter((item) => {
            const textMatch = !q || String(item.title || '').toLowerCase().includes(q);

            return textMatch;
        })
        .sort((first, second) => String(first.title || '').localeCompare(String(second.title || ''), 'it', { sensitivity: 'base' }));
});

const dashboardTodayAbsences = computed(() => props.todayAbsences || []);
const dashboardTodaySmartworking = computed(() => props.todaySmartworking || []);

function metaFor(widget) {
    return widgetMeta[widget.widget_type];
}

function clampSpan(value) {
    return Math.max(1, Math.min(4, Number(value) || 1));
}

function colSpanClass(widget) {
    return {
        1: 'lg:col-span-1',
        2: 'lg:col-span-2',
        3: 'lg:col-span-3',
        4: 'lg:col-span-4',
    }[clampSpan(widget.col_span)];
}

function commitWidgets(nextWidgets, persist = true) {
    widgets.value = nextWidgets.map((widget, index) => ({ ...widget, position: index, col_span: clampSpan(widget.col_span) }));
    if (persist) scheduleSave();
}

function payload() {
    return widgets.value.map((widget, index) => ({
        widget_type: widget.widget_type,
        position: index,
        col_span: clampSpan(widget.col_span),
        visible: Boolean(widget.visible),
    }));
}

function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveWidgets, 350);
}

async function saveWidgets() {
    saving.value = true;

    try {
        const { data } = await window.axios.patch(route('dashboard.widgets.update'), { widgets: payload() });
        if (data?.widgets) {
            widgets.value = normalizeWidgets(data.widgets);
        }
    } finally {
        saving.value = false;
    }
}

function applyNoteCommand(command) {
    initializeNoteEditor();
    noteEditor.value?.focus();

    if (command.startsWith('formatBlock:')) {
        document.execCommand('formatBlock', false, command.split(':')[1]);
    } else if (command === 'createLink') {
        const url = window.prompt('URL del link');
        if (url) document.execCommand('createLink', false, url);
    } else {
        document.execCommand(command, false);
    }

    syncNoteContent();
}

function initializeNoteEditor() {
    if (noteEditor.value && noteEditor.value.innerHTML !== noteHtml.value) {
        noteEditor.value.innerHTML = noteHtml.value;
    }
}

function syncNoteContent() {
    noteHtml.value = noteEditor.value?.innerHTML || '';
    scheduleNoteSave();
}

function scheduleNoteSave() {
    clearTimeout(noteSaveTimer);
    noteSaveTimer = setTimeout(saveNote, 500);
}

async function saveNote() {
    savingNote.value = true;

    try {
        const { data } = await window.axios.patch(route('dashboard.note.update'), { html: noteHtml.value });
        if (data?.note?.html !== undefined) {
            noteHtml.value = data.note.html;
            if (noteEditor.value && noteEditor.value.innerHTML !== data.note.html) {
                noteEditor.value.innerHTML = data.note.html;
            }
        }
    } finally {
        savingNote.value = false;
    }
}

function startMove(widget, event) {
    event.preventDefault();
    event.stopPropagation();

    draggingType.value = widget.widget_type;
    dragOverIndex.value = visibleWidgets.value.findIndex((item) => item.widget_type === widget.widget_type);
    moveState = { type: widget.widget_type };
    document.body.style.cursor = 'grabbing';
    document.body.style.userSelect = 'none';

    window.addEventListener('pointermove', moveWidget);
    window.addEventListener('pointerup', stopMove, { once: true });
}

function moveWidget(event) {
    if (!moveState || !dashboardGrid.value) return;

    dragOverIndex.value = resolveDropIndex(event);
}

function resolveDropIndex(event) {
    const sourceType = draggingType.value;
    const ordered = visibleWidgets.value.filter((widget) => widget.widget_type !== sourceType);
    const cards = [...dashboardGrid.value.querySelectorAll('[data-widget-type]')]
        .filter((element) => element.dataset.widgetType !== sourceType)
        .map((element) => ({
            index: ordered.findIndex((widget) => widget.widget_type === element.dataset.widgetType),
            rect: element.getBoundingClientRect(),
        }))
        .filter((card) => card.index >= 0)
        .sort((a, b) => a.index - b.index);

    if (!cards.length) return 0;

    const y = event.clientY;
    const x = event.clientX;
    const rowTolerance = 12;
    const rowCards = cards.filter((card) => y >= card.rect.top - rowTolerance && y <= card.rect.bottom + rowTolerance);

    if (rowCards.length) {
        const sortedRow = [...rowCards].sort((a, b) => a.rect.left - b.rect.left);
        const beforeCard = sortedRow.find((card) => x < card.rect.left + card.rect.width / 2);

        return beforeCard ? beforeCard.index : sortedRow[sortedRow.length - 1].index + 1;
    }

    const belowPointer = cards.find((card) => y < card.rect.top + card.rect.height / 2);

    return belowPointer ? belowPointer.index : ordered.length;
}

function stopMove() {
    window.removeEventListener('pointermove', moveWidget);
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    dropOnGrid();
    moveState = null;
}

function dropOnGrid() {
    const sourceType = draggingType.value;
    const to = dragOverIndex.value;
    draggingType.value = null;
    dragOverIndex.value = null;

    if (!sourceType || to === null) return;

    const current = visibleWidgets.value.filter((widget) => widget.widget_type !== sourceType);
    const moved = widgets.value.find((widget) => widget.widget_type === sourceType);
    if (!moved) return;

    current.splice(Math.max(0, Math.min(to, current.length)), 0, moved);
    commitWidgets([...current, ...hiddenWidgets.value]);
}

function removeWidget(widget) {
    commitWidgets(widgets.value.map((item) => item.widget_type === widget.widget_type ? { ...item, visible: false } : item));
}

function addWidget(widget) {
    widgetMenuOpen.value = false;
    commitWidgets([
        ...visibleWidgets.value,
        { ...widget, visible: true, col_span: clampSpan(widget.col_span || 1) },
        ...hiddenWidgets.value.filter((item) => item.widget_type !== widget.widget_type),
    ]);

    if (widget.widget_type === 'notes') {
        nextTick(initializeNoteEditor);
    }
}

function startResize(widget, event) {
    event.preventDefault();
    event.stopPropagation();
    resizeState = {
        type: widget.widget_type,
        startX: event.clientX,
        startSpan: clampSpan(widget.col_span),
    };

    window.addEventListener('pointermove', resizeWidget);
    window.addEventListener('pointerup', stopResize, { once: true });
}

function resizeWidget(event) {
    if (!resizeState || !dashboardGrid.value) return;

    const gridWidth = dashboardGrid.value.clientWidth || 1;
    const columnWidth = gridWidth / 4;
    const delta = Math.round((event.clientX - resizeState.startX) / columnWidth);
    const nextSpan = clampSpan(resizeState.startSpan + delta);

    widgets.value = widgets.value.map((widget) => (
        widget.widget_type === resizeState.type ? { ...widget, col_span: nextSpan } : widget
    ));
}

function stopResize() {
    window.removeEventListener('pointermove', resizeWidget);
    resizeState = null;
    scheduleSave();
}

function dateShort(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('it-IT', { timeZone: APP_TIME_ZONE, day: 'numeric', month: 'short' });
}

function priorityDot(priority) {
    return {
        low: 'bg-emerald-500',
        medium: 'bg-amber-500',
        high: 'bg-orange-500',
        urgent: 'bg-red-500',
    }[priority] || 'bg-gray-400';
}

function itemHref(widget, item) {
    if (widget.widget_type === 'active_projects') return route('projects.show', item.id);
    if (widget.widget_type === 'recent_clients') return route('clients.show', item.id);
    return route('tasks.show', item.id);
}

function itemTitle(widget, item) {
    return widget.widget_type === 'active_projects' ? item.name : item.title || item.name;
}

function itemMeta(widget, item) {
    if (widget.widget_type === 'active_projects') return item.client_name;
    if (widget.widget_type === 'recent_clients') return item.email || item.phone;
    return dateShort(item.due_date);
}

function widgetNumber(widget) {
    const meta = metaFor(widget);
    if (meta.kind === 'stat') return meta.value();
    if (meta.kind === 'note') return noteHtml.value.replace(/<[^>]+>/g, ' ').trim().split(/\s+/).filter(Boolean).length;
    if (meta.kind === 'password') return props.passwordItems?.length ?? 0;
    if (meta.kind === 'attendance') return dashboardTodayAbsences.value.length + dashboardTodaySmartworking.value.length;
    return meta.items().length;
}

const absenceTypeLabels = {
    vacation: 'Ferie',
    permission: 'Permesso',
    permit: 'Permesso',
    sickness: 'Malattia',
    late: 'Ritardo',
    delay: 'Ritardo',
    other: 'Altro',
};

function absenceTypeLabel(type) {
    return absenceTypeLabels[type] || type || 'Assenza';
}

function absenceExtraInfo(row) {
    if (row?.start_time && row?.end_time) return `${String(row.start_time).slice(0, 5)} - ${String(row.end_time).slice(0, 5)}`;
    if (row?.type === 'sickness' && row?.inps_code) return `INPS ${row.inps_code}`;

    return '';
}

function normalizeHexColor(value, fallback = '#0B6EF3') {
    if (!value) return fallback;
    const color = String(value).trim();
    if (/^#[0-9a-f]{6}$/i.test(color)) return color;
    if (/^#[0-9a-f]{3}$/i.test(color)) {
        return `#${color.slice(1).split('').map((char) => `${char}${char}`).join('')}`;
    }

    return fallback;
}

function isLightColor(value) {
    const color = normalizeHexColor(value);
    const r = parseInt(color.slice(1, 3), 16);
    const g = parseInt(color.slice(3, 5), 16);
    const b = parseInt(color.slice(5, 7), 16);
    const luminance = ((0.2126 * r) + (0.7152 * g) + (0.0722 * b)) / 255;

    return luminance > 0.62;
}

function passwordVaultBadgeStyle(item) {
    const backgroundColor = normalizeHexColor(item?.vault_color, '#0B6EF3');

    return {
        backgroundColor,
        color: isLightColor(backgroundColor) ? '#111827' : '#ffffff',
    };
}

async function openPasswordReveal(item) {
    passwordRevealItem.value = item;
    passwordRevealUsername.value = '';
    passwordRevealPassword.value = '';
    passwordRevealCopied.value = '';
    passwordRevealError.value = '';

    try {
        const { data } = await window.axios.post(route('passwords.items.reveal', item.id));
        passwordRevealUsername.value = data?.username || '';
        passwordRevealPassword.value = data?.password || '';
    } catch (error) {
        passwordRevealError.value = 'Impossibile aprire questa password.';
    }
}

function closePasswordReveal() {
    passwordRevealItem.value = null;
    passwordRevealUsername.value = '';
    passwordRevealPassword.value = '';
    passwordRevealCopied.value = '';
    passwordRevealError.value = '';
}

async function copyPasswordRevealValue(value, message) {
    if (!value) return;
    await navigator.clipboard.writeText(value);
    passwordRevealCopied.value = message;
}

function closeWidgetMenuOnOutside(event) {
    if (!widgetMenuOpen.value || widgetMenu.value?.contains(event.target)) return;

    widgetMenuOpen.value = false;
}

function requestFloatingUiClose() {
    window.dispatchEvent(new CustomEvent('centro:close-floating-ui'));
}

function toggleWidgetMenu() {
    const nextOpen = !widgetMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    widgetMenuOpen.value = nextOpen;
}

function closeDashboardFloatingUi() {
    widgetMenuOpen.value = false;
}

onMounted(() => {
    nextTick(initializeNoteEditor);
    document.addEventListener('click', closeWidgetMenuOnOutside);
    window.addEventListener('centro:close-floating-ui', closeDashboardFloatingUi);
});

onUnmounted(() => {
    document.removeEventListener('click', closeWidgetMenuOnOutside);
    window.removeEventListener('centro:close-floating-ui', closeDashboardFloatingUi);
});

watch(
    () => visibleWidgets.value.some((widget) => widget.widget_type === 'notes'),
    (hasNotes) => {
        if (hasNotes) nextTick(initializeNoteEditor);
    },
);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
                    <p class="text-sm text-gray-500">Bentornato, {{ page.props.auth?.user?.email }}</p>
                </div>

                <div ref="widgetMenu" class="relative flex items-center gap-2">
                    <span v-if="saving" class="text-xs font-medium text-gray-400">Salvataggio...</span>
                    <button type="button" class="btn btn-outline" @click="toggleWidgetMenu">
                        <Plus class="h-4 w-4" :stroke-width="1.8" />
                        Aggiungi widget
                    </button>

                    <div
                        v-if="widgetMenuOpen"
                        class="app-popover absolute right-0 top-12 z-[2147483647] w-80 overflow-hidden rounded-2xl border border-white bg-white p-2 shadow-[0_24px_70px_rgba(28,42,73,0.14)]"
                    >
                        <div class="px-2 pb-2 pt-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Widget disponibili</div>
                        <button
                            v-for="widget in hiddenWidgets"
                            :key="widget.widget_type"
                            type="button"
                            class="block w-full rounded-2xl px-3 py-2.5 text-left transition hover:-translate-y-0.5 hover:bg-indigo-50/90 hover:shadow-[0_10px_24px_rgba(79,70,229,0.10)] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                            @click="addWidget(widget)"
                        >
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ metaFor(widget).label }}</span>
                                <span class="block text-xs text-gray-500">{{ metaFor(widget).description }}</span>
                            </span>
                        </button>
                        <p v-if="!hiddenWidgets.length" class="px-3 py-6 text-center text-sm text-gray-500">Tutti i widget sono gia attivi.</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <div class="toolbar justify-between">
                    <div class="flex items-center gap-2 text-sm font-medium text-gray-600">
                        <Settings2 class="h-4 w-4 text-indigo-500" :stroke-width="1.8" />
                        Trascina la maniglia per spostare i widget. Usa il bordo destro per allargarli o restringerli su 4 colonne.
                    </div>
                    <span class="rounded-full bg-white/60 px-3 py-1 text-xs font-semibold text-gray-500">{{ visibleWidgets.length }} attivi</span>
                </div>

                <div
                    ref="dashboardGrid"
                    class="grid grid-cols-1 gap-4 lg:grid-cols-4"
                >
                    <article
                        v-for="widget in visibleWidgets"
                        :key="widget.widget_type"
                        :data-widget-type="widget.widget_type"
                        :class="[
                            'app-card widget-card group relative flex min-h-[154px] flex-col overflow-hidden transition',
                            colSpanClass(widget),
                            draggingType === widget.widget_type ? 'opacity-45 ring-2 ring-indigo-300' : '',
                            draggingType && dragOverIndex === visibleWidgets.findIndex((item) => item.widget_type === widget.widget_type) ? 'ring-2 ring-indigo-300' : '',
                        ]"
                    >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 z-20 w-3 cursor-ew-resize rounded-r-[inherit] bg-indigo-500/0 transition hover:bg-indigo-500/12"
                            :title="`Ridimensiona ${metaFor(widget).label}`"
                            @pointerdown="startResize(widget, $event)"
                        >
                            <span class="sr-only">Ridimensiona</span>
                        </button>

                        <div class="absolute left-4 right-5 top-4 z-10 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                class="icon-btn h-7 w-7 cursor-grab active:cursor-grabbing"
                                :title="`Sposta ${metaFor(widget).label}`"
                                @pointerdown="startMove(widget, $event)"
                            >
                                <GripVertical class="h-4 w-4" :stroke-width="1.8" />
                                <span class="sr-only">Sposta</span>
                            </button>

                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" class="icon-btn h-7 w-7" :title="`Rimuovi ${metaFor(widget).label}`" @click="removeWidget(widget)">
                                    <X class="h-4 w-4" :stroke-width="1.8" />
                                    <span class="sr-only">Rimuovi</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-10 flex items-start justify-between gap-4 rounded-2xl px-1 pb-3 pr-4">
                            <span class="flex min-w-0 items-start gap-3">
                                <span :class="['metric-icon', metaFor(widget).iconClass]">
                                    <component :is="metaFor(widget).icon" class="h-5 w-5" :stroke-width="1.7" />
                                </span>
                                <span class="min-w-0 pt-0.5">
                                    <span class="block truncate text-sm font-semibold text-gray-900">{{ metaFor(widget).label }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ metaFor(widget).description }}</span>
                                </span>
                            </span>
                            <span v-if="metaFor(widget).kind !== 'note'" class="shrink-0 text-3xl font-bold leading-none text-gray-950">{{ widgetNumber(widget) }}</span>
                        </div>

                        <div v-if="metaFor(widget).kind === 'list'" class="flex flex-1 flex-col pr-3">
                            <div class="space-y-1">
                                <Link
                                    v-for="item in metaFor(widget).items()"
                                    :key="item.id"
                                    :href="itemHref(widget, item)"
                                    class="group/item flex items-center gap-3 rounded-2xl px-2 py-2 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-[0_12px_28px_rgba(28,42,73,0.10)] hover:ring-1 hover:ring-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                                >
                                    <span
                                        v-if="widget.widget_type === 'active_projects'"
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: item.color || '#64748b' }"
                                    ></span>
                                    <span v-else :class="['h-1.5 w-1.5 shrink-0 rounded-full', priorityDot(item.priority)]"></span>
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900">
                                        {{ itemTitle(widget, item) }}
                                        <span v-if="widget.widget_type === 'upcoming_tasks' && item.client_name" class="font-normal text-gray-500"> - {{ item.client_name }}</span>
                                    </span>
                                    <span class="shrink-0 truncate text-xs text-gray-500">{{ itemMeta(widget, item) }}</span>
                                </Link>
                                <p v-if="!metaFor(widget).items().length" class="py-2 text-sm text-gray-500">{{ metaFor(widget).empty }}</p>
                            </div>
                        </div>

                        <div v-if="metaFor(widget).kind === 'attendance'" class="flex flex-1 flex-col pr-3">
                            <div class="space-y-3">
                                <section class="rounded-2xl border border-white/70 bg-white/80 px-3 py-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.78)]">
                                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-2">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Assenti</span>
                                        <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-600">{{ dashboardTodayAbsences.length }}</span>
                                    </div>
                                    <div class="mt-2 max-h-[94px] space-y-1 overflow-y-auto pr-1">
                                        <Link
                                            v-for="row in dashboardTodayAbsences"
                                            :key="`dashboard-absence-${row.id}`"
                                            :href="route('absences.show', row.id)"
                                            class="group/item flex items-center justify-between gap-3 rounded-xl px-2 py-1.5 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-[0_10px_22px_rgba(28,42,73,0.08)] hover:ring-1 hover:ring-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                                        >
                                            <span class="min-w-0 truncate text-sm font-semibold text-gray-900">{{ row.user_name || row.user_email }}</span>
                                            <span class="shrink-0 truncate text-xs text-gray-500">
                                                {{ absenceTypeLabel(row.type) }}<span v-if="absenceExtraInfo(row)"> - {{ absenceExtraInfo(row) }}</span>
                                            </span>
                                        </Link>
                                        <p v-if="!dashboardTodayAbsences.length" class="py-2 text-sm text-gray-500">Nessuna assenza oggi.</p>
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-white/70 bg-white/80 px-3 py-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.78)]">
                                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-2">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Smart working</span>
                                        <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-bold text-sky-600">{{ dashboardTodaySmartworking.length }}</span>
                                    </div>
                                    <div class="mt-2 max-h-[94px] space-y-1 overflow-y-auto pr-1">
                                        <div
                                            v-for="user in dashboardTodaySmartworking"
                                            :key="`dashboard-smartworking-${user.id}`"
                                            class="flex items-center justify-between gap-3 rounded-xl px-2 py-1.5"
                                        >
                                            <span class="min-w-0 truncate text-sm font-semibold text-gray-900">{{ user.name || user.email }}</span>
                                            <span v-if="user.job_title" class="shrink-0 truncate text-xs text-gray-500">{{ user.job_title }}</span>
                                        </div>
                                        <p v-if="!dashboardTodaySmartworking.length" class="py-2 text-sm text-gray-500">Nessuno in smart working oggi.</p>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div v-if="metaFor(widget).kind === 'password'" class="flex flex-1 flex-col gap-3 pr-3">
                            <div class="space-y-2">
                                <input v-model="passwordWidgetSearch" class="form-control h-[38px]" placeholder="Cerca per titolo" />
                            </div>

                            <div class="max-h-[136px] min-h-[136px] space-y-1 overflow-y-auto pr-1">
                                <button
                                    v-for="item in filteredPasswordItems"
                                    :key="`dashboard-password-${item.id}`"
                                    type="button"
                                    class="group/item flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left transition hover:-translate-y-0.5 hover:bg-white hover:shadow-[0_12px_28px_rgba(28,42,73,0.10)] hover:ring-1 hover:ring-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                                    @click="openPasswordReveal(item)"
                                >
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[var(--radius-sm)]" :style="passwordVaultBadgeStyle(item)">
                                        <KeyRound class="h-4 w-4" :stroke-width="1.8" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-gray-900">{{ item.title || 'Password' }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ item.vault_name || 'Cassaforte' }}<span v-if="item.client_name"> - {{ item.client_name }}</span></span>
                                    </span>
                                </button>
                                <p v-if="!passwordWidgetSearch.trim()" class="py-2 text-sm text-gray-500">Cerca una password per titolo.</p>
                                <p v-else-if="!filteredPasswordItems.length" class="py-2 text-sm text-gray-500">Nessuna password trovata.</p>
                            </div>
                        </div>

                        <div v-if="metaFor(widget).kind === 'note'" class="flex flex-1 flex-col gap-3 pr-3">
                            <div class="flex flex-wrap items-center gap-1 rounded-2xl border border-gray-100 bg-white/90 p-1 shadow-[inset_0_1px_0_rgba(255,255,255,0.78)]">
                                <button
                                    v-for="[command, icon, label] in noteToolbar"
                                    :key="command"
                                    type="button"
                                    class="icon-btn h-8 w-8"
                                    :title="label"
                                    @click="applyNoteCommand(command)"
                                >
                                    <component :is="icon" class="h-4 w-4" :stroke-width="1.8" />
                                    <span class="sr-only">{{ label }}</span>
                                </button>
                                <span v-if="savingNote" class="ml-auto px-2 text-[11px] font-semibold text-gray-400">Salvataggio...</span>
                            </div>

                            <div
                                ref="noteEditor"
                                class="min-h-[180px] rounded-2xl border border-gray-100 bg-white px-4 py-3 text-sm leading-6 text-gray-800 outline-none transition focus:border-indigo-200 focus:ring-4 focus:ring-indigo-500/10 [&_blockquote]:border-l-4 [&_blockquote]:border-indigo-200 [&_blockquote]:pl-3 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc"
                                contenteditable="true"
                                :data-placeholder="noteHtml ? '' : 'Scrivi una nota...'"
                                @input="syncNoteContent"
                                @blur="saveNote"
                            ></div>
                        </div>
                    </article>

                    <div
                        v-if="draggingType"
                        :class="[
                            'hidden min-h-[72px] items-center justify-center rounded-[var(--radius)] border border-dashed text-sm font-semibold transition lg:flex',
                            dragOverIndex === visibleWidgets.length ? 'border-indigo-300 bg-indigo-50/60 text-indigo-600' : 'border-transparent text-transparent',
                        ]"
                    >
                        Rilascia qui
                    </div>
                </div>
            </div>
        </div>

        <div v-if="passwordRevealItem" class="fixed inset-0 z-[5200] grid h-dvh w-dvw place-items-center bg-gray-950/20 px-4 backdrop-blur-[2px]" @click.self="closePasswordReveal">
            <section class="dashboard-password-dialog max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.24)]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900">Dettaglio password</h3>
                    <button type="button" class="icon-btn" @click="closePasswordReveal">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="mt-4 rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/80 p-3">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ passwordRevealItem.title || 'Password' }}</p>
                    <p v-if="passwordRevealItem.vault_name || passwordRevealItem.client_name" class="mt-1 text-xs text-gray-500">
                        <span v-if="passwordRevealItem.vault_name">{{ passwordRevealItem.vault_name }}</span>
                        <span v-if="passwordRevealItem.vault_name && passwordRevealItem.client_name"> - </span>
                        <span v-if="passwordRevealItem.client_name">{{ passwordRevealItem.client_name }}</span>
                    </p>
                </div>
                <p v-if="!passwordRevealPassword && !passwordRevealError" class="mt-4 text-sm text-gray-500">Caricamento credenziale...</p>
                <p v-if="passwordRevealError" class="mt-3 text-sm text-red-600">{{ passwordRevealError }}</p>
                <p v-if="passwordRevealCopied" class="mt-3 text-sm font-semibold text-[hsl(var(--primary-app))]">{{ passwordRevealCopied }}</p>
                <div v-if="passwordRevealPassword" class="mt-4 space-y-3">
                    <button type="button" class="dashboard-password-reveal-row w-full text-left" :disabled="!passwordRevealUsername" @click="copyPasswordRevealValue(passwordRevealUsername, 'Nome utente copiato')">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Nome utente</span>
                        <span class="mt-1 block truncate text-sm font-semibold text-gray-900">{{ passwordRevealUsername || 'Non inserito' }}</span>
                    </button>
                    <button type="button" class="dashboard-password-reveal-row w-full text-left" @click="copyPasswordRevealValue(passwordRevealPassword, 'Password copiata')">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Password</span>
                        <span class="mt-1 block break-all font-mono text-sm tracking-[0.12em] text-gray-900">{{ '•'.repeat(Math.min(Math.max(passwordRevealPassword.length, 8), 24)) }}</span>
                    </button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-password-dialog {
    animation: dashboardPasswordDialogIn 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes dashboardPasswordDialogIn {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.dashboard-password-reveal-row {
    border: 1px solid rgb(229 231 235 / 0.9);
    border-radius: var(--radius-sm);
    background: rgb(249 250 251 / 0.85);
    padding: 0.85rem 0.95rem;
    cursor: pointer;
    transition: border-color 0.18s ease, background-color 0.18s ease, transform 0.18s ease;
}

.dashboard-password-reveal-row:hover:not(:disabled) {
    border-color: hsl(var(--primary-app) / 0.28);
    background: hsl(var(--primary-app) / 0.055);
    transform: translateY(-1px);
}

.dashboard-password-reveal-row:disabled {
    cursor: default;
}
</style>
