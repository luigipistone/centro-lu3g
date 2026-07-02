<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDateInput from '@/Components/AppDateInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTimeInput from '@/Components/AppTimeInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import {
    activityText as formatActivityText,
    dateIt,
    dateTimeIt,
    displayValue,
    money,
    plainText,
    shortDateIt,
} from '@/utils/formatters';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Banknote,
    Bold,
    Briefcase,
    Building2,
    CalendarClock,
    CalendarDays,
    ChevronDown,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    Copy,
    CopyPlus,
    DatabaseBackup,
    ExternalLink,
    FileText,
    Filter,
    GitBranch,
    GripVertical,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Mail,
    MoreHorizontal,
    Pencil,
    Plus,
    Printer,
    Quote,
    Receipt,
    RefreshCw,
    RotateCcw,
    Save,
    Settings,
    ShieldCheck,
    TrendingUp,
    Trash2,
    Underline,
    UserCog,
    UserPlus,
    UserRound,
    Users,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    section: String,
    title: String,
    description: String,
    columns: Array,
    fields: Array,
    rows: Array,
    clients: Array,
    projects: Array,
    services: Array,
    users: Array,
    taskDependencyOptions: Array,
    projectTemplates: Array,
    billingStats: Object,
    clientStats: Object,
    documentSettings: Object,
    emailSettings: Object,
    numberings: Array,
    backupRuns: Array,
    serviceName: String,
});

const AUTOSAVE_IDLE_DELAY = 2500;

function autosaveDelay(delay = AUTOSAVE_IDLE_DELAY) {
    return Number(delay) > 0 ? Number(delay) : AUTOSAVE_IDLE_DELAY;
}

const editing = ref(null);
const formOpen = ref(false);
const deleteTarget = ref(null);
const deleteTargetAction = ref(null);
const deleteConfirmText = ref('');
const restoreTarget = ref(null);
const restoreConfirmText = ref('');
const updateDrafts = ref({});
const savingUpdateKeys = ref([]);
const page = usePage();
const currentRole = computed(() => page.props.auth?.user?.role || 'guest');
const isGuest = computed(() => page.props.auth?.user?.role === 'guest');
const isEditor = computed(() => currentRole.value === 'editor');
const isSuperadmin = computed(() => currentRole.value === 'superadmin');
const canWrite = computed(() => {
    if (!props.fields.length || isGuest.value) return false;
    if (!isEditor.value) return true;

    return props.section === 'projects' || props.section === 'tasks' || props.section.startsWith('updates-');
});
const canCreate = computed(() => {
    if (!canWrite.value) return false;
    if (!isEditor.value) return true;

    return props.section === 'tasks' || props.section.startsWith('updates-');
});
const billingSearch = ref('');
const billingType = ref('all');
const billingStatus = ref('all');
const absenceStatus = ref('all');
const absenceDrafts = ref({});
const absenceAutosaveTimers = {};
const currentCalendarDate = ref(new Date());
const calendarType = ref('all');
const calendarUserIds = ref([]);
const calendarPeopleMenu = ref(null);
const calendarPeopleMenuOpen = ref(false);
const calendarPeopleMenuStyle = ref({});
const compactWeekend = ref(true);
const calendarCreateDate = ref(null);
const calendarDraggedTask = ref(null);
const calendarDropDate = ref(null);
const expandedCalendarDays = ref([]);
const calendarTaskPanelOpen = ref(false);
const calendarTaskPanel = ref(null);
const calendarRowOverrides = ref({});
const calendarTaskParentStack = ref([]);
const calendarTaskPanelMode = ref('edit');
const calendarTaskPanelClosedByUser = ref(false);
const calendarTaskDrawerBody = ref(null);
const calendarScrollArea = ref(null);
let calendarScrollResetting = false;
let calendarScrollTimer = null;
const calendarTaskAutosaveState = ref('idle');
const calendarTaskAutosaveError = ref('');
let calendarTaskAutosaveTimer = null;
let calendarTaskAutosaveSequence = 0;
const calendarTaskDescriptionEditor = ref(null);
const calendarSubtaskDrafts = ref({});
const calendarSubtaskAutosaveStates = ref({});
const calendarSubtaskAutosaveErrors = ref({});
const calendarCommentDrafts = ref({});
const calendarCommentAutosaveStates = ref({});
const calendarCommentAutosaveErrors = ref({});
const calendarEditingCommentId = ref(null);
const calendarTaskFeedTab = ref('comments');
const calendarShowAllComments = ref(false);
const calendarShowAllActivity = ref(false);
const calendarTaskActionMenuOpen = ref(false);
const calendarTaskActionMenuStyle = ref({});
const calendarSubtaskAutosaveTimers = {};
const calendarSubtaskAutosaveSequences = {};
const calendarCommentAutosaveTimers = {};
const calendarCommentAutosaveSequences = {};
let calendarBodyOverflow = '';
const taskWeekStart = ref(startOfWeek(new Date()));
const taskSearch = ref('');
const taskStatus = ref('all');
const taskPriority = ref('all');
const taskType = ref('all');
const clientSearch = ref('');
const clientService = ref('all');
const projectSearch = ref('');
const projectStatus = ref('all');
const projectUserIds = ref([]);
const projectPeopleMenu = ref(null);
const projectPeopleMenuOpen = ref(false);
const taskPeopleMenu = ref(null);
const taskPeopleMenuOpen = ref(null);
const taskSearchSelectOpen = ref(null);
const taskSearchSelectQueries = ref({
    project_id: '',
    client_id: '',
    service_id: '',
    priority: '',
});
const taskDescriptionEditor = ref(null);
const settingsTab = ref('personalizzazione');
const userRoleFilter = ref('all');

const calendarTypeOptions = [
    { value: 'all', label: 'Tutti i tipi' },
    { value: 'task', label: 'Task' },
    { value: 'ongoing', label: 'Continuativa' },
    { value: 'meeting', label: 'Meeting' },
];
const projectStatusOptions = [
    { value: 'all', label: 'Tutti gli stati' },
    { value: 'active', label: 'Attivi' },
    { value: 'completed', label: 'Completati' },
    { value: 'on_hold', label: 'In pausa' },
    { value: 'archived', label: 'Archiviati' },
];
const taskStatusOptions = [
    { value: 'all', label: 'Tutti gli stati' },
    { value: 'todo', label: 'Da fare' },
    { value: 'in_progress', label: 'In corso' },
    { value: 'in_review', label: 'Review' },
    { value: 'done', label: 'Fatte' },
];
const taskPriorityOptions = [
    { value: 'all', label: 'Tutte priorità' },
    { value: 'urgent', label: 'Urgente' },
    { value: 'high', label: 'Alta' },
    { value: 'medium', label: 'Media' },
    { value: 'low', label: 'Bassa' },
];
const taskTypeOptions = [
    { value: 'all', label: 'Tutti i tipi' },
    { value: 'task', label: 'Task' },
    { value: 'project', label: 'Progetto' },
    { value: 'ongoing', label: 'Continuativa' },
    { value: 'meeting', label: 'Meeting' },
];
const taskEditTypeOptions = [
    { value: 'project', label: 'Task' },
    { value: 'ongoing', label: 'Continuativa' },
    { value: 'meeting', label: 'Meeting' },
];
const recurrenceUnitOptions = [
    { value: 'week', label: 'Settimana' },
    { value: 'month', label: 'Mese' },
];
const recurrenceModeOptions = [
    { value: 'fixed', label: 'Fissa' },
    { value: 'relative', label: 'Relativa' },
];
const updateCadenceOptions = [
    { value: '', label: 'Seleziona' },
    { value: 'on_request', label: 'Su richiesta' },
    { value: 'weekly', label: 'Settimanale' },
    { value: 'biweekly', label: 'Bisettimanale' },
    { value: 'monthly', label: 'Mensile' },
];

const docSettingDefaults = {
    company_name: 'Il Centro',
    legal_form: '',
    vat_number: '',
    tax_code: '',
    tax_regime: '',
    street: '',
    street_number: '',
    postal_code: '',
    city: '',
    province: '',
    country: 'IT',
    email: '',
    pec: '',
    phone: '',
    sdi_code: '',
    iban: '',
    bic_swift: '',
    bank_name: '',
    default_payment_method: '',
    default_payment_terms_days: '',
    default_withholding_pct: '',
    default_pension_fund_label: '',
    default_pension_fund_pct: '',
    bollo_threshold: '',
    bollo_amount: '',
    bollo_charged_to_client: false,
    footer_notes: '',
};

const emailSettingDefaults = {
    smtp_enabled: false,
    smtp_host: '',
    smtp_port: '',
    smtp_username: '',
    smtp_password: '',
    smtp_password_saved: false,
    smtp_secure: true,
    smtp_from_email: '',
    smtp_from_name: '',
    smtp_reply_to: '',
    pec_username: '',
    pec_password: '',
    pec_password_saved: false,
};

const toBoolean = (value) => value === true || value === 1 || value === '1' || value === 'true';
const emailSettingsInitial = {
    ...emailSettingDefaults,
    ...(props.emailSettings || {}),
    smtp_enabled: toBoolean(props.emailSettings?.smtp_enabled),
    smtp_secure: props.emailSettings ? toBoolean(props.emailSettings.smtp_secure) : true,
    smtp_password_saved: toBoolean(props.emailSettings?.smtp_password_saved),
    pec_password_saved: toBoolean(props.emailSettings?.pec_password_saved),
};

const documentSettingsForm = useForm({ ...docSettingDefaults, ...(props.documentSettings || {}) });
const emailSettingsForm = useForm({
    ...emailSettingsInitial,
    smtp_password: '',
    pec_password: '',
});
const testEmailForm = useForm({
    recipient: '',
});
const numberingRows = ref((props.numberings || []).map((row) => ({ ...row })));

const routeBase = computed(() => {
    if (props.section === 'settings') return 'settings';
    return props.section;
});

const defaults = computed(() => {
    const base = Object.fromEntries(props.fields.map((field) => {
        if (field.type === 'checkbox') return [field.name, field.name === 'recurring_enabled' ? false : true];
        if (field.name === 'status' && props.section === 'projects') return [field.name, 'active'];
        if (field.name === 'status' && props.section === 'tasks') return [field.name, 'todo'];
        if (field.name === 'priority') return [field.name, 'medium'];
        if (field.name === 'task_type') return [field.name, 'project'];
        if (field.name === 'recurring_interval_value') return [field.name, 1];
        if (field.name === 'recurring_interval_unit') return [field.name, 'week'];
        if (field.name === 'recurring_mode') return [field.name, 'fixed'];
        if (field.name === 'recurring_weekday') return [field.name, 1];
        if (field.name === 'recurring_month_day') return [field.name, 1];
        if (field.name === 'color') return [field.name, '#2563eb'];
        return [field.name, ''];
    }));

    if (props.section === 'tasks') {
        base.assignee_ids = [];
        base.follower_ids = [];
        base.dependency_ids = [];
        base.dependent_ids = [];
    }
    if (props.section === 'projects') {
        base.template_id = '';
        base.template_start_date = new Date().toISOString().slice(0, 10);
    }

    return base;
});

const form = useForm({ ...defaults.value });
const formDependencyToAdd = ref('');
const formDependencyDirection = ref('blocked_by');
const calendarTaskForm = useForm({
    id: '',
    title: '',
    description: '',
    project_id: '',
    client_id: '',
    service_id: '',
    task_type: 'project',
    status: 'todo',
    priority: 'medium',
    start_date: '',
    due_date: '',
    due_time: '',
    location: '',
    recurring_enabled: false,
    recurring_interval_value: 1,
    recurring_interval_unit: 'week',
    recurring_mode: 'fixed',
    recurring_weekday: 1,
    recurring_month_day: 1,
    assignee_ids: [],
    follower_ids: [],
    dependency_ids: [],
    dependent_ids: [],
});
const calendarDependencyToAdd = ref('');
const calendarDependencyDirection = ref('blocked_by');
const calendarSubtaskForm = useForm({
    title: '',
    priority: 'medium',
    due_date: '',
    assignee_ids: [],
});
const calendarCreateSubtaskAssigneeIds = ref([]);
const calendarSubtaskAssigneeMenuOpen = ref(null);
const calendarSubtaskAssigneeMenuStyle = ref({});
const calendarSubtaskCreateAssigneeMenuOpen = ref(false);
const calendarSubtaskCreateAssigneeMenuStyle = ref({});
const calendarSubtaskStatusPulse = ref(null);
const calendarDraggedSubtaskId = ref(null);
const calendarSubtaskDropTarget = ref(null);
const calendarSubtaskDropPlacement = ref(null);
const calendarTaskStatusPulse = ref(false);
const calendarCommentForm = useForm({
    content: '',
});
const taskCreateTypeLabels = {
    project: 'Task',
    task: 'Task',
    ongoing: 'Continuativa',
    meeting: 'Meeting',
};
const projectColors = ['#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#475569'];

function normalizeHexColor(value, fallback = '#2563eb') {
    const color = String(value || '').trim();
    if (/^#[0-9a-f]{6}$/i.test(color)) return color;
    if (/^#[0-9a-f]{3}$/i.test(color)) {
        return `#${color.slice(1).split('').map((char) => char + char).join('')}`;
    }
    return fallback;
}

function hexToRgb(value) {
    const hex = normalizeHexColor(value).slice(1);
    return {
        r: parseInt(hex.slice(0, 2), 16),
        g: parseInt(hex.slice(2, 4), 16),
        b: parseInt(hex.slice(4, 6), 16),
    };
}

function isLightColor(value) {
    const { r, g, b } = hexToRgb(value);
    const luminance = ((0.2126 * r) + (0.7152 * g) + (0.0722 * b)) / 255;
    return luminance > 0.62;
}

function projectCardStyle(project) {
    const backgroundColor = normalizeHexColor(project.color, '#64748b');
    const light = isLightColor(backgroundColor);

    return {
        backgroundColor,
        color: light ? '#111827' : '#ffffff',
        borderColor: light ? 'rgba(17, 24, 39, 0.14)' : 'rgba(255, 255, 255, 0.24)',
        boxShadow: light ? '0 18px 40px rgba(28, 42, 73, 0.12)' : '0 18px 40px rgba(15, 23, 42, 0.22)',
    };
}

function projectCardMutedStyle(project) {
    return {
        color: isLightColor(project.color || '#64748b') ? 'rgba(17, 24, 39, 0.68)' : 'rgba(255, 255, 255, 0.78)',
    };
}

function projectCardChipStyle(project) {
    const light = isLightColor(project.color || '#64748b');

    return {
        color: light ? '#111827' : '#ffffff',
        borderColor: light ? 'rgba(17, 24, 39, 0.14)' : 'rgba(255, 255, 255, 0.28)',
        backgroundColor: light ? 'rgba(255, 255, 255, 0.46)' : 'rgba(255, 255, 255, 0.16)',
    };
}
const settingsTabs = [
    ['personalizzazione', 'Personalizzazione', Building2],
    ['fatturazione', 'Fatturazione', Receipt],
    ['smtp', 'SMTP', Mail],
    ['backup', 'Backup', DatabaseBackup],
    ['gestione', 'Gestione', Settings],
];

const columnLabels = {
    name: 'Nome',
    title: 'Titolo',
    email: 'Email',
    phone: 'Telefono',
    website: 'Sito web',
    status: 'Stato',
    priority: 'Priorità',
    task_type: 'Tipo',
    start_date: 'Inizio',
    due_date: 'Scadenza',
    due_time: 'Ora',
    active: 'Attivo',
    project_name: 'Progetto',
    client_name: 'Cliente',
    service_name: 'Servizio',
    responsible_name: 'Responsabile',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
};

function displayColumn(column) {
    return columnLabels[column] || column.replaceAll('_', ' ');
}

const createButtonLabel = computed(() => ({
    clients: 'Nuovo Cliente',
    projects: 'Nuovo Progetto',
    tasks: 'Nuovo Task',
    billing: 'Nuovo documento',
    users: 'Nuovo utente',
    settings: 'Aggiungi servizio',
}[props.section] || 'Nuovo'));

const formTitle = computed(() => {
    if (props.section === 'tasks' && !editing.value) {
        return `Nuovo ${taskCreateTypeLabels[form.task_type] || 'Task'}`;
    }

    return editing.value ? `Modifica ${createButtonLabel.value.replace(/^Nuovo |^Nuova /, '').toLowerCase()}` : createButtonLabel.value;
});

const modalPanelClass = computed(() => [
    'max-h-[92vh] w-full overflow-y-auto rounded-[var(--radius)] bg-white shadow-xl',
    props.section === 'tasks' ? 'max-w-3xl' : 'max-w-4xl',
]);

const modalFormClass = computed(() => {
    if (props.section === 'tasks') return 'grid gap-3 p-5 md:grid-cols-6';
    if (props.section === 'clients' || props.section === 'billing') return 'grid gap-4 p-5 md:grid-cols-3';

    return 'space-y-4 p-5';
});

function modalFieldClass(field) {
    if (props.section !== 'tasks') {
        return field.type === 'textarea' || ['description', 'notes', 'footer_notes'].includes(field.name) ? 'md:col-span-3' : '';
    }

    if (['title', 'description'].includes(field.name)) return 'md:col-span-6';
    if (['project_id', 'client_id', 'service_id', 'priority', 'start_date', 'due_date', 'due_time', 'recurring_enabled', 'recurring_interval_value', 'recurring_interval_unit', 'recurring_mode', 'recurring_weekday', 'recurring_month_day'].includes(field.name)) {
        return 'md:col-span-2';
    }
    if (field.name === 'status') return 'md:col-span-2';
    if (field.name === 'location') return 'md:col-span-2';

    return 'md:col-span-2';
}

hydrateTaskCreateFromUrl();

function optionsFor(field) {
    if (field.type === 'client') return props.clients;
    if (field.type === 'project') return props.projects;
    if (field.type === 'service') return props.services;
    if (field.type === 'user') return props.users;
    return (field.options || []).map((value) => ({ id: value, name: value }));
}

function optionLabel(field, option) {
    if (field.name === 'payment_terms_days') {
        return Number(option.name) === 0 ? 'A vista' : `${option.name} giorni`;
    }

    return displayValue(option.name);
}

function fieldSelectOptions(field, emptyLabel = 'Seleziona') {
    const options = optionsFor(field).map((option) => ({
        value: option.id,
        label: optionLabel(field, option),
    }));

    return field.required ? options : [{ value: '', label: emptyLabel }, ...options];
}

function objectOptions(source, emptyOption = null) {
    const options = Object.entries(source).map(([value, label]) => ({ value, label }));

    return emptyOption ? [emptyOption, ...options] : options;
}

function namedOptions(source, emptyOption = null) {
    const options = source.map((item) => ({ value: item.id, label: item.name || item.email || item.title || item.id }));

    return emptyOption ? [emptyOption, ...options] : options;
}

const projectTemplateOptions = computed(() => [
    { value: '', label: 'Nessun modello' },
    ...(props.projectTemplates || []).map((template) => ({
        value: template.id,
        label: template.name,
    })),
]);

function taskDependencyLabel(task) {
    return [task.title, task.client_name, task.due_date ? dateIt(task.due_date) : null]
        .filter(Boolean)
        .join(' · ');
}

const taskDependencyDirectionOptions = [
    { value: 'blocked_by', label: 'Bloccata da' },
    { value: 'blocks', label: 'Blocca' },
];

function taskDependencySelectOptions(currentTaskId = null) {
    const selected = calendarDependencyDirection.value === 'blocks' ? (calendarTaskForm.dependent_ids || []) : (calendarTaskForm.dependency_ids || []);
    const opposite = calendarDependencyDirection.value === 'blocks' ? (calendarTaskForm.dependency_ids || []) : (calendarTaskForm.dependent_ids || []);

    return (props.taskDependencyOptions || [])
        .filter((task) => task.id !== currentTaskId && task.status !== 'done' && !selected.includes(task.id) && !opposite.includes(task.id))
        .map((task) => ({
            value: task.id,
            label: taskDependencyLabel(task),
        }));
}

function formTaskDependencySelectOptions() {
    const selected = formDependencyDirection.value === 'blocks' ? (form.dependent_ids || []) : (form.dependency_ids || []);
    const opposite = formDependencyDirection.value === 'blocks' ? (form.dependency_ids || []) : (form.dependent_ids || []);

    return (props.taskDependencyOptions || [])
        .filter((task) => task.status !== 'done' && !selected.includes(task.id) && !opposite.includes(task.id))
        .map((task) => ({
            value: task.id,
            label: taskDependencyLabel(task),
        }));
}

function selectedFormDependencies() {
    const selected = form.dependency_ids || [];
    const byId = new Map((props.taskDependencyOptions || []).map((task) => [task.id, task]));

    return selected.map((id) => byId.get(id)).filter(Boolean);
}

function selectedFormDependents() {
    const selected = form.dependent_ids || [];
    const byId = new Map((props.taskDependencyOptions || []).map((task) => [task.id, task]));

    return selected.map((id) => byId.get(id)).filter(Boolean);
}

function addFormTaskDependency(taskId) {
    if (!taskId) return;

    if (formDependencyDirection.value === 'blocks') {
        if ((form.dependent_ids || []).includes(taskId) || (form.dependency_ids || []).includes(taskId)) return;

        form.dependent_ids = [...(form.dependent_ids || []), taskId];
    } else {
        if ((form.dependency_ids || []).includes(taskId) || (form.dependent_ids || []).includes(taskId)) return;

        form.dependency_ids = [...(form.dependency_ids || []), taskId];
    }

    formDependencyToAdd.value = '';
}

function removeFormTaskDependency(taskId) {
    form.dependency_ids = (form.dependency_ids || []).filter((id) => id !== taskId);
}

function removeFormTaskDependent(taskId) {
    form.dependent_ids = (form.dependent_ids || []).filter((id) => id !== taskId);
}

function selectedCalendarDependencies() {
    const selected = calendarTaskForm.dependency_ids || [];
    const byId = new Map([...(props.taskDependencyOptions || []), ...(calendarTaskPanel.value?.dependencies || [])].map((task) => [task.id, task]));

    return selected.map((id) => byId.get(id)).filter(Boolean);
}

function selectedCalendarDependents() {
    const selected = calendarTaskForm.dependent_ids || [];
    const byId = new Map([...(props.taskDependencyOptions || []), ...(calendarTaskPanel.value?.dependents || [])].map((task) => [task.id, task]));

    return selected.map((id) => byId.get(id)).filter(Boolean);
}

function syncCalendarTaskDependencies() {
    if (!calendarTaskForm.id) return;

    router.put(route('tasks.dependencies.sync', calendarTaskForm.id), {
        dependency_ids: calendarTaskForm.dependency_ids || [],
        dependent_ids: calendarTaskForm.dependent_ids || [],
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['rows', 'errors', 'flash'],
        onSuccess: () => {
            calendarTaskPanel.value = {
                ...(calendarTaskPanel.value || {}),
                dependencies: selectedCalendarDependencies(),
                dependents: selectedCalendarDependents(),
                blocked_dependencies_count: selectedCalendarDependencies().filter((dependency) => dependency.status !== 'done').length,
            };
        },
    });
}

function addCalendarTaskDependency(dependencyId) {
    if (!dependencyId) return;

    if (calendarDependencyDirection.value === 'blocks') {
        if ((calendarTaskForm.dependent_ids || []).includes(dependencyId) || (calendarTaskForm.dependency_ids || []).includes(dependencyId)) return;

        calendarTaskForm.dependent_ids = [...(calendarTaskForm.dependent_ids || []), dependencyId];
    } else {
        if ((calendarTaskForm.dependency_ids || []).includes(dependencyId) || (calendarTaskForm.dependent_ids || []).includes(dependencyId)) return;

        calendarTaskForm.dependency_ids = [...(calendarTaskForm.dependency_ids || []), dependencyId];
    }

    calendarDependencyToAdd.value = '';
    syncCalendarTaskDependencies();
}

function removeCalendarTaskDependency(dependencyId) {
    calendarTaskForm.dependency_ids = (calendarTaskForm.dependency_ids || []).filter((id) => id !== dependencyId);
    syncCalendarTaskDependencies();
}

function removeCalendarTaskDependent(dependentId) {
    calendarTaskForm.dependent_ids = (calendarTaskForm.dependent_ids || []).filter((id) => id !== dependentId);
    syncCalendarTaskDependencies();
}

function blockedDependencyCount(task) {
    return Number(task?.blocked_dependencies_count || (task?.dependencies || []).filter((dependency) => dependency.status !== 'done').length || 0);
}

function isTaskSearchSelect(field) {
    return props.section === 'tasks' && ['project_id', 'client_id', 'service_id', 'priority'].includes(field.name);
}

function taskSearchSelectLabel(field) {
    const selected = optionsFor(field).find((option) => option.id === form[field.name]);

    return selected ? optionLabel(field, selected) : `Seleziona ${field.label.toLowerCase()}`;
}

function taskSearchEmptyLabel(field) {
    return {
        project_id: 'Nessun progetto',
        client_id: 'Nessun cliente',
        service_id: 'Nessun servizio',
    }[field.name] || 'Nessuna selezione';
}

function canClearTaskSearchSelect(field) {
    return field.name !== 'priority' && !field.required;
}

function filteredTaskSearchOptions(field) {
    const query = (taskSearchSelectQueries.value[field.name] || '').trim().toLowerCase();
    const options = optionsFor(field);
    if (!query) return options;

    return options.filter((option) => optionLabel(field, option).toLowerCase().includes(query));
}

function toggleTaskSearchSelect(field) {
    const nextOpen = taskSearchSelectOpen.value === field.name ? null : field.name;
    if (nextOpen) requestFloatingUiClose();
    taskSearchSelectOpen.value = nextOpen;
    taskSearchSelectQueries.value[field.name] = '';
}

function selectTaskSearchOption(field, value) {
    form[field.name] = value;
    taskSearchSelectOpen.value = null;
    taskSearchSelectQueries.value[field.name] = '';
}

function closeTaskSearchSelectOnOutside(event) {
    if (!taskSearchSelectOpen.value) return;
    if (event.target instanceof Element && event.target.closest(`[data-task-search-field="${taskSearchSelectOpen.value}"]`)) return;

    taskSearchSelectOpen.value = null;
}

function shouldShowField(field) {
    if (isUpdatesSection.value) {
        if (field.name === 'report_url') return showUpdateReport.value;
        if (['cadence', 'contact'].includes(field.name)) return showUpdateNewsletter.value;
    }
    if (props.section !== 'tasks') return true;
    if (field.name === 'task_type') return false;
    if (field.name === 'status' && !editing.value) return false;
    if (['recurring_interval_value', 'recurring_interval_unit', 'recurring_mode', 'recurring_weekday', 'recurring_month_day'].includes(field.name)) return false;
    if (field.name === 'location') return form.task_type === 'meeting';
    if (field.name === 'due_time') return form.task_type === 'meeting' || Boolean(form.due_date);
    if (field.name === 'project_id') return ['project', 'task'].includes(form.task_type);
    if (field.name === 'recurring_enabled') return form.task_type !== 'meeting';
    return true;
}

function toggleFormPerson(field, userId) {
    const current = [...(form[field] || [])];
    const index = current.indexOf(userId);
    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push(userId);
    }
    form[field] = current;
}

function selectedFormUsers(field) {
    const selected = form[field] || [];
    return (props.users || []).filter((user) => selected.includes(user.id));
}

function taskPeopleLabel(field) {
    const selected = selectedFormUsers(field);
    if (!selected.length) return field === 'assignee_ids' ? 'Nessuna persona' : 'Nessun follower';
    if (selected.length === 1) return selected[0].name || selected[0].email || '1 persona';

    return `${selected.length} persone`;
}

function selectedCalendarTaskUsers(field) {
    const selected = calendarTaskForm[field] || [];

    return (props.users || []).filter((user) => selected.includes(user.id));
}

function calendarSubtaskAssignees(subtaskId) {
    const selected = calendarSubtaskDrafts.value[subtaskId]?.assignee_ids || [];

    return (props.users || []).filter((user) => selected.includes(user.id));
}

function calendarCreateSubtaskAssignees() {
    return (props.users || []).filter((user) => calendarCreateSubtaskAssigneeIds.value.includes(user.id));
}

function toggleCalendarCreateSubtaskAssigneeMenu(event = null) {
    const nextOpen = !calendarSubtaskCreateAssigneeMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    calendarSubtaskCreateAssigneeMenuStyle.value = floatingMenuStyleFromEvent(event);
    calendarSubtaskCreateAssigneeMenuOpen.value = nextOpen;
}

