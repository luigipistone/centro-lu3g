<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClearableSearchInput from '@/Components/ClearableSearchInput.vue';
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
    Calculator,
    CalendarClock,
    CheckSquare,
    Clock3,
    CloudSun,
    ExternalLink,
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
    dashboardWidgetSettings: Object,
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
const dragPreview = ref(null);
const noteEditor = ref(null);
const noteHtml = ref(props.dashboardNote?.html || '');
const passwordWidgetSearch = ref('');
const passwordRevealItem = ref(null);
const passwordRevealUsername = ref('');
const passwordRevealPassword = ref('');
const passwordRevealCopied = ref('');
const passwordRevealError = ref('');
const clockNow = ref(new Date());
const quickLinks = ref([...(props.dashboardWidgetSettings?.quick_links?.links || [])]);
const quickLinksDraft = ref([]);
const quickLinksOpen = ref(false);
const weatherSettings = ref(props.dashboardWidgetSettings?.weather || { city: 'Milano', latitude: 45.4642, longitude: 9.19 });
const weatherCityDraft = ref(weatherSettings.value.city);
const weatherData = ref(null);
const weatherLoading = ref(false);
const weatherError = ref('');
const weatherSettingsOpen = ref(false);
const calculatorOpen = ref(false);
const calculatorExpression = ref('');
const calculatorResult = ref('0');
let clockTimer = null;
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
    clock_date: {
        label: 'Orologio e data',
        description: 'Ora locale',
        route: 'dashboard',
        icon: Clock3,
        iconClass: 'text-blue-600',
        kind: 'clock',
    },
    quick_links: {
        label: 'Link rapidi',
        description: 'Collegamenti personali',
        route: 'dashboard',
        icon: ExternalLink,
        iconClass: 'text-violet-600',
        kind: 'links',
    },
    calculator: {
        label: 'Calcolatrice',
        description: 'Calcoli veloci',
        route: 'dashboard',
        icon: Calculator,
        iconClass: 'text-emerald-600',
        kind: 'calculator',
    },
    weather: {
        label: 'Meteo',
        description: 'Condizioni attuali',
        route: 'dashboard',
        icon: CloudSun,
        iconClass: 'text-amber-500',
        kind: 'weather',
    },
};

const compactWidgetKinds = new Set(['stat', 'clock', 'links', 'calculator', 'weather']);
const utilityWidgetKinds = new Set(['clock', 'links', 'calculator', 'weather']);

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

function rowSpanClass(widget) {
    const kind = metaFor(widget)?.kind;
    if (compactWidgetKinds.has(kind)) return 'lg:row-span-2';
    return 'lg:row-span-4';
}

function showsWidgetNumber(widget) {
    return ['stat', 'list', 'password', 'attendance'].includes(metaFor(widget)?.kind);
}

function isUtilityWidget(widget) {
    return utilityWidgetKinds.has(metaFor(widget)?.kind);
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
    const card = event.currentTarget.closest('[data-widget-type]');
    const rect = card?.getBoundingClientRect();
    moveState = {
        type: widget.widget_type,
        offsetX: rect ? event.clientX - rect.left : 24,
        offsetY: rect ? event.clientY - rect.top : 24,
    };
    dragPreview.value = rect ? {
        x: rect.left,
        y: rect.top,
        width: rect.width,
        height: rect.height,
        label: metaFor(widget).label,
        description: metaFor(widget).description,
        icon: metaFor(widget).icon,
        iconClass: metaFor(widget).iconClass,
        number: showsWidgetNumber(widget) ? widgetNumber(widget) : null,
    } : null;
    event.currentTarget.setPointerCapture?.(event.pointerId);
    document.body.style.cursor = 'grabbing';
    document.body.style.userSelect = 'none';

    window.addEventListener('pointermove', moveWidget);
    window.addEventListener('pointerup', stopMove, { once: true });
}

function moveWidget(event) {
    if (!moveState || !dashboardGrid.value) return;

    if (dragPreview.value) {
        dragPreview.value = {
            ...dragPreview.value,
            x: event.clientX - moveState.offsetX,
            y: event.clientY - moveState.offsetY,
        };
    }

    const nextIndex = resolveDropIndex(event);
    if (nextIndex !== dragOverIndex.value) reorderWidgetsDuringDrag(nextIndex);
}

