<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    AlertTriangle,
    Briefcase,
    CalendarClock,
    CheckSquare,
    GripVertical,
    Plus,
    Settings2,
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
});

const page = usePage();
const dashboardGrid = ref(null);
const widgetMenuOpen = ref(false);
const saving = ref(false);
const draggingType = ref(null);
const dragOverIndex = ref(null);
let saveTimer = null;
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
    stat_projects: {
        label: 'Progetti Attivi',
        description: 'Progetti in corso',
        route: 'projects.index',
        icon: Briefcase,
        iconClass: 'text-sky-600',
        kind: 'stat',
        value: () => props.stats?.activeProjects ?? 0,
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
        description: 'In corso',
        route: 'projects.index',
        icon: Briefcase,
        kind: 'list',
        empty: 'Nessun progetto attivo',
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
};

function normalizeWidgets(source = []) {
    const saved = new Map(source.map((widget) => [widget.widget_type, widget]));

    return Object.keys(widgetMeta)
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
    return new Date(value).toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
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
    return meta.items().length;
}
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

                <div class="relative flex items-center gap-2">
                    <span v-if="saving" class="text-xs font-medium text-gray-400">Salvataggio...</span>
                    <button type="button" class="btn btn-outline" @click="widgetMenuOpen = !widgetMenuOpen">
                        <Plus class="h-4 w-4" :stroke-width="1.8" />
                        Aggiungi widget
                    </button>

                    <div
                        v-if="widgetMenuOpen"
                        class="absolute right-0 top-12 z-30 w-80 overflow-hidden rounded-2xl border border-white/80 bg-white/95 p-2 shadow-[0_24px_70px_rgba(28,42,73,0.14)] backdrop-blur-xl"
                    >
                        <div class="px-2 pb-2 pt-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Widget disponibili</div>
                        <button
                            v-for="widget in hiddenWidgets"
                            :key="widget.widget_type"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-2xl px-3 py-2 text-left transition hover:bg-indigo-50/80"
                            @click="addWidget(widget)"
                        >
                            <span class="section-icon h-8 w-8">
                                <component :is="metaFor(widget).icon" class="h-4 w-4" :stroke-width="1.7" />
                            </span>
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
                            'app-card group relative flex min-h-[154px] flex-col overflow-hidden transition',
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

                        <component
                            :is="metaFor(widget).kind === 'stat' ? Link : 'div'"
                            v-bind="metaFor(widget).kind === 'stat' ? { href: route(metaFor(widget).route) } : {}"
                            class="mt-10 flex items-start justify-between gap-4 rounded-2xl px-1 pb-3 pr-4 transition hover:bg-white/55"
                        >
                            <span class="flex min-w-0 items-start gap-3">
                                <span :class="['metric-icon', metaFor(widget).iconClass]">
                                    <component :is="metaFor(widget).icon" class="h-5 w-5" :stroke-width="1.7" />
                                </span>
                                <span class="min-w-0 pt-0.5">
                                    <span class="block truncate text-sm font-semibold text-gray-900">{{ metaFor(widget).label }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ metaFor(widget).description }}</span>
                                </span>
                            </span>
                            <span class="shrink-0 text-3xl font-bold leading-none text-gray-950">{{ widgetNumber(widget) }}</span>
                        </component>

                        <div v-if="metaFor(widget).kind === 'list'" class="flex flex-1 flex-col pr-3">
                            <div class="space-y-1">
                                <Link
                                    v-for="item in metaFor(widget).items()"
                                    :key="item.id"
                                    :href="itemHref(widget, item)"
                                    class="flex items-center gap-3 rounded-2xl px-2 py-2 transition hover:bg-white/52"
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
    </AuthenticatedLayout>
</template>