function toggleCalendarCreateSubtaskAssignee(userId) {
    const values = [...calendarCreateSubtaskAssigneeIds.value];
    const index = values.indexOf(userId);
    if (index >= 0) {
        values.splice(index, 1);
    } else {
        values.push(userId);
    }
    calendarCreateSubtaskAssigneeIds.value = values;
}

function openInlineDatePicker(event) {
    const input = event.currentTarget?.closest('[data-inline-date]')?.querySelector('input[type="date"]');
    if (!input) return;

    if (typeof input.showPicker === 'function') {
        input.showPicker();
        return;
    }

    input.focus();
    input.click();
}

function startCalendarSubtaskDrag(subtask) {
    calendarDraggedSubtaskId.value = subtask.id;
}

function dragOverCalendarSubtask(targetSubtask, event) {
    if (!calendarDraggedSubtaskId.value || calendarDraggedSubtaskId.value === targetSubtask.id) return;
    const rect = event.currentTarget.getBoundingClientRect();
    calendarSubtaskDropTarget.value = targetSubtask.id;
    calendarSubtaskDropPlacement.value = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
}

function dropCalendarSubtask(targetSubtask) {
    const fromId = calendarDraggedSubtaskId.value;
    const placement = calendarSubtaskDropPlacement.value || 'before';
    calendarDraggedSubtaskId.value = null;
    calendarSubtaskDropTarget.value = null;
    calendarSubtaskDropPlacement.value = null;
    if (!fromId || fromId === targetSubtask.id || !calendarTaskPanel.value) return;

    const current = calendarPanelSubtasks();
    const fromIndex = current.findIndex((subtask) => subtask.id === fromId);
    let toIndex = current.findIndex((subtask) => subtask.id === targetSubtask.id);
    if (fromIndex < 0 || toIndex < 0) return;

    const [moved] = current.splice(fromIndex, 1);
    if (fromIndex < toIndex) toIndex -= 1;
    if (placement === 'after') toIndex += 1;
    current.splice(toIndex, 0, moved);
    calendarTaskPanel.value = {
        ...calendarTaskPanel.value,
        subtasks: current,
    };

    router.put(route('tasks.subtasks.reorder', calendarTaskPanel.value.id), {
        ids: current.map((subtask) => subtask.id),
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function endCalendarSubtaskDrag() {
    calendarDraggedSubtaskId.value = null;
    calendarSubtaskDropTarget.value = null;
    calendarSubtaskDropPlacement.value = null;
}

function floatingMenuStyleFromEvent(event, width = 288) {
    const rect = event?.currentTarget?.getBoundingClientRect?.();
    if (!rect) return { right: '1.5rem', top: '50%', transform: 'translateY(-50%)' };

    const left = Math.min(Math.max(12, rect.right - width), window.innerWidth - width - 12);
    const bottom = Math.max(12, window.innerHeight - rect.top + 8);

    return {
        left: `${left}px`,
        bottom: `${bottom}px`,
    };
}

function dropdownMenuStyleFromEvent(event, width = 220) {
    const rect = event?.currentTarget?.getBoundingClientRect?.();
    if (!rect) return { right: '1.5rem', top: '4.5rem' };

    const left = Math.min(Math.max(12, rect.right - width), window.innerWidth - width - 12);
    const top = Math.min(rect.bottom + 8, window.innerHeight - 12);

    return {
        left: `${left}px`,
        top: `${top}px`,
    };
}

function requestFloatingUiClose() {
    window.dispatchEvent(new CustomEvent('centro:close-floating-ui'));
}

function closeCentroIndexFloatingUi() {
    calendarCreateDate.value = null;
    calendarTaskActionMenuOpen.value = false;
    calendarSubtaskCreateAssigneeMenuOpen.value = false;
    calendarSubtaskAssigneeMenuOpen.value = null;
    calendarPeopleMenuOpen.value = false;
    projectPeopleMenuOpen.value = false;
    taskPeopleMenuOpen.value = null;
    taskSearchSelectOpen.value = null;
}

function calendarTaskPeopleLabel(field) {
    const selected = selectedCalendarTaskUsers(field);
    if (!selected.length) return field === 'assignee_ids' ? 'Nessuna persona' : 'Nessun follower';
    if (selected.length === 1) return selected[0].name || selected[0].email || '1 persona';

    return `${selected.length} persone`;
}

function toggleCalendarTaskPerson(field, userId) {
    const current = [...(calendarTaskForm[field] || [])];
    const index = current.indexOf(userId);
    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push(userId);
    }
    calendarTaskForm[field] = current;
    saveCalendarTaskInline(0);
}

function toggleCalendarSubtaskAssigneeMenu(subtaskId, event = null) {
    const nextOpen = calendarSubtaskAssigneeMenuOpen.value === subtaskId ? null : subtaskId;
    if (nextOpen) requestFloatingUiClose();
    calendarSubtaskAssigneeMenuStyle.value = floatingMenuStyleFromEvent(event);
    calendarSubtaskAssigneeMenuOpen.value = nextOpen;
}

function closeCalendarSubtaskAssigneeMenuOnOutside(event) {
    if (calendarTaskActionMenuOpen.value && !(event.target instanceof Element && event.target.closest('[data-calendar-task-actions-menu]'))) {
        calendarTaskActionMenuOpen.value = false;
    }
    if (calendarSubtaskCreateAssigneeMenuOpen.value && !(event.target instanceof Element && event.target.closest('[data-calendar-subtask-create-assignees]'))) {
        calendarSubtaskCreateAssigneeMenuOpen.value = false;
    }
    if (!calendarSubtaskAssigneeMenuOpen.value) return;
    if (event.target instanceof Element && event.target.closest(`[data-calendar-subtask-assignees="${calendarSubtaskAssigneeMenuOpen.value}"]`)) return;

    calendarSubtaskAssigneeMenuOpen.value = null;
}

function toggleCalendarSubtaskAssignee(subtask, userId) {
    if (!calendarSubtaskDrafts.value[subtask.id]) return;

    const values = [...(calendarSubtaskDrafts.value[subtask.id].assignee_ids || [])];
    const index = values.indexOf(userId);
    if (index >= 0) {
        values.splice(index, 1);
    } else {
        values.push(userId);
    }

    calendarSubtaskDrafts.value = {
        ...calendarSubtaskDrafts.value,
        [subtask.id]: {
            ...calendarSubtaskDrafts.value[subtask.id],
            assignee_ids: values,
        },
    };
    router.put(route('tasks.people.sync', [subtask.id, 'assignees']), {
        user_ids: values,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['rows', 'errors', 'flash'],
        onError: () => {
            calendarSubtaskDrafts.value = {
                ...calendarSubtaskDrafts.value,
                [subtask.id]: {
                    ...calendarSubtaskDrafts.value[subtask.id],
                    assignee_ids: subtask.assignee_ids || [],
                },
            };
        },
    });
}

function toggleTaskPeopleMenu(field) {
    const nextOpen = taskPeopleMenuOpen.value === field ? null : field;
    if (nextOpen) requestFloatingUiClose();
    taskSearchSelectOpen.value = null;
    taskPeopleMenuOpen.value = nextOpen;
}

function closeTaskPeopleMenuOnOutside(event) {
    if (!taskPeopleMenuOpen.value) return;
    if (event.target instanceof Element && event.target.closest(`[data-task-people-field="${taskPeopleMenuOpen.value}"]`)) return;

    taskPeopleMenuOpen.value = null;
}

function openFieldPicker(event, field) {
    if (field.type !== 'date') return;

    taskPeopleMenuOpen.value = null;
    taskSearchSelectOpen.value = null;
    try {
        event.currentTarget?.showPicker?.();
    } catch (error) {
        event.currentTarget?.focus?.();
    }
}

function openDatePicker(event) {
    taskPeopleMenuOpen.value = null;
    taskSearchSelectOpen.value = null;

    try {
        event.currentTarget?.showPicker?.();
    } catch (error) {
        event.currentTarget?.focus?.();
    }
}

function refreshTaskDescriptionEditor() {
    if (props.section !== 'tasks') return;
    nextTick(() => {
        if (!taskDescriptionEditor.value) return;
        if (taskDescriptionEditor.value.innerHTML !== (form.description || '')) {
            taskDescriptionEditor.value.innerHTML = form.description || '';
        }
    });
}

function updateTaskDescriptionFromEditor() {
    form.description = taskDescriptionEditor.value?.innerHTML || '';
}

function runTaskEditorCommand(command, value = null) {
    taskDescriptionEditor.value?.focus();
    document.execCommand(command, false, value);
    updateTaskDescriptionFromEditor();
}

function addTaskEditorLink() {
    const url = window.prompt('URL del link');
    if (!url) return;

    runTaskEditorCommand('createLink', url);
}

function setInlineState(target, id, value) {
    target.value = { ...target.value, [id]: value };
}

function autosaveLabel(state, fallback = '') {
    return {
        queued: 'In attesa',
        saving: 'Salvataggio',
        saved: 'Salvato',
        error: fallback || 'Errore',
    }[state] || '';
}

function refreshCalendarTaskDescriptionEditor() {
    nextTick(() => {
        if (!calendarTaskDescriptionEditor.value) return;
        if (calendarTaskDescriptionEditor.value.innerHTML !== (calendarTaskForm.description || '')) {
            calendarTaskDescriptionEditor.value.innerHTML = calendarTaskForm.description || '';
        }
    });
}

function updateCalendarTaskDescriptionFromEditor() {
    calendarTaskForm.description = calendarTaskDescriptionEditor.value?.innerHTML || '';
    saveCalendarTaskInline();
}

function runCalendarTaskEditorCommand(command, value = null) {
    calendarTaskDescriptionEditor.value?.focus();
    document.execCommand(command, false, value);
    updateCalendarTaskDescriptionFromEditor();
}

function addCalendarTaskEditorLink() {
    const url = window.prompt('URL del link');
    if (!url) return;

    runCalendarTaskEditorCommand('createLink', url);
}

function calendarCommentEditorSelector(commentId = 'new') {
    return `[data-calendar-comment-editor="${commentId}"]`;
}

function calendarCommentEditorElement(commentId = 'new') {
    return document.querySelector(calendarCommentEditorSelector(commentId));
}

function updateCalendarCommentFromEditor(commentId = 'new') {
    const html = calendarCommentEditorElement(commentId)?.innerHTML || '';

    if (commentId === 'new') {
        calendarCommentForm.content = html;
        return;
    }

    if (calendarCommentDrafts.value[commentId]) {
        calendarCommentDrafts.value[commentId].content = html;
    }
}

function refreshCalendarCommentEditor(commentId = 'new') {
    nextTick(() => {
        const editor = calendarCommentEditorElement(commentId);
        if (!editor) return;

        const html = commentId === 'new'
            ? calendarCommentForm.content || ''
            : calendarCommentDrafts.value[commentId]?.content || '';

        if (editor.innerHTML !== html) {
            editor.innerHTML = html;
        }
    });
}

function runCalendarCommentEditorCommand(commentId, command, value = null) {
    const editor = calendarCommentEditorElement(commentId);
    editor?.focus();
    document.execCommand(command, false, value);
    updateCalendarCommentFromEditor(commentId);
}

function addCalendarCommentEditorLink(commentId = 'new') {
    const url = window.prompt('URL del link');
    if (!url) return;

    runCalendarCommentEditorCommand(commentId, 'createLink', url);
}

function setTaskFormType(type) {
    form.task_type = type;
    if (type === 'meeting' && !form.due_time) {
        form.due_time = '09:00';
    }
    if (type === 'meeting') {
        form.recurring_enabled = false;
        form.project_id = '';
    }
    if (type === 'ongoing') {
        form.project_id = '';
    }
    if (type !== 'meeting') {
        form.location = '';
    }
}

function openCreate(patch = {}) {
    editing.value = null;
    form.clearErrors();
    form.defaults({ ...defaults.value });
    form.reset();
    Object.assign(form, { ...defaults.value, ...patch });
    formDependencyToAdd.value = '';
    formDependencyDirection.value = 'blocked_by';
    formOpen.value = true;
    taskPeopleMenuOpen.value = null;
    taskSearchSelectOpen.value = null;
    refreshTaskDescriptionEditor();
}

function resetForm() {
    editing.value = null;
    formOpen.value = false;
    taskPeopleMenuOpen.value = null;
    taskSearchSelectOpen.value = null;
    form.clearErrors();
    form.defaults({ ...defaults.value });
    form.reset();
    Object.assign(form, { ...defaults.value });
    formDependencyToAdd.value = '';
    formDependencyDirection.value = 'blocked_by';
    refreshTaskDescriptionEditor();
}

function hydrateTaskCreateFromUrl() {
    if (props.section !== 'tasks' || typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const create = params.get('create');
    const date = params.get('date');
    const allowedTypes = ['project', 'task', 'ongoing', 'meeting'];

    if (!allowedTypes.includes(create)) return;

    openCreate({
        task_type: create,
        due_date: date || '',
        start_date: '',
        status: 'todo',
        priority: 'medium',
        due_time: create === 'meeting' ? '09:00' : '',
    });

    params.delete('create');
    params.delete('date');
    const nextQuery = params.toString();
    window.history.replaceState({}, '', `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}`);
}

function editRow(row) {
    editing.value = row;
    formOpen.value = true;
    taskPeopleMenuOpen.value = null;
    taskSearchSelectOpen.value = null;
    form.clearErrors();
    props.fields.forEach((field) => {
        form[field.name] = row[field.name] ?? (field.type === 'checkbox' ? false : '');
    });
    if (props.section === 'tasks') {
        form.assignee_ids = [...(row.assignee_ids || [])];
        form.follower_ids = [...(row.follower_ids || [])];
        form.dependency_ids = [];
        form.dependent_ids = [];
        formDependencyToAdd.value = '';
        formDependencyDirection.value = 'blocked_by';
        refreshTaskDescriptionEditor();
    }
}

function submit() {
    const name = routeBase.value;
    if (editing.value) {
        form.put(route(`${name}.update`, editing.value.id), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post(route(`${name}.store`), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
}

function saveDocumentSettings() {
    const payload = Object.keys(docSettingDefaults).reduce((values, key) => {
        values[key] = documentSettingsForm[key] ?? docSettingDefaults[key];
        return values;
    }, {});

    router.put(route('settings.document.update'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            Object.entries(payload).forEach(([key, value]) => {
                documentSettingsForm[key] = value;
            });
            documentSettingsForm.defaults(payload);
            documentSettingsForm.clearErrors();
        },
        onError: (errors) => {
            documentSettingsForm.setError(errors);
        },
    });
}

function saveEmailSettings() {
    const hadNewSmtpPassword = Boolean(emailSettingsForm.smtp_password);
    const hadNewPecPassword = Boolean(emailSettingsForm.pec_password);

    emailSettingsForm
        .transform((data) => ({
            smtp_enabled: toBoolean(data.smtp_enabled),
            smtp_host: data.smtp_host || '',
            smtp_port: data.smtp_port || '',
            smtp_username: data.smtp_username || '',
            smtp_password: data.smtp_password || '',
            smtp_secure: toBoolean(data.smtp_secure),
            smtp_from_email: data.smtp_from_email || '',
            smtp_from_name: data.smtp_from_name || '',
            smtp_reply_to: data.smtp_reply_to || '',
            pec_username: data.pec_username || '',
            pec_password: data.pec_password || '',
        }))
        .put(route('settings.email.update'), {
            preserveScroll: true,
            onSuccess: () => {
                emailSettingsForm.smtp_password = '';
                emailSettingsForm.pec_password = '';
                if (hadNewSmtpPassword) emailSettingsForm.smtp_password_saved = true;
                if (hadNewPecPassword) emailSettingsForm.pec_password_saved = true;
            },
        });
}

function sendTestEmail() {
    testEmailForm.post(route('settings.email.test'), {
        preserveScroll: true,
    });
}

function saveNumbering(row) {
    router.put(route('settings.numbering.update', row.id), {
        prefix: row.prefix || '',
        format: row.format || '{prefix}{year}/{seq}',
        current_seq: row.current_seq || 0,
        yearly_reset: Boolean(row.yearly_reset),
    }, { preserveScroll: true });
}

function runBackup() {
    router.post(route('settings.backup.run'), {}, { preserveScroll: true });
}

function openRestoreBackup(run) {
    if (!run?.restorable) return;

    restoreTarget.value = run;
    restoreConfirmText.value = '';
}

function cancelRestoreBackup() {
    restoreTarget.value = null;
    restoreConfirmText.value = '';
}

function confirmRestoreBackup() {
    if (!restoreTarget.value || restoreConfirmText.value !== 'RIPRISTINA') return;

    router.post(route('settings.backup.restore', restoreTarget.value.id), {}, {
        preserveScroll: true,
        onFinish: cancelRestoreBackup,
    });
}

function removeBackup(run) {
    remove(run, () => {
        router.delete(route('settings.backup.destroy', run.id), {
            preserveScroll: true,
            onFinish: cancelDelete,
        });
    });
}

function backupFrequencyLabel(frequency) {
    return {
        manual: 'Manuale',
        weekly: 'Settimanale',
        monthly: 'Mensile',
    }[frequency] || displayValue(frequency);
}

function backupStatusClass(status) {
    return {
        completed: 'bg-emerald-100 text-emerald-700',
        running: 'bg-sky-100 text-sky-700',
        failed: 'bg-red-100 text-red-700',
    }[status] || 'bg-gray-100 text-gray-700';
}

function backupStatusLabel(status) {
    return {
        completed: 'Completato',
        running: 'In corso',
        failed: 'Errore',
    }[status] || displayValue(status);
}

function fileSize(value) {
    const bytes = Number(value || 0);
    if (!bytes) return '-';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const amount = bytes / (1024 ** index);

    return `${new Intl.NumberFormat('it-IT', { maximumFractionDigits: index ? 1 : 0 }).format(amount)} ${units[index]}`;
}

function remove(row, action = null) {
    if (!canDeleteRow(row)) return;
    if (isSuperadmin.value) {
        executeDelete(row, action);
        return;
    }

    deleteTarget.value = row;
    deleteTargetAction.value = action;
    deleteConfirmText.value = '';
}

function canDeleteRow(row) {
    if (isGuest.value) return false;
    if (!isEditor.value) return true;
    if (props.section === 'tasks' || props.section === 'calendar') {
        return row?.created_by === page.props.auth?.user?.id;
    }

    return props.section.startsWith('updates-');
}

function deleteTargetName() {
    return deleteTarget.value?.name || deleteTarget.value?.title || deleteTarget.value?.number || deleteTarget.value?.email || deleteTarget.value?.client_name || deleteTarget.value?.user_name || deleteTarget.value?.storage_path || 'elemento';
}

function cancelDelete() {
    deleteTarget.value = null;
    deleteTargetAction.value = null;
    deleteConfirmText.value = '';
}

function confirmDelete() {
    if (!deleteTarget.value || deleteConfirmText.value !== 'ELIMINA') return;
    executeDelete(deleteTarget.value, deleteTargetAction.value);
}

function executeDelete(row, action = null) {
    if (action) {
        action();
        return;
    }

    router.delete(route(`${routeBase.value}.destroy`, row.id), {
        preserveScroll: true,
        onFinish: cancelDelete,
    });
}

function showRoute(row) {
    if (!['clients', 'projects', 'tasks', 'billing', 'users'].includes(props.section)) return null;
    return route(`${props.section}.show`, row.id);
}

function priorityClass(priority) {
    return {
        urgent: 'bg-red-100 text-red-700',
        high: 'bg-orange-100 text-orange-700',
        medium: 'bg-amber-100 text-amber-700',
        low: 'bg-emerald-100 text-emerald-700',
    }[priority] || 'bg-gray-100 text-gray-700';
}

function priorityColor(priority) {
    return {
        urgent: '#dc2626',
        high: '#f97316',
        medium: '#f59e0b',
        low: '#10b981',
    }[priority] || '#64748b';
}

function priorityTextColor(priority) {
    const hex = priorityColor(priority).replace('#', '');
    if (!/^[0-9a-f]{6}$/i.test(hex)) return '#ffffff';

    const [red, green, blue] = [0, 2, 4].map((start) => parseInt(hex.slice(start, start + 2), 16) / 255);
    const luminance = [red, green, blue]
        .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4))
        .reduce((total, channel, index) => total + channel * [0.2126, 0.7152, 0.0722][index], 0);

    return luminance > 0.42 ? '#111827' : '#ffffff';
}

const subtaskIconPath = 'M20,15c-1.9,0-3.4,1.3-3.9,3H7c-2.8,0-5-2.2-5-5v-3h14.1c0.4,1.7,2,3,3.9,3c2.2,0,4-1.8,4-4s-1.8-4-4-4 c-1.9,0-3.4,1.3-3.9,3H2V3c0-0.6-0.4-1-1-1S0,2.4,0,3v10c0,3.9,3.1,7,7,7h9.1c0.4,1.7,2,3,3.9,3c2.2,0,4-1.8,4-4S22.2,15,20,15z M20,7c1.1,0,2,0.9,2,2s-0.9,2-2,2s-2-0.9-2-2S18.9,7,20,7z M20,21c-1.1,0-2-0.9-2-2s0.9-2,2-2s2,0.9,2,2S21.1,21,20,21z';

const documentTypeLabels = {
    preventivo: 'Preventivo',
    proforma: 'Proforma',
    fattura: 'Fattura',
    nota_credito: 'Nota credito',
};

const documentStatusLabels = {
    draft: 'Bozza',
    sent: 'Inviato',
    accepted: 'Accettato',
    rejected: 'Rifiutato',
    paid: 'Pagato',
    partially_paid: 'Parziale',
    overdue: 'Scaduto',
    cancelled: 'Annullato',
};

function activityText(activity) {
    return formatActivityText(activity, columnLabels);
}

function parseDateOnly(value) {
    if (!value) return null;
    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
    if (!year || !month || !day) return null;

    return new Date(year, month - 1, day);
}

function startOfWeek(value) {
    const date = new Date(value);
    date.setHours(0, 0, 0, 0);
    const day = date.getDay() || 7;
    date.setDate(date.getDate() - day + 1);

    return date;
}

function addTaskDays(value, days) {
    const date = new Date(value);
    date.setDate(date.getDate() + days);

    return date;
}

function changeTaskWeek(offset) {
    taskWeekStart.value = addTaskDays(taskWeekStart.value, offset * 7);
}

function resetTaskWeek() {
    taskWeekStart.value = startOfWeek(new Date());
}

const taskWeekEnd = computed(() => addTaskDays(taskWeekStart.value, 6));
const taskWeekLabel = computed(() => `${dateIt(taskWeekStart.value)} - ${dateIt(taskWeekEnd.value)}`);

function statusClass(status) {
    return {
        draft: 'bg-gray-100 text-gray-700',
        sent: 'bg-sky-100 text-sky-700',
        accepted: 'bg-indigo-100 text-indigo-700',
        rejected: 'bg-red-100 text-red-700',
        paid: 'bg-emerald-100 text-emerald-700',
        partially_paid: 'bg-amber-100 text-amber-700',
        overdue: 'bg-rose-100 text-rose-700',
        cancelled: 'bg-slate-100 text-slate-600',
    }[status] || 'bg-gray-100 text-gray-700';
}

const billingRows = computed(() => props.rows.filter((row) => {
    const search = billingSearch.value.trim().toLowerCase();
    const matchesSearch = !search
        || (row.number || '').toLowerCase().includes(search)
        || (row.client_name || '').toLowerCase().includes(search)
        || (row.notes || '').toLowerCase().includes(search);
    const matchesType = billingType.value === 'all' || row.doc_type === billingType.value;
    const matchesStatus = billingStatus.value === 'all' || row.status === billingStatus.value;

    return matchesSearch && matchesType && matchesStatus;
}));

const maxMonthly = computed(() => Math.max(1, ...((props.billingStats?.monthly || []).map((row) => Math.max(row.invoiced, row.paid)))));
const maxTopClient = computed(() => Math.max(1, ...((props.billingStats?.topClients || []).map((row) => row.total))));
const taskColumns = [
    ['todo', 'Da fare'],
    ['in_progress', 'In corso'],
    ['in_review', 'Review'],
    ['done', 'Fatte'],
];

const projectStatusLabels = {
    active: 'Attivo',
    completed: 'Completato',
    on_hold: 'In Pausa',
    archived: 'Archiviato',
};

function projectStatusClass(status) {
    return {
        active: 'bg-green-100 text-green-700',
        completed: 'bg-blue-100 text-blue-700',
        on_hold: 'bg-amber-100 text-amber-700',
        archived: 'bg-gray-100 text-gray-700',
    }[status] || 'bg-gray-100 text-gray-700';
}

const projectRows = computed(() => props.rows.filter((row) => {
    const search = projectSearch.value.trim().toLowerCase();
    const matchesSearch = !search
        || (row.name || '').toLowerCase().includes(search)
        || plainText(row.description).toLowerCase().includes(search)
        || (row.client_name || '').toLowerCase().includes(search);
    const matchesStatus = projectStatus.value === 'all' || row.status === projectStatus.value;
    const matchesUsers = !projectUserIds.value.length
        || projectUserIds.value.every((userId) => (row.follower_ids || []).includes(userId));

    return matchesSearch && matchesStatus && matchesUsers;
}));

const roleLabels = {
    superadmin: 'Superadmin',
    admin: 'Admin',
    editor: 'Editor',
    guest: 'Ospite',
};

const roleOrder = ['superadmin', 'admin', 'editor', 'guest'];
const absenceTypeLabels = {
    vacation: 'Ferie',
    permission: 'Permesso',
    sickness: 'Malattia',
    late: 'Ritardo',
    other: 'Altra assenza',
};
const absenceStatusLabels = {
    pending: 'In attesa',
    approved: 'Approvata',
    rejected: 'Rifiutata',
};
const absenceStatusOptions = [
    { value: 'all', label: 'Tutte' },
    { value: 'pending', label: 'In attesa' },
    { value: 'approved', label: 'Approvate' },
    { value: 'rejected', label: 'Rifiutate' },
];
const absenceHourOptions = Array.from({ length: 14 }, (_, index) => {
    const hour = String(index + 7).padStart(2, '0');
    return { value: `${hour}:00`, label: `${hour}:00` };
});

function roleClass(role) {
    return {
        superadmin: 'bg-red-100 text-red-700',
        admin: 'bg-blue-100 text-blue-700',
        editor: 'bg-green-100 text-green-700',
        guest: 'bg-gray-100 text-gray-600',
    }[role] || 'bg-gray-100 text-gray-600';
}

function personAvatarClass(selected) {
    return [
        'group/person relative inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300',
        selected
            ? 'bg-indigo-50 ring-2 ring-indigo-500 ring-offset-2 ring-offset-white'
            : 'bg-white/70 ring-1 ring-gray-200 hover:-translate-y-0.5 hover:ring-indigo-200 hover:shadow-[0_10px_24px_rgba(79,70,229,0.10)]',
    ];
}

function toggleProjectUserFilter(userId) {
    const current = [...projectUserIds.value];
    const index = current.indexOf(userId);
    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push(userId);
    }
    projectUserIds.value = current;
}

function toggleProjectPeopleMenu() {
    const nextOpen = !projectPeopleMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    projectPeopleMenuOpen.value = nextOpen;
}

function resetProjectFilters() {
    projectSearch.value = '';
    projectStatus.value = 'all';
    projectUserIds.value = [];
    projectPeopleMenuOpen.value = false;
}

function projectUsers(project) {
    const selected = project.follower_ids || [];
    return (props.users || []).filter((user) => selected.includes(user.id));
}

const selectedCalendarFilterUsers = computed(() => (props.users || []).filter((user) => calendarUserIds.value.includes(user.id)));

const calendarPeopleFilterLabel = computed(() => {
    if (!calendarUserIds.value.length) return 'Tutte le persone';
    if (calendarUserIds.value.length === 1) {
        const user = selectedCalendarFilterUsers.value[0];
        return user?.name || user?.email || '1 persona';
    }

    return `${calendarUserIds.value.length} persone`;
});

function toggleCalendarUserFilter(userId) {
    const current = [...calendarUserIds.value];
    const index = current.indexOf(userId);
    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push(userId);
    }
    calendarUserIds.value = current;
}

function toggleCalendarPeopleMenu(event = null) {
    const nextOpen = !calendarPeopleMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    calendarPeopleMenuStyle.value = dropdownMenuStyleFromEvent(event, 320);
    calendarPeopleMenuOpen.value = nextOpen;
}

function closeCalendarPeopleMenuOnOutside(event) {
    if (!calendarPeopleMenuOpen.value) return;
    if (calendarPeopleMenu.value?.contains(event.target)) return;
    if (event.target instanceof Element && event.target.closest('[data-calendar-people-menu]')) return;

    calendarPeopleMenuOpen.value = false;
}

const selectedProjectFilterUsers = computed(() => (props.users || []).filter((user) => projectUserIds.value.includes(user.id)));

const projectPeopleFilterLabel = computed(() => {
    if (!projectUserIds.value.length) return 'Tutte le persone';
    if (projectUserIds.value.length === 1) {
        const user = selectedProjectFilterUsers.value[0];
        return user?.name || user?.email || '1 persona';
    }

    return `${projectUserIds.value.length} persone`;
});

function closeProjectPeopleMenuOnOutside(event) {
    if (!projectPeopleMenuOpen.value) return;
    if (projectPeopleMenu.value?.contains(event.target)) return;

    projectPeopleMenuOpen.value = false;
}

const userRows = computed(() => props.rows.filter((row) => userRoleFilter.value === 'all' || (row.role || 'guest') === userRoleFilter.value));
const userRoleFilters = computed(() => [
    { value: 'all', label: 'Tutti', count: props.rows.length },
    ...roleOrder.map((role) => ({
        value: role,
        label: roleLabels[role],
        count: props.rows.filter((user) => (user.role || 'guest') === role).length,
    })),
]);
const usersByRole = computed(() => roleOrder
    .map((role) => ({ role, rows: userRows.value.filter((row) => (row.role || 'guest') === role) }))
    .filter((group) => group.rows.length));
const absenceRows = computed(() => props.rows.filter((row) => absenceStatus.value === 'all' || row.status === absenceStatus.value));
const absenceStats = computed(() => ({
    pending: props.rows.filter((row) => row.status === 'pending').length,
    approved: props.rows.filter((row) => row.status === 'approved').length,
    rejected: props.rows.filter((row) => row.status === 'rejected').length,
    total: props.rows.length,
}));
const absenceTodayIso = computed(() => {
    const today = new Date();
    return formatCalendarDate(today.getFullYear(), today.getMonth(), today.getDate());
});
const absenceTodayRows = computed(() => props.rows
    .filter((row) => row.status !== 'rejected' && isAbsenceActiveOn(row, absenceTodayIso.value))
    .sort((a, b) => {
        const timeCompare = String(a.start_time || '').localeCompare(String(b.start_time || ''));
        if (timeCompare) return timeCompare;

        return String(a.user_name || '').localeCompare(String(b.user_name || ''));
    }));
const smartworkingDayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
const smartworkingTodayKey = computed(() => smartworkingDayKeys[new Date().getDay()]);
const smartworkingTodayRows = computed(() => (props.users || [])
    .filter((user) => user.smartworking_day === smartworkingTodayKey.value)
    .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''))));