function reorderWidgetsDuringDrag(nextIndex) {
    const sourceType = draggingType.value;
    if (!sourceType) return;

    const before = new Map(
        [...dashboardGrid.value.querySelectorAll('[data-widget-type]')]
            .map((element) => [element.dataset.widgetType, element.getBoundingClientRect()]),
    );
    const current = visibleWidgets.value.filter((widget) => widget.widget_type !== sourceType);
    const moved = widgets.value.find((widget) => widget.widget_type === sourceType);
    if (!moved) return;

    const target = Math.max(0, Math.min(nextIndex, current.length));
    current.splice(target, 0, moved);
    dragOverIndex.value = target;
    widgets.value = [...current, ...hiddenWidgets.value].map((widget, index) => ({ ...widget, position: index }));

    nextTick(() => {
        dashboardGrid.value?.querySelectorAll('[data-widget-type]').forEach((element) => {
            if (element.dataset.widgetType === sourceType) return;
            const previous = before.get(element.dataset.widgetType);
            const currentRect = element.getBoundingClientRect();
            if (!previous) return;
            const deltaX = previous.left - currentRect.left;
            const deltaY = previous.top - currentRect.top;
            if (!deltaX && !deltaY) return;
            element.animate(
                [
                    { transform: `translate(${deltaX}px, ${deltaY}px)` },
                    { transform: 'translate(0, 0)' },
                ],
                { duration: 260, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
            );
        });
    });
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
    dragPreview.value = null;
}

function dropOnGrid() {
    const sourceType = draggingType.value;
    const to = dragOverIndex.value;
    draggingType.value = null;
    dragOverIndex.value = null;

    if (!sourceType || to === null) return;

    commitWidgets(widgets.value);
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
    if (widget.widget_type === 'weather') loadWeather();
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

const clockTime = computed(() => clockNow.value.toLocaleTimeString('it-IT', {
    timeZone: APP_TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
}));
const clockDate = computed(() => clockNow.value.toLocaleDateString('it-IT', {
    timeZone: APP_TIME_ZONE,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
}));

function weatherDescription(code) {
    if (code === 0) return 'Sereno';
    if ([1, 2].includes(code)) return 'Poco nuvoloso';
    if (code === 3) return 'Nuvoloso';
    if ([45, 48].includes(code)) return 'Nebbia';
    if ([51, 53, 55, 56, 57].includes(code)) return 'Pioviggine';
    if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return 'Pioggia';
    if ([71, 73, 75, 77, 85, 86].includes(code)) return 'Neve';
    if ([95, 96, 99].includes(code)) return 'Temporale';
    return 'Variabile';
}

async function loadWeather() {
    weatherLoading.value = true;
    weatherError.value = '';
    try {
        const { latitude, longitude } = weatherSettings.value;
        const { data } = await window.axios.get('https://api.open-meteo.com/v1/forecast', {
            params: {
                latitude,
                longitude,
                current: 'temperature_2m,apparent_temperature,weather_code',
                timezone: 'Europe/Rome',
            },
        });
        weatherData.value = data?.current || null;
    } catch (error) {
        weatherError.value = 'Meteo non disponibile';
    } finally {
        weatherLoading.value = false;
    }
}

function openWeatherSettings() {
    weatherCityDraft.value = weatherSettings.value.city;
    weatherSettingsOpen.value = true;
}

async function saveWeatherSettings() {
    weatherError.value = '';
    try {
        const { data: geocoding } = await window.axios.get('https://geocoding-api.open-meteo.com/v1/search', {
            params: { name: weatherCityDraft.value, count: 1, language: 'it', format: 'json' },
        });
        const location = geocoding?.results?.[0];
        if (!location) {
            weatherError.value = 'Località non trovata';
            return;
        }
        const settings = { city: location.name, latitude: location.latitude, longitude: location.longitude };
        await window.axios.patch(route('dashboard.widgets.settings.update', 'weather'), settings);
        weatherSettings.value = settings;
        weatherSettingsOpen.value = false;
        await loadWeather();
    } catch (error) {
        weatherError.value = 'Impossibile salvare la località';
    }
}

function openQuickLinksSettings() {
    quickLinksDraft.value = quickLinks.value.map((link) => ({ ...link }));
    quickLinksOpen.value = true;
}

function addQuickLink() {
    if (quickLinksDraft.value.length < 8) quickLinksDraft.value.push({ label: '', url: '' });
}

function removeQuickLink(index) {
    quickLinksDraft.value.splice(index, 1);
}

async function saveQuickLinks() {
    const links = quickLinksDraft.value
        .map((link) => ({ label: link.label.trim(), url: link.url.trim() }))
        .filter((link) => link.label && link.url);
    await window.axios.patch(route('dashboard.widgets.settings.update', 'quick_links'), { links });
    quickLinks.value = links;
    quickLinksOpen.value = false;
}

function appendCalculator(value) {
    calculatorExpression.value += value;
}

function calculate() {
    const expression = calculatorExpression.value.replace(/×/g, '*').replace(/÷/g, '/').replace(/,/g, '.');
    if (!expression || !/^[0-9+\-*/().\s]+$/.test(expression)) return;
    try {
        const result = Function(`"use strict"; return (${expression})`)();
        if (Number.isFinite(result)) calculatorResult.value = String(Math.round(result * 1e10) / 1e10);
    } catch (error) {
        calculatorResult.value = 'Errore';
    }
}

function clearCalculator() {
    calculatorExpression.value = '';
    calculatorResult.value = '0';
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
    clockTimer = window.setInterval(() => { clockNow.value = new Date(); }, 1000);
    if (visibleWidgets.value.some((widget) => widget.widget_type === 'weather')) loadWeather();
    document.addEventListener('click', closeWidgetMenuOnOutside);
    window.addEventListener('centro:close-floating-ui', closeDashboardFloatingUi);
});

onUnmounted(() => {
    window.clearInterval(clockTimer);
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
                    class="grid grid-cols-1 gap-4 lg:auto-rows-[84px] lg:grid-flow-dense lg:grid-cols-4"
                >
                    <article
                        v-for="widget in visibleWidgets"
                        :key="widget.widget_type"
                        :data-widget-type="widget.widget_type"
                        :class="[
                            'app-card widget-card group relative flex min-h-[154px] flex-col overflow-hidden transition lg:h-full',
                            colSpanClass(widget),
                            rowSpanClass(widget),
                            draggingType === widget.widget_type ? 'widget-card-drag-source' : '',
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
                                <button v-if="widget.widget_type === 'quick_links'" type="button" class="icon-btn h-7 w-7" title="Configura link" @click="openQuickLinksSettings">
                                    <Settings2 class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                                <button v-if="widget.widget_type === 'weather'" type="button" class="icon-btn h-7 w-7" title="Configura località" @click="openWeatherSettings">
                                    <Settings2 class="h-4 w-4" :stroke-width="1.8" />
                                </button>
                                <button type="button" class="icon-btn h-7 w-7" :title="`Rimuovi ${metaFor(widget).label}`" @click="removeWidget(widget)">
                                    <X class="h-4 w-4" :stroke-width="1.8" />
                                    <span class="sr-only">Rimuovi</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="!isUtilityWidget(widget)" class="mt-10 flex items-start justify-between gap-4 rounded-2xl px-1 pb-3 pr-4">
                            <span class="flex min-w-0 items-start gap-3">
                                <span :class="['metric-icon', metaFor(widget).iconClass]">
                                    <component :is="metaFor(widget).icon" class="h-5 w-5" :stroke-width="1.7" />
                                </span>
                                <span class="min-w-0 pt-0.5">
                                    <span class="block truncate text-sm font-semibold text-gray-900">{{ metaFor(widget).label }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ metaFor(widget).description }}</span>
                                </span>
                            </span>
                            <span v-if="showsWidgetNumber(widget)" class="shrink-0 text-3xl font-bold leading-none text-gray-950">{{ widgetNumber(widget) }}</span>
                        </div>

                        <div v-if="metaFor(widget).kind === 'clock'" class="flex flex-1 items-end justify-between gap-3 pr-4">
                            <strong class="text-3xl font-semibold leading-none text-gray-950">{{ clockTime }}</strong>
                            <span class="max-w-[55%] text-right text-xs font-medium capitalize leading-4 text-gray-500">{{ clockDate }}</span>
                        </div>

                        <div v-if="metaFor(widget).kind === 'links'" class="flex min-h-0 flex-1 items-center pr-4">
                            <div v-if="quickLinks.length" class="flex max-w-full gap-2 overflow-x-auto py-1">
                                <a
                                    v-for="link in quickLinks"
                                    :key="`${link.label}-${link.url}`"
                                    :href="link.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-[var(--radius-sm)] border border-gray-100 bg-white/80 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-[hsl(var(--primary-app)/0.25)] hover:text-[hsl(var(--primary-app))]"
                                >
                                    {{ link.label }}
                                    <ExternalLink class="h-3 w-3" :stroke-width="1.8" />
                                </a>
                            </div>
                            <button v-else type="button" class="text-left text-xs font-medium text-gray-500 hover:text-[hsl(var(--primary-app))]" @click="openQuickLinksSettings">Aggiungi i tuoi collegamenti</button>
                        </div>

                        <button v-if="metaFor(widget).kind === 'calculator'" type="button" class="flex flex-1 items-end justify-between gap-3 pr-4 text-left" @click="calculatorOpen = true">
                            <span class="text-xs font-medium text-gray-500">Tocca per calcolare</span>
                            <strong class="max-w-[60%] truncate text-2xl font-semibold text-gray-950">{{ calculatorResult }}</strong>
                        </button>

                        <div v-if="metaFor(widget).kind === 'weather'" class="flex flex-1 items-end justify-between gap-3 pr-4">
                            <div>
                                <strong v-if="weatherData" class="text-3xl font-semibold leading-none text-gray-950">{{ Math.round(weatherData.temperature_2m) }}°</strong>
                                <span v-else class="text-sm font-medium text-gray-500">{{ weatherLoading ? 'Caricamento...' : weatherError }}</span>
                                <p v-if="weatherData" class="mt-1 text-xs text-gray-500">{{ weatherDescription(weatherData.weather_code) }}</p>
                            </div>
                            <span class="max-w-[48%] truncate text-right text-xs font-semibold text-gray-600">{{ weatherSettings.city }}</span>
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
                                <ClearableSearchInput v-model="passwordWidgetSearch" input-class="h-[38px]" placeholder="Cerca per titolo" />
                            </div>

                            <div class="max-h-[136px] min-h-[136px] space-y-1 overflow-y-auto px-1">
                                <button
                                    v-for="item in filteredPasswordItems"
                                    :key="`dashboard-password-${item.id}`"
                                    type="button"
                                    class="group/item flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left transition hover:-translate-y-0.5 hover:bg-white hover:shadow-[0_8px_18px_rgba(28,42,73,0.08)] hover:ring-1 hover:ring-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
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

        <Teleport to="body">
            <div
                v-if="dragPreview"
                class="widget-drag-preview pointer-events-none fixed z-[8000] overflow-hidden rounded-[var(--radius)]"
                :style="{
                    left: `${dragPreview.x}px`,
                    top: `${dragPreview.y}px`,
                    width: `${dragPreview.width}px`,
                    height: `${dragPreview.height}px`,
                }"
            >
                <div class="flex items-start justify-between gap-4 px-5 pt-5">
                    <span class="flex min-w-0 items-start gap-3">
                        <span :class="['metric-icon', dragPreview.iconClass]">
                            <component :is="dragPreview.icon" class="h-5 w-5" :stroke-width="1.7" />
                        </span>
                        <span class="min-w-0 pt-0.5">
                            <span class="block truncate text-sm font-semibold text-gray-900">{{ dragPreview.label }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ dragPreview.description }}</span>
                        </span>
                    </span>
                    <span v-if="dragPreview.number !== null" class="shrink-0 text-3xl font-bold leading-none text-gray-950">{{ dragPreview.number }}</span>
                </div>
                <div class="absolute inset-x-5 bottom-4 flex items-center gap-2 text-xs font-semibold text-indigo-600">
                    <GripVertical class="h-4 w-4" :stroke-width="1.9" />
                    Sposta e rilascia
                </div>
            </div>
        </Teleport>

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

        <Teleport to="body">
            <div v-if="quickLinksOpen" class="fixed inset-0 z-[8000] grid place-items-center bg-gray-950/20 px-4 backdrop-blur-[2px]" @click.self="quickLinksOpen = false">
                <form class="dashboard-utility-dialog surface max-h-[calc(100dvh-2rem)] w-full max-w-xl overflow-y-auto bg-white p-5" @submit.prevent="saveQuickLinks">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Link rapidi</h3>
                            <p class="mt-1 text-sm text-gray-500">Aggiungi fino a otto collegamenti personali.</p>
                        </div>
                        <button type="button" class="icon-btn" title="Chiudi" @click="quickLinksOpen = false"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="mt-5 space-y-3">
                        <div v-for="(link, index) in quickLinksDraft" :key="index" class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.5fr)_auto] items-end gap-3">
                            <label class="block text-sm font-medium text-gray-700">Nome<input v-model="link.label" class="form-control mt-1" maxlength="40" required /></label>
                            <label class="block text-sm font-medium text-gray-700">URL<input v-model="link.url" class="form-control mt-1" type="url" placeholder="https://" required /></label>
                            <button type="button" class="icon-btn mb-1 h-10 w-10 text-red-600" title="Rimuovi" @click="removeQuickLink(index)"><X class="h-4 w-4" /></button>
                        </div>
                        <button v-if="quickLinksDraft.length < 8" type="button" class="btn btn-outline" @click="addQuickLink"><Plus class="h-4 w-4" /> Aggiungi link</button>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn btn-outline" @click="quickLinksOpen = false">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>

            <div v-if="weatherSettingsOpen" class="fixed inset-0 z-[8000] grid place-items-center bg-gray-950/20 px-4 backdrop-blur-[2px]" @click.self="weatherSettingsOpen = false">
                <form class="dashboard-utility-dialog surface w-full max-w-md bg-white p-5" @submit.prevent="saveWeatherSettings">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Località meteo</h3>
                            <p class="mt-1 text-sm text-gray-500">Inserisci la città da mostrare nel widget.</p>
                        </div>
                        <button type="button" class="icon-btn" title="Chiudi" @click="weatherSettingsOpen = false"><X class="h-4 w-4" /></button>
                    </div>
                    <label class="mt-5 block text-sm font-medium text-gray-700">Città<input v-model="weatherCityDraft" class="form-control mt-1" required /></label>
                    <p v-if="weatherError" class="mt-2 text-sm text-red-600">{{ weatherError }}</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn btn-outline" @click="weatherSettingsOpen = false">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>

            <div v-if="calculatorOpen" class="fixed inset-0 z-[8000] grid place-items-center bg-gray-950/20 px-4 backdrop-blur-[2px]" @click.self="calculatorOpen = false">
                <section class="dashboard-utility-dialog surface w-full max-w-sm bg-white p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-gray-900">Calcolatrice</h3>
                        <button type="button" class="icon-btn" title="Chiudi" @click="calculatorOpen = false"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="mt-5 rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 px-4 py-3 text-right">
                        <p class="min-h-5 truncate text-sm text-gray-500">{{ calculatorExpression || '0' }}</p>
                        <p class="mt-1 truncate text-3xl font-semibold text-gray-950">{{ calculatorResult }}</p>
                    </div>
                    <div class="mt-4 grid grid-cols-4 gap-2">
                        <button type="button" class="calculator-key col-span-2" @click="clearCalculator">AC</button>
                        <button type="button" class="calculator-key" @click="calculatorExpression = calculatorExpression.slice(0, -1)">⌫</button>
                        <button type="button" class="calculator-key is-operator" @click="appendCalculator('÷')">÷</button>
                        <template v-for="key in ['7','8','9','×','4','5','6','-','1','2','3','+','0',',']" :key="key">
                            <button type="button" :class="['calculator-key', ['×','-','+'].includes(key) ? 'is-operator' : '', key === '0' ? 'col-span-2' : '']" @click="appendCalculator(key)">{{ key }}</button>
                        </template>
                        <button type="button" class="calculator-key is-equals" @click="calculate">=</button>
                    </div>
                </section>
            </div>
        </Teleport>
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

.dashboard-utility-dialog {
    animation: dashboardPasswordDialogIn 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

.calculator-key {
    min-height: 2.75rem;
    border: 1px solid rgb(229 231 235 / 0.9);
    border-radius: var(--radius-sm);
    background: rgb(249 250 251 / 0.9);
    color: rgb(31 41 55);
    font-weight: 600;
    cursor: pointer;
    transition: background-color 160ms ease, transform 160ms ease;
}

.calculator-key:hover {
    background: hsl(var(--primary-app) / 0.08);
    transform: translateY(-1px);
}

.calculator-key.is-operator {
    color: hsl(var(--primary-app));
}

.calculator-key.is-equals {
    border-color: transparent;
    background: hsl(var(--primary-app));
    color: white;
}
</style>