function absenceUser(row) {
    return {
        id: row.user_id,
        name: row.user_name,
        email: row.user_email,
        avatar_url: row.user_avatar_url,
    };
}

function isAbsenceActiveOn(row, day) {
    const start = String(row.start_date || '').slice(0, 10);
    const end = String(row.end_date || row.start_date || '').slice(0, 10);

    return start && day >= start && day <= end;
}

function absenceExtraInfo(row) {
    if (['permission', 'late'].includes(row.type) && (row.start_time || row.end_time)) {
        return `${String(row.start_time || '--:--').slice(0, 5)} - ${String(row.end_time || '--:--').slice(0, 5)}`;
    }

    if (row.type === 'sickness' && row.inps_code) {
        return `INPS ${row.inps_code}`;
    }

    return '';
}

function smartworkingUserLabel(user) {
    return user.job_title || user.email || '';
}

function absenceStatusClass(status) {
    return {
        pending: 'bg-amber-50 text-amber-700',
        approved: 'bg-emerald-50 text-emerald-700',
        rejected: 'bg-red-50 text-red-700',
    }[status] || 'bg-gray-100 text-gray-600';
}

function absenceNeedsEndDate(type) {
    return ['vacation', 'sickness', 'other'].includes(type);
}

function absenceNeedsTime(type) {
    return ['permission', 'late', 'other'].includes(type);
}

function ensureAbsenceDraft(row) {
    if (absenceDrafts.value[row.id]) return absenceDrafts.value[row.id];

    absenceDrafts.value = {
        ...absenceDrafts.value,
        [row.id]: {
            type: row.type || 'vacation',
            start_date: row.start_date || '',
            end_date: row.end_date || row.start_date || '',
            start_time: row.start_time ? String(row.start_time).slice(0, 5) : '',
            end_time: row.end_time ? String(row.end_time).slice(0, 5) : '',
            inps_code: row.inps_code || '',
            status: row.status || 'pending',
            notes: row.notes || '',
        },
    };

    return absenceDrafts.value[row.id];
}

function absencePayload(row) {
    const draft = ensureAbsenceDraft(row);
    const type = draft.type || 'vacation';

    return {
        type,
        start_date: draft.start_date || row.start_date,
        end_date: absenceNeedsEndDate(type) ? (draft.end_date || draft.start_date || row.start_date) : (draft.start_date || row.start_date),
        start_time: absenceNeedsTime(type) ? (draft.start_time || null) : null,
        end_time: absenceNeedsTime(type) ? (draft.end_time || null) : null,
        inps_code: type === 'sickness' ? (draft.inps_code || null) : null,
        status: draft.status || 'pending',
        notes: draft.notes || null,
    };
}

function saveAbsenceInline(row, delay = AUTOSAVE_IDLE_DELAY) {
    window.clearTimeout(absenceAutosaveTimers[row.id]);
    absenceAutosaveTimers[row.id] = window.setTimeout(() => {
        router.put(route('absences.update', row.id), absencePayload(row), {
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'errors', 'flash'],
        });
    }, autosaveDelay(delay));
}

function updateAbsenceStatus(row, status) {
    ensureAbsenceDraft(row).status = status;
    router.patch(route('absences.status.update', row.id), { status }, {
        preserveScroll: true,
        preserveState: true,
    });
}

const isUpdatesSection = computed(() => props.section?.startsWith('updates-'));
const showUpdateReport = computed(() => props.serviceName === 'ADV');
const showUpdateNewsletter = computed(() => props.serviceName === 'NEWSLETTER');
const updateRows = computed(() => props.rows || []);
const cadenceLabels = {
    on_request: 'Su richiesta',
    weekly: 'Settimanale',
    biweekly: 'Bisettimanale',
    monthly: 'Mensile',
};

function openServiceUpdate(row) {
    if (row.id) {
        editRow(row);
        return;
    }

    openCreate({
        client_id: row.client_id,
        responsible_user_id: row.responsible_user_id || '',
        cadence: row.cadence || '',
        contact: row.contact || '',
        report_url: row.report_url || '',
        notes: row.notes || '',
    });
}

function updateRowKey(row) {
    return row.id || row.client_id;
}

function syncUpdateDrafts(rows = updateRows.value) {
    const next = {};
    rows.forEach((row) => {
        next[updateRowKey(row)] = {
            notes: row.notes || '',
            report_url: row.report_url || '',
            cadence: row.cadence || '',
            contact: row.contact || '',
            responsible_user_id: row.responsible_user_id || '',
        };
    });
    updateDrafts.value = next;
}

watch(updateRows, (rows) => syncUpdateDrafts(rows), { immediate: true });

function draftValue(row, field) {
    return updateDrafts.value[updateRowKey(row)]?.[field] ?? '';
}

function setDraftValue(row, field, value) {
    const key = updateRowKey(row);
    updateDrafts.value = {
        ...updateDrafts.value,
        [key]: {
            ...(updateDrafts.value[key] || {}),
            [field]: value,
        },
    };
}

function saveServiceUpdateInline(row, patch = {}) {
    const key = updateRowKey(row);
    const draft = updateDrafts.value[key] || {};
    const payload = {
        client_id: row.client_id,
        responsible_user_id: draft.responsible_user_id || null,
        cadence: draft.cadence || null,
        contact: draft.contact || null,
        report_url: draft.report_url || null,
        notes: draft.notes || null,
        ...patch,
    };

    savingUpdateKeys.value = [...new Set([...savingUpdateKeys.value, key])];

    const options = {
        preserveScroll: true,
        only: ['rows', 'errors', 'flash'],
        onFinish: () => {
            savingUpdateKeys.value = savingUpdateKeys.value.filter((item) => item !== key);
        },
    };

    if (row.id) {
        router.put(route(`${routeBase.value}.update`, row.id), payload, options);
        return;
    }

    router.post(route(`${routeBase.value}.store`), payload, options);
}

function saveDraftField(row, field) {
    const value = draftValue(row, field);
    const current = row[field] || '';
    if ((value || '') === (current || '')) return;
    saveServiceUpdateInline(row, { [field]: value || null });
}

const taskRows = computed(() => props.rows.filter((row) => {
    if (row.parent_task_id !== null && row.parent_task_id !== undefined && row.parent_task_id !== '') return false;

    const dueDate = parseDateOnly(row.due_date);
    const matchesWeek = dueDate && dueDate >= taskWeekStart.value && dueDate <= taskWeekEnd.value;
    const search = taskSearch.value.trim().toLowerCase();
    const matchesSearch = !search
        || (row.title || '').toLowerCase().includes(search)
        || (row.client_name || '').toLowerCase().includes(search)
        || (row.project_name || '').toLowerCase().includes(search)
        || (row.service_name || '').toLowerCase().includes(search);
    const matchesStatus = taskStatus.value === 'all' || row.status === taskStatus.value;
    const matchesPriority = taskPriority.value === 'all' || row.priority === taskPriority.value;
    const matchesType = taskType.value === 'all' || (row.task_type || 'task') === taskType.value;

    return matchesWeek && matchesSearch && matchesStatus && matchesPriority && matchesType;
}));

function tasksByStatus(status) {
    return taskRows.value.filter((row) => row.status === status);
}

const clientRows = computed(() => props.rows.filter((row) => {
    const search = clientSearch.value.trim().toLowerCase();
    const matchesSearch = !search
        || (row.name || '').toLowerCase().includes(search)
        || (row.legal_name || '').toLowerCase().includes(search)
        || (row.email || '').toLowerCase().includes(search)
        || (row.vat_number || '').toLowerCase().includes(search)
        || (row.city || '').toLowerCase().includes(search);
    const matchesService = clientService.value === 'all'
        || (row.services || []).some((service) => service.id === clientService.value);

    return matchesSearch && matchesService;
}));

const clientServicesDrag = {
    element: null,
    pointerId: null,
    startX: 0,
    scrollLeft: 0,
    dragged: false,
};

function canScrollClientServices(element) {
    return element && element.scrollWidth > element.clientWidth;
}

function startClientServicesDrag(event) {
    const element = event.currentTarget;
    if (!canScrollClientServices(element)) return;

    event.preventDefault();
    clientServicesDrag.element = element;
    clientServicesDrag.pointerId = event.pointerId;
    clientServicesDrag.startX = event.clientX;
    clientServicesDrag.scrollLeft = element.scrollLeft;
    clientServicesDrag.dragged = false;
    element.classList.add('is-dragging');
    document.addEventListener('pointermove', dragClientServices, { passive: false });
    document.addEventListener('pointerup', stopClientServicesDrag);
    document.addEventListener('pointercancel', stopClientServicesDrag);
}

function dragClientServices(event) {
    const element = clientServicesDrag.element;
    if (!element || clientServicesDrag.pointerId !== event.pointerId) return;

    const delta = event.clientX - clientServicesDrag.startX;
    if (Math.abs(delta) > 3) {
        clientServicesDrag.dragged = true;
    }

    event.preventDefault();
    element.scrollLeft = clientServicesDrag.scrollLeft - delta;
}

function stopClientServicesDrag(event) {
    const element = clientServicesDrag.element;
    if (!element || (event.pointerId && clientServicesDrag.pointerId !== event.pointerId)) return;

    element.classList.remove('is-dragging');
    document.removeEventListener('pointermove', dragClientServices);
    document.removeEventListener('pointerup', stopClientServicesDrag);
    document.removeEventListener('pointercancel', stopClientServicesDrag);
    clientServicesDrag.element = null;
    clientServicesDrag.pointerId = null;
}

function cancelClientServicesDrag() {
    const element = clientServicesDrag.element;
    element?.classList.remove('is-dragging');
    document.removeEventListener('pointermove', dragClientServices);
    document.removeEventListener('pointerup', stopClientServicesDrag);
    document.removeEventListener('pointercancel', stopClientServicesDrag);
    clientServicesDrag.element = null;
    clientServicesDrag.pointerId = null;
    clientServicesDrag.dragged = false;
}

function scrollClientServicesWheel(event) {
    const element = event.currentTarget;
    if (!canScrollClientServices(element)) return;

    event.preventDefault();
    element.scrollLeft += event.deltaX || event.deltaY;
}

function blockClientServicesClick(event) {
    event.preventDefault();
    event.stopPropagation();
    clientServicesDrag.dragged = false;
}

const monthNames = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
const dayNames = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
const calendarYear = computed(() => currentCalendarDate.value.getFullYear());
const calendarMonth = computed(() => currentCalendarDate.value.getMonth());
const calendarMonthSections = computed(() => [-1, 0, 1].map((delta) => {
    const date = new Date(calendarYear.value, calendarMonth.value + delta, 1);
    const year = date.getFullYear();
    const month = date.getMonth();

    return {
        key: `${year}-${month}`,
        delta,
        year,
        month,
        label: `${monthNames[month]} ${year}`,
        cells: buildCalendarGrid(year, month),
    };
}));

function buildCalendarGrid(year, month) {
    const days = new Date(year, month + 1, 0).getDate();
    const offset = (new Date(year, month, 1).getDay() + 6) % 7;
    const cells = [];
    for (let index = 0; index < offset; index += 1) {
        cells.push({ key: `empty-${index}`, empty: true });
    }
    for (let day = 1; day <= days; day += 1) {
        const date = formatCalendarDate(year, month, day);
        const weekday = (new Date(year, month, day).getDay() + 6) % 7;
        cells.push({
            key: date,
            day,
            date,
            weekday,
            weekend: weekday >= 5,
            today: isCalendarToday(year, month, day),
            tasks: tasksForDay(date),
        });
    }
    cells.forEach((cell, index) => {
        cell.weekIndex = Math.floor(index / 7);
    });
    return cells;
}

function formatCalendarDate(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function isCalendarToday(year, month, day) {
    const today = new Date();
    return today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
}

function changeMonth(delta) {
    calendarCreateDate.value = null;
    expandedCalendarDays.value = [];
    currentCalendarDate.value = new Date(calendarYear.value, calendarMonth.value + delta, 1);
    centerCalendarScroll();
}

function centerCalendarScroll() {
    nextTick(() => {
        const element = calendarScrollArea.value;
        if (!element) return;

        calendarScrollResetting = true;
        const currentMonth = element.querySelector('[data-current-calendar-month="true"]');
        element.scrollTop = currentMonth?.offsetTop || (element.scrollHeight / 3);
        window.clearTimeout(calendarScrollTimer);
        calendarScrollTimer = window.setTimeout(() => {
            calendarScrollResetting = false;
        }, 140);
    });
}

function handleCalendarScroll(event) {
    if (calendarScrollResetting) return;
    const element = event.currentTarget;
    const threshold = 140;

    if (element.scrollTop < threshold) {
        changeMonth(-1);
        return;
    }

    if (element.scrollTop + element.clientHeight > element.scrollHeight - threshold) {
        changeMonth(1);
    }
}

function taskTypeLabel(type) {
    return {
        task: 'Task',
        project: 'Task',
        ongoing: 'Continuativa',
        meeting: 'Meeting',
    }[type || 'task'] || type;
}

function calendarTaskTypeButtonClass(type) {
    const active = calendarTaskForm.task_type === type || (type === 'project' && calendarTaskForm.task_type === 'task');
    const styles = {
        project: active
            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
            : 'border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-50',
        ongoing: active
            ? 'border-amber-500 bg-amber-50 text-amber-800 shadow-sm'
            : 'border-amber-200 bg-white text-amber-700 hover:bg-amber-50',
        meeting: active
            ? 'border-violet-500 bg-violet-50 text-violet-800 shadow-sm'
            : 'border-violet-200 bg-white text-violet-700 hover:bg-violet-50',
    };

    return styles[type] || styles.project;
}

function openCalendarTask(task, options = {}) {
    const normalizedTask = normalizeCalendarTask(task);
    if (!options.preserveStack) {
        calendarTaskParentStack.value = [];
    }
    calendarShowAllComments.value = false;
    calendarShowAllActivity.value = false;
    calendarTaskActionMenuOpen.value = false;
    calendarTaskPanel.value = normalizedTask;
    calendarTaskPanelMode.value = 'edit';
    calendarTaskPanelClosedByUser.value = false;
    calendarTaskPanelOpen.value = true;
    calendarTaskAutosaveState.value = 'idle';
    calendarTaskAutosaveError.value = '';
    window.clearTimeout(calendarTaskAutosaveTimer);
    calendarTaskForm.defaults({
        id: normalizedTask.id,
        title: normalizedTask.title || '',
        description: normalizedTask.description || '',
        project_id: normalizedTask.project_id || '',
        client_id: normalizedTask.client_id || '',
        service_id: normalizedTask.service_id || '',
        task_type: normalizedTask.task_type || 'project',
        status: normalizedTask.status || 'todo',
        priority: normalizedTask.priority || 'medium',
        start_date: normalizedTask.start_date || '',
        due_date: normalizedTask.due_date || '',
        due_time: normalizedTask.due_time ? String(normalizedTask.due_time).slice(0, 5) : '',
        location: normalizedTask.location || '',
        recurring_enabled: Boolean(normalizedTask.recurring_enabled),
        recurring_interval_value: normalizedTask.recurring_interval_value || 1,
        recurring_interval_unit: normalizedTask.recurring_interval_unit || 'week',
        recurring_mode: normalizedTask.recurring_mode || 'fixed',
        recurring_weekday: normalizedTask.recurring_weekday || 1,
        recurring_month_day: normalizedTask.recurring_month_day || 1,
        assignee_ids: [...(normalizedTask.assignee_ids || [])],
        follower_ids: [...(normalizedTask.follower_ids || [])],
        dependency_ids: (normalizedTask.dependencies || []).map((dependency) => dependency.id),
        dependent_ids: (normalizedTask.dependents || []).map((dependent) => dependent.id),
    });
    calendarTaskForm.reset();
    calendarTaskForm.clearErrors();
    hydrateCalendarTaskRelated(normalizedTask);
    refreshCalendarTaskDescriptionEditor();
}

function calendarRowsWithOverrides(rows = props.rows) {
    return (rows || []).map((row) => {
        const override = calendarRowOverrides.value[row.id];

        return override ? normalizeCalendarTask({ ...row, ...override }) : row;
    });
}

function rememberCalendarRowOverride(task) {
    if (!task?.id || task.parent_task_id) return;

    calendarRowOverrides.value = {
        ...calendarRowOverrides.value,
        [task.id]: normalizeCalendarTask(task),
    };
}

function findCalendarTaskInRows(taskId, rows = calendarRowsWithOverrides()) {
    if (!taskId) return null;

    for (const row of rows || []) {
        if (row.id === taskId) return normalizeCalendarTask(row);

        const subtask = (row.subtasks || []).find((item) => item.id === taskId);
        if (subtask) {
            return normalizeCalendarTask({
                ...subtask,
                parent_task_id: subtask.parent_task_id || row.id,
                parent_title: row.title,
            });
        }
    }

    return null;
}

function refreshCalendarTaskPanelFromRows(rows = calendarRowsWithOverrides()) {
    if (!calendarTaskPanelOpen.value || !calendarTaskForm.id) return;

    const freshTask = findCalendarTaskInRows(calendarTaskForm.id, rows);
    if (!freshTask) return;

    calendarTaskPanel.value = normalizeCalendarTask(freshTask);
    hydrateCalendarTaskRelated(calendarTaskPanel.value);
}

async function refreshCalendarTaskPanelFromServer(taskId = calendarTaskPanel.value?.id || calendarTaskForm.id) {
    if (!taskId) return;

    const response = await fetch(route('tasks.snapshot', taskId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) return;

    const freshTask = normalizeCalendarTask(await response.json());
    rememberCalendarRowOverride(freshTask);
    openCalendarTask(freshTask, { preserveStack: true });
    return freshTask;
}

function openCalendarTaskCreate(type, date) {
    const taskType = type === 'task' ? 'project' : type;
    calendarCreateDate.value = null;
    calendarTaskParentStack.value = [];
    calendarShowAllComments.value = false;
    calendarShowAllActivity.value = false;
    calendarTaskActionMenuOpen.value = false;
    calendarTaskPanelMode.value = 'create';
    calendarTaskPanelClosedByUser.value = false;
    calendarTaskPanel.value = {
        id: '',
        title: '',
        description: '',
        project_id: '',
        client_id: '',
        service_id: '',
        task_type: taskType,
        status: 'todo',
        priority: 'medium',
        start_date: '',
        due_date: date || '',
        due_time: taskType === 'meeting' ? '09:00' : '',
        location: '',
        recurring_enabled: false,
        recurring_interval_value: 1,
        recurring_interval_unit: 'week',
        recurring_mode: 'fixed',
        recurring_weekday: 1,
        recurring_month_day: 1,
        assignee_ids: [],
        follower_ids: [],
        dependency_ids: [],
        dependent_ids: [],
        dependencies: [],
        dependents: [],
        blocked_dependencies_count: 0,
        subtasks: [],
        comments: [],
        activity: [],
    };
    calendarTaskPanelOpen.value = true;
    calendarTaskAutosaveState.value = 'idle';
    calendarTaskAutosaveError.value = '';
    window.clearTimeout(calendarTaskAutosaveTimer);
    calendarTaskForm.defaults({ ...calendarTaskPanel.value });
    calendarTaskForm.reset();
    calendarTaskForm.clearErrors();
    hydrateCalendarTaskRelated(calendarTaskPanel.value);
    calendarCommentForm.reset();
    calendarSubtaskForm.reset();
    refreshCalendarTaskDescriptionEditor();
    refreshCalendarCommentEditor('new');
}

function closeCalendarTaskPanel() {
    calendarTaskPanelClosedByUser.value = true;
    calendarTaskPanelOpen.value = false;
    calendarTaskPanel.value = null;
    calendarTaskParentStack.value = [];
    calendarTaskPanelMode.value = 'edit';
    calendarEditingCommentId.value = null;
    calendarTaskFeedTab.value = 'comments';
    calendarShowAllComments.value = false;
    calendarShowAllActivity.value = false;
    calendarTaskActionMenuOpen.value = false;
    calendarDependencyToAdd.value = '';
    calendarTaskAutosaveState.value = 'idle';
    calendarTaskAutosaveError.value = '';
    window.clearTimeout(calendarTaskAutosaveTimer);
}

function openCalendarSubtask(subtask) {
    const parent = calendarTaskPanel.value;
    if (!parent) return;

    calendarTaskParentStack.value = [...calendarTaskParentStack.value, parent];
    openCalendarTask({
        ...subtask,
        task_type: subtask.task_type || 'task',
        subtasks: filteredCalendarSubtasks(subtask),
        comments: subtask.comments || [],
        activity: subtask.activity || [],
        assignee_ids: subtask.assignee_ids || [],
        follower_ids: subtask.follower_ids || [],
        dependency_ids: (subtask.dependencies || []).map((dependency) => dependency.id),
        dependent_ids: (subtask.dependents || []).map((dependent) => dependent.id),
        parent_task_id: parent.id,
        parent_title: parent.title,
    }, { preserveStack: true });
    scrollCalendarTaskDrawerTop();
}

function returnToCalendarParentTask() {
    const stack = [...calendarTaskParentStack.value];
    const parent = stack.pop();
    if (!parent) return;

    calendarTaskParentStack.value = stack;
    openCalendarTask(parent, { preserveStack: true });
    scrollCalendarTaskDrawerTop();
}

function scrollCalendarTaskDrawerTop() {
    nextTick(() => {
        calendarTaskDrawerBody.value?.scrollTo?.({ top: 0, behavior: 'smooth' });
    });
}

function hydrateCalendarTaskRelated(task) {
    const subtasks = filteredCalendarSubtasks(task);
    const comments = task?.comments || [];
    const nextSubtasks = {};
    const nextComments = {};

    for (const subtask of subtasks) {
        nextSubtasks[subtask.id] = {
            ...(calendarSubtaskDrafts.value[subtask.id] || {}),
            title: subtask.title || '',
            task_type: subtask.task_type || 'task',
            status: subtask.status || 'todo',
            priority: subtask.priority || 'medium',
            project_id: subtask.project_id || task?.project_id || '',
            client_id: subtask.client_id || task?.client_id || '',
            service_id: subtask.service_id || task?.service_id || '',
            start_date: subtask.start_date || '',
            due_date: subtask.due_date || '',
            due_time: subtask.due_time ? String(subtask.due_time).slice(0, 5) : '',
            location: subtask.location || '',
            description: subtask.description || '',
            assignee_ids: [...(subtask.assignee_ids || [])],
            follower_ids: [...(subtask.follower_ids || [])],
        };
    }

    for (const comment of comments) {
        nextComments[comment.id] = {
            ...(calendarCommentDrafts.value[comment.id] || {}),
            content: comment.content || '',
        };
    }

    calendarSubtaskDrafts.value = nextSubtasks;
    calendarCommentDrafts.value = nextComments;
}

function calendarPanelSubtasks() {
    return filteredCalendarSubtasks(calendarTaskPanel.value);
}

function filteredCalendarSubtasks(task) {
    if (!task?.id) return [];

    return (task.subtasks || [])
        .filter((subtask) => !subtask.parent_task_id || subtask.parent_task_id === task.id)
        .map((subtask) => normalizeCalendarTask({
            ...subtask,
            parent_task_id: subtask.parent_task_id || task.id,
            parent_title: subtask.parent_title || task.title,
        }));
}

function normalizeCalendarTask(task, overrides = {}) {
    const normalized = {
        ...(task || {}),
        ...overrides,
    };

    return {
        ...normalized,
        assignee_ids: [...(normalized.assignee_ids || [])],
        follower_ids: [...(normalized.follower_ids || [])],
        dependencies: [...(normalized.dependencies || [])],
        dependents: [...(normalized.dependents || [])],
        comments: [...(normalized.comments || [])],
        activity: [...(normalized.activity || [])],
        subtasks: (normalized.subtasks || [])
            .filter((subtask) => !subtask.parent_task_id || subtask.parent_task_id === normalized.id)
            .map((subtask) => ({
                ...subtask,
                assignee_ids: [...(subtask.assignee_ids || [])],
                follower_ids: [...(subtask.follower_ids || [])],
                dependencies: [...(subtask.dependencies || [])],
                dependents: [...(subtask.dependents || [])],
                comments: [...(subtask.comments || [])],
                activity: [...(subtask.activity || [])],
                subtasks: [],
                parent_task_id: subtask.parent_task_id || normalized.id,
                parent_title: subtask.parent_title || normalized.title,
            })),
    };
}

function isCalendarSubtaskPanel() {
    return Boolean(calendarTaskParentStack.value.length || calendarTaskPanel.value?.parent_task_id);
}

function calendarPanelComments() {
    return calendarTaskPanel.value?.comments || [];
}

function calendarPanelActivity() {
    return calendarTaskPanel.value?.activity || [];
}

function visibleCalendarPanelComments() {
    const comments = calendarPanelComments();
    return calendarShowAllComments.value ? comments : comments.slice(0, 3);
}

function hiddenCalendarCommentsCount() {
    return Math.max(0, calendarPanelComments().length - 3);
}

function visibleCalendarPanelActivity() {
    const activity = calendarPanelActivity();
    return calendarShowAllActivity.value ? activity : activity.slice(0, 3);
}

function hiddenCalendarActivityCount() {
    return Math.max(0, calendarPanelActivity().length - 3);
}

function calendarSubtaskPayload(subtaskId) {
    const draft = calendarSubtaskDrafts.value[subtaskId] || {};

    return {
        title: draft.title || '',
        task_type: draft.task_type || 'task',
        status: draft.status || 'todo',
        priority: draft.priority || 'medium',
        project_id: draft.project_id || calendarTaskForm.project_id || '',
        client_id: draft.client_id || calendarTaskForm.client_id || '',
        service_id: draft.service_id || calendarTaskForm.service_id || '',
        start_date: draft.start_date || '',
        due_date: draft.due_date || '',
        due_time: draft.due_time || '',
        location: draft.location || '',
        description: draft.description || '',
        recurring_enabled: false,
        recurring_interval_value: '',
        recurring_interval_unit: '',
        recurring_mode: '',
        recurring_weekday: '',
        recurring_month_day: '',
    };
}

function saveCalendarSubtaskInline(subtask, delay = AUTOSAVE_IDLE_DELAY) {
    const payload = calendarSubtaskPayload(subtask.id);
    if (!String(payload.title).trim()) {
        setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'idle');
        return;
    }

    window.clearTimeout(calendarSubtaskAutosaveTimers[subtask.id]);
    setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'queued');
    setInlineState(calendarSubtaskAutosaveErrors, subtask.id, '');

    calendarSubtaskAutosaveTimers[subtask.id] = window.setTimeout(() => {
        const sequence = (calendarSubtaskAutosaveSequences[subtask.id] || 0) + 1;
        calendarSubtaskAutosaveSequences[subtask.id] = sequence;
        setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'saving');

        router.put(route('tasks.update', subtask.id), payload, {
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'errors', 'flash'],
            onSuccess: () => {
                if (sequence !== calendarSubtaskAutosaveSequences[subtask.id]) return;
                setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'saved');
                window.setTimeout(() => {
                    if (calendarSubtaskAutosaveStates.value[subtask.id] === 'saved') {
                        setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'idle');
                    }
                }, 1400);
            },
            onError: () => {
                if (sequence !== calendarSubtaskAutosaveSequences[subtask.id]) return;
                setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'error');
                setInlineState(calendarSubtaskAutosaveErrors, subtask.id, 'Non salvato');
            },
        });
    }, autosaveDelay(delay));
}

function setCalendarSubtaskStatus(subtask, done) {
    const wasDone = (calendarSubtaskDrafts.value[subtask.id]?.status || subtask.status) === 'done';
    calendarSubtaskStatusPulse.value = subtask.id;
    window.setTimeout(() => {
        if (calendarSubtaskStatusPulse.value === subtask.id) {
            calendarSubtaskStatusPulse.value = null;
        }
    }, 360);
    const status = done ? 'done' : 'todo';
    if (calendarSubtaskDrafts.value[subtask.id]) {
        calendarSubtaskDrafts.value[subtask.id].status = status;
    }

    setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'saving');
    setInlineState(calendarSubtaskAutosaveErrors, subtask.id, '');
    router.patch(route('tasks.status.update', subtask.id), { status }, {
        preserveScroll: true,
        preserveState: true,
        only: ['rows', 'errors', 'flash'],
        onSuccess: () => {
            setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'saved');
            if (!wasDone && status === 'done') {
                window.dispatchEvent(new CustomEvent('centro:task-completed'));
            }
            window.setTimeout(() => {
                if (calendarSubtaskAutosaveStates.value[subtask.id] === 'saved') {
                    setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'idle');
                }
            }, 1400);
        },
        onError: () => {
            if (calendarSubtaskDrafts.value[subtask.id]) {
                calendarSubtaskDrafts.value[subtask.id].status = subtask.status;
            }
            setInlineState(calendarSubtaskAutosaveStates, subtask.id, 'error');
            setInlineState(calendarSubtaskAutosaveErrors, subtask.id, 'Non salvato');
        },
    });
}

function addCalendarSubtask() {
    const parentTaskId = calendarTaskPanel.value?.id || calendarTaskForm.id;
    if (!parentTaskId) return;

    const payload = {
        title: calendarSubtaskForm.title,
        priority: calendarSubtaskForm.priority || 'medium',
        due_date: calendarSubtaskForm.due_date || '',
        assignee_ids: [...calendarCreateSubtaskAssigneeIds.value],
    };

    router.post(route('tasks.subtasks.store', parentTaskId), payload, {
        preserveScroll: true,
        preserveState: true,
        only: ['rows', 'errors', 'flash'],
        onSuccess: () => {
            refreshCalendarTaskPanelFromServer(parentTaskId);
            calendarSubtaskForm.reset();
            calendarCreateSubtaskAssigneeIds.value = [];
            calendarSubtaskCreateAssigneeMenuOpen.value = false;
        },
    });
}

function removeCalendarSubtask(subtask) {
    const parentTaskId = calendarTaskPanel.value?.id || calendarTaskForm.id;
    remove(subtask, () => {
        router.delete(route('tasks.destroy', subtask.id), {
            data: { stay: true },
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'errors', 'flash'],
            onSuccess: () => {
                if (calendarTaskPanel.value) {
                    const nextTask = normalizeCalendarTask({
                        ...calendarTaskPanel.value,
                        subtasks: filteredCalendarSubtasks(calendarTaskPanel.value).filter((item) => item.id !== subtask.id),
                    });
                    calendarTaskPanel.value = nextTask;
                    rememberCalendarRowOverride(nextTask);
                    hydrateCalendarTaskRelated(nextTask);
                }
                refreshCalendarTaskPanelFromServer(parentTaskId);
            },
            onFinish: cancelDelete,
        });
    });
}

function removeCalendarTask() {
    if (!calendarTaskForm.id) return;
    calendarTaskActionMenuOpen.value = false;

    remove({ id: calendarTaskForm.id, title: calendarTaskForm.title || 'Task' }, () => {
        router.delete(route('tasks.destroy', calendarTaskForm.id), {
            data: { stay: true },
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'errors', 'flash'],
            onSuccess: closeCalendarTaskPanel,
            onFinish: cancelDelete,
        });
    });
}

async function copyCalendarTaskLink() {
    if (!calendarTaskForm.id) return;
    calendarTaskActionMenuOpen.value = false;
    const href = route('tasks.show', calendarTaskForm.id);
    const absoluteHref = href.startsWith('http') ? href : `${window.location.origin}${href}`;

    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(absoluteHref);
    }
}

function duplicateCalendarTask() {
    if (!calendarTaskForm.id) return;
    calendarTaskActionMenuOpen.value = false;
    router.post(route('tasks.duplicate', calendarTaskForm.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function printCalendarTask() {
    calendarTaskActionMenuOpen.value = false;
    window.print();
}

function toggleCalendarTaskActionMenu(event = null) {
    const nextOpen = !calendarTaskActionMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    calendarTaskActionMenuStyle.value = dropdownMenuStyleFromEvent(event, 220);
    calendarTaskActionMenuOpen.value = nextOpen;
}

function calendarCommentPayload(commentId) {
    return {
        content: calendarCommentDrafts.value[commentId]?.content || '',
    };
}

function saveCalendarCommentInline(comment, delay = AUTOSAVE_IDLE_DELAY) {
    updateCalendarCommentFromEditor(comment.id);
    const payload = calendarCommentPayload(comment.id);
    if (!String(payload.content).trim()) {
        setInlineState(calendarCommentAutosaveStates, comment.id, 'idle');
        return;
    }

    window.clearTimeout(calendarCommentAutosaveTimers[comment.id]);
    setInlineState(calendarCommentAutosaveStates, comment.id, 'queued');
    setInlineState(calendarCommentAutosaveErrors, comment.id, '');

    calendarCommentAutosaveTimers[comment.id] = window.setTimeout(() => {
        const sequence = (calendarCommentAutosaveSequences[comment.id] || 0) + 1;
        calendarCommentAutosaveSequences[comment.id] = sequence;
        setInlineState(calendarCommentAutosaveStates, comment.id, 'saving');

        router.put(route('tasks.comments.update', [calendarTaskForm.id, comment.id]), payload, {
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'errors', 'flash'],
            onSuccess: () => {
                if (sequence !== calendarCommentAutosaveSequences[comment.id]) return;
                setInlineState(calendarCommentAutosaveStates, comment.id, 'saved');
                window.setTimeout(() => {
                    if (calendarCommentAutosaveStates.value[comment.id] === 'saved') {
                        setInlineState(calendarCommentAutosaveStates, comment.id, 'idle');
                    }
                }, 1400);
            },
            onError: () => {
                if (sequence !== calendarCommentAutosaveSequences[comment.id]) return;
                setInlineState(calendarCommentAutosaveStates, comment.id, 'error');
                setInlineState(calendarCommentAutosaveErrors, comment.id, 'Non salvato');
            },
        });
    }, autosaveDelay(delay));
}

function addCalendarComment() {
    if (!calendarTaskForm.id) return;

    updateCalendarCommentFromEditor('new');
    if (!String(calendarCommentForm.content || '').trim()) return;

    calendarCommentForm.post(route('tasks.comments.store', calendarTaskForm.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['rows', 'errors', 'flash'],
        onSuccess: () => {
            calendarCommentForm.reset();
            refreshCalendarCommentEditor('new');
        },
    });
}

function removeCalendarComment(comment) {
    remove({ ...comment, title: 'Commento' }, () => {
        router.delete(route('tasks.comments.destroy', [calendarTaskForm.id, comment.id]), {
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'errors', 'flash'],
            onFinish: cancelDelete,
        });
    });
}

function editCalendarComment(comment) {
    calendarEditingCommentId.value = comment.id;
    if (!calendarCommentDrafts.value[comment.id]) {
        calendarCommentDrafts.value = {
            ...calendarCommentDrafts.value,
            [comment.id]: { content: comment.content || '' },
        };
    }
    refreshCalendarCommentEditor(comment.id);
}

function stopEditingCalendarComment(comment) {
    if (calendarEditingCommentId.value !== comment.id) return;

    saveCalendarCommentInline(comment, 0);
    calendarEditingCommentId.value = null;
}

function calendarTaskPayload() {
    return {
        title: calendarTaskForm.title,
        description: calendarTaskForm.description,
        project_id: calendarTaskForm.project_id || null,
        client_id: calendarTaskForm.client_id || null,
        service_id: calendarTaskForm.service_id || null,
        task_type: calendarTaskForm.task_type,
        status: calendarTaskForm.status,
        priority: calendarTaskForm.priority,
        start_date: calendarTaskForm.start_date || null,
        due_date: calendarTaskForm.due_date || null,
        due_time: calendarTaskForm.due_time || null,
        location: calendarTaskForm.location || null,
        recurring_enabled: Boolean(calendarTaskForm.recurring_enabled),
        recurring_interval_value: calendarTaskForm.recurring_enabled ? (calendarTaskForm.recurring_interval_value || 1) : null,
        recurring_interval_unit: calendarTaskForm.recurring_enabled ? (calendarTaskForm.recurring_interval_unit || 'week') : null,
        recurring_mode: calendarTaskForm.recurring_enabled ? (calendarTaskForm.recurring_mode || 'fixed') : null,
        recurring_weekday: calendarTaskForm.recurring_enabled ? calendarTaskForm.recurring_weekday : null,
        recurring_month_day: calendarTaskForm.recurring_enabled ? calendarTaskForm.recurring_month_day : null,
        assignee_ids: [...(calendarTaskForm.assignee_ids || [])],
        follower_ids: [...(calendarTaskForm.follower_ids || [])],
    };
}

function saveCalendarTaskInline(delay = AUTOSAVE_IDLE_DELAY) {
    if (!String(calendarTaskForm.title || '').trim()) return;

    window.clearTimeout(calendarTaskAutosaveTimer);
    calendarTaskAutosaveState.value = 'queued';
    calendarTaskAutosaveError.value = '';

    calendarTaskAutosaveTimer = window.setTimeout(() => {
        const sequence = calendarTaskAutosaveSequence + 1;
        calendarTaskAutosaveSequence = sequence;
        calendarTaskAutosaveState.value = 'saving';
        const requestOptions = {
            preserveScroll: true,
            preserveState: true,
            only: ['rows', 'errors', 'flash'],
            onSuccess: () => {
                if (sequence !== calendarTaskAutosaveSequence) return;
                const createdId = page.props.flash?.created_id;
                if (!calendarTaskForm.id && createdId) {
                    calendarTaskForm.id = createdId;
                    if (!calendarTaskPanelClosedByUser.value) {
                        calendarTaskPanelOpen.value = true;
                    }
                    calendarTaskPanelMode.value = 'edit';
                    calendarTaskPanel.value = {
                        ...(calendarTaskPanel.value || {}),
                        id: createdId,
                        title: calendarTaskForm.title,
                        task_type: calendarTaskForm.task_type,
                        status: calendarTaskForm.status,
                        priority: calendarTaskForm.priority,
                        start_date: calendarTaskForm.start_date,
                        due_date: calendarTaskForm.due_date,
                        due_time: calendarTaskForm.due_time,
                    };
                    calendarTaskForm.defaults({
                        id: createdId,
                        title: calendarTaskForm.title,
                        description: calendarTaskForm.description,
                        project_id: calendarTaskForm.project_id,
                        client_id: calendarTaskForm.client_id,
                        service_id: calendarTaskForm.service_id,
                        task_type: calendarTaskForm.task_type,
                        status: calendarTaskForm.status,
                        priority: calendarTaskForm.priority,
                        start_date: calendarTaskForm.start_date,
                        due_date: calendarTaskForm.due_date,
                        due_time: calendarTaskForm.due_time,
                        location: calendarTaskForm.location,
                        recurring_enabled: calendarTaskForm.recurring_enabled,
                        recurring_interval_value: calendarTaskForm.recurring_interval_value,
                        recurring_interval_unit: calendarTaskForm.recurring_interval_unit,
                        recurring_mode: calendarTaskForm.recurring_mode,
                        recurring_weekday: calendarTaskForm.recurring_weekday,
                        recurring_month_day: calendarTaskForm.recurring_month_day,
                        assignee_ids: [...(calendarTaskForm.assignee_ids || [])],
                        follower_ids: [...(calendarTaskForm.follower_ids || [])],
                    });
                }
                calendarTaskAutosaveState.value = 'saved';
                window.setTimeout(() => {
                    if (calendarTaskAutosaveState.value === 'saved') {
                        calendarTaskAutosaveState.value = 'idle';
                    }
                }, 1400);
            },
            onError: () => {
                if (sequence !== calendarTaskAutosaveSequence) return;
                calendarTaskAutosaveState.value = 'error';
                calendarTaskAutosaveError.value = calendarTaskForm.id ? 'Non salvato' : 'Non creata';
            },
            onFinish: () => {
                if (calendarTaskForm.id && !calendarTaskPanelClosedByUser.value) {
                    calendarTaskPanelOpen.value = true;
                }
                calendarTaskForm.transform((data) => data);
            },
        };

        calendarTaskForm.transform(() => calendarTaskPayload());
        if (calendarTaskForm.id) {
            calendarTaskForm.put(route('tasks.update', calendarTaskForm.id), requestOptions);
        } else {
            calendarTaskForm.post(route('tasks.store'), requestOptions);
        }
    }, autosaveDelay(delay));
}

function setCalendarTaskType(type) {
    calendarTaskForm.task_type = type;
    if (type === 'meeting') {
        calendarTaskForm.project_id = '';
        calendarTaskForm.recurring_enabled = false;
        calendarTaskForm.due_time = calendarTaskForm.due_time || '09:00';
    } else {
        calendarTaskForm.location = '';
        if (type === 'ongoing') calendarTaskForm.project_id = '';
    }
    saveCalendarTaskInline(0);
}

function toggleCalendarTaskComplete() {
    if (!calendarTaskForm.id) return;
    const willComplete = calendarTaskForm.status !== 'done';
    if (willComplete && blockedDependencyCount(calendarTaskPanel.value) > 0) {
        calendarTaskAutosaveState.value = 'error';
        calendarTaskAutosaveError.value = 'Task bloccata dalle dipendenze.';
        return;
    }
    const nextStatus = willComplete ? 'done' : 'todo';
    calendarTaskStatusPulse.value = true;
    window.setTimeout(() => {
        calendarTaskStatusPulse.value = false;
    }, 360);
    calendarTaskForm.status = nextStatus;
    router.patch(route('tasks.status.update', calendarTaskForm.id), { status: nextStatus }, {
        preserveScroll: true,
        preserveState: true,
        only: ['rows', 'errors', 'flash'],
        onSuccess: () => {
            calendarTaskAutosaveError.value = '';
            if (willComplete) {
                window.dispatchEvent(new CustomEvent('centro:task-completed'));
            }
        },
        onError: (errors) => {
            calendarTaskForm.status = willComplete ? 'todo' : 'done';
            calendarTaskAutosaveState.value = 'error';
            calendarTaskAutosaveError.value = errors.status || 'Task bloccata dalle dipendenze.';
        },
    });
}

function setCalendarTaskStatusFromSelect(value) {
    if (value === 'done' && blockedDependencyCount(calendarTaskPanel.value) > 0) {
        calendarTaskForm.status = calendarTaskPanel.value?.status || 'todo';
        calendarTaskAutosaveState.value = 'error';
        calendarTaskAutosaveError.value = 'Task bloccata dalle dipendenze.';
        return;
    }
    calendarTaskAutosaveError.value = '';
    saveCalendarTaskInline(0);
}

function openCalendarCreateMenu(date) {
    const nextDate = calendarCreateDate.value === date ? null : date;
    if (nextDate) requestFloatingUiClose();
    calendarCreateDate.value = nextDate;
}

function closeCalendarCreateMenuOnOutside(event) {
    if (event?.target instanceof Element && event.target.closest('[data-calendar-create-menu]')) return;

    calendarCreateDate.value = null;
}

watch(
    () => props.rows,
    (rows) => {
        refreshCalendarTaskPanelFromRows(calendarRowsWithOverrides(rows));
    },
);

watch(
    calendarTaskPanelOpen,
    (open) => {
        if (typeof document === 'undefined') return;

        if (open) {
            calendarBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            return;
        }

        document.body.style.overflow = calendarBodyOverflow;
    },
);

onMounted(() => {
    document.addEventListener('pointerdown', closeCalendarCreateMenuOnOutside, true);
    document.addEventListener('pointerdown', closeCalendarSubtaskAssigneeMenuOnOutside, true);
    document.addEventListener('pointerdown', closeCalendarPeopleMenuOnOutside, true);
    document.addEventListener('pointerdown', closeProjectPeopleMenuOnOutside, true);
    document.addEventListener('pointerdown', closeTaskPeopleMenuOnOutside, true);
    document.addEventListener('pointerdown', closeTaskSearchSelectOnOutside, true);
    window.addEventListener('centro:close-floating-ui', closeCentroIndexFloatingUi);
    if (props.section === 'calendar') {
        centerCalendarScroll();
    }
});

watch(
    () => props.rows,
    (rows) => {
        if (props.section !== 'absences') return;
        const nextDrafts = {};
        (rows || []).forEach((row) => {
            nextDrafts[row.id] = absenceDrafts.value[row.id] || {
                type: row.type || 'vacation',
                start_date: row.start_date || '',
                end_date: row.end_date || row.start_date || '',
                start_time: row.start_time ? String(row.start_time).slice(0, 5) : '',
                end_time: row.end_time ? String(row.end_time).slice(0, 5) : '',
                inps_code: row.inps_code || '',
                status: row.status || 'pending',
                notes: row.notes || '',
            };
        });
        absenceDrafts.value = nextDrafts;
    },
    { immediate: true },
);
onUnmounted(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = calendarBodyOverflow;
    }
    document.removeEventListener('pointerdown', closeCalendarCreateMenuOnOutside, true);
    document.removeEventListener('pointerdown', closeCalendarSubtaskAssigneeMenuOnOutside, true);
    document.removeEventListener('pointerdown', closeCalendarPeopleMenuOnOutside, true);
    document.removeEventListener('pointerdown', closeProjectPeopleMenuOnOutside, true);
    document.removeEventListener('pointerdown', closeTaskPeopleMenuOnOutside, true);
    document.removeEventListener('pointerdown', closeTaskSearchSelectOnOutside, true);
    window.removeEventListener('centro:close-floating-ui', closeCentroIndexFloatingUi);
    window.clearTimeout(calendarScrollTimer);
    cancelClientServicesDrag();
});

function toggleTaskDone(task) {
    const willComplete = task.status !== 'done';
    if (willComplete && blockedDependencyCount(task) > 0) return;

    router.patch(route('tasks.status.update', task.id), {
        status: task.status === 'done' ? 'todo' : 'done',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            if (willComplete) {
                window.dispatchEvent(new CustomEvent('centro:task-completed'));
            }
        },
    });
}

function daysBetween(start, end) {
    const startDate = new Date(`${start}T00:00:00`);
    const endDate = new Date(`${end}T00:00:00`);
    return Math.round((endDate.getTime() - startDate.getTime()) / 86400000);
}

function addDays(date, days) {
    const next = new Date(`${date}T00:00:00`);
    next.setDate(next.getDate() + days);
    return formatCalendarDate(next.getFullYear(), next.getMonth(), next.getDate());
}

function startCalendarDrag(task) {
    calendarDraggedTask.value = task;
}

function endCalendarDrag() {
    calendarDraggedTask.value = null;
    calendarDropDate.value = null;
}

function moveCalendarTask(date) {
    const task = calendarDraggedTask.value;
    if (!task || !task.due_date) return;

    const start = task.start_date && task.start_date <= task.due_date ? task.start_date : task.due_date;
    const duration = start !== task.due_date ? daysBetween(start, task.due_date) : 0;
    const payload = {
        due_date: duration > 0 ? addDays(date, duration) : date,
        start_date: duration > 0 ? date : null,
    };

    if (payload.due_date === task.due_date && (payload.start_date || null) === (task.start_date || null)) {
        endCalendarDrag();
        return;
    }

    router.patch(route('tasks.schedule.update', task.id), payload, {
        preserveScroll: true,
        onFinish: endCalendarDrag,
    });
}

function taskSpansDate(row, date) {
    if (!row.due_date) return false;

    const start = row.start_date && row.start_date <= row.due_date ? row.start_date : row.due_date;
    return start <= date && row.due_date >= date;
}

function taskSpanRole(row, date) {
    const start = row.start_date && row.start_date <= row.due_date ? row.start_date : row.due_date;
    if (start === row.due_date) return 'single';
    if (date === start) return 'start';
    if (date === row.due_date) return 'end';
    return 'middle';
}

function taskSpanClass(task) {
    return 'rounded-xl';
}

function taskDateRange(task) {
    if (!task?.due_date) {
        return { start: null, end: null };
    }

    const start = task.start_date && task.start_date <= task.due_date ? task.start_date : task.due_date;
    return { start, end: task.due_date };
}

function isMultiDayTask(task) {
    const { start, end } = taskDateRange(task);
    return Boolean(start && end && start !== end);
}

function calendarMonthEndDate(sectionMonth) {
    const lastDay = new Date(sectionMonth.year, sectionMonth.month + 1, 0).getDate();
    return formatCalendarDate(sectionMonth.year, sectionMonth.month, lastDay);
}

function isCalendarTaskBarStart(cell, task) {
    if (!cell?.date || !isMultiDayTask(task)) return true;

    const { start } = taskDateRange(task);
    return cell.date === start || cell.weekday === 0 || cell.day === 1;
}

function calendarTaskRenderClass(cell, task) {
    if (isCalendarTaskBarStart(cell, task)) return '';
    return 'invisible pointer-events-none';
}

function calendarTaskBarStyle(sectionMonth, cell, task) {
    if (!sectionMonth || !cell || !isMultiDayTask(task) || !isCalendarTaskBarStart(cell, task)) {
        return {};
    }

    const { end } = taskDateRange(task);
    const weekEnd = addDays(cell.date, 6 - cell.weekday);
    const monthEnd = calendarMonthEndDate(sectionMonth);
    let visibleEnd = [end, weekEnd, monthEnd].sort()[0];

    if (compactWeekend.value && cell.weekday < 5) {
        visibleEnd = [visibleEnd, addDays(cell.date, 4 - cell.weekday)].sort()[0];
    }

    const spanDays = Math.max(1, daysBetween(cell.date, visibleEnd) + 1);

    return {
        width: `calc(${spanDays * 100}% + ${(spanDays - 1) * 17}px)`,
        zIndex: 20,
    };
}

function taskTypeClass(type) {
    return {
        meeting: 'border-violet-200 bg-violet-50 text-violet-800',
        ongoing: 'border-amber-200 bg-amber-50 text-amber-800',
        project: 'border-blue-200 bg-blue-50 text-blue-800',
        task: 'border-blue-200 bg-blue-50 text-blue-800',
    }[type || 'task'] || 'border-gray-200 bg-gray-50 text-gray-800';
}

function tasksForDay(date) {
    return calendarRowsWithOverrides()
        .filter((row) => row.parent_task_id === null || row.parent_task_id === undefined || row.parent_task_id === '')
        .filter((row) => taskSpansDate(row, date))
        .filter((row) => {
            if (calendarType.value === 'all') return true;
            if (calendarType.value === 'task') return ['task', 'project'].includes(row.task_type || 'task');

            return (row.task_type || 'task') === calendarType.value;
        })
        .filter((row) => {
            if (!calendarUserIds.value.length) return true;
            const people = [...(row.assignee_ids || []), ...(row.follower_ids || [])];

            return calendarUserIds.value.some((userId) => people.includes(userId));
        })
        .map((row) => normalizeCalendarTask(row, { spanRole: taskSpanRole(row, date) }))
        .sort((a, b) => `${a.due_time || '99:99'}${a.title}`.localeCompare(`${b.due_time || '99:99'}${b.title}`));
}

function isCalendarDayExpanded(date) {
    return expandedCalendarDays.value.includes(date);
}

function expandCalendarDay(date) {
    if (!date || isCalendarDayExpanded(date)) return;
    expandedCalendarDays.value = [...expandedCalendarDays.value, date];
}

function collapseCalendarDay(date) {
    if (!date) return;
    expandedCalendarDays.value = expandedCalendarDays.value.filter((expandedDate) => expandedDate !== date);
}

function hiddenCalendarTaskCount(cell) {
    return Math.max(0, (cell?.tasks?.length || 0) - 2);
}

function visibleCalendarTasks(cell) {
    if (isCalendarDayExpanded(cell.date)) return cell.tasks;
    return cell.tasks.slice(0, 2);
}

function calendarWeekVisibleTaskCount(sectionMonth, cell) {
    if (!sectionMonth || !cell || cell.empty) return 2;

    return Math.max(
        2,
        ...sectionMonth.cells
            .filter((item) => !item.empty && item.weekIndex === cell.weekIndex)
            .filter((item) => !(compactWeekend.value && item.weekend))
            .map((item) => visibleCalendarTasks(item).length),
    );
}

function calendarDayStyle(sectionMonth, cell) {
    if (!cell || cell.empty || (compactWeekend.value && cell.weekend)) {
        return {};
    }

    const visibleCount = calendarWeekVisibleTaskCount(sectionMonth, cell);
    return {
        minHeight: `${170 + Math.max(0, visibleCount - 2) * 54}px`,
    };
}
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
                <p class="text-sm text-gray-500">{{ description }}</p>
            </div>
        </template>

        <div v-if="formOpen && canWrite" class="fixed inset-0 z-[5000] flex items-center justify-center bg-gray-900/40 px-4 py-6" @click.self="resetForm">
            <div :class="modalPanelClass">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">{{ formTitle }}</h3>
                    <button type="button" class="icon-btn" @click="resetForm">
                        <span class="sr-only">Chiudi</span>
                        <X class="h-4 w-4" :stroke-width="1.7" />
                    </button>
                </div>

                <form :class="modalFormClass" @focusin.capture="closeTaskPeopleMenuOnOutside" @pointerdown.capture="closeTaskPeopleMenuOnOutside" @submit.prevent="submit">
                    <section v-if="section === 'projects' && !editing" class="rounded-[var(--radius)] border border-gray-100 bg-gray-50/80 p-4 md:col-span-3">
                        <div class="mb-3">
                            <h4 class="text-sm font-semibold text-gray-900">Crea da modello</h4>
                            <p class="mt-1 text-xs text-gray-500">Se scegli un modello, il progetto verrà creato con fasi e task già programmate dalla data di avvio.</p>
                        </div>
                        <div class="grid items-start gap-3 sm:grid-cols-[minmax(0,1fr)_180px]">
                            <div class="flex flex-col gap-1.5">
                                <label class="block text-sm font-medium leading-5 text-gray-700">Modello</label>
                                <AppSelect v-model="form.template_id" :options="projectTemplateOptions" searchable />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="block text-sm font-medium leading-5 text-gray-700">Data avvio</label>
                                <AppDateInput v-model="form.template_start_date" />
                            </div>
                        </div>
                    </section>

                    <template
                        v-for="field in fields"
                        :key="field.name"
                    >
                        <div v-if="section === 'tasks' && field.name === 'recurring_enabled'" ref="taskPeopleMenu" class="grid gap-3 md:col-span-6 sm:grid-cols-2">
                            <div class="relative" data-task-people-field="assignee_ids">
                                <label class="block text-sm font-medium text-gray-700">{{ form.task_type === 'meeting' ? 'Partecipanti' : 'Assegnatari' }}</label>
                                <button type="button" class="form-control task-people-control flex items-center justify-between gap-3 text-left" @click.stop="toggleTaskPeopleMenu('assignee_ids')">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="flex -space-x-2">
                                            <UserAvatar v-for="user in selectedFormUsers('assignee_ids').slice(0, 3)" :key="`assignee-preview-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                        </span>
                                        <span class="truncate">{{ taskPeopleLabel('assignee_ids') }}</span>
                                    </span>
                                    <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', taskPeopleMenuOpen === 'assignee_ids' ? 'rotate-180' : '']" :stroke-width="1.7" />
                                </button>
                                <div v-if="taskPeopleMenuOpen === 'assignee_ids'" class="app-popover field-dropdown-menu absolute left-0 right-0 top-full z-[5300] mt-2 p-3" @click.stop>
                                    <div class="people-avatar-picker max-h-44">
                                        <button
                                            v-for="user in users"
                                            :key="`modal-assignee-${user.id}`"
                                            type="button"
                                            :class="personAvatarClass((form.assignee_ids || []).includes(user.id))"
                                            :aria-pressed="(form.assignee_ids || []).includes(user.id)"
                                            :aria-label="`${(form.assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                            :title="user.name || user.email"
                                            @click="toggleFormPerson('assignee_ids', user.id)"
                                        >
                                            <UserAvatar :user="user" size="md" />
                                        </button>
                                        <p v-if="!users?.length" class="text-xs text-gray-500">Nessun utente disponibile.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="relative" data-task-people-field="follower_ids">
                                <label class="block text-sm font-medium text-gray-700">Follower</label>
                                <button type="button" class="form-control task-people-control flex items-center justify-between gap-3 text-left" @click.stop="toggleTaskPeopleMenu('follower_ids')">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="flex -space-x-2">
                                            <UserAvatar v-for="user in selectedFormUsers('follower_ids').slice(0, 3)" :key="`follower-preview-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                        </span>
                                        <span class="truncate">{{ taskPeopleLabel('follower_ids') }}</span>
                                    </span>
                                    <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', taskPeopleMenuOpen === 'follower_ids' ? 'rotate-180' : '']" :stroke-width="1.7" />
                                </button>
                                <div v-if="taskPeopleMenuOpen === 'follower_ids'" class="app-popover field-dropdown-menu absolute left-0 right-0 top-full z-[5300] mt-2 p-3" @click.stop>
                                    <div class="people-avatar-picker max-h-44">
                                        <button
                                            v-for="user in users"
                                            :key="`modal-follower-${user.id}`"
                                            type="button"
                                            :class="personAvatarClass((form.follower_ids || []).includes(user.id))"
                                            :aria-pressed="(form.follower_ids || []).includes(user.id)"
                                            :aria-label="`${(form.follower_ids || []).includes(user.id) ? 'Rimuovi follower' : 'Aggiungi follower'} ${user.name || user.email}`"
                                            :title="user.name || user.email"
                                            @click="toggleFormPerson('follower_ids', user.id)"
                                        >
                                            <UserAvatar :user="user" size="md" />
                                        </button>
                                        <p v-if="!users?.length" class="text-xs text-gray-500">Nessun utente disponibile.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <section v-if="section === 'tasks' && field.name === 'recurring_enabled' && !editing" class="rounded-md border border-gray-100 bg-gray-50/90 p-3 md:col-span-6">
                            <div class="mb-3">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dipendenze</h4>
                                <p class="mt-1 text-xs text-gray-500">Imposta se la nuova task è bloccata da altre task o se blocca task già aperte.</p>
                            </div>
                            <div class="grid gap-2 md:grid-cols-[170px_minmax(0,1fr)]">
                                <AppSelect
                                    v-model="formDependencyDirection"
                                    :options="taskDependencyDirectionOptions"
                                    placeholder="Tipo relazione"
                                />
                                <AppSelect
                                    v-model="formDependencyToAdd"
                                    :options="formTaskDependencySelectOptions()"
                                    :placeholder="formDependencyDirection === 'blocks' ? 'Scegli task bloccata' : 'Scegli task bloccante'"
                                    searchable
                                    @change="addFormTaskDependency"
                                />
                            </div>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Bloccata da</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="dependency in selectedFormDependencies()"
                                            :key="`form-dependency-${dependency.id}`"
                                            class="inline-flex max-w-full items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700"
                                        >
                                            <span class="truncate">{{ dependency.title }}</span>
                                            <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi dipendenza" @click="removeFormTaskDependency(dependency.id)">
                                                <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                            </button>
                                        </span>
                                        <span v-if="!selectedFormDependencies().length" class="text-xs text-gray-500">Nessuna task.</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Blocca</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="dependent in selectedFormDependents()"
                                            :key="`form-dependent-${dependent.id}`"
                                            class="inline-flex max-w-full items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-gray-600"
                                        >
                                            <span class="truncate">{{ dependent.title }}</span>
                                            <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi relazione" @click="removeFormTaskDependent(dependent.id)">
                                                <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                            </button>
                                        </span>
                                        <span v-if="!selectedFormDependents().length" class="text-xs text-gray-500">Nessuna task.</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="form.errors.dependencies" class="mt-2 text-sm text-red-600">{{ form.errors.dependencies }}</div>
                        </section>

                        <div
                            v-show="shouldShowField(field)"
                            :class="modalFieldClass(field)"
                        >
                            <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                            <div v-if="section === 'tasks' && field.name === 'description'" class="mt-1 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 px-2 py-1.5">
                                    <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @click="runTaskEditorCommand('bold')">
                                        <Bold class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @click="runTaskEditorCommand('italic')">
                                        <Italic class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @click="runTaskEditorCommand('underline')">
                                        <Underline class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button type="button" class="icon-btn h-8 w-8" title="Titolo" @click="runTaskEditorCommand('formatBlock', 'h3')">
                                        <Heading3 class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Testo normale" @click="runTaskEditorCommand('formatBlock', 'p')">
                                        <span class="text-xs font-semibold">P</span>
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @click="runTaskEditorCommand('insertUnorderedList')">
                                        <List class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @click="runTaskEditorCommand('insertOrderedList')">
                                        <ListOrdered class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Citazione" @click="runTaskEditorCommand('formatBlock', 'blockquote')">
                                        <Quote class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Link" @click="addTaskEditorLink">
                                        <Link2 class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                </div>
                                <div
                                    ref="taskDescriptionEditor"
                                    class="min-h-28 px-3 py-2 text-sm leading-6 text-gray-800 outline-none"
                                    contenteditable="true"
                                    data-placeholder="Aggiungi una descrizione..."
                                    @input="updateTaskDescriptionFromEditor"
                                ></div>
                            </div>
                            <textarea v-else-if="field.type === 'textarea'" v-model="form[field.name]" :rows="section === 'tasks' ? 3 : 4" class="form-control" />
                            <div v-else-if="isTaskSearchSelect(field)" class="relative" :data-task-search-field="field.name">
                                <button
                                    type="button"
                                    class="form-control flex h-[38px] items-center justify-between gap-3 text-left"
                                    @click.stop="toggleTaskSearchSelect(field)"
                                >
                                    <span class="truncate">{{ taskSearchSelectLabel(field) }}</span>
                                    <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', taskSearchSelectOpen === field.name ? 'rotate-180' : '']" :stroke-width="1.7" />
                                </button>
                                <div
                                    v-if="taskSearchSelectOpen === field.name"
                                    class="app-popover field-dropdown-menu absolute left-0 right-0 top-full z-[5300] mt-2 p-3"
                                    @click.stop
                                >
                                    <input
                                        v-model="taskSearchSelectQueries[field.name]"
                                        type="search"
                                        class="form-control mt-0"
                                        :placeholder="`Cerca ${field.label.toLowerCase()}...`"
                                        autocomplete="off"
                                    />
                                    <div class="mt-2 max-h-48 overflow-y-auto pr-1">
                                        <button
                                            v-if="canClearTaskSearchSelect(field)"
                                            type="button"
                                            :class="[
                                                'field-dropdown-option flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-indigo-50',
                                                !form[field.name] ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700',
                                            ]"
                                            @click="selectTaskSearchOption(field, '')"
                                        >
                                            <span>{{ taskSearchEmptyLabel(field) }}</span>
                                            <Check v-if="!form[field.name]" class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <button
                                            v-for="option in filteredTaskSearchOptions(field)"
                                            :key="option.id"
                                            type="button"
                                            :class="[
                                                'field-dropdown-option flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition hover:bg-indigo-50',
                                                form[field.name] === option.id ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700',
                                            ]"
                                            @click="selectTaskSearchOption(field, option.id)"
                                        >
                                            <span class="truncate">{{ optionLabel(field, option) }}</span>
                                            <Check v-if="form[field.name] === option.id" class="h-4 w-4 shrink-0" :stroke-width="1.8" />
                                        </button>
                                        <p v-if="!filteredTaskSearchOptions(field).length" class="px-3 py-2 text-sm text-gray-500">Nessun risultato</p>
                                    </div>
                                </div>
                            </div>
                            <AppSelect
                                v-else-if="['select', 'client', 'project', 'service'].includes(field.type)"
                                v-model="form[field.name]"
                                :options="fieldSelectOptions(field)"
                                placeholder="Seleziona"
                                searchable
                            />
                            <div v-else-if="field.type === 'user'" class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-if="!field.required"
                                    type="button"
                                    :class="personAvatarClass(!form[field.name])"
                                    aria-label="Nessuna persona"
                                    title="Nessuna persona"
                                    @click="form[field.name] = ''"
                                >
                                    <span class="text-xs font-semibold">-</span>
                                </button>
                                <button
                                    v-for="user in users"
                                    :key="`${field.name}-${user.id}`"
                                    type="button"
                                    :class="personAvatarClass(form[field.name] === user.id)"
                                    :aria-pressed="form[field.name] === user.id"
                                    :aria-label="`Seleziona ${user.name || user.email}`"
                                    :title="user.name || user.email"
                                    @click="form[field.name] = user.id"
                                >
                                    <UserAvatar :user="user" size="md" />
                                </button>
                            </div>
                            <div v-else-if="field.type === 'color'" class="mt-2 flex flex-wrap items-center gap-2">
                                <button
                                    v-for="color in projectColors"
                                    :key="`${field.name}-${color}`"
                                    type="button"
                                    :class="['h-8 w-8 rounded-full border-2', form[field.name] === color ? 'border-gray-900 ring-2 ring-gray-300' : 'border-white']"
                                    :style="{ backgroundColor: color }"
                                    :aria-label="`Colore ${color}`"
                                    @click="form[field.name] = color"
                                ></button>
                                <label class="relative inline-flex h-8 w-8 cursor-pointer items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white shadow-sm ring-1 ring-gray-200 transition hover:ring-gray-300" :style="{ backgroundColor: normalizeHexColor(form[field.name]) }">
                                    <span class="sr-only">Scegli colore custom</span>
                                    <input v-model="form[field.name]" type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                                </label>
                                <input v-model="form[field.name]" type="text" class="form-control mt-0 w-28 font-mono text-xs" :required="field.required" />
                            </div>
                            <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form[field.name]" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Si
                            </label>
                            <input v-else v-model="form[field.name]" :type="field.type" class="form-control" :required="field.required" @click="openFieldPicker($event, field)" />
                            <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                        </div>

                        <div v-if="section === 'tasks' && field.name === 'recurring_enabled' && form.recurring_enabled" class="rounded-md border border-gray-100 bg-gray-50/90 p-3 md:col-span-6">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Ogni</label>
                                    <input v-model="form.recurring_interval_value" type="number" min="1" class="form-control" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Unita ricorrenza</label>
                                    <AppSelect v-model="form.recurring_interval_unit" :options="recurrenceUnitOptions" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Modalita ricorrenza</label>
                                    <AppSelect v-model="form.recurring_mode" :options="recurrenceModeOptions" />
                                </div>
                                <div v-if="form.recurring_interval_unit === 'week'">
                                    <label class="block text-sm font-medium text-gray-700">Giorno settimana</label>
                                    <input v-model="form.recurring_weekday" type="number" min="1" max="7" class="form-control" />
                                </div>
                                <div v-if="form.recurring_interval_unit === 'month' && form.recurring_mode === 'fixed'">
                                    <label class="block text-sm font-medium text-gray-700">Giorno mese</label>
                                    <input v-model="form.recurring_month_day" type="number" min="1" max="31" class="form-control" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <div :class="['flex justify-end gap-2 border-t border-gray-100 pt-4', section === 'tasks' ? 'md:col-span-6' : 'md:col-span-3']">
                        <button type="button" class="btn btn-outline" @click="resetForm"><X class="h-4 w-4" :stroke-width="1.7" />Annulla</button>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <Save v-if="editing" class="h-4 w-4" :stroke-width="1.7" />
                            <Plus v-else class="h-4 w-4" :stroke-width="1.7" />
                            {{ editing ? 'Salva modifiche' : 'Crea' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 z-[7000] flex items-center justify-center bg-transparent px-4 py-6" @click.self="cancelDelete">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">Conferma eliminazione</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Questa azione e' irreversibile: <span class="font-medium text-gray-900">{{ deleteTargetName() }}</span>.
                    Digita <span class="font-mono font-semibold text-gray-900">ELIMINA</span> per confermare.
                </p>
                <input v-model="deleteConfirmText" class="form-control font-mono" placeholder="ELIMINA" autocomplete="off" />
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="cancelDelete">
                        <X class="h-4 w-4" :stroke-width="1.7" />
                        Annulla
                    </button>
                    <button
                        type="button"
                        class="btn bg-red-600 text-white hover:bg-red-500"
                        :disabled="deleteConfirmText !== 'ELIMINA'"
                        @click="confirmDelete"
                    >
                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                        Elimina
                    </button>
                </div>
            </div>
        </div>

        <div v-if="restoreTarget" class="fixed inset-0 z-[7000] flex items-center justify-center bg-transparent px-4 py-6" @click.self="cancelRestoreBackup">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl dark:bg-gray-950">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Conferma ripristino</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Questa azione sostituirà i contenuti attuali con il backup del
                    <span class="font-medium text-gray-900 dark:text-white">{{ dateIt(restoreTarget.started_at) }}</span>.
                    Digita <span class="font-mono font-semibold text-gray-900 dark:text-white">RIPRISTINA</span> per confermare.
                </p>
                <input v-model="restoreConfirmText" class="form-control font-mono" placeholder="RIPRISTINA" autocomplete="off" />
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="cancelRestoreBackup">
                        <X class="h-4 w-4" :stroke-width="1.7" />
                        Annulla
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="restoreConfirmText !== 'RIPRISTINA'"
                        @click="confirmRestoreBackup"
                    >
                        <RotateCcw class="h-4 w-4" :stroke-width="1.7" />
                        Ripristina
                    </button>
                </div>
            </div>
        </div>

        <div v-if="section === 'calendar'" class="py-8">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center justify-between gap-3 overflow-x-auto pb-1">
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" class="icon-btn" @click="changeMonth(-1)">
                            <span class="sr-only">Mese precedente</span>
                            <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                        </button>
                        <div class="min-w-[190px] text-center text-lg font-bold text-gray-950">
                            {{ monthNames[calendarMonth] }} {{ calendarYear }}
                        </div>
                        <button type="button" class="icon-btn" @click="changeMonth(1)">
                            <span class="sr-only">Mese successivo</span>
                            <ChevronRight class="h-4 w-4" :stroke-width="1.7" />
                        </button>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <div class="w-80 shrink-0">
                            <AppSelect v-model="calendarType" :options="calendarTypeOptions" />
                        </div>
                        <div v-if="!isGuest" ref="calendarPeopleMenu" class="relative z-30 w-64 shrink-0">
                            <button
                                type="button"
                                :class="[
                                    'form-control mt-0 flex h-[38px] items-center justify-between gap-3 text-left',
                                    calendarPeopleMenuOpen ? 'border-indigo-300 ring-4 ring-indigo-500/10' : '',
                                ]"
                                :aria-expanded="calendarPeopleMenuOpen"
                                @click.stop="toggleCalendarPeopleMenu($event)"
                            >
                                <span class="truncate">{{ calendarPeopleFilterLabel }}</span>
                                <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', calendarPeopleMenuOpen ? 'rotate-180' : '']" :stroke-width="1.7" />
                            </button>
                            <Teleport to="body">
                                <div v-if="calendarPeopleMenuOpen" class="fixed inset-0 z-[7600] bg-transparent" data-calendar-people-menu @click.self="calendarPeopleMenuOpen = false">
                                    <div
                                        class="app-popover field-dropdown-menu fixed w-80 max-w-[calc(100vw-2rem)] p-3"
                                        :style="calendarPeopleMenuStyle"
                                        @click.stop
                                    >
                                        <div class="people-avatar-picker max-h-64">
                                            <button
                                                v-for="user in users"
                                                :key="`calendar-filter-user-${user.id}`"
                                                type="button"
                                                :class="personAvatarClass(calendarUserIds.includes(user.id))"
                                                :aria-pressed="calendarUserIds.includes(user.id)"
                                                :aria-label="`${calendarUserIds.includes(user.id) ? 'Rimuovi filtro' : 'Filtra per'} ${user.name || user.email}`"
                                                :title="user.name || user.email"
                                                @click="toggleCalendarUserFilter(user.id)"
                                            >
                                                <UserAvatar :user="user" size="md" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Teleport>
                        </div>
                        <label class="inline-flex h-[38px] shrink-0 items-center gap-2 rounded-[var(--radius-sm)] border border-white/70 bg-white/58 px-3 text-sm font-medium text-gray-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.62)] backdrop-blur-xl">
                            <input v-model="compactWeekend" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            Weekend
                        </label>
                    </div>
                </div>

                <div ref="calendarScrollArea" class="surface max-h-[calc(100vh-210px)] min-h-[620px] overflow-y-auto" @scroll.passive="handleCalendarScroll">
                    <section v-for="sectionMonth in calendarMonthSections" :key="sectionMonth.key" class="overflow-visible border-b border-gray-100 last:border-b-0" :data-current-calendar-month="sectionMonth.delta === 0">
                        <div class="border-b border-gray-100 bg-white px-4 py-3">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ sectionMonth.label }}</h3>
                        </div>
                        <div :class="['grid gap-px bg-gray-200/55', compactWeekend ? 'grid-cols-[repeat(5,minmax(0,1fr))_minmax(58px,0.34fr)_minmax(58px,0.34fr)]' : 'grid-cols-7']">
                            <div
                                v-for="(day, index) in dayNames"
                                :key="`${sectionMonth.key}-${day}`"
                                :class="['bg-white px-2 py-3 text-center text-xs font-bold uppercase tracking-wide text-gray-500', compactWeekend && index >= 5 ? 'text-[10px]' : '']"
                            >
                                {{ compactWeekend && index >= 5 ? day.slice(0, 1) : day }}
                            </div>
                        </div>

                        <div :class="['grid gap-px bg-gray-200/55', compactWeekend ? 'grid-cols-[repeat(5,minmax(0,1fr))_minmax(58px,0.34fr)_minmax(58px,0.34fr)]' : 'grid-cols-7']">
                            <div
                                v-for="cell in sectionMonth.cells"
                                :key="`${sectionMonth.key}-${cell.key}`"
                                :class="[
                                    'group flex min-h-[170px] flex-col bg-white p-2 transition',
                                    cell.empty ? 'bg-white/70' : '',
                                    cell.today ? 'ring-2 ring-inset ring-indigo-500/70' : '',
                                    calendarDropDate === cell.date ? 'bg-indigo-50/80' : '',
                                    calendarDraggedTask && !cell.empty ? 'outline outline-1 outline-transparent transition hover:outline-indigo-200' : '',
                                    compactWeekend && cell.weekend ? 'min-h-[170px] px-1' : '',
                                ]"
                                :style="calendarDayStyle(sectionMonth, cell)"
                                @dragover.prevent="!cell.empty && (calendarDropDate = cell.date)"
                                @dragleave="calendarDropDate === cell.date && (calendarDropDate = null)"
                                @drop.prevent="!cell.empty && moveCalendarTask(cell.date)"
                            >
                                <template v-if="!cell.empty">
                                    <div class="mb-2 flex items-center justify-between">
                                        <span :class="['text-sm font-semibold', cell.today ? 'text-indigo-600' : 'text-gray-500']">{{ cell.day }}</span>
                                        <div v-if="!isGuest && !(compactWeekend && cell.weekend)" class="relative" data-calendar-create-menu>
                                            <button
                                                type="button"
                                                class="rounded-xl bg-white/58 px-2 py-1 text-[11px] font-semibold text-gray-400 opacity-0 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)] transition hover:bg-indigo-50/90 hover:text-indigo-600 group-hover:opacity-100"
                                                @click.stop="openCalendarCreateMenu(cell.date)"
                                            >
                                                + crea
                                            </button>
                                            <div
                                                v-if="calendarCreateDate === cell.date"
                                                class="absolute right-0 top-7 z-20 w-44 overflow-hidden rounded-2xl border border-white bg-white p-1 shadow-[0_20px_55px_rgba(28,42,73,0.16)]"
                                                @click.stop
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 rounded-xl px-2 py-1.5 text-left text-sm font-medium text-gray-700 hover:bg-indigo-50/80"
                                                    @click.stop="openCalendarTaskCreate('project', cell.date)"
                                                >
                                                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                                    Task
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 rounded-xl px-2 py-1.5 text-left text-sm font-medium text-gray-700 hover:bg-amber-50/80"
                                                    @click.stop="openCalendarTaskCreate('ongoing', cell.date)"
                                                >
                                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                                    Continuativa
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 rounded-xl px-2 py-1.5 text-left text-sm font-medium text-gray-700 hover:bg-violet-50/80"
                                                    @click.stop="openCalendarTaskCreate('meeting', cell.date)"
                                                >
                                                    <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                                                    Meeting
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="compactWeekend && cell.weekend" class="flex flex-wrap justify-center gap-1 pt-1">
                                        <button
                                            v-for="task in cell.tasks"
                                            :key="task.id"
                                            type="button"
                                            class="h-3 w-3 rounded-full ring-2 ring-white/85 transition hover:scale-125 focus:outline-none focus:ring-indigo-300"
                                            :style="{ backgroundColor: priorityColor(task.priority) }"
                                            @click="openCalendarTask(task)"
                                        />
                                    </div>

                                    <div v-else class="flex flex-1 flex-col space-y-1.5">
                                        <div
                                            v-for="task in visibleCalendarTasks(cell)"
                                            :key="task.id"
                                            :class="[
                                                'group/task relative cursor-grab overflow-hidden border px-2 py-1.5 text-xs shadow-[inset_0_1px_0_rgba(255,255,255,0.68)] backdrop-blur-xl transition hover:border-indigo-300 hover:shadow-md active:cursor-grabbing',
                                                taskTypeClass(task.task_type),
                                                taskSpanClass(task),
                                                calendarTaskRenderClass(cell, task),
                                                task.status === 'done' ? 'opacity-55 hover:opacity-80' : '',
                                                calendarDraggedTask?.id === task.id ? 'opacity-50' : '',
                                            ]"
                                            :style="calendarTaskBarStyle(sectionMonth, cell, task)"
                                            role="link"
                                            tabindex="0"
                                            draggable="true"
                                            :title="task.title"
                                            @click="openCalendarTask(task)"
                                            @keydown.enter.prevent="openCalendarTask(task)"
                                            @dragstart="startCalendarDrag(task)"
                                            @dragend="endCalendarDrag"
                                        >
                                            <div class="flex items-start">
                                                <button
                                                    type="button"
                                                    :class="['status-action-button absolute left-2 top-2 h-3.5 w-3.5 shrink-0 -translate-x-5 rounded-md border opacity-0 shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] transition duration-200 group-hover/task:translate-x-0 group-hover/task:opacity-100 group-focus/task:translate-x-0 group-focus/task:opacity-100', task.status === 'done' ? 'border-emerald-500 bg-emerald-500' : blockedDependencyCount(task) ? 'cursor-not-allowed border-amber-200 bg-amber-50' : 'border-gray-300 bg-white/78 hover:border-indigo-400']"
                                                    :title="task.status === 'done' ? 'Riapri task' : (blockedDependencyCount(task) ? 'Task bloccata da dipendenze' : 'Completa task')"
                                                    :disabled="task.status !== 'done' && blockedDependencyCount(task) > 0"
                                                    @click.stop="toggleTaskDone(task)"
                                                >
                                                    <Check v-if="task.status === 'done'" class="h-full w-full p-[2px] text-white" :stroke-width="2.2" />
                                                    <span class="sr-only">{{ task.status === 'done' ? 'Riapri task' : 'Completa task' }}</span>
                                                </button>
                                                <div class="min-w-0 flex-1 transition-transform duration-200 group-hover/task:translate-x-5 group-focus/task:translate-x-5">
                                                    <div class="flex items-center gap-1">
                                                        <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: priorityColor(task.priority) }"></span>
                                                        <span v-if="(task.task_type || 'task') === 'meeting' && task.due_time" class="shrink-0 text-[10px] text-gray-500">{{ String(task.due_time).slice(0, 5) }}</span>
                                                        <span :class="['truncate font-medium', task.status === 'done' ? 'line-through opacity-60' : '']">{{ task.title }}</span>
                                                    </div>
                                                    <div class="mt-0.5 flex items-center justify-between gap-2 text-[10px] text-gray-500">
                                                        <span class="truncate">{{ task.client_name || task.project_name || task.service_name || taskTypeLabel(task.task_type) }}</span>
                                                        <span class="inline-flex shrink-0 items-center gap-1 font-semibold text-gray-500">
                                                            <span v-if="blockedDependencyCount(task)" class="inline-flex h-3 w-3 items-center justify-center text-rose-700" :title="`Task bloccata da ${blockedDependencyCount(task)} dipendenze`">
                                                                <GitBranch class="h-3 w-3" :stroke-width="2" />
                                                            </span>
                                                            <span v-if="task.subtask_count" class="inline-flex items-center gap-1">
                                                                <span>{{ task.subtask_count }}</span>
                                                                <svg class="h-3 w-3 fill-current" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                    <path :d="subtaskIconPath" />
                                                                </svg>
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            v-if="hiddenCalendarTaskCount(cell) && !isCalendarDayExpanded(cell.date)"
                                            type="button"
                                            class="mt-auto self-start rounded-lg px-2 py-1 text-left text-[11px] font-semibold text-gray-500 transition hover:bg-indigo-50 hover:text-indigo-600"
                                            @click.stop="expandCalendarDay(cell.date)"
                                        >
                                            altri {{ hiddenCalendarTaskCount(cell) }}
                                        </button>
                                        <button
                                            v-else-if="hiddenCalendarTaskCount(cell) && isCalendarDayExpanded(cell.date)"
                                            type="button"
                                            class="mt-auto self-start rounded-lg px-2 py-1 text-left text-[11px] font-semibold text-gray-500 transition hover:bg-indigo-50 hover:text-indigo-600"
                                            @click.stop="collapseCalendarDay(cell.date)"
                                        >
                                            mostra di meno
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-if="!rows.length" class="mt-4 rounded-md border border-dashed border-gray-300 bg-white px-5 py-8 text-center text-sm text-gray-500">
                    Nessuna attivita con scadenza. Usa la pagina Task per creare la prima attivita e vederla comparire nel calendario.
                </div>
                <div v-else class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-600"></span>Urgente</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-orange-500"></span>Alta</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Media</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Bassa</span>
                </div>
            </div>

            <Transition name="calendar-task-drawer">
                <div v-if="calendarTaskPanelOpen" class="fixed inset-0 z-[5200] bg-gray-950/20 backdrop-blur-[2px]" @click.self="closeCalendarTaskPanel">
                <aside class="calendar-task-drawer-panel absolute right-0 top-0 flex h-full w-full max-w-3xl flex-col border-l border-white/80 bg-white shadow-2xl sm:w-[62vw]">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                        <div class="min-w-0">
                            <button
                                v-if="calendarTaskParentStack.length"
                                type="button"
                                class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 transition hover:text-indigo-600"
                                @click="returnToCalendarParentTask"
                            >
                                <ChevronLeft class="h-3.5 w-3.5" :stroke-width="1.8" />
                                Torna alla task genitore
                            </button>
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: priorityColor(calendarTaskForm.priority) }"></span>
                                <h3 class="truncate text-lg font-bold text-gray-950">{{ calendarTaskPanelMode === 'create' ? 'Nuova task' : 'Modifica task' }}</h3>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ calendarTaskForm.id ? 'Le modifiche si salvano automaticamente.' : 'Inserisci un titolo: la task verrà creata automaticamente.' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span v-if="calendarTaskAutosaveState !== 'idle'" :class="['rounded-full px-2.5 py-1 text-xs font-semibold', calendarTaskAutosaveState === 'error' ? 'bg-red-50 text-red-700' : calendarTaskAutosaveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700']">
                                <template v-if="calendarTaskAutosaveState === 'queued'">In attesa</template>
                                <template v-else-if="calendarTaskAutosaveState === 'saving'">Salvataggio</template>
                                <template v-else-if="calendarTaskAutosaveState === 'saved'">Salvato</template>
                                <template v-else>{{ calendarTaskAutosaveError || 'Errore' }}</template>
                            </span>
                            <button
                                v-if="calendarTaskForm.id"
                                type="button"
                                :class="['btn btn-outline status-action-button', calendarTaskStatusPulse ? 'status-action-pulse' : '', calendarTaskForm.status !== 'done' && blockedDependencyCount(calendarTaskPanel) ? 'cursor-not-allowed opacity-60' : '']"
                                :disabled="calendarTaskForm.status !== 'done' && blockedDependencyCount(calendarTaskPanel) > 0"
                                @click="toggleCalendarTaskComplete"
                            >
                                <Check class="h-4 w-4" :stroke-width="1.7" />
                                {{ calendarTaskForm.status === 'done' ? 'Riapri' : 'Completa' }}
                            </button>
                            <button
                                v-if="calendarTaskForm.id"
                                type="button"
                                class="icon-btn h-10 w-10"
                                data-calendar-task-actions-menu
                                title="Azioni task"
                                @click.stop="toggleCalendarTaskActionMenu($event)"
                            >
                                <MoreHorizontal class="h-5 w-5" :stroke-width="1.8" />
                            </button>
                            <Teleport to="body">
                                <div v-if="calendarTaskActionMenuOpen" class="fixed inset-0 z-[7600] bg-transparent" data-calendar-task-actions-menu @click.self="calendarTaskActionMenuOpen = false">
                                    <div class="app-popover field-dropdown-menu fixed w-56 p-2" :style="calendarTaskActionMenuStyle" @click.stop>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="copyCalendarTaskLink">
                                            <Copy class="h-4 w-4" :stroke-width="1.7" />
                                            Copia link
                                        </button>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="duplicateCalendarTask">
                                            <Copy class="h-4 w-4" :stroke-width="1.7" />
                                            Duplica
                                        </button>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printCalendarTask">
                                            <Printer class="h-4 w-4" :stroke-width="1.7" />
                                            Stampa
                                        </button>
                                        <button v-if="canDeleteRow(calendarTaskPanel)" type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50" @click="removeCalendarTask">
                                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            Elimina
                                        </button>
                                    </div>
                                </div>
                            </Teleport>
                            <button type="button" class="icon-btn" @click="closeCalendarTaskPanel">
                                <X class="h-4 w-4" :stroke-width="1.8" />
                            </button>
                        </div>
                    </div>

                    <div ref="calendarTaskDrawerBody" class="flex-1 overflow-y-auto px-5 py-5">
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Titolo</label>
                                <input v-model="calendarTaskForm.title" class="form-control" required @input="saveCalendarTaskInline()" />
                                <div v-if="calendarTaskForm.errors.title" class="mt-1 text-sm text-red-600">{{ calendarTaskForm.errors.title }}</div>
                            </div>

                            <div class="grid gap-2 sm:grid-cols-3">
                                <button
                                    v-for="option in taskEditTypeOptions"
                                    :key="option.value"
                                    type="button"
                                    :class="[
                                        'rounded-[var(--radius-sm)] border px-3 py-2 text-left text-sm font-semibold transition',
                                        calendarTaskTypeButtonClass(option.value),
                                    ]"
                                    @click="setCalendarTaskType(option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stato</label>
                                    <AppSelect v-model="calendarTaskForm.status" :options="taskStatusOptions.filter((option) => option.value !== 'all')" @change="setCalendarTaskStatusFromSelect" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Priorità</label>
                                    <AppSelect v-model="calendarTaskForm.priority" :options="taskPriorityOptions.filter((option) => option.value !== 'all')" @change="saveCalendarTaskInline(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Inizio</label>
                                    <AppDateInput v-model="calendarTaskForm.start_date" @change="saveCalendarTaskInline(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                                    <AppDateInput v-model="calendarTaskForm.due_date" @change="saveCalendarTaskInline(0)" />
                                </div>
                                <div v-if="calendarTaskForm.task_type === 'meeting'">
                                    <label class="block text-sm font-medium text-gray-700">Ora</label>
                                    <AppTimeInput v-model="calendarTaskForm.due_time" @change="saveCalendarTaskInline(0)" />
                                </div>
                                <div v-if="calendarTaskForm.task_type === 'meeting'">
                                    <label class="block text-sm font-medium text-gray-700">Luogo / link</label>
                                    <input v-model="calendarTaskForm.location" class="form-control" placeholder="Sala riunioni o link meeting" @input="saveCalendarTaskInline()" />
                                </div>
                                <div v-if="['project', 'task'].includes(calendarTaskForm.task_type)">
                                    <label class="block text-sm font-medium text-gray-700">Progetto</label>
                                    <AppSelect v-model="calendarTaskForm.project_id" :options="namedOptions(projects, { value: '', label: 'Nessun progetto' })" searchable @change="saveCalendarTaskInline(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cliente</label>
                                    <AppSelect v-model="calendarTaskForm.client_id" :options="namedOptions(clients, { value: '', label: 'Nessun cliente' })" searchable @change="saveCalendarTaskInline(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Servizio</label>
                                    <AppSelect v-model="calendarTaskForm.service_id" :options="namedOptions(services, { value: '', label: 'Nessun servizio' })" searchable @change="saveCalendarTaskInline(0)" />
                                </div>
                            </div>

                            <div v-if="calendarTaskForm.task_type !== 'meeting'" class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/80 p-4">
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input v-model="calendarTaskForm.recurring_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @change="saveCalendarTaskInline(0)" />
                                    Ricorrente
                                </label>
                                <div v-if="calendarTaskForm.recurring_enabled" class="mt-3 grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Ogni</label>
                                        <input v-model="calendarTaskForm.recurring_interval_value" type="number" min="1" class="form-control" @input="saveCalendarTaskInline()" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Unità</label>
                                        <AppSelect v-model="calendarTaskForm.recurring_interval_unit" :options="recurrenceUnitOptions" @change="saveCalendarTaskInline(0)" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Modalità</label>
                                        <AppSelect v-model="calendarTaskForm.recurring_mode" :options="recurrenceModeOptions" @change="saveCalendarTaskInline(0)" />
                                    </div>
                                    <div v-if="calendarTaskForm.recurring_interval_unit === 'week'">
                                        <label class="block text-xs font-medium text-gray-500">Giorno settimana</label>
                                        <input v-model="calendarTaskForm.recurring_weekday" type="number" min="1" max="7" class="form-control" @input="saveCalendarTaskInline()" />
                                    </div>
                                    <div v-if="calendarTaskForm.recurring_interval_unit === 'month' && calendarTaskForm.recurring_mode === 'fixed'">
                                        <label class="block text-xs font-medium text-gray-500">Giorno mese</label>
                                        <input v-model="calendarTaskForm.recurring_month_day" type="number" min="1" max="31" class="form-control" @input="saveCalendarTaskInline()" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ calendarTaskForm.task_type === 'meeting' ? 'Partecipanti' : 'Assegnatari' }}</div>
                                    <div class="people-avatar-picker max-h-36">
                                        <button
                                            v-for="user in users"
                                            :key="`calendar-assignee-${user.id}`"
                                            type="button"
                                            :class="personAvatarClass((calendarTaskForm.assignee_ids || []).includes(user.id))"
                                            :aria-pressed="(calendarTaskForm.assignee_ids || []).includes(user.id)"
                                            :title="user.name || user.email"
                                            @click="toggleCalendarTaskPerson('assignee_ids', user.id)"
                                        >
                                            <UserAvatar :user="user" size="md" />
                                        </button>
                                        <p v-if="!users?.length" class="text-xs text-gray-500">Nessun utente disponibile.</p>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-400">{{ calendarTaskPeopleLabel('assignee_ids') }}</p>
                                </div>
                                <div>
                                    <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Follower</div>
                                    <div class="people-avatar-picker max-h-36">
                                        <button
                                            v-for="user in users"
                                            :key="`calendar-follower-${user.id}`"
                                            type="button"
                                            :class="personAvatarClass((calendarTaskForm.follower_ids || []).includes(user.id))"
                                            :aria-pressed="(calendarTaskForm.follower_ids || []).includes(user.id)"
                                            :title="user.name || user.email"
                                            @click="toggleCalendarTaskPerson('follower_ids', user.id)"
                                        >
                                            <UserAvatar :user="user" size="md" />
                                        </button>
                                        <p v-if="!users?.length" class="text-xs text-gray-500">Nessun utente disponibile.</p>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-400">{{ calendarTaskPeopleLabel('follower_ids') }}</p>
                                </div>
                            </div>

                            <section v-if="calendarTaskForm.id" class="content-card rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dipendenze</h4>
                                        <p class="mt-1 text-xs text-gray-500">Questa task resta bloccata finché le dipendenze non sono completate.</p>
                                    </div>
                                    <span
                                        v-if="blockedDependencyCount(calendarTaskPanel)"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-100"
                                        title="Task bloccata"
                                    >
                                        <GitBranch class="h-4 w-4" :stroke-width="1.8" />
                                    </span>
                                </div>
                                <div class="grid gap-2 md:grid-cols-[170px_minmax(0,1fr)]">
                                    <AppSelect
                                        v-model="calendarDependencyDirection"
                                        :options="taskDependencyDirectionOptions"
                                        placeholder="Tipo relazione"
                                    />
                                    <AppSelect
                                        v-model="calendarDependencyToAdd"
                                        :options="taskDependencySelectOptions(calendarTaskForm.id)"
                                        :placeholder="calendarDependencyDirection === 'blocks' ? 'Scegli task bloccata' : 'Scegli task bloccante'"
                                        searchable
                                        @change="addCalendarTaskDependency"
                                    />
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="dependency in selectedCalendarDependencies()"
                                        :key="`calendar-dependency-${dependency.id}`"
                                        :class="['inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold', dependency.status === 'done' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']"
                                    >
                                        <span class="truncate">{{ dependency.title }}</span>
                                        <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi dipendenza" @click="removeCalendarTaskDependency(dependency.id)">
                                            <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                        </button>
                                    </span>
                                    <span v-if="!selectedCalendarDependencies().length" class="text-xs text-gray-500">Nessuna dipendenza.</span>
                                </div>
                                <div v-if="selectedCalendarDependents().length" class="mt-3 border-t border-gray-100 pt-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Blocca</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="dependent in selectedCalendarDependents()"
                                            :key="`calendar-dependent-${dependent.id}`"
                                            :class="['inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold', dependent.status === 'done' ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-600']"
                                        >
                                            <span class="truncate">{{ dependent.title }}</span>
                                            <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi relazione" @click="removeCalendarTaskDependent(dependent.id)">
                                                <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                            </button>
                                        </span>
                                    </div>
                                </div>
                                <div v-if="calendarTaskForm.errors.dependencies || calendarTaskForm.errors.status" class="mt-2 text-sm text-red-600">
                                    {{ calendarTaskForm.errors.dependencies || calendarTaskForm.errors.status }}
                                </div>
                            </section>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                                <div class="mt-1 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                    <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                        <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @click="runCalendarTaskEditorCommand('bold')">
                                            <Bold class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @click="runCalendarTaskEditorCommand('italic')">
                                            <Italic class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @click="runCalendarTaskEditorCommand('underline')">
                                            <Underline class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Titolo" @click="runCalendarTaskEditorCommand('formatBlock', 'h3')">
                                            <Heading3 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @click="runCalendarTaskEditorCommand('insertUnorderedList')">
                                            <List class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @click="runCalendarTaskEditorCommand('insertOrderedList')">
                                            <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Citazione" @click="runCalendarTaskEditorCommand('formatBlock', 'blockquote')">
                                            <Quote class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Link" @click="addCalendarTaskEditorLink">
                                            <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                    </div>
                                    <div
                                        ref="calendarTaskDescriptionEditor"
                                        class="min-h-[150px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                        contenteditable="true"
                                        data-placeholder="Aggiungi una descrizione..."
                                        @input="updateCalendarTaskDescriptionFromEditor"
                                        @blur="updateCalendarTaskDescriptionFromEditor"
                                    ></div>
                                </div>
                            </div>

                            <div v-if="!calendarTaskForm.id" class="content-card rounded-[var(--radius-sm)] border border-dashed border-indigo-200 bg-indigo-50/60 p-4 text-sm text-indigo-700">
                                Completa almeno il titolo per creare la task. Dopo la creazione potrai aggiungere sottoattività e commenti.
                            </div>

                            <section v-if="calendarTaskForm.id && !isCalendarSubtaskPanel()" class="content-card rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-4 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sottoattività</h4>
                                    <span class="text-xs text-gray-400">{{ calendarPanelSubtasks().length }} elementi</span>
                                </div>
                                <form class="mb-4 grid items-center gap-x-2 gap-y-2 md:grid-cols-[minmax(0,1fr)_48px_72px_auto]" data-calendar-subtask-create-assignees @submit.prevent="addCalendarSubtask">
                                    <input v-model="calendarSubtaskForm.title" class="subtask-line-control font-medium" placeholder="Nuova sottoattività..." required />
                                    <div class="relative" data-calendar-subtask-create-assignees>
                                        <button type="button" class="subtask-line-people justify-end" @click.stop="toggleCalendarCreateSubtaskAssigneeMenu($event)">
                                            <span v-if="calendarCreateSubtaskAssignees().length" class="flex min-w-0 items-center -space-x-2">
                                                <UserAvatar v-for="user in calendarCreateSubtaskAssignees().slice(0, 4)" :key="`calendar-new-subtask-assignee-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                                <span v-if="calendarCreateSubtaskAssignees().length > 4" class="ml-3 text-xs font-semibold text-gray-500">+{{ calendarCreateSubtaskAssignees().length - 4 }}</span>
                                            </span>
                                            <span v-else class="subtask-line-token">
                                                <UserRound class="h-4 w-4" :stroke-width="1.7" />
                                            </span>
                                        </button>
                                        <Teleport to="body">
                                            <div v-if="calendarSubtaskCreateAssigneeMenuOpen" class="pointer-events-none fixed inset-0 z-[7600] bg-transparent" data-calendar-subtask-create-assignees>
                                                <div class="app-popover field-dropdown-menu pointer-events-auto fixed w-72 p-3" :style="calendarSubtaskCreateAssigneeMenuStyle" @click.stop>
                                                    <div class="people-avatar-picker max-h-56">
                                                        <button
                                                            v-for="user in users"
                                                            :key="`calendar-new-subtask-person-${user.id}`"
                                                            type="button"
                                                            :class="personAvatarClass(calendarCreateSubtaskAssigneeIds.includes(user.id))"
                                                            :aria-pressed="calendarCreateSubtaskAssigneeIds.includes(user.id)"
                                                            :aria-label="`${calendarCreateSubtaskAssigneeIds.includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                            @click="toggleCalendarCreateSubtaskAssignee(user.id)"
                                                        >
                                                            <UserAvatar :user="user" size="md" />
                                                        </button>
                                                    </div>
                                                    <p v-if="!users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                                                </div>
                                            </div>
                                        </Teleport>
                                    </div>
                                    <div class="relative flex items-center justify-end">
                                        <AppDateInput
                                            v-model="calendarSubtaskForm.due_date"
                                            variant="token"
                                            :label="shortDateIt(calendarSubtaskForm.due_date)"
                                            placeholder="Scadenza"
                                        />
                                    </div>
                                    <button type="submit" class="btn btn-primary justify-center px-4" :disabled="calendarSubtaskForm.processing">
                                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </form>
                                <div class="space-y-2">
                                    <div
                                        v-for="subtask in calendarPanelSubtasks()"
                                        :key="subtask.id"
                                        draggable="true"
                                        :class="[
                                            'subtask-line md:grid-cols-[68px_minmax(0,1fr)_96px_72px_auto]',
                                            calendarSubtaskAssigneeMenuOpen === subtask.id ? 'z-[6600]' : 'z-0',
                                            calendarDraggedSubtaskId === subtask.id ? 'is-dragging' : '',
                                            calendarSubtaskDropTarget === subtask.id && calendarSubtaskDropPlacement === 'before' ? 'drop-before' : '',
                                            calendarSubtaskDropTarget === subtask.id && calendarSubtaskDropPlacement === 'after' ? 'drop-after' : '',
                                        ]"
                                        @dragstart="startCalendarSubtaskDrag(subtask)"
                                        @dragover.prevent="dragOverCalendarSubtask(subtask, $event)"
                                        @drop.prevent="dropCalendarSubtask(subtask)"
                                        @dragend="endCalendarSubtaskDrag"
                                    >
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="inline-flex h-9 w-6 cursor-grab items-center justify-center text-gray-300 transition hover:text-gray-500 active:cursor-grabbing" title="Sposta sottoattività">
                                                <GripVertical class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button
                                                type="button"
                                                :class="['icon-btn status-action-button h-9 w-9', calendarSubtaskStatusPulse === subtask.id ? 'status-action-pulse' : '']"
                                                :title="(calendarSubtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'Riapri sottoattività' : 'Completa sottoattività'"
                                                @click="setCalendarSubtaskStatus(subtask, (calendarSubtaskDrafts[subtask.id]?.status || subtask.status) !== 'done')"
                                            >
                                                <RotateCcw v-if="(calendarSubtaskDrafts[subtask.id]?.status || subtask.status) === 'done'" class="h-4 w-4" :stroke-width="1.7" />
                                                <Check v-else class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                        </div>
                                        <div class="min-w-0">
                                            <input
                                                v-if="calendarSubtaskDrafts[subtask.id]"
                                                v-model="calendarSubtaskDrafts[subtask.id].title"
                                                :class="['subtask-line-control font-medium', (calendarSubtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'text-gray-400 line-through' : '']"
                                                placeholder="Titolo sottoattività"
                                                @input="saveCalendarSubtaskInline(subtask)"
                                            />
                                        </div>
                                        <div v-if="calendarSubtaskDrafts[subtask.id]" class="relative" :data-calendar-subtask-assignees="subtask.id">
                                            <button type="button" class="subtask-line-people justify-end" @click.stop="toggleCalendarSubtaskAssigneeMenu(subtask.id, $event)">
                                                <span v-if="calendarSubtaskAssignees(subtask.id).length" class="flex min-w-0 items-center -space-x-2">
                                                    <UserAvatar v-for="user in calendarSubtaskAssignees(subtask.id).slice(0, 4)" :key="`calendar-subtask-assignee-${subtask.id}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                                    <span v-if="calendarSubtaskAssignees(subtask.id).length > 4" class="ml-3 text-xs font-semibold text-gray-500">+{{ calendarSubtaskAssignees(subtask.id).length - 4 }}</span>
                                                </span>
                                                <span v-else class="subtask-line-token">
                                                    <UserRound class="h-4 w-4" :stroke-width="1.7" />
                                                </span>
                                            </button>
                                            <Teleport to="body">
                                                <div
                                                    v-if="calendarSubtaskAssigneeMenuOpen === subtask.id"
                                                    class="fixed inset-0 z-[7600] bg-transparent"
                                                    :data-calendar-subtask-assignees="subtask.id"
                                                    @click.self="calendarSubtaskAssigneeMenuOpen = null"
                                                >
                                                    <div class="app-popover field-dropdown-menu fixed w-72 p-3" :style="calendarSubtaskAssigneeMenuStyle" @click.stop>
                                                        <div class="people-avatar-picker max-h-56">
                                                            <button
                                                                v-for="user in users"
                                                                :key="`calendar-subtask-person-${subtask.id}-${user.id}`"
                                                                type="button"
                                                                :class="personAvatarClass((calendarSubtaskDrafts[subtask.id].assignee_ids || []).includes(user.id))"
                                                                :aria-pressed="(calendarSubtaskDrafts[subtask.id].assignee_ids || []).includes(user.id)"
                                                                :aria-label="`${(calendarSubtaskDrafts[subtask.id].assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                                @click="toggleCalendarSubtaskAssignee(subtask, user.id)"
                                                            >
                                                                <UserAvatar :user="user" size="md" />
                                                            </button>
                                                        </div>
                                                        <p v-if="!users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                                                    </div>
                                                </div>
                                            </Teleport>
                                        </div>
                                        <div v-if="calendarSubtaskDrafts[subtask.id]" class="relative flex items-center justify-end">
                                            <AppDateInput
                                                v-model="calendarSubtaskDrafts[subtask.id].due_date"
                                                variant="token"
                                                :label="shortDateIt(calendarSubtaskDrafts[subtask.id].due_date)"
                                                placeholder="Scadenza"
                                                @change="saveCalendarSubtaskInline(subtask, 0)"
                                            />
                                        </div>
                                        <div class="subtask-actions">
                                            <button type="button" class="icon-btn h-9 w-9" title="Apri sottoattività" @click="openCalendarSubtask(subtask)">
                                                <ExternalLink class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button v-if="canDeleteRow(subtask)" type="button" class="icon-btn h-9 w-9 text-red-600 hover:bg-red-50" title="Elimina sottoattività" @click="removeCalendarSubtask(subtask)">
                                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="!calendarPanelSubtasks().length" class="text-sm text-gray-500">Nessuna sottoattività.</p>
                                </div>
                            </section>

                            <section v-if="calendarTaskForm.id" class="content-card rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-4 flex items-center gap-4 border-b border-gray-100 pb-3">
                                    <button
                                        type="button"
                                        :class="['text-sm font-semibold uppercase tracking-wide transition', calendarTaskFeedTab === 'comments' ? 'text-gray-900' : 'text-gray-400 hover:text-gray-700']"
                                        @click="calendarTaskFeedTab = 'comments'"
                                    >
                                        Commenti
                                    </button>
                                    <button
                                        type="button"
                                        :class="['text-sm font-semibold uppercase tracking-wide transition', calendarTaskFeedTab === 'activity' ? 'text-gray-900' : 'text-gray-400 hover:text-gray-700']"
                                        @click="calendarTaskFeedTab = 'activity'"
                                    >
                                        Attività
                                    </button>
                                </div>
                                <div v-if="calendarTaskFeedTab === 'comments'">
                                <form class="mb-5 grid gap-3 md:grid-cols-[1fr_auto]" @submit.prevent="addCalendarComment">
                                    <div class="overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                        <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                            <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runCalendarCommentEditorCommand('new', 'bold')">
                                                <Bold class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runCalendarCommentEditorCommand('new', 'italic')">
                                                <Italic class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runCalendarCommentEditorCommand('new', 'underline')">
                                                <Underline class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                            <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runCalendarCommentEditorCommand('new', 'insertUnorderedList')">
                                                <List class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runCalendarCommentEditorCommand('new', 'insertOrderedList')">
                                                <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addCalendarCommentEditorLink('new')">
                                                <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                        </div>
                                        <div
                                            class="min-h-[92px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                            contenteditable="true"
                                            data-calendar-comment-editor="new"
                                            data-placeholder="Scrivi un commento..."
                                            @input="updateCalendarCommentFromEditor('new')"
                                            @blur="updateCalendarCommentFromEditor('new')"
                                        ></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary self-start px-4" :disabled="calendarCommentForm.processing">Invia</button>
                                </form>
                                <div class="space-y-3">
                                    <div v-for="comment in visibleCalendarPanelComments()" :key="comment.id" class="rounded-[var(--radius-sm)] border border-gray-100 bg-white px-3 py-3 text-sm transition hover:border-indigo-100 hover:shadow-sm">
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <div class="text-xs font-medium text-gray-500">{{ comment.user_name || 'Utente' }} · {{ dateTimeIt(comment.created_at) }}</div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="text-xs font-semibold text-indigo-600 transition hover:text-indigo-500"
                                                    @click="editCalendarComment(comment)"
                                                >
                                                    Modifica
                                                </button>
                                                <button
                                                    type="button"
                                                    class="text-xs font-semibold text-red-600 transition hover:text-red-500"
                                                    @click="removeCalendarComment(comment)"
                                                >
                                                    Elimina
                                                </button>
                                            </div>
                                        </div>
                                        <div v-if="calendarEditingCommentId !== comment.id" class="min-h-10 rounded-[var(--radius-sm)] bg-gray-50/70 px-3 py-2 text-sm leading-6 text-gray-700" v-html="comment.content"></div>
                                        <div v-else-if="calendarCommentDrafts[comment.id]" class="overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                            <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                                <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runCalendarCommentEditorCommand(comment.id, 'bold')">
                                                    <Bold class="h-4 w-4" :stroke-width="1.7" />
                                                </button>
                                                <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runCalendarCommentEditorCommand(comment.id, 'italic')">
                                                    <Italic class="h-4 w-4" :stroke-width="1.7" />
                                                </button>
                                                <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runCalendarCommentEditorCommand(comment.id, 'underline')">
                                                    <Underline class="h-4 w-4" :stroke-width="1.7" />
                                                </button>
                                                <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                                <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runCalendarCommentEditorCommand(comment.id, 'insertUnorderedList')">
                                                    <List class="h-4 w-4" :stroke-width="1.7" />
                                                </button>
                                                <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runCalendarCommentEditorCommand(comment.id, 'insertOrderedList')">
                                                    <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                                </button>
                                                <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addCalendarCommentEditorLink(comment.id)">
                                                    <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                                </button>
                                            </div>
                                            <div
                                                class="min-h-[110px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                                contenteditable="true"
                                                :data-calendar-comment-editor="comment.id"
                                                data-placeholder="Commento..."
                                                @input="saveCalendarCommentInline(comment)"
                                                @blur="stopEditingCalendarComment(comment)"
                                            ></div>
                                        </div>
                                    </div>
                                    <button
                                        v-if="!calendarShowAllComments && hiddenCalendarCommentsCount()"
                                        type="button"
                                        class="text-sm font-semibold text-indigo-600 transition hover:text-indigo-500"
                                        @click="calendarShowAllComments = true"
                                    >
                                        Mostra i {{ hiddenCalendarCommentsCount() }} commenti precedenti
                                    </button>
                                    <p v-if="!calendarPanelComments().length" class="text-sm text-gray-500">Nessun commento.</p>
                                </div>
                                </div>
                                <div v-else class="space-y-3">
                                    <div v-for="activity in visibleCalendarPanelActivity()" :key="activity.id" class="rounded-[var(--radius-sm)] border border-gray-100 bg-white px-3 py-3 text-sm transition hover:border-indigo-100 hover:shadow-sm">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-indigo-300"></span>
                                            <div class="min-w-0">
                                                <div class="font-medium leading-6 text-gray-700">{{ activityText(activity) }}</div>
                                                <div class="text-xs text-gray-400">{{ dateTimeIt(activity.created_at) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        v-if="!calendarShowAllActivity && hiddenCalendarActivityCount()"
                                        type="button"
                                        class="text-sm font-semibold text-indigo-600 transition hover:text-indigo-500"
                                        @click="calendarShowAllActivity = true"
                                    >
                                        Mostra i {{ hiddenCalendarActivityCount() }} aggiornamenti precedenti
                                    </button>
                                    <p v-if="!calendarPanelActivity().length" class="text-sm text-gray-500">Nessuna attività registrata.</p>
                                </div>
                            </section>
                        </div>
                    </div>
                </aside>
                </div>
            </Transition>
        </div>

        <div v-else-if="section === 'projects'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-3 lg:grid-cols-[minmax(220px,0.82fr)_165px_215px_auto_auto_auto]">
                    <input v-model="projectSearch" class="form-control mt-0" placeholder="Cerca per progetto, cliente o descrizione..." />
                    <AppSelect v-model="projectStatus" :options="projectStatusOptions" />
                    <div ref="projectPeopleMenu" class="relative z-30">
                        <button
                            type="button"
                            :class="[
                                'form-control mt-0 flex min-h-10 items-center justify-between gap-3 px-3 py-2 text-left',
                                projectPeopleMenuOpen ? 'border-indigo-300 ring-4 ring-indigo-500/10' : '',
                            ]"
                            :aria-expanded="projectPeopleMenuOpen"
                            @click.stop="toggleProjectPeopleMenu"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <Users class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
                                <span class="truncate">{{ projectPeopleFilterLabel }}</span>
                            </span>
                            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', projectPeopleMenuOpen ? 'rotate-180' : '']" :stroke-width="1.7" />
                        </button>

                        <div
                            v-if="projectPeopleMenuOpen"
                            class="app-popover field-dropdown-menu absolute left-0 right-0 top-full z-[5200] mt-2 p-4"
                            @click.stop
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Filtra per persone</span>
                                <button
                                    v-if="projectUserIds.length"
                                    type="button"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                                    @click="projectUserIds = []"
                                >
                                    Pulisci
                                </button>
                            </div>
                            <div class="people-avatar-picker max-h-52">
                                <button
                                    v-for="user in users"
                                    :key="`project-filter-${user.id}`"
                                    type="button"
                                    :class="personAvatarClass(projectUserIds.includes(user.id))"
                                    :aria-pressed="projectUserIds.includes(user.id)"
                                    :aria-label="`${projectUserIds.includes(user.id) ? 'Rimuovi filtro' : 'Filtra per'} ${user.name || user.email}`"
                                    :title="user.name || user.email"
                                    @click="toggleProjectUserFilter(user.id)"
                                >
                                    <UserAvatar :user="user" size="md" />
                                </button>
                                <span v-if="!users?.length" class="text-xs text-gray-500">Nessun utente</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline" @click="resetProjectFilters"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                    <Link v-if="canCreate" :href="route('project-templates.index')" class="btn btn-outline">
                        <CopyPlus class="h-4 w-4" :stroke-width="1.7" />
                        Modelli
                    </Link>
                    <button v-if="canCreate" type="button" class="btn btn-primary" @click="openCreate()">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo Progetto
                    </button>
                </div>

                <section class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="project in projectRows"
                        :key="project.id"
                        class="content-card project-preview-card group relative border shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                        :style="projectCardStyle(project)"
                    >
                        <Link :href="route('projects.show', project.id)" class="flex h-full min-h-[190px] flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 pr-12">
                                    <h3 class="truncate text-base font-semibold">{{ project.name }}</h3>
                                </div>
                            </div>

                            <p v-if="plainText(project.description)" class="mt-3 line-clamp-2 text-sm" :style="projectCardMutedStyle(project)">
                                {{ plainText(project.description) }}
                            </p>

                            <div v-if="projectUsers(project).length" class="mt-4 flex -space-x-2">
                                <UserAvatar
                                    v-for="user in projectUsers(project).slice(0, 4)"
                                    :key="`project-user-${project.id}-${user.id}`"
                                    :user="user"
                                    size="sm"
                                    class="ring-2 ring-white/60"
                                    :title="user.name || user.email"
                                />
                            </div>

                            <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                                <span v-if="project.client_name" class="project-preview-chip min-w-0 truncate border px-2 py-0.5 text-xs" :style="projectCardChipStyle(project)">{{ project.client_name }}</span>
                                <span v-else class="text-xs" :style="projectCardMutedStyle(project)">Nessun cliente</span>
                                <span class="project-preview-chip ml-auto shrink-0 border px-2 py-0.5 text-right text-xs font-medium" :style="projectCardChipStyle(project)">
                                    {{ displayValue(project.status) }}
                                </span>
                            </div>
                        </Link>

                        <button
                            v-if="canDeleteRow(project)"
                            type="button"
                            class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-100 bg-white/90 text-red-600 shadow-sm transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-200"
                            :aria-label="`Elimina ${project.name}`"
                            @click.stop.prevent="remove(project)"
                        >
                            <Trash2 class="h-4 w-4" :stroke-width="1.8" />
                        </button>
                    </article>

                    <div v-if="!projectRows.length" class="content-card rounded-md border border-dashed border-gray-300 bg-white px-5 py-12 text-center text-sm text-gray-500 sm:col-span-2 lg:col-span-4">
                        Nessun progetto trovato.
                    </div>
                </section>
            </div>
        </div>

        <div v-else-if="section === 'absences'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="app-card overflow-hidden">
                        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Oggi</p>
                                <h3 class="mt-1 text-xl font-semibold text-gray-900">Assenti</h3>
                                <p class="mt-1 text-sm font-medium text-gray-500">{{ dateIt(absenceTodayIso) }}</p>
                            </div>
                            <span class="inline-flex h-10 min-w-10 items-center justify-center rounded-full bg-indigo-50 px-3 text-sm font-semibold text-indigo-700 ring-1 ring-indigo-100">
                                {{ absenceTodayRows.length }}
                            </span>
                        </div>
                        <div v-if="absenceTodayRows.length" class="grid gap-3 sm:grid-cols-2">
                            <Link
                                v-for="row in absenceTodayRows"
                                :key="row.id"
                                :href="route('absences.show', row.id)"
                                class="group flex min-h-[92px] items-center gap-3 rounded-[var(--radius)] border border-gray-100 bg-white/72 p-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-100 hover:bg-white hover:shadow-[0_16px_38px_rgba(15,23,42,0.09)]"
                            >
                                <UserAvatar :user="absenceUser(row)" size="md" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-gray-900">{{ row.user_name || 'Utente' }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100">
                                            {{ absenceTypeLabels[row.type] || displayValue(row.type) }}
                                        </span>
                                        <span v-if="absenceExtraInfo(row)" class="truncate text-xs font-semibold text-gray-500">
                                            {{ absenceExtraInfo(row) }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                        <p v-else class="rounded-[var(--radius)] border border-dashed border-gray-200 bg-white/60 px-4 py-6 text-sm text-gray-500">
                            Nessuna persona assente oggi.
                        </p>
                    </section>

                    <section class="app-card overflow-hidden">
                        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-500">Oggi</p>
                                <h3 class="mt-1 text-xl font-semibold text-gray-900">Smart working</h3>
                                <p class="mt-1 text-sm font-medium text-gray-500">{{ dateIt(absenceTodayIso) }}</p>
                            </div>
                            <span class="inline-flex h-10 min-w-10 items-center justify-center rounded-full bg-sky-50 px-3 text-sm font-semibold text-sky-700 ring-1 ring-sky-100">
                                {{ smartworkingTodayRows.length }}
                            </span>
                        </div>
                        <div v-if="smartworkingTodayRows.length" class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="user in smartworkingTodayRows"
                                :key="user.id"
                                class="flex min-h-[92px] items-center gap-3 rounded-[var(--radius)] border border-gray-100 bg-white/72 p-3 shadow-sm"
                            >
                                <UserAvatar :user="user" size="md" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-gray-900">{{ user.name || 'Utente' }}</div>
                                    <div v-if="smartworkingUserLabel(user)" class="mt-1 truncate text-xs font-semibold text-gray-500">
                                        {{ smartworkingUserLabel(user) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="rounded-[var(--radius)] border border-dashed border-gray-200 bg-white/60 px-4 py-6 text-sm text-gray-500">
                            Nessuna persona in smart working oggi.
                        </p>
                    </section>
                </div>

                <section class="app-card">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="w-full sm:w-56">
                            <AppSelect v-model="absenceStatus" :options="absenceStatusOptions" />
                        </div>
                        <button type="button" class="btn btn-outline" @click="absenceStatus = 'all'"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Persona</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Tipo</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Periodo</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Orario</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Codice INPS</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Allegato</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Note</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Stato</th>
                                    <th class="px-3 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in absenceRows" :key="row.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-3">
                                            <UserAvatar :user="absenceUser(row)" size="sm" />
                                            <div class="min-w-0">
                                                <div class="truncate font-semibold text-gray-900">{{ row.user_name || 'Utente' }}</div>
                                                <div class="truncate text-xs text-gray-500">{{ row.user_email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 font-medium text-gray-700">
                                        <Link :href="route('absences.show', row.id)" class="text-indigo-600 hover:text-indigo-500">
                                            {{ absenceTypeLabels[row.type] || displayValue(row.type) }}
                                        </Link>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        {{ dateIt(row.start_date) }}
                                        <span v-if="row.end_date && row.end_date !== row.start_date"> - {{ dateIt(row.end_date) }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <span v-if="row.start_time || row.end_time">{{ String(row.start_time || '--:--').slice(0, 5) }} - {{ String(row.end_time || '--:--').slice(0, 5) }}</span>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        {{ row.inps_code || '-' }}
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">
                                        <a
                                            v-if="row.medical_document_path"
                                            :href="route('absences.medical-document.download', row.id)"
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 transition hover:text-indigo-500"
                                        >
                                            <FileText class="h-3.5 w-3.5" :stroke-width="1.7" />
                                            Apri
                                        </a>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="max-w-xs px-3 py-3 text-gray-600">
                                        <div v-if="row.notes" class="line-clamp-2" v-html="row.notes"></div>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span :class="['inline-flex rounded-full px-2 py-1 text-xs font-semibold', absenceStatusClass(row.status)]">{{ absenceStatusLabels[row.status] || row.status }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right">
                                        <div class="inline-flex items-center justify-end gap-1">
                                            <Link :href="route('absences.show', row.id)" class="icon-btn h-9 w-9" title="Apri richiesta" aria-label="Apri richiesta">
                                                <ExternalLink class="h-4 w-4" :stroke-width="1.7" />
                                            </Link>
                                            <button
                                                v-if="row.status !== 'approved'"
                                                type="button"
                                                class="icon-btn h-9 w-9 text-emerald-600 hover:bg-emerald-50"
                                                title="Approva richiesta"
                                                aria-label="Approva richiesta"
                                                @click="updateAbsenceStatus(row, 'approved')"
                                            >
                                                <Check class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button type="button" class="icon-btn h-9 w-9 text-red-600 hover:bg-red-50" title="Elimina richiesta" aria-label="Elimina richiesta" @click="remove(row)">
                                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!absenceRows.length">
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nessuna richiesta trovata.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <div v-else-if="section === 'users'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="surface flex max-w-full flex-wrap gap-2 p-2">
                        <button
                            v-for="filter in userRoleFilters"
                            :key="filter.value"
                            type="button"
                            :class="['settings-tab', userRoleFilter === filter.value ? 'settings-tab-active' : '']"
                            @click="userRoleFilter = filter.value"
                        >
                            <span>{{ filter.label }}</span>
                            <span class="rounded-full bg-white/60 px-2 py-0.5 text-[11px] text-current">{{ filter.count }}</span>
                        </button>
                    </div>
                    <button v-if="canCreate" type="button" class="btn btn-primary" @click="openCreate()">
                        <UserPlus class="h-4 w-4" :stroke-width="1.7" />
                        Crea Utente
                    </button>
                </div>

                <div v-if="usersByRole.length" class="space-y-6">
                    <section v-for="group in usersByRole" :key="group.role" class="space-y-3">
                        <div class="flex items-center gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ roleLabels[group.role] }}</h3>
                            <span :class="['rounded px-2 py-0.5 text-xs font-medium', roleClass(group.role)]">{{ group.rows.length }}</span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <article
                                v-for="user in group.rows"
                                :key="user.id"
                                class="content-card relative overflow-hidden rounded-[var(--radius-sm)] border border-white/70 bg-white/82 p-4 text-center shadow-[0_18px_45px_rgba(15,23,42,0.08)] backdrop-blur-xl transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-[0_22px_55px_rgba(79,70,229,0.14)]"
                            >
                                <button type="button" class="icon-btn absolute right-3 top-3 h-8 w-8 text-red-600 hover:bg-red-50 hover:text-red-500" title="Elimina utente" :aria-label="`Elimina ${user.name || user.email}`" @click="remove(user)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <Link :href="route('users.show', user.id)" class="block p-2">
                                    <UserAvatar :user="user" size="lg" class="mx-auto" />
                                    <div class="mt-3 min-w-0">
                                        <h4 class="truncate text-sm font-semibold text-gray-900">{{ user.name || 'Senza nome' }}</h4>
                                        <p class="mt-1 truncate text-xs text-gray-500">{{ user.email }}</p>
                                    </div>
                                </Link>
                                <div class="mt-3 flex items-center justify-center">
                                    <span :class="['rounded-full px-2.5 py-1 text-[11px] font-semibold', roleClass(user.role || 'guest')]">{{ roleLabels[user.role || 'guest'] || user.role || 'Ospite' }}</span>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <div v-else class="content-card rounded-md border border-dashed border-gray-300 bg-white px-5 py-12 text-center text-sm text-gray-500">
                    Nessun utente trovato.
                </div>
            </div>
        </div>

        <div v-else-if="section === 'clients'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-3 md:grid-cols-[1fr_220px_auto_auto]">
                    <input v-model="clientSearch" class="form-control mt-0" placeholder="Cerca per nome, ragione sociale, email, P.IVA o citta..." />
                    <AppSelect
                        v-model="clientService"
                        :options="namedOptions(services, { value: 'all', label: 'Tutti i servizi' })"
                        searchable
                    />
                    <button type="button" class="btn btn-outline" @click="clientSearch = ''; clientService = 'all'"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                    <button v-if="canCreate" type="button" class="btn btn-primary" @click="openCreate()"><Plus class="h-4 w-4" :stroke-width="1.7" />Nuovo Cliente</button>
                </div>

                <div class="grid gap-6">
                    <section v-if="false" class="rounded-md bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">{{ editing ? 'Modifica cliente' : 'Nuovo cliente' }}</h3>
                            <button v-if="editing" type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="resetForm">Annulla</button>
                        </div>

                        <form class="space-y-4" @submit.prevent="submit">
                            <div v-for="field in fields" :key="field.name">
                                <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                                <textarea v-if="field.type === 'textarea'" v-model="form[field.name]" rows="4" class="form-control" />
                                <AppSelect
                                    v-else-if="['select', 'client', 'project', 'service'].includes(field.type)"
                                    v-model="form[field.name]"
                                    :options="fieldSelectOptions(field)"
                                    placeholder="Seleziona"
                                    searchable
                                />
                                <div v-else-if="field.type === 'user'" class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-if="!field.required"
                                        type="button"
                                        :class="personAvatarClass(!form[field.name])"
                                        aria-label="Nessuna persona"
                                        title="Nessuna persona"
                                        @click="form[field.name] = ''"
                                    >
                                        <span class="text-xs font-semibold">-</span>
                                    </button>
                                    <button
                                        v-for="user in users"
                                        :key="`${field.name}-${user.id}`"
                                        type="button"
                                        :class="personAvatarClass(form[field.name] === user.id)"
                                        :aria-pressed="form[field.name] === user.id"
                                        :aria-label="`Seleziona ${user.name || user.email}`"
                                        :title="user.name || user.email"
                                        @click="form[field.name] = user.id"
                                    >
                                        <UserAvatar :user="user" size="md" />
                                    </button>
                                </div>
                                <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="form[field.name]" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Si
                                </label>
                                <input v-else v-model="form[field.name]" :type="field.type" class="form-control" :required="field.required" />
                                <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                            </div>
                            <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="form.processing">
                                {{ editing ? 'Salva modifiche' : 'Crea cliente' }}
                            </button>
                        </form>
                    </section>

                    <section class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <article
                            v-for="client in clientRows"
                            :key="client.id"
                            class="content-card group relative rounded-md border border-gray-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
                        >
                            <Link :href="route('clients.show', client.id)" class="block h-full p-5 pr-14">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-semibold text-gray-900">{{ client.name }}</h3>
                                    <p v-if="client.legal_name && client.legal_name !== client.name" class="mt-0.5 truncate text-sm text-gray-500">{{ client.legal_name }}</p>
                                </div>

                                <div class="mt-4 space-y-1 text-sm text-gray-600">
                                    <p v-if="client.email" class="truncate">{{ client.email }}</p>
                                    <p v-if="client.phone" class="truncate">{{ client.phone }}</p>
                                    <p v-if="client.city || client.province" class="truncate">{{ [client.city, client.province].filter(Boolean).join(', ') }}</p>
                                    <p v-if="client.vat_number" class="truncate text-xs text-gray-500">P.IVA {{ client.vat_number }}</p>
                                </div>

                                <div
                                    class="client-services-carousel mt-4 max-w-full"
                                    @pointerdown.stop="startClientServicesDrag"
                                    @wheel.stop="scrollClientServicesWheel"
                                    @click="blockClientServicesClick"
                                >
                                    <span
                                        v-for="service in client.services || []"
                                        :key="service.id"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium"
                                        :style="{ borderColor: `${service.color || '#64748b'}55`, color: service.color || '#64748b', backgroundColor: `${service.color || '#64748b'}18` }"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: service.color || '#64748b' }"></span>
                                        {{ service.name }}
                                    </span>
                                    <span v-if="!(client.services || []).length" class="shrink-0 text-xs text-gray-400">Nessun servizio collegato</span>
                                </div>
                            </Link>

                            <button
                                v-if="canDeleteRow(client)"
                                type="button"
                                class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-100 bg-white/90 text-red-600 shadow-sm transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-200"
                                :aria-label="`Elimina ${client.name}`"
                                @click.stop.prevent="remove(client)"
                            >
                                <Trash2 class="h-4 w-4" :stroke-width="1.8" />
                            </button>
                        </article>

                        <div v-if="!clientRows.length" class="content-card rounded-md border border-dashed border-gray-300 bg-white px-5 py-12 text-center text-sm text-gray-500 sm:col-span-2 lg:col-span-4">
                            Nessun cliente trovato.
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div v-else-if="section === 'tasks'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="surface flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Vista settimanale</p>
                        <h2 class="text-lg font-bold text-gray-950">{{ taskWeekLabel }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="icon-btn" @click="changeTaskWeek(-1)">
                            <span class="sr-only">Settimana precedente</span>
                            <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                        </button>
                        <button type="button" class="btn btn-outline" @click="resetTaskWeek">Oggi</button>
                        <button type="button" class="icon-btn" @click="changeTaskWeek(1)">
                            <span class="sr-only">Settimana successiva</span>
                            <ChevronRight class="h-4 w-4" :stroke-width="1.7" />
                        </button>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-[1fr_150px_150px_150px_auto_auto_auto_auto]">
                    <input v-model="taskSearch" class="form-control mt-0" placeholder="Cerca task, cliente, progetto o servizio..." />
                    <AppSelect v-model="taskStatus" :options="taskStatusOptions" />
                    <AppSelect v-model="taskPriority" :options="taskPriorityOptions" />
                    <AppSelect v-model="taskType" :options="taskTypeOptions" />
                    <button type="button" class="btn btn-outline" @click="taskSearch = ''; taskStatus = 'all'; taskPriority = 'all'; taskType = 'all'"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                    <button v-if="canCreate" type="button" class="btn btn-primary" @click="openCreate({ task_type: 'project' })"><Briefcase class="h-4 w-4" :stroke-width="1.7" />Task</button>
                    <button v-if="canCreate" type="button" class="btn border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100" @click="openCreate({ task_type: 'ongoing' })"><RefreshCw class="h-4 w-4" :stroke-width="1.7" />Continuativa</button>
                    <button v-if="canCreate" type="button" class="btn border border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100" @click="openCreate({ task_type: 'meeting', due_time: '09:00' })"><CalendarClock class="h-4 w-4" :stroke-width="1.7" />Meeting</button>
                </div>

                <div class="grid gap-4">
                    <section v-if="false" class="rounded-md bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">{{ editing ? 'Modifica task' : 'Nuovo task' }}</h3>
                            <button v-if="editing" type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="resetForm">Annulla</button>
                        </div>

                        <form class="space-y-4" @submit.prevent="submit">
                            <div v-for="field in fields" v-show="shouldShowField(field)" :key="field.name">
                                <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                                <textarea v-if="field.type === 'textarea'" v-model="form[field.name]" rows="4" class="form-control" />
                                <AppSelect
                                    v-else-if="['select', 'client', 'project', 'service'].includes(field.type)"
                                    v-model="form[field.name]"
                                    :options="fieldSelectOptions(field)"
                                    placeholder="Seleziona"
                                    searchable
                                />
                                <div v-else-if="field.type === 'user'" class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-if="!field.required"
                                        type="button"
                                        :class="personAvatarClass(!form[field.name])"
                                        aria-label="Nessuna persona"
                                        title="Nessuna persona"
                                        @click="form[field.name] = ''"
                                    >
                                        <span class="text-xs font-semibold">-</span>
                                    </button>
                                    <button
                                        v-for="user in users"
                                        :key="`${field.name}-${user.id}`"
                                        type="button"
                                        :class="personAvatarClass(form[field.name] === user.id)"
                                        :aria-pressed="form[field.name] === user.id"
                                        :aria-label="`Seleziona ${user.name || user.email}`"
                                        :title="user.name || user.email"
                                        @click="form[field.name] = user.id"
                                    >
                                        <UserAvatar :user="user" size="md" />
                                    </button>
                                </div>
                                <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="form[field.name]" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Si
                                </label>
                                <input v-else v-model="form[field.name]" :type="field.type" class="form-control" :required="field.required" />
                                <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                            </div>
                            <div class="rounded-md border border-gray-100 bg-gray-50 p-3">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-900">{{ form.task_type === 'meeting' ? 'Partecipanti' : 'Persone' }}</h4>
                                    <span class="text-xs text-gray-500">{{ (form.assignee_ids || []).length }} assegnati</span>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ form.task_type === 'meeting' ? 'Partecipanti' : 'Assegnatari' }}</div>
                                        <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto pr-1">
                                            <button
                                                v-for="user in users"
                                                :key="`assignee-${user.id}`"
                                                type="button"
                                                :class="personAvatarClass((form.assignee_ids || []).includes(user.id))"
                                                :aria-pressed="(form.assignee_ids || []).includes(user.id)"
                                                :aria-label="`${(form.assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                :title="user.name || user.email"
                                                @click="toggleFormPerson('assignee_ids', user.id)"
                                            >
                                                <UserAvatar :user="user" size="md" />
                                            </button>
                                            <p v-if="!users?.length" class="text-xs text-gray-500">Nessun utente disponibile.</p>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Follower</div>
                                        <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto pr-1">
                                            <button
                                                v-for="user in users"
                                                :key="`follower-${user.id}`"
                                                type="button"
                                                :class="personAvatarClass((form.follower_ids || []).includes(user.id))"
                                                :aria-pressed="(form.follower_ids || []).includes(user.id)"
                                                :aria-label="`${(form.follower_ids || []).includes(user.id) ? 'Rimuovi follower' : 'Aggiungi follower'} ${user.name || user.email}`"
                                                :title="user.name || user.email"
                                                @click="toggleFormPerson('follower_ids', user.id)"
                                            >
                                                <UserAvatar :user="user" size="md" />
                                            </button>
                                            <p v-if="!users?.length" class="text-xs text-gray-500">Nessun utente disponibile.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="form.processing">
                                {{ editing ? 'Salva modifiche' : 'Crea task' }}
                            </button>
                        </form>
                    </section>

                    <section class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div v-for="[status, label] in taskColumns" :key="status" class="content-card min-h-[520px] rounded-md border border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                <h3 class="text-sm font-semibold text-gray-800">{{ label }}</h3>
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500">{{ tasksByStatus(status).length }}</span>
                            </div>
                            <div class="space-y-3 p-3">
                                <article
                                    v-for="task in tasksByStatus(status)"
                                    :key="task.id"
                                    :class="[
                                        'content-card task-type-card relative rounded-[var(--radius-sm)] border p-3 shadow-sm transition hover:shadow',
                                        taskTypeClass(task.task_type),
                                        task.status === 'done' ? 'task-card-done' : '',
                                    ]"
                                >
                                    <button
                                        v-if="canDeleteRow(task)"
                                        type="button"
                                        class="absolute right-3 top-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border border-red-100 bg-white/86 text-red-500 shadow-sm transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-200"
                                        :aria-label="`Elimina ${task.title}`"
                                        @click.stop.prevent="remove(task)"
                                    >
                                        <Trash2 class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <Link :href="route('tasks.show', task.id)" class="block min-w-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 pr-9">
                                                <h4 :class="['truncate text-sm font-semibold text-gray-900', task.status === 'done' ? 'line-through opacity-60' : '']">{{ task.title }}</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                            <span
                                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold shadow-[inset_0_1px_0_rgba(255,255,255,0.28)]"
                                                :style="{ backgroundColor: priorityColor(task.priority), color: priorityTextColor(task.priority) }"
                                            >
                                                {{ displayValue(task.priority) }}
                                            </span>
                                            <span v-if="task.client_name" class="min-w-0 max-w-full truncate rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ task.client_name }}</span>
                                        </div>
                                        <div class="mt-4 flex items-center justify-between gap-3 text-[11px] leading-none text-gray-500">
                                            <div class="inline-flex min-w-0 items-center gap-2.5">
                                                <span
                                                    v-if="blockedDependencyCount(task)"
                                                    class="inline-flex items-center text-rose-700"
                                                    :title="`Task bloccata da ${blockedDependencyCount(task)} dipendenze`"
                                                >
                                                    <GitBranch class="h-3.5 w-3.5" :stroke-width="1.8" />
                                                </span>
                                                <span v-if="task.subtask_count" class="inline-flex items-center gap-1 font-semibold text-gray-500">
                                                    <span>{{ task.subtask_count }}</span>
                                                    <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                        <path :d="subtaskIconPath" />
                                                    </svg>
                                                </span>
                                                <span v-if="task.due_time" class="inline-flex items-center gap-1 font-semibold text-gray-500">
                                                    <Clock class="h-3.5 w-3.5" :stroke-width="1.8" />
                                                    <span>{{ String(task.due_time).slice(0, 5) }}</span>
                                                </span>
                                            </div>
                                            <span class="ml-auto shrink-0 text-right font-medium">{{ task.due_date ? dateIt(task.due_date) : 'Senza scadenza' }}</span>
                                        </div>
                                    </Link>
                                </article>
                                <div v-if="!tasksByStatus(status).length" class="content-card rounded-md border border-dashed border-gray-300 bg-white px-3 py-8 text-center text-xs text-gray-500">
                                    Nessun task.
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div v-else-if="section === 'settings'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="rounded-md border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <div class="surface flex flex-wrap gap-2 p-2">
                    <button
                        v-for="tab in settingsTabs"
                        :key="tab[0]"
                        type="button"
                        :class="['settings-tab', settingsTab === tab[0] ? 'settings-tab-active' : '']"
                        @click="settingsTab = tab[0]"
                    >
                        <component :is="tab[2]" class="h-4 w-4" :stroke-width="1.7" />
                        {{ tab[1] }}
                    </button>
                </div>

                <section v-if="settingsTab === 'personalizzazione'" class="grid gap-6 lg:grid-cols-[1fr_320px]">
                    <form class="app-card" @submit.prevent="saveDocumentSettings">
                        <h3 class="section-title"><span class="section-icon"><Building2 class="h-4 w-4" :stroke-width="1.7" /></span>Identità aziendale</h3>
                        <p class="mt-1 text-sm text-gray-500">Questi dati alimentano intestazioni, PDF, XML e firme documentali.</p>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ragione sociale *</label>
                                <input v-model="documentSettingsForm.company_name" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Forma giuridica</label>
                                <input v-model="documentSettingsForm.legal_form" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Partita IVA</label>
                                <input v-model="documentSettingsForm.vat_number" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Codice fiscale</label>
                                <input v-model="documentSettingsForm.tax_code" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input v-model="documentSettingsForm.email" type="email" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">PEC</label>
                                <input v-model="documentSettingsForm.pec" type="email" class="form-control" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Note footer documenti</label>
                                <textarea v-model="documentSettingsForm.footer_notes" rows="4" class="form-control" />
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-5" :disabled="documentSettingsForm.processing">
                            <Save class="h-4 w-4" :stroke-width="1.7" />
                            Salva identità
                        </button>
                    </form>

                    <aside class="app-card">
                        <h3 class="section-title"><span class="section-icon"><Banknote class="h-4 w-4" :stroke-width="1.7" /></span>Indirizzo e banca</h3>
                        <div class="mt-4 space-y-3">
                            <input v-model="documentSettingsForm.street" class="form-control mt-0" placeholder="Via" />
                            <div class="grid grid-cols-3 gap-3">
                                <input v-model="documentSettingsForm.street_number" class="form-control mt-0" placeholder="N." />
                                <input v-model="documentSettingsForm.postal_code" class="form-control mt-0" placeholder="CAP" />
                                <input v-model="documentSettingsForm.province" class="form-control mt-0" placeholder="Prov." />
                            </div>
                            <input v-model="documentSettingsForm.city" class="form-control mt-0" placeholder="Città" />
                            <input v-model="documentSettingsForm.country" class="form-control mt-0" placeholder="Paese" />
                            <input v-model="documentSettingsForm.iban" class="form-control mt-0" placeholder="IBAN" />
                            <input v-model="documentSettingsForm.bic_swift" class="form-control mt-0" placeholder="BIC/SWIFT" />
                            <input v-model="documentSettingsForm.bank_name" class="form-control mt-0" placeholder="Banca" />
                        </div>
                    </aside>
                </section>

                <section v-else-if="settingsTab === 'fatturazione'" class="space-y-6">
                    <form class="app-card" @submit.prevent="saveDocumentSettings">
                        <h3 class="section-title"><span class="section-icon"><Receipt class="h-4 w-4" :stroke-width="1.7" /></span>Default fatturazione</h3>
                        <div class="mt-5 grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Regime fiscale</label>
                                <input v-model="documentSettingsForm.tax_regime" class="form-control" placeholder="RF01" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Pagamento default</label>
                                <input v-model="documentSettingsForm.default_payment_method" class="form-control" placeholder="Bonifico" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Termini pagamento</label>
                                <input v-model="documentSettingsForm.default_payment_terms_days" type="number" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Codice SDI</label>
                                <input v-model="documentSettingsForm.sdi_code" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ritenuta %</label>
                                <input v-model="documentSettingsForm.default_withholding_pct" type="number" step="0.01" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Cassa label</label>
                                <input v-model="documentSettingsForm.default_pension_fund_label" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Cassa %</label>
                                <input v-model="documentSettingsForm.default_pension_fund_pct" type="number" step="0.01" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Telefono</label>
                                <input v-model="documentSettingsForm.phone" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Soglia bollo</label>
                                <input v-model="documentSettingsForm.bollo_threshold" type="number" step="0.01" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Importo bollo</label>
                                <input v-model="documentSettingsForm.bollo_amount" type="number" step="0.01" class="form-control" />
                            </div>
                            <label class="mt-7 flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="documentSettingsForm.bollo_charged_to_client" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Bollo a carico cliente
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary mt-5" :disabled="documentSettingsForm.processing">
                            <Save class="h-4 w-4" :stroke-width="1.7" />
                            Salva fatturazione
                        </button>
                    </form>

                    <section class="app-card">
                        <h3 class="section-title"><span class="section-icon"><FileText class="h-4 w-4" :stroke-width="1.7" /></span>Numerazioni</h3>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Tipo</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Anno</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Prefisso</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Formato</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Progressivo</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Reset</th>
                                        <th class="px-3 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in numberingRows" :key="row.id">
                                        <td class="px-3 py-3">{{ documentTypeLabels[row.doc_type] || row.doc_type }}</td>
                                        <td class="px-3 py-3">{{ row.year }}</td>
                                        <td class="px-3 py-3"><input v-model="row.prefix" class="form-control mt-0 w-24" /></td>
                                        <td class="px-3 py-3"><input v-model="row.format" class="form-control mt-0 min-w-56" /></td>
                                        <td class="px-3 py-3"><input v-model="row.current_seq" type="number" class="form-control mt-0 w-28" /></td>
                                        <td class="px-3 py-3"><input v-model="row.yearly_reset" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" /></td>
                                        <td class="px-3 py-3 text-right"><button type="button" class="action-link" @click="saveNumbering(row)"><Save class="h-4 w-4" :stroke-width="1.7" />Salva</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </section>

                <section v-else-if="settingsTab === 'smtp'" class="space-y-6">
                    <form class="app-card" @submit.prevent="saveEmailSettings">
                        <h3 class="section-title"><span class="section-icon"><Mail class="h-4 w-4" :stroke-width="1.7" /></span>Email e SMTP</h3>
                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <label class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-3 text-sm text-gray-700">
                                <span class="flex items-center justify-between gap-3">
                                    <span>
                                        <span class="block font-semibold text-gray-900">SMTP attivo</span>
                                        <span class="mt-0.5 block text-xs text-gray-500">Usa queste credenziali per inviare email dalla piattaforma.</span>
                                    </span>
                                    <input v-model="emailSettingsForm.smtp_enabled" type="checkbox" class="sr-only" />
                                    <span
                                        class="relative h-6 w-11 shrink-0 rounded-full transition"
                                        :class="emailSettingsForm.smtp_enabled ? 'bg-[hsl(var(--primary-app))]' : 'bg-gray-200'"
                                    >
                                        <span
                                            class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow-sm transition"
                                            :class="emailSettingsForm.smtp_enabled ? 'translate-x-5' : 'translate-x-0'"
                                        ></span>
                                    </span>
                                </span>
                                <span class="mt-2 block text-xs font-semibold" :class="emailSettingsForm.smtp_enabled ? 'text-emerald-600' : 'text-gray-400'">
                                    {{ emailSettingsForm.smtp_enabled ? 'Attivo' : 'Disattivo' }}
                                </span>
                            </label>
                            <label class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-3 text-sm text-gray-700">
                                <span class="flex items-center justify-between gap-3">
                                    <span>
                                        <span class="block font-semibold text-gray-900">TLS/SSL</span>
                                        <span class="mt-0.5 block text-xs text-gray-500">Connessione cifrata verso il server SMTP.</span>
                                    </span>
                                    <input v-model="emailSettingsForm.smtp_secure" type="checkbox" class="sr-only" />
                                    <span
                                        class="relative h-6 w-11 shrink-0 rounded-full transition"
                                        :class="emailSettingsForm.smtp_secure ? 'bg-[hsl(var(--primary-app))]' : 'bg-gray-200'"
                                    >
                                        <span
                                            class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow-sm transition"
                                            :class="emailSettingsForm.smtp_secure ? 'translate-x-5' : 'translate-x-0'"
                                        ></span>
                                    </span>
                                </span>
                                <span class="mt-2 block text-xs font-semibold" :class="emailSettingsForm.smtp_secure ? 'text-emerald-600' : 'text-gray-400'">
                                    {{ emailSettingsForm.smtp_secure ? 'Attivo' : 'Disattivo' }}
                                </span>
                            </label>
                            <input v-model="emailSettingsForm.smtp_host" class="form-control mt-0" placeholder="Host SMTP" />
                            <input v-model="emailSettingsForm.smtp_port" type="number" class="form-control mt-0" placeholder="Porta" />
                            <input v-model="emailSettingsForm.smtp_username" class="form-control mt-0" placeholder="Username" />
                            <div>
                                <input v-model="emailSettingsForm.smtp_password" type="password" class="form-control mt-0" placeholder="Nuova password SMTP" autocomplete="new-password" />
                                <p v-if="emailSettingsForm.smtp_password_saved" class="mt-1 text-xs font-medium text-emerald-600">Password SMTP salvata. Compila questo campo solo per sostituirla.</p>
                                <p v-else class="mt-1 text-xs text-gray-500">Nessuna password SMTP salvata.</p>
                            </div>
                            <input v-model="emailSettingsForm.smtp_from_email" type="email" class="form-control mt-0" placeholder="Email mittente" />
                            <input v-model="emailSettingsForm.smtp_from_name" class="form-control mt-0" placeholder="Nome mittente" />
                            <input v-model="emailSettingsForm.smtp_reply_to" type="email" class="form-control mt-0" placeholder="Reply-to" />
                            <input v-model="emailSettingsForm.pec_username" class="form-control mt-0" placeholder="PEC username" />
                            <div>
                                <input v-model="emailSettingsForm.pec_password" type="password" class="form-control mt-0" placeholder="Nuova password PEC" autocomplete="new-password" />
                                <p v-if="emailSettingsForm.pec_password_saved" class="mt-1 text-xs font-medium text-emerald-600">Password PEC salvata. Compila questo campo solo per sostituirla.</p>
                                <p v-else class="mt-1 text-xs text-gray-500">Nessuna password PEC salvata.</p>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-5" :disabled="emailSettingsForm.processing">
                            <Save class="h-4 w-4" :stroke-width="1.7" />
                            Salva email
                        </button>
                    </form>

                    <form class="app-card" @submit.prevent="sendTestEmail">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="section-title"><span class="section-icon"><Mail class="h-4 w-4" :stroke-width="1.7" /></span>Test invio email</h3>
                                <p class="mt-2 text-sm text-gray-500">Invia una mail di prova usando la configurazione SMTP salvata.</p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
                            <div>
                                <input v-model="testEmailForm.recipient" type="email" class="form-control mt-0" placeholder="Destinatario test" autocomplete="email" />
                                <p v-if="testEmailForm.errors.recipient" class="mt-1 text-sm text-red-600">{{ testEmailForm.errors.recipient }}</p>
                                <p v-else-if="testEmailForm.recentlySuccessful" class="mt-1 text-sm font-medium text-emerald-600">Mail di test inviata.</p>
                            </div>
                            <button type="submit" class="btn btn-outline h-[42px] self-start" :disabled="testEmailForm.processing">
                                <Mail class="h-4 w-4" :stroke-width="1.7" />
                                {{ testEmailForm.processing ? 'Invio...' : 'Invia test' }}
                            </button>
                        </div>
                    </form>
                </section>

                <section v-else-if="settingsTab === 'backup'" class="grid gap-6 lg:grid-cols-[360px_1fr]">
                    <div class="app-card">
                        <h3 class="section-title"><span class="section-icon"><DatabaseBackup class="h-4 w-4" :stroke-width="1.7" /></span>Backup manuale</h3>
                        <p class="mt-2 text-sm text-gray-500">Crea subito un dump SQL ripristinabile del database attuale.</p>
                        <button type="button" class="btn btn-primary mt-5" @click="runBackup">
                            <DatabaseBackup class="h-4 w-4" :stroke-width="1.7" />
                            Crea backup ora
                        </button>
                        <div class="mt-6 rounded-[var(--radius-sm)] border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                            <div class="font-semibold text-gray-900 dark:text-white">Automatici attivi</div>
                            <div class="mt-2 space-y-1">
                                <p>Settimanale: lunedì alle 03:00.</p>
                                <p>Mensile: giorno 1 alle 03:30.</p>
                                <p>Retention: massimo 2 settimanali e 2 mensili.</p>
                            </div>
                        </div>
                    </div>

                    <div class="app-card">
                        <h3 class="section-title"><span class="section-icon"><Clock class="h-4 w-4" :stroke-width="1.7" /></span>Storico backup</h3>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Data</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Tipo</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Stato</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Tabelle</th>
                                        <th class="px-3 py-3 text-left font-semibold text-gray-600">Dimensione</th>
                                        <th class="px-3 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="run in backupRuns || []" :key="run.id">
                                        <td class="px-3 py-3">{{ dateIt(run.started_at) }}</td>
                                        <td class="px-3 py-3">{{ backupFrequencyLabel(run.frequency) }}</td>
                                        <td class="px-3 py-3">
                                            <span :class="['rounded-full px-2 py-1 text-xs font-semibold', backupStatusClass(run.status)]">
                                                {{ backupStatusLabel(run.status) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">{{ run.tables_count || '-' }}</td>
                                        <td class="px-3 py-3">{{ fileSize(run.size_bytes) }}</td>
                                        <td class="px-3 py-3">
                                            <div class="flex justify-end gap-1">
                                            <button
                                                type="button"
                                                :class="['icon-btn', run.restorable ? 'restore-backup-button' : '']"
                                                :disabled="!run.restorable"
                                                :title="run.restorable ? 'Ripristina backup' : 'File backup non disponibile'"
                                                :aria-label="run.restorable ? 'Ripristina backup' : 'File backup non disponibile'"
                                                @click="openRestoreBackup(run)"
                                            >
                                                <RotateCcw class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button
                                                type="button"
                                                class="icon-btn text-red-600 hover:bg-red-50 hover:text-red-500"
                                                title="Elimina backup"
                                                aria-label="Elimina backup"
                                                @click="removeBackup(run)"
                                            >
                                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!(backupRuns || []).length">
                                        <td colspan="6" class="px-3 py-8 text-center text-gray-500">Nessun backup registrato.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section v-else class="space-y-4">
                    <div class="flex justify-end">
                        <button v-if="canCreate" type="button" class="btn btn-primary" @click="openCreate()">
                            <Plus class="h-4 w-4" :stroke-width="1.7" />
                            Aggiungi servizio
                        </button>
                    </div>

                    <section v-if="false" class="rounded-md bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">{{ editing ? 'Modifica servizio' : 'Nuovo servizio' }}</h3>
                            <button v-if="editing" type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="resetForm">Annulla</button>
                        </div>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div v-for="field in fields" :key="field.name">
                                <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                                <textarea v-if="field.type === 'textarea'" v-model="form[field.name]" rows="4" class="form-control" />
                                <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="form[field.name]" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Attivo
                                </label>
                                <input v-else v-model="form[field.name]" :type="field.type" class="form-control" :required="field.required" />
                                <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                            </div>
                            <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="form.processing">
                                {{ editing ? 'Salva modifiche' : 'Crea servizio' }}
                            </button>
                        </form>
                    </section>

                    <section class="surface overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Servizio</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Stato</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Colore</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in rows" :key="row.id">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
                                    <td class="px-4 py-3">{{ row.active ? 'Attivo' : 'Disattivo' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="h-3 w-3 rounded-full ring-1 ring-gray-200" :style="{ backgroundColor: row.color || '#64748b' }"></span>
                                            {{ row.color || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <button type="button" class="icon-btn" title="Modifica servizio" :aria-label="`Modifica ${row.name}`" @click="editRow(row)">
                                                <Pencil class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button type="button" class="icon-btn text-red-600 hover:bg-red-50 hover:text-red-500" title="Elimina servizio" :aria-label="`Elimina ${row.name}`" @click="remove(row)">
                                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </section>
            </div>
        </div>

        <div v-else-if="section === 'billing'" class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div
                        v-for="card in [
                            ['Fatturato anno', billingStats?.totalInvoiced, null, TrendingUp, 'bg-indigo-50 text-indigo-600'],
                            ['Incassato anno', billingStats?.totalReceived, `${billingStats?.collectedPct || 0}% del fatturato`, Wallet, 'bg-emerald-50 text-emerald-600'],
                            ['Da incassare', billingStats?.openAmount, null, Banknote, 'bg-amber-50 text-amber-700'],
                            [`Scaduti (${billingStats?.overdueCount || 0})`, billingStats?.overdueAmount, 'Da sollecitare', AlertTriangle, 'bg-red-50 text-red-600'],
                        ]"
                        :key="card[0]"
                        class="app-card flex items-center gap-4"
                    >
                        <span :class="['metric-icon', card[4]]">
                            <component :is="card[3]" class="h-5 w-5" :stroke-width="1.7" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-medium uppercase tracking-wide text-gray-500">{{ card[0] }}</span>
                            <span class="mt-1 block text-2xl font-semibold text-gray-900">{{ money(card[1]) }}</span>
                            <span v-if="card[2]" class="mt-0.5 block truncate text-xs text-gray-500">{{ card[2] }}</span>
                        </span>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <section class="app-card">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="section-title"><span class="section-icon"><TrendingUp class="h-4 w-4" :stroke-width="1.7" /></span>Andamento mensile {{ billingStats?.year }}</h3>
                            <span class="text-xs text-gray-500">Fatturato / incassato</span>
                        </div>
                        <div class="grid h-64 grid-cols-12 items-end gap-2 border-b border-gray-100 pb-2">
                            <div v-for="month in billingStats?.monthly || []" :key="month.month" class="flex h-full flex-col justify-end gap-1">
                                <div class="flex flex-1 items-end justify-center gap-1">
                                    <div class="w-3 rounded-t bg-indigo-500" :style="{ height: `${Math.max(2, (month.invoiced / maxMonthly) * 100)}%` }"></div>
                                    <div class="w-3 rounded-t bg-emerald-500" :style="{ height: `${Math.max(2, (month.paid / maxMonthly) * 100)}%` }"></div>
                                </div>
                                <div class="truncate text-center text-[10px] text-gray-500">{{ month.month }}</div>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-4 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Fatturato</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Incassato</span>
                        </div>
                    </section>

                    <section class="app-card">
                        <h3 class="section-title"><span class="section-icon"><Users class="h-4 w-4" :stroke-width="1.7" /></span>Top clienti</h3>
                        <div class="mt-4 space-y-4">
                            <div v-for="client in billingStats?.topClients || []" :key="client.name">
                                <div class="mb-1 flex justify-between gap-3 text-xs">
                                    <span class="truncate font-medium text-gray-700">{{ client.name }}</span>
                                    <span class="text-gray-500">{{ money(client.total) }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-indigo-500" :style="{ width: `${Math.max(3, (client.total / maxTopClient) * 100)}%` }"></div>
                                </div>
                            </div>
                            <p v-if="!billingStats?.topClients?.length" class="text-sm text-gray-500">Nessuna fattura nell'anno.</p>
                        </div>
                    </section>
                </div>

                <section class="app-card">
                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_170px_170px_auto_auto]">
                        <input
                            v-model="billingSearch"
                            class="form-control mt-0"
                            placeholder="Cerca per numero, cliente o note..."
                        />
                        <AppSelect v-model="billingType" :options="objectOptions(documentTypeLabels, { value: 'all', label: 'Tutti i tipi' })" />
                        <AppSelect v-model="billingStatus" :options="objectOptions(documentStatusLabels, { value: 'all', label: 'Tutti gli stati' })" />
                        <button type="button" class="btn btn-outline" @click="billingSearch = ''; billingType = 'all'; billingStatus = 'all'"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                        <button v-if="canCreate" type="button" class="btn btn-primary" @click="openCreate()"><Plus class="h-4 w-4" :stroke-width="1.7" />Nuovo documento</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Numero</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Tipo</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Cliente</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Data</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Scadenza</th>
                                    <th class="px-3 py-3 text-right font-semibold text-gray-600">Totale</th>
                                    <th class="px-3 py-3 text-right font-semibold text-gray-600">Pagato</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Stato</th>
                                    <th class="px-3 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in billingRows" :key="row.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-3">
                                        <Link :href="route('billing.show', row.id)" class="font-mono text-xs font-medium text-indigo-600">
                                            {{ row.number || 'bozza' }}
                                        </Link>
                                    </td>
                                    <td class="px-3 py-3">{{ documentTypeLabels[row.doc_type] || displayValue(row.doc_type) }}</td>
                                    <td class="px-3 py-3">{{ row.client_name || '-' }}</td>
                                    <td class="px-3 py-3">{{ dateIt(row.issue_date) }}</td>
                                    <td class="px-3 py-3">{{ dateIt(row.due_date) }}</td>
                                    <td class="px-3 py-3 text-right font-medium">{{ money(row.total_amount) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-500">{{ money(row.total_paid) }}</td>
                                    <td class="px-3 py-3">
                                        <span :class="['rounded-full px-2 py-1 text-xs font-medium', statusClass(row.status)]">{{ displayValue(row.status) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right">
                                        <Link :href="route('billing.show', row.id)" class="action-link"><ExternalLink class="h-4 w-4" :stroke-width="1.7" />Apri</Link>
                                        <button type="button" class="danger-link ml-4" @click="remove(row)"><Trash2 class="h-4 w-4" :stroke-width="1.7" />Elimina</button>
                                    </td>
                                </tr>
                                <tr v-if="!billingRows.length">
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nessun documento trovato.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="false" class="rounded-md bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">{{ editing ? 'Modifica documento' : 'Nuovo documento' }}</h3>
                        <button v-if="editing" type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="resetForm">Annulla</button>
                    </div>

                    <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submit">
                        <div v-for="field in fields" :key="field.name" :class="field.type === 'textarea' ? 'md:col-span-3' : ''">
                            <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                            <textarea v-if="field.type === 'textarea'" v-model="form[field.name]" rows="3" class="form-control" />
                            <AppSelect
                                v-else-if="['select', 'client', 'project', 'service'].includes(field.type)"
                                v-model="form[field.name]"
                                :options="fieldSelectOptions(field)"
                                placeholder="Seleziona"
                                searchable
                            />
                            <div v-else-if="field.type === 'user'" class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-if="!field.required"
                                    type="button"
                                    :class="personAvatarClass(!form[field.name])"
                                    aria-label="Nessuna persona"
                                    title="Nessuna persona"
                                    @click="form[field.name] = ''"
                                >
                                    <span class="text-xs font-semibold">-</span>
                                </button>
                                <button
                                    v-for="user in users"
                                    :key="`${field.name}-${user.id}`"
                                    type="button"
                                    :class="personAvatarClass(form[field.name] === user.id)"
                                    :aria-pressed="form[field.name] === user.id"
                                    :aria-label="`Seleziona ${user.name || user.email}`"
                                    :title="user.name || user.email"
                                    @click="form[field.name] = user.id"
                                >
                                    <UserAvatar :user="user" size="md" />
                                </button>
                            </div>
                            <input v-else v-model="form[field.name]" :type="field.type" class="form-control" :required="field.required" />
                            <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                        </div>
                        <div class="md:col-span-3">
                            <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="form.processing">
                                {{ editing ? 'Salva modifiche' : 'Crea documento' }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>

        <div v-else-if="isUpdatesSection" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="app-card">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="section-title">
                                <span class="section-icon"><RefreshCw class="h-4 w-4" :stroke-width="1.7" /></span>
                                {{ updateRows.length }} clienti
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Clienti con il servizio {{ serviceName }} attivo.</p>
                        </div>
                    </div>

                    <div v-if="updateRows.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Cliente</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Ultimo aggiornamento</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Note</th>
                                    <th v-if="showUpdateReport" class="px-3 py-3 text-left font-semibold text-gray-600">Report</th>
                                    <th v-if="showUpdateNewsletter" class="px-3 py-3 text-left font-semibold text-gray-600">Cadenza</th>
                                    <th v-if="showUpdateNewsletter" class="px-3 py-3 text-left font-semibold text-gray-600">Contatto</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600">Responsabile</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in updateRows" :key="`${row.client_id}-${row.id || 'new'}`" class="hover:bg-gray-50">
                                    <td class="px-3 py-3">
                                        <Link :href="route('clients.show', row.client_id)" class="font-medium text-gray-900 hover:text-indigo-600">
                                            {{ row.client_name }}
                                        </Link>
                                    </td>
                                    <td class="px-3 py-3">
                                        <Link v-if="row.last_task_id" :href="route('tasks.show', row.last_task_id)" class="hover:text-indigo-600">
                                            <span class="block max-w-[280px] truncate text-sm font-medium text-gray-800">{{ row.last_task_title }}</span>
                                            <span class="text-xs text-gray-500">{{ dateIt(row.last_task_updated_at) }}</span>
                                        </Link>
                                        <span v-else class="text-xs italic text-gray-500">Nessun aggiornamento</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            :value="draftValue(row, 'notes')"
                                            class="form-control mt-0 min-w-[260px]"
                                            placeholder="Aggiungi note..."
                                            @input="setDraftValue(row, 'notes', $event.target.value)"
                                            @blur="saveDraftField(row, 'notes')"
                                        />
                                    </td>
                                    <td v-if="showUpdateReport" class="px-3 py-3">
                                        <div class="flex min-w-[220px] flex-col gap-1">
                                            <input
                                                :value="draftValue(row, 'report_url')"
                                                class="form-control mt-0"
                                                placeholder="https://..."
                                                type="url"
                                                @input="setDraftValue(row, 'report_url', $event.target.value)"
                                                @blur="saveDraftField(row, 'report_url')"
                                            />
                                            <a v-if="row.report_url" :href="row.report_url" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 truncate text-xs text-indigo-600 hover:text-indigo-500">
                                                <ExternalLink class="h-3.5 w-3.5" :stroke-width="1.7" />
                                                Apri report
                                            </a>
                                        </div>
                                    </td>
                                    <td v-if="showUpdateNewsletter" class="px-3 py-3">
                                        <AppSelect
                                            :model-value="draftValue(row, 'cadence')"
                                            class="min-w-[150px]"
                                            :options="updateCadenceOptions"
                                            @change="(value) => { setDraftValue(row, 'cadence', value); saveServiceUpdateInline(row, { cadence: value || null }); }"
                                        />
                                    </td>
                                    <td v-if="showUpdateNewsletter" class="px-3 py-3">
                                        <input
                                            :value="draftValue(row, 'contact')"
                                            class="form-control mt-0 min-w-[180px]"
                                            placeholder="Nome / email..."
                                            @input="setDraftValue(row, 'contact', $event.target.value)"
                                            @blur="saveDraftField(row, 'contact')"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex min-w-[200px] items-center gap-2">
                                            <AppSelect
                                                :model-value="draftValue(row, 'responsible_user_id')"
                                                :options="namedOptions(users, { value: '', label: 'Nessuno' })"
                                                searchable
                                                @change="(value) => { setDraftValue(row, 'responsible_user_id', value); saveServiceUpdateInline(row, { responsible_user_id: value || null }); }"
                                            />
                                            <span v-if="savingUpdateKeys.includes(updateRowKey(row))" class="inline-flex shrink-0 items-center gap-1 text-xs text-indigo-500">
                                                <Save class="h-3.5 w-3.5" :stroke-width="1.7" />
                                                Salvo...
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="rounded-md border border-dashed border-gray-300 bg-white px-5 py-12 text-center text-sm text-gray-500">
                        Nessun cliente con questo servizio attivo.
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div v-if="canCreate" class="flex justify-end">
                    <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500" @click="openCreate()">
                        {{ createButtonLabel }}
                    </button>
                </div>

                <div class="grid gap-6 lg:grid-cols-1">
                <section v-if="false" class="rounded bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">{{ editing ? 'Modifica' : 'Nuovo' }}</h3>
                        <button v-if="editing" type="button" class="text-sm text-gray-500 hover:text-gray-800" @click="resetForm">Annulla</button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div v-for="field in fields" :key="field.name">
                            <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>

                            <textarea
                                v-if="field.type === 'textarea'"
                                v-model="form[field.name]"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <AppSelect
                                v-else-if="['select', 'client', 'project', 'service'].includes(field.type)"
                                v-model="form[field.name]"
                                :options="fieldSelectOptions(field)"
                                placeholder="Seleziona"
                                searchable
                            />

                            <div v-else-if="field.type === 'user'" class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-if="!field.required"
                                    type="button"
                                    :class="personAvatarClass(!form[field.name])"
                                    aria-label="Nessuna persona"
                                    title="Nessuna persona"
                                    @click="form[field.name] = ''"
                                >
                                    <span class="text-xs font-semibold">-</span>
                                </button>
                                <button
                                    v-for="user in users"
                                    :key="`${field.name}-${user.id}`"
                                    type="button"
                                    :class="personAvatarClass(form[field.name] === user.id)"
                                    :aria-pressed="form[field.name] === user.id"
                                    :aria-label="`Seleziona ${user.name || user.email}`"
                                    :title="user.name || user.email"
                                    @click="form[field.name] = user.id"
                                >
                                    <UserAvatar :user="user" size="md" />
                                </button>
                            </div>

                            <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form[field.name]" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Attivo
                            </label>

                            <input
                                v-else
                                v-model="form[field.name]"
                                :type="field.type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :required="field.required"
                            />

                            <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ editing ? 'Salva modifiche' : 'Crea' }}
                        </button>
                    </form>
                </section>

                <section :class="['overflow-hidden rounded bg-white shadow-sm', canWrite ? '' : 'lg:col-span-2']">
                    <div v-if="page.props.flash?.status" class="border-b border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ page.props.flash.status }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th v-for="column in columns" :key="column" class="px-4 py-3 text-left font-semibold text-gray-600">
                                        {{ displayColumn(column) }}
                                    </th>
                                    <th v-if="canWrite" class="px-4 py-3 text-right font-semibold text-gray-600">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="row in rows" :key="row.id">
                                    <td v-for="column in columns" :key="column" class="max-w-xs truncate px-4 py-3 text-gray-800">
                                        <Link
                                            v-if="column === columns[0] && showRoute(row)"
                                            :href="showRoute(row)"
                                            class="font-medium text-indigo-600 hover:text-indigo-500"
                                        >
                                            {{ displayValue(row[column]) }}
                                        </Link>
                                        <span v-else>{{ displayValue(row[column]) }}</span>
                                    </td>
                                    <td v-if="canWrite" class="whitespace-nowrap px-4 py-3 text-right">
                                        <Link v-if="showRoute(row)" :href="showRoute(row)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Apri</Link>
                                        <button v-else type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" @click="editRow(row)">Modifica</button>
                                        <button v-if="canDeleteRow(row)" type="button" class="ml-4 text-sm font-medium text-red-600 hover:text-red-500" @click="remove(row)">Elimina</button>
                                    </td>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td :colspan="columns.length + (canWrite ? 1 : 0)" class="px-4 py-8 text-center text-gray-500">
                                        Nessun dato presente.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
