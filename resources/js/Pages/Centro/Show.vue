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
    Bold,
    CalendarDays,
    Check,
    ChevronDown,
    ChevronLeft,
    Copy,
    Download,
    ExternalLink,
    FileText,
    GitBranch,
    GripVertical,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Mail,
    MoreHorizontal,
    Paperclip,
    Plus,
    Printer,
    Quote,
    RotateCcw,
    Send,
    Trash2,
    Underline,
    UploadCloud,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    section: String,
    title: String,
    record: Object,
    related: Object,
});

const page = usePage();
const currentRole = computed(() => page.props.auth?.user?.role || 'guest');
const isGuest = computed(() => page.props.auth?.user?.role === 'guest');
const isEditor = computed(() => currentRole.value === 'editor');
const isSuperadmin = computed(() => currentRole.value === 'superadmin');
const canEditClient = computed(() => !isGuest.value && !isEditor.value);
const canEditProject = computed(() => !isGuest.value);
const canDeleteProject = computed(() => !isGuest.value && !isEditor.value);
const canDeleteCurrentTask = computed(() => {
    if (isGuest.value) return false;
    if (!isEditor.value) return true;

    return props.record?.created_by === page.props.auth?.user?.id;
});

function canDeleteTaskRecord(task) {
    if (isGuest.value) return false;
    if (!isEditor.value) return true;

    return task?.created_by === page.props.auth?.user?.id;
}

function canOpenRelatedProject(project) {
    if (!project?.id) return false;
    if (!isGuest.value) return true;

    return (props.related.taskProjects || []).some((item) => item.id === project.id);
}

function canOpenRelatedItem(name) {
    if (isGuest.value && name === 'clients') return false;
    if (isEditor.value && props.section === 'clients' && name === 'documents') return false;

    return true;
}
const AUTOSAVE_IDLE_DELAY = 2500;

function autosaveDelay(delay = AUTOSAVE_IDLE_DELAY) {
    return Number(delay) > 0 ? Number(delay) : AUTOSAVE_IDLE_DELAY;
}

const labels = {
    name: 'Nome',
    legal_name: 'Ragione sociale',
    email: 'Email',
    phone: 'Telefono',
    city: 'Citta',
    province: 'Provincia',
    vat_number: 'Partita IVA',
    tax_code: 'Codice fiscale',
    website: 'Sito web',
    status: 'Stato',
    priority: 'Priorità',
    task_type: 'Tipo',
    start_date: 'Inizio',
    due_date: 'Scadenza',
    due_time: 'Ora',
    recurring_enabled: 'Ricorrente',
    recurring_mode: 'Modalita ricorrenza',
    recurring_interval_value: 'Ogni',
    recurring_interval_unit: 'Unita ricorrenza',
    recurring_weekday: 'Giorno settimana',
    recurring_month_day: 'Giorno mese',
    parent_task_id: 'Task padre',
    project_id: 'Progetto',
    client_id: 'Cliente',
    service_id: 'Servizio',
    description: 'Descrizione',
    notes: 'Note',
};
const clientSelectOptions = {
    legal_form: ['srl', 'srls', 'spa', 'sas', 'snc', 'ditta_individuale', 'libero_professionista', 'associazione', 'ente_pubblico', 'altro'],
    business_sector: ['ecommerce', 'retail', 'servizi', 'immobiliare', 'turismo', 'ristorazione', 'salute_benessere', 'formazione', 'industria', 'no_profit', 'altro'],
    source: ['passaparola', 'sito_web', 'social', 'campagna_adv', 'evento', 'partner', 'chiamata', 'email', 'altro'],
    country: ['IT', 'SM', 'VA', 'FR', 'DE', 'ES', 'CH', 'AT', 'GB', 'US', 'altro'],
    vat_treatment: ['ordinario', 'split_payment', 'reverse_charge', 'esente', 'non_imponibile', 'fuori_campo', 'forfettario'],
    payment_terms_days: [0, 15, 30, 45, 60, 90, 120],
};

const documentStatusOptions = [
    { value: 'draft', label: 'Bozza' },
    { value: 'sent', label: 'Inviato' },
    { value: 'accepted', label: 'Accettato' },
    { value: 'rejected', label: 'Rifiutato' },
    { value: 'paid', label: 'Pagato' },
    { value: 'partially_paid', label: 'Parziale' },
    { value: 'overdue', label: 'Scaduto' },
    { value: 'cancelled', label: 'Annullato' },
];

const projectStatusOptions = [
    { value: 'active', label: 'Attivo' },
    { value: 'completed', label: 'Completato' },
    { value: 'on_hold', label: 'In pausa' },
    { value: 'archived', label: 'Archiviato' },
];
const taskStatusOptions = [
    { value: 'todo', label: 'Da fare' },
    { value: 'in_progress', label: 'In corso' },
    { value: 'in_review', label: 'Review' },
    { value: 'done', label: 'Fatte' },
];
const priorityOptions = [
    { value: 'low', label: 'Bassa' },
    { value: 'medium', label: 'Media' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
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
const weekdayOptions = [
    { value: 1, label: 'Lunedì' },
    { value: 2, label: 'Martedì' },
    { value: 3, label: 'Mercoledì' },
    { value: 4, label: 'Giovedì' },
    { value: 5, label: 'Venerdì' },
    { value: 6, label: 'Sabato' },
    { value: 7, label: 'Domenica' },
];
const absenceTypeOptions = [
    { value: 'vacation', label: 'Ferie' },
    { value: 'permission', label: 'Permesso' },
    { value: 'sickness', label: 'Malattia' },
    { value: 'late', label: 'Ritardo' },
    { value: 'other', label: 'Altra assenza' },
];
const absenceStatusOptions = [
    { value: 'pending', label: 'In attesa' },
    { value: 'approved', label: 'Approvata' },
    { value: 'rejected', label: 'Rifiutata' },
];
const absenceHourOptions = Array.from({ length: 14 }, (_, index) => {
    const hour = String(index + 7).padStart(2, '0');
    return { value: `${hour}:00`, label: `${hour}:00` };
});
const subscriptionFrequencyOptions = [
    { value: 'month', label: 'Mese/i' },
    { value: 'year', label: 'Anno/i' },
];
const completionEffectOptions = [
    { value: 'balloons', label: 'Palloncini' },
    { value: 'fireworks', label: "Fuochi d'artificio" },
    { value: 'snow', label: 'Nevicata' },
    { value: 'glitch', label: 'Glitch' },
];
const smartworkingDayOptions = [
    { value: 'none', label: 'Non impostato' },
    { value: 'monday', label: 'Lunedì' },
    { value: 'tuesday', label: 'Martedì' },
    { value: 'wednesday', label: 'Mercoledì' },
    { value: 'thursday', label: 'Giovedì' },
    { value: 'friday', label: 'Venerdì' },
];
const smartworkingWeekdayOptions = smartworkingDayOptions.filter((option) => option.value !== 'none');
const completionEffectValues = completionEffectOptions.map((option) => option.value);

function clientOptionLabel(field, value) {
    if (field === 'payment_terms_days') {
        return Number(value) === 0 ? 'A vista' : `${value} giorni`;
    }

    return displayValue(value);
}

function clientSelectFieldOptions(field) {
    return [
        { value: '', label: 'Seleziona' },
        ...clientSelectOptions[field].map((value) => ({ value, label: clientOptionLabel(field, value) })),
    ];
}

function namedOptions(source, emptyOption = null) {
    const options = (source || []).map((item) => ({ value: item.id, label: item.name || item.email || item.title || item.id }));

    return emptyOption ? [emptyOption, ...options] : options;
}

function taskDependencyLabel(task) {
    return [task.title, task.client_name, task.due_date ? dateIt(task.due_date) : null]
        .filter(Boolean)
        .join(' · ');
}

const taskDependencyDirectionOptions = [
    { value: 'blocked_by', label: 'Bloccata da' },
    { value: 'blocks', label: 'Blocca' },
];

function taskDependencySelectOptions() {
    const selected = taskDependencyDirection.value === 'blocks' ? (taskForm.dependent_ids || []) : (taskForm.dependency_ids || []);
    const opposite = taskDependencyDirection.value === 'blocks' ? (taskForm.dependency_ids || []) : (taskForm.dependent_ids || []);

    return (props.related.taskDependencyOptions || [])
        .filter((task) => task.id !== props.record.id && task.status !== 'done' && !selected.includes(task.id) && !opposite.includes(task.id))
        .map((task) => ({
            value: task.id,
            label: taskDependencyLabel(task),
        }));
}

function selectedTaskDependencies() {
    const selected = taskForm.dependency_ids || [];
    const byId = new Map([...(props.related.taskDependencyOptions || []), ...(props.related.dependencies || [])].map((task) => [task.id, task]));

    return selected.map((id) => byId.get(id)).filter(Boolean);
}

function selectedTaskDependents() {
    const selected = taskForm.dependent_ids || [];
    const byId = new Map([...(props.related.taskDependencyOptions || []), ...(props.related.dependents || [])].map((task) => [task.id, task]));

    return selected.map((id) => byId.get(id)).filter(Boolean);
}

function projectDrawerDependencyOptions(direction = 'blocked_by') {
    const selected = direction === 'blocks' ? (projectTaskDrawerForm.dependent_ids || []) : (projectTaskDrawerForm.dependency_ids || []);
    const opposite = direction === 'blocks' ? (projectTaskDrawerForm.dependency_ids || []) : (projectTaskDrawerForm.dependent_ids || []);

    return (props.related.taskDependencyOptions || [])
        .filter((task) => task.id !== projectTaskDrawerTask.value?.id && task.status !== 'done' && !selected.includes(task.id) && !opposite.includes(task.id))
        .map((task) => ({ value: task.id, label: taskDependencyLabel(task) }));
}

function selectedProjectDrawerDependencies() {
    const byId = new Map([...(props.related.taskDependencyOptions || []), ...(projectTaskDrawerTask.value?.dependencies || [])].map((task) => [task.id, task]));

    return (projectTaskDrawerForm.dependency_ids || []).map((id) => byId.get(id)).filter(Boolean);
}

function selectedProjectDrawerDependents() {
    const byId = new Map([...(props.related.taskDependencyOptions || []), ...(projectTaskDrawerTask.value?.dependents || [])].map((task) => [task.id, task]));

    return (projectTaskDrawerForm.dependent_ids || []).map((id) => byId.get(id)).filter(Boolean);
}

function blockedDependencyCount(task = null) {
    const dependencies = task?.dependencies || selectedTaskDependencies();

    return dependencies.filter((dependency) => dependency.status !== 'done').length;
}

function projectTaskDependencyPreviewLabel(task) {
    const dependenciesCount = (task?.dependencies || []).length;
    const dependentsCount = (task?.dependents || []).length;
    const parts = [];

    if (dependenciesCount) {
        parts.push(`Bloccata da ${dependenciesCount} task`);
    }

    if (dependentsCount) {
        parts.push(`Bloccante per ${dependentsCount} task`);
    }

    return parts.join(' · ');
}

function projectTaskDependencyBadges(task) {
    const dependenciesCount = (task?.dependencies || []).length;
    const badges = [];

    if (dependenciesCount) {
        badges.push({
            key: 'blocked',
            icon: GitBranch,
            label: `Bloccata da ${dependenciesCount} task`,
            class: 'bg-rose-50 text-rose-700 ring-rose-100',
        });
    }

    return badges;
}

function syncTaskDependencies() {
    router.put(route('tasks.dependencies.sync', props.record.id), {
        dependency_ids: taskForm.dependency_ids || [],
        dependent_ids: taskForm.dependent_ids || [],
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
    });
}

function addTaskDependency(dependencyId) {
    if (!dependencyId) return;

    if (taskDependencyDirection.value === 'blocks') {
        if ((taskForm.dependent_ids || []).includes(dependencyId) || (taskForm.dependency_ids || []).includes(dependencyId)) return;

        taskForm.dependent_ids = [...(taskForm.dependent_ids || []), dependencyId];
    } else {
        if ((taskForm.dependency_ids || []).includes(dependencyId) || (taskForm.dependent_ids || []).includes(dependencyId)) return;

        taskForm.dependency_ids = [...(taskForm.dependency_ids || []), dependencyId];
    }

    taskDependencyToAdd.value = '';
    syncTaskDependencies();
}

function removeTaskDependency(dependencyId) {
    taskForm.dependency_ids = (taskForm.dependency_ids || []).filter((id) => id !== dependencyId);
    syncTaskDependencies();
}

function removeTaskDependent(dependentId) {
    taskForm.dependent_ids = (taskForm.dependent_ids || []).filter((id) => id !== dependentId);
    syncTaskDependencies();
}

function primitiveOptions(source) {
    return (source || []).map((value) => ({ value, label: displayValue(value) }));
}

function relatedSectionLabel(name) {
    return {
        projects: 'Progetti',
        tasks: 'Task',
        documents: 'Documenti',
    }[name] || displayValue(name);
}

function relatedItemHref(name, item) {
    if (name === 'projects') return route('projects.show', item.id);
    if (name === 'tasks') return route('tasks.show', item.id);
    if (name === 'documents') return route('billing.show', item.id);

    return '#';
}

function relatedItemTitle(name, item) {
    if (name === 'projects') return item.name;
    if (name === 'tasks') return item.title;
    if (name === 'documents') return item.number || docTypeLabel(item.doc_type);

    return item.number || item.action || item.content || '-';
}

function relatedItemMeta(name, item) {
    if (name === 'documents') {
        return [docTypeLabel(item.doc_type), item.status ? docStatusLabel(item.status) : null, item.issue_date ? dateIt(item.issue_date) : null]
            .filter(Boolean)
            .join(' - ');
    }

    return item.status ? displayValue(item.status) : (item.created_at ? dateIt(item.created_at) : '-');
}

function contrastColor(value, light = '#111827', dark = '#ffffff') {
    const color = normalizeHexColor(value || '#ffffff', '#ffffff');
    const red = parseInt(color.slice(1, 3), 16);
    const green = parseInt(color.slice(3, 5), 16);
    const blue = parseInt(color.slice(5, 7), 16);
    const luminance = ((0.2126 * red) + (0.7152 * green) + (0.0722 * blue)) / 255;

    return luminance > 0.62 ? light : dark;
}

function relatedProjectStyle(project) {
    const backgroundColor = normalizeHexColor(project?.color, '#2563eb');

    return {
        backgroundColor,
        color: contrastColor(backgroundColor),
        borderColor: contrastColor(backgroundColor, 'rgba(17,24,39,0.14)', 'rgba(255,255,255,0.22)'),
    };
}

function relatedProjectMetaStyle(project) {
    return {
        color: contrastColor(project?.color, 'rgba(17,24,39,0.64)', 'rgba(255,255,255,0.78)'),
    };
}

function relatedTasksSorted() {
    return (props.related.tasks || [])
        .filter((task) => task.status !== 'done' && !task.parent_task_id)
        .sort((first, second) => {
            const firstDate = first.due_date || '9999-12-31';
            const secondDate = second.due_date || '9999-12-31';
            return firstDate.localeCompare(secondDate) || String(first.title || '').localeCompare(String(second.title || ''), 'it', { sensitivity: 'base' });
        });
}

function relatedItemsFor(name) {
    if (name !== 'tasks') return props.related[name] || [];

    return relatedTasksSorted().slice(0, clientRelatedTasksExpanded.value ? 6 : 3);
}

function hiddenRelatedTaskCount() {
    return Math.max(relatedTasksSorted().slice(0, 6).length - relatedItemsFor('tasks').length, 0);
}

function userKpiClass(tone) {
    return {
        blue: 'border-sky-100 bg-sky-50/80 text-sky-700',
        red: 'border-red-100 bg-red-50/80 text-red-700',
        amber: 'border-amber-100 bg-amber-50/80 text-amber-700',
        green: 'border-emerald-100 bg-emerald-50/80 text-emerald-700',
        violet: 'border-violet-100 bg-violet-50/80 text-violet-700',
        slate: 'border-slate-100 bg-slate-50/80 text-slate-700',
    }[tone] || 'border-gray-100 bg-gray-50/80 text-gray-700';
}

function userStatBarWidth(value, rows) {
    const max = Math.max(1, ...(rows || []).map((row) => Number(row.value || 0)));
    return `${Math.max(5, Math.round((Number(value || 0) / max) * 100))}%`;
}

function userPriorityClass(priority) {
    return {
        urgent: 'bg-red-500',
        high: 'bg-orange-400',
        medium: 'bg-amber-300',
        low: 'bg-emerald-400',
    }[priority] || 'bg-gray-300';
}

function userTaskMeta(task) {
    return [task.client_name, task.project_name, task.due_date ? dateIt(task.due_date) : null, task.due_time ? task.due_time.slice(0, 5) : null]
        .filter(Boolean)
        .join(' - ');
}

const visibleEntries = Object.entries(props.record).filter(([key, value]) =>
    !['id', 'created_by', 'updated_at', 'created_at', 'password', 'remember_token'].includes(key)
    && value !== null
    && value !== ''
);

const commentForm = useForm({ content: '' });
const commentDrafts = ref({});
const commentAutosaveStates = ref({});
const commentAutosaveErrors = ref({});
const commentAutosaveTimers = {};
const commentAutosaveSequences = {};
const editingCommentId = ref(null);
const taskFeedTab = ref('comments');
const showAllTaskComments = ref(false);
const showAllTaskActivity = ref(false);
const taskActionMenuOpen = ref(false);
const taskActionMenuStyle = ref({});
const clientRelatedTasksExpanded = ref(false);
const lineForm = useForm({
    description: '',
    quantity: 1,
    unit_price: 0,
    vat_rate: 22,
    discount_pct: 0,
});
const paymentForm = useForm({
    amount: props.record.total_amount || 0,
    paid_at: new Date().toISOString().slice(0, 10),
    method: '',
    notes: '',
});
const lineDrafts = ref({});
const paymentDrafts = ref({});
const lineAutosaveStates = ref({});
const paymentAutosaveStates = ref({});
const lineAutosaveErrors = ref({});
const paymentAutosaveErrors = ref({});
const lineAutosaveTimers = {};
const paymentAutosaveTimers = {};
const lineAutosaveSequences = {};
const paymentAutosaveSequences = {};
const documentForm = useForm({
    issue_date: props.record.issue_date || new Date().toISOString().slice(0, 10),
    due_date: props.record.due_date || '',
    status: props.record.status || 'draft',
    payment_method: props.record.payment_method || '',
    payment_terms_days: props.record.payment_terms_days || '',
    causale: props.record.causale || '',
    notes: props.record.notes || '',
    footer_notes: props.record.footer_notes || '',
    withholding_pct: props.record.withholding_pct || 0,
    pension_fund_pct: props.record.pension_fund_pct || 0,
    pension_fund_label: props.record.pension_fund_label || '',
});
const documentAutosaveState = ref('idle');
const documentAutosaveError = ref('');
let documentAutosaveTimer = null;
let documentAutosaveSequence = 0;
const emailForm = useForm({
    recipient: props.related?.client?.pec || props.related?.client?.email || '',
    cc: '',
    subject: '',
    message: '',
    include_xml: ['fattura', 'nota_credito'].includes(props.record.doc_type),
});
const contactForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: '',
    notes: '',
});
const contactDrafts = ref({});
const contactAutosaveStates = ref({});
const contactAutosaveErrors = ref({});
const contactAutosaveTimers = {};
const contactAutosaveSequences = {};
const clientServiceIds = ref([...(props.related?.clientServices || [])]);
const serviceToggleStates = ref({});
const serviceToggleErrors = ref({});
const clientForm = useForm({
    name: props.record.name || '',
    legal_name: props.record.legal_name || '',
    vat_number: props.record.vat_number || '',
    tax_code: props.record.tax_code || '',
    legal_form: props.record.legal_form || '',
    business_sector: props.record.business_sector || '',
    source: props.record.source || '',
    country: props.record.country || 'IT',
    street: props.record.street || '',
    street_number: props.record.street_number || '',
    postal_code: props.record.postal_code || '',
    city: props.record.city || '',
    province: props.record.province || '',
    email: props.record.email || '',
    phone: props.record.phone || '',
    website: props.record.website || '',
    pec: props.record.pec || '',
    sdi_code: props.record.sdi_code || '',
    iban: props.record.iban || '',
    bic_swift: props.record.bic_swift || '',
    vat_treatment: props.record.vat_treatment || '',
    payment_terms_days: props.record.payment_terms_days || '',
    is_pa: Boolean(props.record.is_pa),
    notes: props.record.notes || '',
});
const clientAutosaveState = ref('idle');
const clientAutosaveError = ref('');
let clientAutosaveTimer = null;
let clientAutosaveSequence = 0;
const subscriptionDefaults = {
    name: '',
    description: '',
    amount: 0,
    vat_rate: 22,
    vat_nature_code: '',
    frequency_value: 1,
    frequency_unit: 'month',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    next_invoice_date: new Date().toISOString().slice(0, 10),
    payment_terms_days: props.record.payment_terms_days || 30,
    auto_generate: true,
    active: true,
    notes: '',
};
const subscriptionForm = useForm({ ...subscriptionDefaults });
const subscriptionsOpen = ref(false);
const editingSubscription = ref(null);
const confirmAction = ref(null);
const confirmText = ref('');
const taskForm = useForm({
    title: props.record.title || '',
    description: props.record.description || '',
    project_id: props.record.project_id || '',
    client_id: props.record.client_id || '',
    service_id: props.record.service_id || '',
    task_type: props.record.task_type || 'project',
    status: props.record.status || 'todo',
    priority: props.record.priority || 'medium',
    start_date: props.record.start_date || '',
    due_date: props.record.due_date || '',
    due_time: props.record.due_time ? String(props.record.due_time).slice(0, 5) : '',
    location: props.record.location || '',
    recurring_enabled: Boolean(props.record.recurring_enabled),
    recurring_interval_value: props.record.recurring_interval_value || 1,
    recurring_interval_unit: props.record.recurring_interval_unit || 'week',
    recurring_mode: props.record.recurring_mode || 'fixed',
    recurring_weekday: props.record.recurring_weekday || 1,
    recurring_month_day: props.record.recurring_month_day || 1,
    assignee_ids: [...(props.related.assignees || [])],
    follower_ids: [...(props.related.followers || [])],
    dependency_ids: (props.related.dependencies || []).map((dependency) => dependency.id),
    dependent_ids: (props.related.dependents || []).map((dependent) => dependent.id),
});
const taskDependencyToAdd = ref('');
const taskDependencyDirection = ref('blocked_by');
const subtaskForm = useForm({
    title: '',
    priority: 'medium',
    due_date: '',
    assignee_ids: [],
});
const subtaskDrafts = ref({});
const subtaskAutosaveStates = ref({});
const subtaskAutosaveErrors = ref({});
const subtaskAssigneeMenuOpen = ref(null);
const subtaskAssigneeMenuStyle = ref({});
const subtaskCreateAssigneeMenuOpen = ref(false);
const subtaskCreateAssigneeMenuStyle = ref({});
const subtaskStatusPulse = ref(null);
const orderedSubtasks = ref([]);
const draggedSubtaskId = ref(null);
const subtaskDropTarget = ref(null);
const subtaskDropPlacement = ref(null);
const subtaskAutosaveTimers = {};
const subtaskAutosaveSequences = {};
const selectedAssignees = ref([...(props.related.assignees || [])]);
const selectedFollowers = ref([...(props.related.followers || [])]);
const taskAutosaveState = ref('idle');
const taskAutosaveError = ref('');
const lastOpenTaskStatus = ref(taskForm.status === 'done' ? 'todo' : taskForm.status);
let taskAutosaveTimer = null;
let taskAutosaveSequence = 0;
const taskDescriptionEditor = ref(null);
const projectForm = useForm({
    name: props.record.name || '',
    description: props.record.description || '',
    client_id: props.record.client_id || '',
    status: props.record.status || 'active',
    color: props.record.color || '#2563eb',
    user_ids: [...(props.related.followers || [])],
});
const selectedProjectFollowers = ref([...(props.related.followers || [])]);
const projectAutosaveState = ref('idle');
const projectAutosaveError = ref('');
let projectAutosaveTimer = null;
let projectAutosaveSequence = 0;
const projectWorkspaceTab = ref('overview');
const projectDescriptionEditor = ref(null);
const projectMessageEditor = ref(null);
const projectResourceInput = ref(null);
const projectFileInput = ref(null);
const projectFileDragActive = ref(false);
const projectMessageForm = useForm({ content: '' });
const projectFileForm = useForm({ file: null, kind: 'file' });
const projectSectionCollapsed = ref({});
const projectSectionDrafts = ref({});
const projectSectionSaveTimers = {};
const projectTaskDrafts = ref({});
const projectNewSectionName = ref('');
const projectNewSectionOpen = ref(false);
const projectNewSectionInput = ref(null);
const projectSectionActionMenuOpen = ref(null);
const projectSectionActionMenuPlacement = ref('down');
const projectTaskDrawerOpen = ref(false);
const projectTaskDrawerTask = ref(null);
const projectTaskParentStack = ref([]);
const projectTaskDrawerForm = useForm({
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
const projectTaskDrawerDescriptionEditor = ref(null);
const projectTaskDrawerFeedTab = ref('comments');
const projectDrawerDependencyDirection = ref('blocked_by');
const projectDrawerDependencyToAdd = ref('');
const projectDrawerShowAllComments = ref(false);
const projectDrawerShowAllActivity = ref(false);
const projectDrawerSubtaskForm = useForm({ title: '', priority: 'medium', due_date: '', assignee_ids: [] });
const projectDrawerCreateSubtaskAssigneeIds = ref([]);
const projectDrawerCommentForm = useForm({ content: '' });
const projectDrawerCommentEditor = ref(null);
const projectTaskActionMenuOpen = ref(false);
const projectTaskActionMenuStyle = ref({});
const draggedProjectTaskId = ref(null);
const projectTaskDropTarget = ref(null);
const projectTaskDropPlacement = ref(null);
const projectTaskDropSectionId = ref(null);
let projectTaskDrawerAutosaveTimer = null;
const projectColors = ['#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#475569'];
const userForm = useForm({
    name: props.record.name || '',
    email: props.record.email || '',
    role: props.record.role || 'guest',
    employee_code: props.record.employee_code || '',
    job_title: props.record.job_title || '',
    phone: props.record.phone || '',
    bio: props.record.bio || '',
    completion_effect: completionEffectValues.includes(props.record.completion_effect) ? props.record.completion_effect : 'balloons',
    smartworking_day: props.record.smartworking_day || 'none',
    password: '',
});
const userPerformance = computed(() => props.related?.performance || {
    kpis: [],
    completionRate: 0,
    status: [],
    priority: [],
    upcomingTasks: [],
    recentCompletedTasks: [],
    absence: {},
});
const userAutosaveState = ref('idle');
const userAutosaveError = ref('');
let userAutosaveTimer = null;
let userAutosaveSequence = 0;
const userAvatarInput = ref(null);
const userAvatarPreview = ref(null);
const userAvatarForm = useForm({ avatar: null });
const clientFiscalOpen = ref(false);
const absenceForm = useForm({
    type: props.record.type || 'vacation',
    start_date: props.record.start_date || '',
    end_date: props.record.end_date || props.record.start_date || '',
    start_time: props.record.start_time ? String(props.record.start_time).slice(0, 5) : '',
    end_time: props.record.end_time ? String(props.record.end_time).slice(0, 5) : '',
    inps_code: props.record.inps_code || '',
    status: props.record.status || 'pending',
    notes: props.record.notes || '',
});
const absenceAutosaveState = ref('idle');
const absenceAutosaveError = ref('');
let absenceAutosaveTimer = null;
let absenceAutosaveSequence = 0;
const absenceNotesEditor = ref(null);
const absenceMedicalDocumentInput = ref(null);
const absenceMedicalDocumentForm = useForm({ medical_document: null });

function normalizeHexColor(value, fallback = '#2563eb') {
    const color = String(value || '').trim();
    if (/^#[0-9a-f]{6}$/i.test(color)) return color;
    if (/^#[0-9a-f]{3}$/i.test(color)) {
        return `#${color.slice(1).split('').map((char) => char + char).join('')}`;
    }
    return fallback;
}

function backHref() {
    if (props.section === 'tasks' && props.related?.parentTask) {
        return route('tasks.show', props.related.parentTask.id);
    }

    return route(`${props.section}.index`);
}

function backLabel() {
    if (props.section === 'tasks' && props.related?.parentTask) {
        return `Torna a ${props.related.parentTask.title || 'task genitore'}`;
    }

    return `Torna a ${props.title}`;
}

function absenceNeedsEndDate(type = absenceForm.type) {
    return ['vacation', 'sickness', 'other'].includes(type);
}

function absenceNeedsTime(type = absenceForm.type) {
    return ['permission', 'late', 'other'].includes(type);
}

function absencePayload() {
    return {
        type: absenceForm.type,
        start_date: absenceForm.start_date,
        end_date: absenceNeedsEndDate() ? (absenceForm.end_date || absenceForm.start_date) : absenceForm.start_date,
        start_time: absenceNeedsTime() ? (absenceForm.start_time || null) : null,
        end_time: absenceNeedsTime() ? (absenceForm.end_time || null) : null,
        inps_code: absenceForm.type === 'sickness' ? (absenceForm.inps_code || null) : null,
        status: absenceForm.status,
        notes: absenceForm.notes || null,
    };
}

function updateAbsenceNotesFromEditor() {
    absenceForm.notes = absenceNotesEditor.value?.innerHTML || '';
}

function refreshAbsenceNotesEditor() {
    nextTick(() => {
        if (absenceNotesEditor.value && absenceNotesEditor.value.innerHTML !== (absenceForm.notes || '')) {
            absenceNotesEditor.value.innerHTML = absenceForm.notes || '';
        }
    });
}

function runAbsenceNotesCommand(command, value = null) {
    absenceNotesEditor.value?.focus();
    document.execCommand(command, false, value);
    updateAbsenceNotesFromEditor();
    saveAbsenceInline();
}

function addAbsenceNotesLink() {
    const url = window.prompt('URL del link');
    if (!url) return;

    runAbsenceNotesCommand('createLink', url);
}

function chooseAbsenceMedicalDocument() {
    absenceMedicalDocumentInput.value?.click();
}

function uploadAbsenceMedicalDocument(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    absenceMedicalDocumentForm.medical_document = file;
    absenceMedicalDocumentForm.post(route('absences.medical-document.update', props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            absenceMedicalDocumentForm.reset();
            if (absenceMedicalDocumentInput.value) absenceMedicalDocumentInput.value.value = '';
        },
    });
}

function saveAbsenceInline(delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'absences') return;
    if (!absenceForm.start_date) return;

    window.clearTimeout(absenceAutosaveTimer);
    absenceAutosaveState.value = 'queued';
    absenceAutosaveError.value = '';

    absenceAutosaveTimer = window.setTimeout(() => {
        const sequence = ++absenceAutosaveSequence;
        absenceAutosaveState.value = 'saving';
        absenceForm.transform(() => absencePayload()).put(route('absences.update', props.record.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== absenceAutosaveSequence) return;
                absenceAutosaveState.value = 'saved';
                window.setTimeout(() => {
                    if (absenceAutosaveState.value === 'saved') absenceAutosaveState.value = 'idle';
                }, 1600);
            },
            onError: () => {
                if (sequence !== absenceAutosaveSequence) return;
                absenceAutosaveState.value = 'error';
                absenceAutosaveError.value = 'Non salvato';
            },
            onFinish: () => absenceForm.transform((data) => data),
        });
    }, autosaveDelay(delay));
}

function setAbsenceStatus(status) {
    absenceForm.status = status;
    router.patch(route('absences.status.update', props.record.id), { status }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function deleteAbsenceFromDetail() {
    confirmAction.value = {
        title: 'Elimina richiesta',
        description: 'Vuoi eliminare questa richiesta assenza?',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('absences.destroy', props.record.id), {
            preserveScroll: true,
            onSuccess: () => router.visit(route('absences.index')),
            onFinish: closeConfirm,
        }),
    };
    confirmText.value = '';
}

function updateTaskDescriptionFromEditor() {
    taskForm.description = taskDescriptionEditor.value?.innerHTML || '';
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

function commentEditorSelector(commentId = 'new') {
    return `[data-task-comment-editor="${commentId}"]`;
}

function commentEditorElement(commentId = 'new') {
    return document.querySelector(commentEditorSelector(commentId));
}

function updateCommentFromEditor(commentId = 'new') {
    const html = commentEditorElement(commentId)?.innerHTML || '';

    if (commentId === 'new') {
        commentForm.content = html;
        return;
    }

    if (commentDrafts.value[commentId]) {
        commentDrafts.value[commentId].content = html;
    }
}

function refreshCommentEditor(commentId = 'new') {
    nextTick(() => {
        const editor = commentEditorElement(commentId);
        if (!editor) return;

        const html = commentId === 'new'
            ? commentForm.content || ''
            : commentDrafts.value[commentId]?.content || '';

        if (editor.innerHTML !== html) {
            editor.innerHTML = html;
        }
    });
}

function runCommentEditorCommand(commentId, command, value = null) {
    const editor = commentEditorElement(commentId);
    editor?.focus();
    document.execCommand(command, false, value);
    updateCommentFromEditor(commentId);
}

function addCommentEditorLink(commentId = 'new') {
    const url = window.prompt('URL del link');
    if (!url) return;

    runCommentEditorCommand(commentId, 'createLink', url);
}

function addComment() {
    updateCommentFromEditor('new');
    if (!String(commentForm.content || '').trim()) return;

    commentForm.post(route('tasks.comments.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            refreshCommentEditor('new');
        },
    });
}

function commentDraftPayload(commentId) {
    return {
        content: commentDrafts.value[commentId]?.content || '',
    };
}

function saveCommentInline(comment, delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'tasks') return;

    updateCommentFromEditor(comment.id);
    const payload = commentDraftPayload(comment.id);
    if (!String(payload.content).trim()) {
        setInlineState(commentAutosaveStates, comment.id, 'idle');
        return;
    }

    window.clearTimeout(commentAutosaveTimers[comment.id]);
    setInlineState(commentAutosaveStates, comment.id, 'queued');
    setInlineState(commentAutosaveErrors, comment.id, '');

    commentAutosaveTimers[comment.id] = window.setTimeout(() => {
        const sequence = (commentAutosaveSequences[comment.id] || 0) + 1;
        commentAutosaveSequences[comment.id] = sequence;
        setInlineState(commentAutosaveStates, comment.id, 'saving');
        router.put(route('tasks.comments.update', [props.record.id, comment.id]), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== commentAutosaveSequences[comment.id]) return;
                setInlineState(commentAutosaveStates, comment.id, 'saved');
                window.setTimeout(() => {
                    if (commentAutosaveStates.value[comment.id] === 'saved') {
                        setInlineState(commentAutosaveStates, comment.id, 'idle');
                    }
                }, 1400);
            },
            onError: () => {
                if (sequence !== commentAutosaveSequences[comment.id]) return;
                setInlineState(commentAutosaveStates, comment.id, 'error');
                setInlineState(commentAutosaveErrors, comment.id, 'Non salvato');
            },
        });
    }, autosaveDelay(delay));
}

function removeComment(comment) {
    openConfirm({
        title: 'Eliminare questo commento?',
        description: comment.content || 'Commento',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('tasks.comments.destroy', [props.record.id, comment.id]), { preserveScroll: true, onFinish: closeConfirm }),
    });
}

function editComment(comment) {
    editingCommentId.value = comment.id;
    if (!commentDrafts.value[comment.id]) {
        commentDrafts.value = {
            ...commentDrafts.value,
            [comment.id]: { content: comment.content || '' },
        };
    }
    refreshCommentEditor(comment.id);
}

function stopEditingComment(comment) {
    if (editingCommentId.value !== comment.id) return;

    saveCommentInline(comment, 0);
    editingCommentId.value = null;
}

function setTaskStatus(status) {
    const wasDone = taskForm.status === 'done';
    if (status === 'done' && !wasDone && blockedDependencyCount() > 0) {
        taskForm.status = lastOpenTaskStatus.value || 'todo';
        taskAutosaveState.value = 'error';
        taskAutosaveError.value = 'Task bloccata dalle dipendenze.';
        return;
    }

    taskForm.status = status;
    taskAutosaveState.value = 'saving';
    taskAutosaveError.value = '';

    router.patch(route('tasks.status.update', props.record.id), { status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            taskAutosaveState.value = 'saved';
            if (status !== 'done') {
                lastOpenTaskStatus.value = status;
            }
            if (!wasDone && status === 'done') {
                window.dispatchEvent(new CustomEvent('centro:task-completed'));
            }
            window.setTimeout(() => {
                if (taskAutosaveState.value === 'saved') {
                    taskAutosaveState.value = 'idle';
                }
            }, 1800);
        },
        onError: () => {
            taskAutosaveState.value = 'error';
            taskAutosaveError.value = 'Stato non salvato';
        },
    });
}

function taskPayload() {
    return {
        title: taskForm.title,
        description: taskForm.description,
        project_id: taskForm.project_id,
        client_id: taskForm.client_id,
        service_id: taskForm.service_id,
        task_type: taskForm.task_type,
        status: taskForm.status,
        priority: taskForm.priority,
        start_date: taskForm.start_date,
        due_date: taskForm.due_date,
        due_time: taskForm.due_time,
        location: taskForm.location,
        recurring_enabled: taskForm.recurring_enabled,
        recurring_interval_value: taskForm.recurring_interval_value,
        recurring_interval_unit: taskForm.recurring_interval_unit,
        recurring_mode: taskForm.recurring_mode,
        recurring_weekday: taskForm.recurring_weekday,
        recurring_month_day: taskForm.recurring_month_day,
    };
}

function saveTaskInline(delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'tasks') return;

    window.clearTimeout(taskAutosaveTimer);
    taskAutosaveState.value = 'queued';
    taskAutosaveError.value = '';

    taskAutosaveTimer = window.setTimeout(() => {
        const sequence = ++taskAutosaveSequence;
        taskAutosaveState.value = 'saving';
        taskForm.transform(() => taskPayload()).put(route('tasks.update', props.record.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== taskAutosaveSequence) return;
                taskAutosaveState.value = 'saved';
                window.setTimeout(() => {
                    if (taskAutosaveState.value === 'saved') {
                        taskAutosaveState.value = 'idle';
                    }
                }, 1800);
            },
            onError: () => {
                if (sequence !== taskAutosaveSequence) return;
                taskAutosaveState.value = 'error';
                taskAutosaveError.value = 'Non salvato';
            },
            onFinish: () => {
                taskForm.transform((data) => data);
            },
        });
    }, autosaveDelay(delay));
}

function saveTaskDetails() {
    taskForm.assignee_ids = [...selectedAssignees.value];
    taskForm.follower_ids = [...selectedFollowers.value];
    taskForm.put(route('tasks.update', props.record.id), { preserveScroll: true });
}

function setTaskType(type) {
    taskForm.task_type = type;
    if (type === 'project' || type === 'task') {
        taskForm.location = '';
    }
    if (type === 'ongoing') {
        taskForm.project_id = '';
        taskForm.location = '';
    }
    if (type === 'meeting') {
        taskForm.project_id = '';
        taskForm.recurring_enabled = false;
        taskForm.due_time = taskForm.due_time || '09:00';
    }
    saveTaskInline(0);
}

function toggleTaskPerson(type, userId) {
    const list = type === 'assignees' ? selectedAssignees : selectedFollowers;
    togglePerson(list, userId);
    taskAutosaveState.value = 'saving';
    taskAutosaveError.value = '';

    router.put(route('tasks.people.sync', [props.record.id, type]), {
        user_ids: [...list.value],
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            taskAutosaveState.value = 'saved';
            window.setTimeout(() => {
                if (taskAutosaveState.value === 'saved') {
                    taskAutosaveState.value = 'idle';
                }
            }, 1800);
        },
        onError: () => {
            taskAutosaveState.value = 'error';
            taskAutosaveError.value = 'Persone non salvate';
        },
    });
}

function toggleTaskComplete() {
    if (taskForm.status !== 'done' && blockedDependencyCount() > 0) {
        taskAutosaveState.value = 'error';
        taskAutosaveError.value = 'Task bloccata dalle dipendenze.';
        return;
    }

    setTaskStatus(taskForm.status === 'done' ? 'todo' : 'done');
}

function duplicateTask() {
    taskActionMenuOpen.value = false;
    router.post(route('tasks.duplicate', props.record.id));
}

async function copyTaskLink() {
    taskActionMenuOpen.value = false;
    const href = route('tasks.show', props.record.id);
    const absoluteHref = href.startsWith('http') ? href : `${window.location.origin}${href}`;

    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(absoluteHref);
    }
}

function printTask() {
    taskActionMenuOpen.value = false;
    window.print();
}

function deleteTaskFromDetail() {
    if (!canDeleteCurrentTask.value) return;
    taskActionMenuOpen.value = false;
    openConfirm({
        title: 'Eliminare questa task?',
        description: props.record.title || 'Task',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('tasks.destroy', props.record.id), {
            preserveScroll: true,
            onFinish: closeConfirm,
        }),
    });
}

function toggleTaskActionMenu(event = null) {
    const nextOpen = !taskActionMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    taskActionMenuStyle.value = dropdownMenuStyleFromEvent(event, 220);
    taskActionMenuOpen.value = nextOpen;
}

function closeTaskActionMenuOnOutside(event) {
    if (taskActionMenuOpen.value && !(event.target instanceof Element && event.target.closest('[data-task-actions-menu]'))) {
        taskActionMenuOpen.value = false;
    }
}

function visibleTaskComments() {
    const comments = props.related?.comments || [];
    return showAllTaskComments.value ? comments : comments.slice(0, 3);
}

function hiddenTaskCommentsCount() {
    return Math.max(0, (props.related?.comments || []).length - 3);
}

function visibleTaskActivity() {
    const activity = props.related?.activity || [];
    return showAllTaskActivity.value ? activity : activity.slice(0, 3);
}

function hiddenTaskActivityCount() {
    return Math.max(0, (props.related?.activity || []).length - 3);
}

function priorityClass(priority) {
    return {
        urgent: 'bg-red-100 text-red-700',
        high: 'bg-orange-100 text-orange-700',
        medium: 'bg-amber-100 text-amber-700',
        low: 'bg-emerald-100 text-emerald-700',
    }[priority] || 'bg-gray-100 text-gray-700';
}

function setSubtaskStatus(subtask, done) {
    const wasDone = (subtaskDrafts.value[subtask.id]?.status || subtask.status) === 'done';
    pulseSubtaskStatus(subtask.id);
    const status = done ? 'done' : 'todo';
    if (subtaskDrafts.value[subtask.id]) {
        subtaskDrafts.value[subtask.id].status = status;
    }
    setInlineState(subtaskAutosaveStates, subtask.id, 'saving');
    setInlineState(subtaskAutosaveErrors, subtask.id, '');
    router.patch(route('tasks.status.update', subtask.id), { status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            setInlineState(subtaskAutosaveStates, subtask.id, 'saved');
            if (!wasDone && status === 'done') {
                window.dispatchEvent(new CustomEvent('centro:task-completed'));
            }
            window.setTimeout(() => {
                if (subtaskAutosaveStates.value[subtask.id] === 'saved') {
                    setInlineState(subtaskAutosaveStates, subtask.id, 'idle');
                }
            }, 1400);
        },
        onError: () => {
            if (subtaskDrafts.value[subtask.id]) {
                subtaskDrafts.value[subtask.id].status = subtask.status;
            }
            setInlineState(subtaskAutosaveStates, subtask.id, 'error');
            setInlineState(subtaskAutosaveErrors, subtask.id, 'Non salvato');
        },
    });
}

function subtaskDraftPayload(subtaskId) {
    const draft = subtaskDrafts.value[subtaskId] || {};
    return {
        title: draft.title || '',
        task_type: draft.task_type || 'task',
        status: draft.status || 'todo',
        priority: draft.priority || 'medium',
        project_id: draft.project_id || '',
        client_id: draft.client_id || '',
        service_id: draft.service_id || '',
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

function saveSubtaskInline(subtask, delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'tasks' && !projectTaskDrawerOpen.value) return;

    const payload = subtaskDraftPayload(subtask.id);
    if (!String(payload.title).trim()) {
        setInlineState(subtaskAutosaveStates, subtask.id, 'idle');
        return;
    }

    window.clearTimeout(subtaskAutosaveTimers[subtask.id]);
    setInlineState(subtaskAutosaveStates, subtask.id, 'queued');
    setInlineState(subtaskAutosaveErrors, subtask.id, '');

    subtaskAutosaveTimers[subtask.id] = window.setTimeout(() => {
        const sequence = (subtaskAutosaveSequences[subtask.id] || 0) + 1;
        subtaskAutosaveSequences[subtask.id] = sequence;
        setInlineState(subtaskAutosaveStates, subtask.id, 'saving');
        router.put(route('tasks.update', subtask.id), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== subtaskAutosaveSequences[subtask.id]) return;
                setInlineState(subtaskAutosaveStates, subtask.id, 'saved');
                window.setTimeout(() => {
                    if (subtaskAutosaveStates.value[subtask.id] === 'saved') {
                        setInlineState(subtaskAutosaveStates, subtask.id, 'idle');
                    }
                }, 1400);
            },
            onError: () => {
                if (sequence !== subtaskAutosaveSequences[subtask.id]) return;
                setInlineState(subtaskAutosaveStates, subtask.id, 'error');
                setInlineState(subtaskAutosaveErrors, subtask.id, 'Non salvato');
            },
        });
    }, autosaveDelay(delay));
}

function addLine() {
    lineForm.post(route('billing.lines.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => lineForm.reset(),
    });
}

function removeLine(line) {
    openConfirm({
        title: 'Eliminare questa riga?',
        description: line.description || 'Riga documento',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('billing.lines.destroy', [props.record.id, line.id]), { preserveScroll: true, onFinish: closeConfirm }),
    });
}

function addPayment() {
    paymentForm.post(route('billing.payments.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset(),
    });
}

function removePayment(payment) {
    openConfirm({
        title: 'Eliminare questo pagamento?',
        description: `${money(payment.amount)} del ${dateIt(payment.paid_at)}`,
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('billing.payments.destroy', [props.record.id, payment.id]), { preserveScroll: true, onFinish: closeConfirm }),
    });
}

function lineDraftPayload(lineId) {
    const draft = lineDrafts.value[lineId] || {};
    return {
        description: draft.description || '',
        quantity: draft.quantity ?? 0,
        unit_price: draft.unit_price ?? 0,
        vat_rate: draft.vat_rate ?? 0,
        discount_pct: draft.discount_pct ?? 0,
    };
}

function paymentDraftPayload(paymentId) {
    const draft = paymentDrafts.value[paymentId] || {};
    return {
        amount: draft.amount ?? 0,
        paid_at: draft.paid_at || new Date().toISOString().slice(0, 10),
        method: draft.method || '',
        notes: draft.notes || '',
    };
}

function setInlineState(bucket, id, value) {
    bucket.value = { ...bucket.value, [id]: value };
}

function saveLineInline(line, delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'billing') return;

    const payload = lineDraftPayload(line.id);
    if (!String(payload.description).trim()) {
        setInlineState(lineAutosaveStates, line.id, 'idle');
        return;
    }

    window.clearTimeout(lineAutosaveTimers[line.id]);
    setInlineState(lineAutosaveStates, line.id, 'queued');
    setInlineState(lineAutosaveErrors, line.id, '');

    lineAutosaveTimers[line.id] = window.setTimeout(() => {
        const sequence = (lineAutosaveSequences[line.id] || 0) + 1;
        lineAutosaveSequences[line.id] = sequence;
        setInlineState(lineAutosaveStates, line.id, 'saving');
        router.put(route('billing.lines.update', [props.record.id, line.id]), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== lineAutosaveSequences[line.id]) return;
                setInlineState(lineAutosaveStates, line.id, 'saved');
                window.setTimeout(() => {
                    if (lineAutosaveStates.value[line.id] === 'saved') {
                        setInlineState(lineAutosaveStates, line.id, 'idle');
                    }
                }, 1600);
            },
            onError: () => {
                if (sequence !== lineAutosaveSequences[line.id]) return;
                setInlineState(lineAutosaveStates, line.id, 'error');
                setInlineState(lineAutosaveErrors, line.id, 'Non salvato');
            },
        });
    }, autosaveDelay(delay));
}

function savePaymentInline(payment, delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'billing') return;

    const payload = paymentDraftPayload(payment.id);
    if (!payload.paid_at) {
        setInlineState(paymentAutosaveStates, payment.id, 'idle');
        return;
    }

    window.clearTimeout(paymentAutosaveTimers[payment.id]);
    setInlineState(paymentAutosaveStates, payment.id, 'queued');
    setInlineState(paymentAutosaveErrors, payment.id, '');

    paymentAutosaveTimers[payment.id] = window.setTimeout(() => {
        const sequence = (paymentAutosaveSequences[payment.id] || 0) + 1;
        paymentAutosaveSequences[payment.id] = sequence;
        setInlineState(paymentAutosaveStates, payment.id, 'saving');
        router.put(route('billing.payments.update', [props.record.id, payment.id]), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== paymentAutosaveSequences[payment.id]) return;
                setInlineState(paymentAutosaveStates, payment.id, 'saved');
                window.setTimeout(() => {
                    if (paymentAutosaveStates.value[payment.id] === 'saved') {
                        setInlineState(paymentAutosaveStates, payment.id, 'idle');
                    }
                }, 1600);
            },
            onError: () => {
                if (sequence !== paymentAutosaveSequences[payment.id]) return;
                setInlineState(paymentAutosaveStates, payment.id, 'error');
                setInlineState(paymentAutosaveErrors, payment.id, 'Non salvato');
            },
        });
    }, autosaveDelay(delay));
}

function autosaveLabel(state, error = '') {
    if (state === 'queued') return 'In attesa';
    if (state === 'saving') return 'Salvo';
    if (state === 'saved') return 'Salvato';
    if (state === 'error') return error || 'Errore';
    return '';
}

function documentPayload() {
    return {
        issue_date: documentForm.issue_date,
        due_date: documentForm.due_date,
        status: documentForm.status,
        payment_method: documentForm.payment_method,
        payment_terms_days: documentForm.payment_terms_days,
        causale: documentForm.causale,
        notes: documentForm.notes,
        footer_notes: documentForm.footer_notes,
        withholding_pct: documentForm.withholding_pct,
        pension_fund_pct: documentForm.pension_fund_pct,
        pension_fund_label: documentForm.pension_fund_label,
    };
}

function saveDocumentInline(delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'billing') return;

    window.clearTimeout(documentAutosaveTimer);
    documentAutosaveState.value = 'queued';
    documentAutosaveError.value = '';

    documentAutosaveTimer = window.setTimeout(() => {
        const sequence = ++documentAutosaveSequence;
        documentAutosaveState.value = 'saving';
        documentForm.transform(() => documentPayload()).put(route('billing.header.update', props.record.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== documentAutosaveSequence) return;
                documentAutosaveState.value = 'saved';
                window.setTimeout(() => {
                    if (documentAutosaveState.value === 'saved') {
                        documentAutosaveState.value = 'idle';
                    }
                }, 1800);
            },
            onError: () => {
                if (sequence !== documentAutosaveSequence) return;
                documentAutosaveState.value = 'error';
                documentAutosaveError.value = 'Non salvato';
            },
            onFinish: () => {
                documentForm.transform((data) => data);
            },
        });
    }, autosaveDelay(delay));
}

function issueDocument() {
    openConfirm({
        title: 'Emettere il documento?',
        description: 'Verrà assegnato il numero progressivo e il documento non resterà più una semplice bozza.',
        keyword: 'EMETTI',
        button: 'Emetti',
        danger: false,
        action: () => router.post(route('billing.issue', props.record.id), {}, { preserveScroll: true, onFinish: closeConfirm }),
    });
}

function duplicateDocument() {
    router.post(route('billing.duplicate', props.record.id));
}

function convertDocument(type) {
    router.post(route('billing.convert', [props.record.id, type]));
}

function printDocument() {
    window.print();
}

function sendDocumentEmail() {
    emailForm.post(route('billing.email', props.record.id), { preserveScroll: true });
}

function addContact() {
    if (!canEditClient.value) return;
    contactForm.post(route('clients.contacts.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => contactForm.reset(),
    });
}

function contactDraftPayload(contactId) {
    const draft = contactDrafts.value[contactId] || {};
    return {
        first_name: draft.first_name || '',
        last_name: draft.last_name || '',
        email: draft.email || '',
        phone: draft.phone || '',
        role: draft.role || '',
        notes: draft.notes || '',
    };
}

function saveContactInline(contact, delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'clients' || !canEditClient.value) return;

    const payload = contactDraftPayload(contact.id);
    if (!String(payload.first_name).trim() || !String(payload.last_name).trim()) {
        setInlineState(contactAutosaveStates, contact.id, 'idle');
        return;
    }

    window.clearTimeout(contactAutosaveTimers[contact.id]);
    setInlineState(contactAutosaveStates, contact.id, 'queued');
    setInlineState(contactAutosaveErrors, contact.id, '');

    contactAutosaveTimers[contact.id] = window.setTimeout(() => {
        const sequence = (contactAutosaveSequences[contact.id] || 0) + 1;
        contactAutosaveSequences[contact.id] = sequence;
        setInlineState(contactAutosaveStates, contact.id, 'saving');
        router.put(route('clients.contacts.update', [props.record.id, contact.id]), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== contactAutosaveSequences[contact.id]) return;
                setInlineState(contactAutosaveStates, contact.id, 'saved');
                window.setTimeout(() => {
                    if (contactAutosaveStates.value[contact.id] === 'saved') {
                        setInlineState(contactAutosaveStates, contact.id, 'idle');
                    }
                }, 1600);
            },
            onError: () => {
                if (sequence !== contactAutosaveSequences[contact.id]) return;
                setInlineState(contactAutosaveStates, contact.id, 'error');
                setInlineState(contactAutosaveErrors, contact.id, 'Non salvato');
            },
        });
    }, autosaveDelay(delay));
}

function clientPayload() {
    return {
        name: clientForm.name,
        legal_name: clientForm.legal_name,
        vat_number: clientForm.vat_number,
        tax_code: clientForm.tax_code,
        legal_form: clientForm.legal_form,
        business_sector: clientForm.business_sector,
        source: clientForm.source,
        country: clientForm.country,
        street: clientForm.street,
        street_number: clientForm.street_number,
        postal_code: clientForm.postal_code,
        city: clientForm.city,
        province: clientForm.province,
        email: clientForm.email,
        phone: clientForm.phone,
        website: clientForm.website,
        pec: clientForm.pec,
        sdi_code: clientForm.sdi_code,
        iban: clientForm.iban,
        bic_swift: clientForm.bic_swift,
        vat_treatment: clientForm.vat_treatment,
        payment_terms_days: clientForm.payment_terms_days,
        is_pa: clientForm.is_pa,
        notes: clientForm.notes,
    };
}

function saveClientInline(delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'clients' || !canEditClient.value) return;

    window.clearTimeout(clientAutosaveTimer);
    clientAutosaveState.value = 'queued';
    clientAutosaveError.value = '';

    clientAutosaveTimer = window.setTimeout(() => {
        const sequence = ++clientAutosaveSequence;
        clientAutosaveState.value = 'saving';
        clientForm.transform(() => clientPayload()).put(route('clients.update', props.record.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== clientAutosaveSequence) return;
                clientAutosaveState.value = 'saved';
                window.setTimeout(() => {
                    if (clientAutosaveState.value === 'saved') {
                        clientAutosaveState.value = 'idle';
                    }
                }, 1800);
            },
            onError: () => {
                if (sequence !== clientAutosaveSequence) return;
                clientAutosaveState.value = 'error';
                clientAutosaveError.value = 'Non salvato';
            },
            onFinish: () => {
                clientForm.transform((data) => data);
            },
        });
    }, autosaveDelay(delay));
}

function removeContact(contact) {
    if (!canEditClient.value) return;
    openConfirm({
        title: 'Eliminare questo referente?',
        description: [contact.first_name, contact.last_name].filter(Boolean).join(' ') || contact.email || 'Referente',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('clients.contacts.destroy', [props.record.id, contact.id]), { preserveScroll: true, onFinish: closeConfirm }),
    });
}

function projectPayload() {
    return {
        name: projectForm.name,
        description: projectForm.description,
        client_id: projectForm.client_id,
        status: projectForm.status,
        color: normalizeHexColor(projectForm.color),
    };
}

function saveProjectInline(delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'projects' || !canEditProject.value) return;

    window.clearTimeout(projectAutosaveTimer);
    projectAutosaveState.value = 'queued';
    projectAutosaveError.value = '';

    projectAutosaveTimer = window.setTimeout(() => {
        const sequence = ++projectAutosaveSequence;
        projectAutosaveState.value = 'saving';
        projectForm.color = normalizeHexColor(projectForm.color);
        projectForm.transform(() => projectPayload()).put(route('projects.update', props.record.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== projectAutosaveSequence) return;
                projectAutosaveState.value = 'saved';
                window.setTimeout(() => {
                    if (projectAutosaveState.value === 'saved') {
                        projectAutosaveState.value = 'idle';
                    }
                }, 1800);
            },
            onError: () => {
                if (sequence !== projectAutosaveSequence) return;
                projectAutosaveState.value = 'error';
                projectAutosaveError.value = 'Non salvato';
            },
            onFinish: () => {
                projectForm.transform((data) => data);
            },
        });
    }, autosaveDelay(delay));
}

function updateProjectDescriptionFromEditor() {
    projectForm.description = projectDescriptionEditor.value?.innerHTML || '';
}

function refreshProjectDescriptionEditor() {
    nextTick(() => {
        if (projectDescriptionEditor.value && projectDescriptionEditor.value.innerHTML !== (projectForm.description || '')) {
            projectDescriptionEditor.value.innerHTML = projectForm.description || '';
        }
    });
}

function runProjectDescriptionCommand(command, value = null) {
    projectDescriptionEditor.value?.focus();
    document.execCommand(command, false, value);
    updateProjectDescriptionFromEditor();
}

function addProjectDescriptionLink() {
    const url = window.prompt('URL del link');
    if (!url) return;

    runProjectDescriptionCommand('createLink', url);
}

function updateProjectMessageFromEditor() {
    projectMessageForm.content = projectMessageEditor.value?.innerHTML || '';
}

function runProjectMessageCommand(command, value = null) {
    projectMessageEditor.value?.focus();
    document.execCommand(command, false, value);
    updateProjectMessageFromEditor();
}

function addProjectMessageLink() {
    const url = window.prompt('URL del link');
    if (!url) return;

    runProjectMessageCommand('createLink', url);
}

function submitProjectMessage() {
    if (isGuest.value) return;
    updateProjectMessageFromEditor();
    if (!plainText(projectMessageForm.content).trim()) return;

    projectMessageForm.post(route('projects.messages.store', props.record.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            projectMessageForm.reset();
            if (projectMessageEditor.value) projectMessageEditor.value.innerHTML = '';
        },
    });
}

function chooseProjectFile(kind) {
    if (isGuest.value) return;
    if (kind === 'resource') {
        projectResourceInput.value?.click();
        return;
    }

    projectFileInput.value?.click();
}

function uploadProjectFile(file, kind = 'file') {
    if (!file || isGuest.value) return;

    projectFileForm.file = file;
    projectFileForm.kind = kind;
    projectFileForm.post(route('projects.files.store', props.record.id), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            projectFileForm.reset();
            if (projectResourceInput.value) projectResourceInput.value.value = '';
            if (projectFileInput.value) projectFileInput.value.value = '';
        },
    });
}

function uploadProjectFileFromInput(event, kind = 'file') {
    uploadProjectFile(event.target.files?.[0], kind);
}

function dropProjectFile(event) {
    projectFileDragActive.value = false;
    uploadProjectFile(event.dataTransfer?.files?.[0], 'file');
}

function removeProjectFile(file) {
    if (isGuest.value) return;
    openConfirm({
        title: 'Eliminare questo file?',
        description: file.original_name || file.name || 'File progetto',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('projects.files.destroy', [props.record.id, file.id]), { preserveScroll: true, onFinish: closeConfirm }),
    });
}

function fileSize(size) {
    const bytes = Number(size || 0);
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function userPayload() {
    return {
        name: userForm.name,
        email: userForm.email,
        role: userForm.role,
        employee_code: userForm.employee_code,
        job_title: userForm.job_title,
        phone: userForm.phone,
        bio: userForm.bio,
        completion_effect: userForm.completion_effect,
        smartworking_day: userForm.smartworking_day,
        password: userForm.password,
    };
}

function saveUserInline(delay = AUTOSAVE_IDLE_DELAY) {
    if (props.section !== 'users') return;
    if (!String(userForm.name).trim() || !String(userForm.email).trim()) return;

    window.clearTimeout(userAutosaveTimer);
    userAutosaveState.value = 'queued';
    userAutosaveError.value = '';

    userAutosaveTimer = window.setTimeout(() => {
        const sequence = ++userAutosaveSequence;
        userAutosaveState.value = 'saving';
        userForm.transform(() => userPayload()).put(route('users.update', props.record.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (sequence !== userAutosaveSequence) return;
                userForm.password = '';
                userAutosaveState.value = 'saved';
                window.setTimeout(() => {
                    if (userAutosaveState.value === 'saved') {
                        userAutosaveState.value = 'idle';
                    }
                }, 1800);
            },
            onError: () => {
                if (sequence !== userAutosaveSequence) return;
                userAutosaveState.value = 'error';
                userAutosaveError.value = 'Non salvato';
            },
            onFinish: () => {
                userForm.transform((data) => data);
            },
        });
    }, autosaveDelay(delay));
}

function selectSmartworkingDay(day) {
    userForm.smartworking_day = userForm.smartworking_day === day ? 'none' : day;
    saveUserInline(0);
}

function smartworkingDayShortLabel(day) {
    return {
        monday: 'Lu',
        tuesday: 'Ma',
        wednesday: 'Me',
        thursday: 'Gi',
        friday: 'Ve',
    }[day] || '';
}

function userPreview() {
    return {
        ...props.record,
        name: userForm.name,
        email: userForm.email,
        avatar_url: userAvatarPreview.value || props.record.avatar_url,
    };
}

function chooseUserAvatar() {
    userAvatarInput.value?.click();
}

function uploadUserAvatar(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    userAvatarForm.avatar = file;
    userAvatarPreview.value = URL.createObjectURL(file);
    userAvatarForm.post(route('users.avatar.update', props.record.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            userAutosaveState.value = 'saved';
            window.setTimeout(() => {
                if (userAutosaveState.value === 'saved') {
                    userAutosaveState.value = 'idle';
                }
            }, 1800);
        },
    });
}

function toggleProjectPerson(userId) {
    if (isGuest.value) return;
    togglePerson(selectedProjectFollowers, userId);
    projectAutosaveState.value = 'saving';
    projectAutosaveError.value = '';

    router.put(route('projects.followers.sync', props.record.id), {
        user_ids: [...selectedProjectFollowers.value],
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            projectAutosaveState.value = 'saved';
            window.setTimeout(() => {
                if (projectAutosaveState.value === 'saved') {
                    projectAutosaveState.value = 'idle';
                }
            }, 1800);
        },
        onError: () => {
            projectAutosaveState.value = 'error';
            projectAutosaveError.value = 'Membri non salvati';
        },
    });
}

function deleteProjectFromDetail() {
    if (!canDeleteProject.value) return;
    openConfirm({
        title: 'Eliminare questo progetto?',
        description: props.record.name || 'Progetto',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('projects.destroy', props.record.id), {
            preserveScroll: true,
            onFinish: closeConfirm,
            onSuccess: () => router.visit(route('projects.index')),
        }),
    });
}

function resetSubscriptionForm() {
    if (!canEditClient.value) return;
    editingSubscription.value = null;
    subscriptionForm.clearErrors();
    subscriptionForm.defaults({ ...subscriptionDefaults });
    subscriptionForm.reset();
    Object.assign(subscriptionForm, { ...subscriptionDefaults });
}

function editSubscription(subscription) {
    if (!canEditClient.value) return;
    subscriptionsOpen.value = true;
    editingSubscription.value = subscription;
    subscriptionForm.clearErrors();
    Object.keys(subscriptionDefaults).forEach((key) => {
        subscriptionForm[key] = subscription[key] ?? subscriptionDefaults[key];
    });
}

function saveSubscription() {
    if (!canEditClient.value) return;
    if (editingSubscription.value) {
        subscriptionForm.put(route('clients.subscriptions.update', [props.record.id, editingSubscription.value.id]), {
            preserveScroll: true,
            onSuccess: resetSubscriptionForm,
        });
        return;
    }

    subscriptionForm.post(route('clients.subscriptions.store', props.record.id), {
        preserveScroll: true,
        onSuccess: resetSubscriptionForm,
    });
}

function toggleSubscription(subscription) {
    if (!canEditClient.value) return;
    router.patch(route('clients.subscriptions.active', [props.record.id, subscription.id]), { active: !subscription.active }, { preserveScroll: true });
}

function generateSubscription(subscription) {
    if (!canEditClient.value) return;
    router.post(route('clients.subscriptions.generate', [props.record.id, subscription.id]));
}

function removeSubscription(subscription) {
    if (!canEditClient.value) return;
    openConfirm({
        title: 'Eliminare questo abbonamento?',
        description: subscription.name || 'Abbonamento',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('clients.subscriptions.destroy', [props.record.id, subscription.id]), { preserveScroll: true, onFinish: closeConfirm }),
    });
}

function openConfirm(action) {
    if (isSuperadmin.value && action?.danger && action?.keyword === 'ELIMINA') {
        action.action();
        return;
    }

    confirmAction.value = action;
    confirmText.value = '';
}

function closeConfirm() {
    confirmAction.value = null;
    confirmText.value = '';
}

function runConfirmAction() {
    if (!confirmAction.value || confirmText.value !== confirmAction.value.keyword) return;
    confirmAction.value.action();
}

function clientHasService(service) {
    return clientServiceIds.value.includes(service.id);
}

function setClientService(serviceId, enabled) {
    const ids = new Set(clientServiceIds.value);
    if (enabled) {
        ids.add(serviceId);
    } else {
        ids.delete(serviceId);
    }
    clientServiceIds.value = [...ids];
}

function toggleService(service) {
    if (!canEditClient.value) return;
    const wasEnabled = clientHasService(service);
    const nextEnabled = !wasEnabled;

    setClientService(service.id, nextEnabled);
    setInlineState(serviceToggleStates, service.id, 'saving');
    setInlineState(serviceToggleErrors, service.id, '');

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            setInlineState(serviceToggleStates, service.id, 'saved');
            window.setTimeout(() => {
                if (serviceToggleStates.value[service.id] === 'saved') {
                    setInlineState(serviceToggleStates, service.id, 'idle');
                }
            }, 1400);
        },
        onError: () => {
            setClientService(service.id, wasEnabled);
            setInlineState(serviceToggleStates, service.id, 'error');
            setInlineState(serviceToggleErrors, service.id, 'Non salvato');
        },
    };

    if (nextEnabled) {
        router.post(route('clients.services.attach', [props.record.id, service.id]), {}, options);
        return;
    }

    router.delete(route('clients.services.detach', [props.record.id, service.id]), options);
}

function togglePerson(list, userId) {
    const values = Array.isArray(list) ? list : list.value;
    const index = values.indexOf(userId);
    if (index >= 0) {
        values.splice(index, 1);
        return;
    }

    values.push(userId);
}

function peopleSelected(list, users) {
    const values = Array.isArray(list) ? list : list.value;

    return (users || []).filter((user) => values.includes(user.id));
}

function peopleAvailable(list, users) {
    const values = Array.isArray(list) ? list : list.value;

    return (users || []).filter((user) => !values.includes(user.id));
}

function subtaskAssignees(subtaskId) {
    const selected = subtaskDrafts.value[subtaskId]?.assignee_ids || [];

    return peopleSelected(selected, props.related.users || []);
}

function projectDrawerCreateSubtaskAssignees() {
    return peopleSelected(projectDrawerCreateSubtaskAssigneeIds.value, props.related.users || []);
}

function toggleProjectDrawerCreateSubtaskAssigneeMenu(event = null) {
    const nextOpen = !subtaskCreateAssigneeMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    subtaskCreateAssigneeMenuStyle.value = floatingMenuStyleFromEvent(event);
    subtaskCreateAssigneeMenuOpen.value = nextOpen;
}

function toggleProjectDrawerCreateSubtaskAssignee(userId) {
    const values = [...projectDrawerCreateSubtaskAssigneeIds.value];
    const index = values.indexOf(userId);
    if (index >= 0) {
        values.splice(index, 1);
    } else {
        values.push(userId);
    }
    projectDrawerCreateSubtaskAssigneeIds.value = values;
}

function createSubtaskAssignees() {
    return peopleSelected(subtaskForm.assignee_ids || [], props.related.users || []);
}

function toggleCreateSubtaskAssigneeMenu(event = null) {
    const nextOpen = !subtaskCreateAssigneeMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    subtaskCreateAssigneeMenuStyle.value = floatingMenuStyleFromEvent(event);
    subtaskCreateAssigneeMenuOpen.value = nextOpen;
}

function toggleCreateSubtaskAssignee(userId) {
    const values = [...(subtaskForm.assignee_ids || [])];
    const index = values.indexOf(userId);
    if (index >= 0) {
        values.splice(index, 1);
    } else {
        values.push(userId);
    }
    subtaskForm.assignee_ids = values;
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

function startSubtaskDrag(subtask) {
    draggedSubtaskId.value = subtask.id;
}

function dragOverSubtask(targetSubtask, event) {
    if (!draggedSubtaskId.value || draggedSubtaskId.value === targetSubtask.id) return;
    const rect = event.currentTarget.getBoundingClientRect();
    subtaskDropTarget.value = targetSubtask.id;
    subtaskDropPlacement.value = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
}

function dropSubtask(targetSubtask) {
    const fromId = draggedSubtaskId.value;
    const placement = subtaskDropPlacement.value || 'before';
    draggedSubtaskId.value = null;
    subtaskDropTarget.value = null;
    subtaskDropPlacement.value = null;
    if (!fromId || fromId === targetSubtask.id) return;

    const current = [...orderedSubtasks.value];
    const fromIndex = current.findIndex((subtask) => subtask.id === fromId);
    let toIndex = current.findIndex((subtask) => subtask.id === targetSubtask.id);
    if (fromIndex < 0 || toIndex < 0) return;

    const [moved] = current.splice(fromIndex, 1);
    if (fromIndex < toIndex) toIndex -= 1;
    if (placement === 'after') toIndex += 1;
    current.splice(toIndex, 0, moved);
    orderedSubtasks.value = current;

    router.put(route('tasks.subtasks.reorder', props.record.id), {
        ids: current.map((subtask) => subtask.id),
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function endSubtaskDrag() {
    draggedSubtaskId.value = null;
    subtaskDropTarget.value = null;
    subtaskDropPlacement.value = null;
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
    const estimatedHeight = 160;
    const spaceBelow = window.innerHeight - rect.bottom;
    const top = spaceBelow >= estimatedHeight + 16
        ? rect.bottom + 8
        : Math.max(12, rect.top - estimatedHeight - 8);

    return {
        left: `${left}px`,
        top: `${top}px`,
    };
}

function requestFloatingUiClose() {
    window.dispatchEvent(new CustomEvent('centro:close-floating-ui'));
}

function closeCentroShowFloatingUi() {
    taskActionMenuOpen.value = false;
    projectTaskActionMenuOpen.value = false;
    projectSectionActionMenuOpen.value = null;
    subtaskCreateAssigneeMenuOpen.value = false;
    subtaskAssigneeMenuOpen.value = null;
}

function parentTaskRows(tasks = []) {
    return (tasks || []).filter((task) => !String(task.parent_task_id || '').trim());
}

function projectTaskSections() {
    const sections = [...(props.related.sections || [])];
    const hasUnsectioned = parentTaskRows(props.related.tasks).some((task) => !task.project_section_id);

    return hasUnsectioned
        ? [...sections, { id: '__unsectioned', name: 'Senza fase', virtual: true }]
        : sections;
}

function projectTasksForSection(section) {
    return parentTaskRows(props.related.tasks).filter((task) => {
        if (section.virtual) return !task.project_section_id;

        return task.project_section_id === section.id;
    });
}

function findProjectTaskInRelated(taskId) {
    if (!taskId) return null;

    for (const task of props.related.tasks || []) {
        if (task.id === taskId) return normalizeProjectDrawerTask(task);

        const subtask = (task.subtasks || []).find((row) => row.id === taskId);
        if (subtask) {
            return normalizeProjectDrawerTask({
                ...subtask,
                parent_task_id: subtask.parent_task_id || task.id,
                parent_title: task.title,
            });
        }
    }

    return null;
}

function refreshProjectTaskDrawerTask() {
    const refreshed = findProjectTaskInRelated(projectTaskDrawerTask.value?.id);
    if (refreshed) {
        projectTaskDrawerTask.value = normalizeProjectDrawerTask(refreshed);
        hydrateProjectDrawerSubtaskDrafts(projectTaskDrawerTask.value);
    }
}

async function refreshProjectTaskDrawerFromServer(taskId = projectTaskDrawerTask.value?.id) {
    if (!taskId) return;

    const response = await fetch(route('tasks.snapshot', taskId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) return;

    projectTaskDrawerTask.value = normalizeProjectDrawerTask(await response.json());
    hydrateProjectDrawerSubtaskDrafts(projectTaskDrawerTask.value);
}

async function projectDrawerTaskSnapshot(taskId) {
    if (!taskId) return null;

    const response = await fetch(route('tasks.snapshot', taskId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) return null;

    return normalizeProjectDrawerTask(await response.json());
}

function hydrateProjectDrawerSubtaskDrafts(task) {
    const subtasks = (task?.subtasks || [])
        .filter((subtask) => !subtask.parent_task_id || subtask.parent_task_id === task.id)
        .map((subtask) => normalizeProjectDrawerTask({
            ...subtask,
            parent_task_id: subtask.parent_task_id || task.id,
            parent_title: subtask.parent_title || task.title,
        }));

    seedProjectDrawerSubtaskDrafts(subtasks, task, true);
}

function projectDrawerSubtaskDraft(subtask, task) {
    return {
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

function seedProjectDrawerSubtaskDrafts(subtasks, task, overwrite = false) {
    const nextDrafts = { ...subtaskDrafts.value };
    let changed = false;

    for (const subtask of subtasks || []) {
        if (!subtask.id) continue;

        const current = nextDrafts[subtask.id];
        if (
            overwrite
            || !current
            || (!String(current.title || '').trim() && String(subtask.title || '').trim())
            || (!(current.assignee_ids || []).length && (subtask.assignee_ids || []).length)
        ) {
            nextDrafts[subtask.id] = projectDrawerSubtaskDraft(subtask, task);
            changed = true;
        }
    }

    if (changed) {
        subtaskDrafts.value = nextDrafts;
    }
}

function projectDrawerSubtasks() {
    if (!projectTaskDrawerTask.value?.id) return [];

    const subtasks = (projectTaskDrawerTask.value.subtasks || [])
        .filter((subtask) => !subtask.parent_task_id || subtask.parent_task_id === projectTaskDrawerTask.value.id)
        .map((subtask) => normalizeProjectDrawerTask({
            ...subtask,
            parent_task_id: subtask.parent_task_id || projectTaskDrawerTask.value.id,
            parent_title: subtask.parent_title || projectTaskDrawerTask.value.title,
        }));

    seedProjectDrawerSubtaskDrafts(subtasks, projectTaskDrawerTask.value);

    return subtasks;
}

function normalizeProjectDrawerTask(task) {
    return {
        ...(task || {}),
        assignee_ids: [...(task?.assignee_ids || [])],
        follower_ids: [...(task?.follower_ids || [])],
        dependencies: [...(task?.dependencies || [])],
        dependents: [...(task?.dependents || [])],
        comments: [...(task?.comments || [])],
        activity: [...(task?.activity || [])],
        subtasks: (task?.subtasks || [])
            .filter((subtask) => !subtask.parent_task_id || subtask.parent_task_id === task.id)
            .map((subtask) => ({
                ...subtask,
                assignee_ids: [...(subtask.assignee_ids || [])],
                follower_ids: [...(subtask.follower_ids || [])],
                dependencies: [...(subtask.dependencies || [])],
                dependents: [...(subtask.dependents || [])],
                comments: [...(subtask.comments || [])],
                activity: [...(subtask.activity || [])],
                subtasks: [],
                parent_task_id: subtask.parent_task_id || task.id,
                parent_title: subtask.parent_title || task.title,
            })),
    };
}

function toggleProjectSection(sectionId) {
    projectSectionCollapsed.value = {
        ...projectSectionCollapsed.value,
        [sectionId]: !projectSectionCollapsed.value[sectionId],
    };
}

function showProjectSectionInput() {
    projectNewSectionOpen.value = true;
    nextTick(() => projectNewSectionInput.value?.focus());
}

function projectSectionName(section) {
    return projectSectionDrafts.value[section.id] ?? section.name;
}

function setProjectSectionName(section, value) {
    projectSectionDrafts.value = {
        ...projectSectionDrafts.value,
        [section.id]: value,
    };
}

function saveProjectSectionName(section) {
    if (section.virtual || isGuest.value) return;
    const name = String(projectSectionDrafts.value[section.id] || '').trim();
    if (!name) {
        projectSectionDrafts.value = { ...projectSectionDrafts.value, [section.id]: section.name };
        return;
    }
    if (name === section.name) return;

    router.put(route('projects.sections.update', [props.record.id, section.id]), { name }, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
    });
}

function toggleProjectSectionActionMenu(section, event = null) {
    if (section.virtual || isGuest.value) return;
    const nextOpen = projectSectionActionMenuOpen.value === section.id ? null : section.id;
    if (nextOpen) requestFloatingUiClose();
    const rect = event?.currentTarget?.getBoundingClientRect?.();
    projectSectionActionMenuPlacement.value = rect && window.innerHeight - rect.bottom < 170 ? 'up' : 'down';
    projectSectionActionMenuOpen.value = nextOpen;
}

function closeProjectSectionActionMenu() {
    projectSectionActionMenuOpen.value = null;
}

function duplicateProjectSection(section) {
    if (section.virtual || isGuest.value) return;
    closeProjectSectionActionMenu();
    router.post(route('projects.sections.duplicate', [props.record.id, section.id]), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
    });
}

function collapseProjectSectionFromMenu(section) {
    closeProjectSectionActionMenu();
    projectSectionCollapsed.value = {
        ...projectSectionCollapsed.value,
        [section.id]: !projectSectionCollapsed.value[section.id],
    };
}

function removeProjectSection(section) {
    if (section.virtual || isGuest.value) return;
    closeProjectSectionActionMenu();
    openConfirm({
        title: 'Eliminare questa sezione?',
        description: 'Le task non verranno eliminate: passeranno in "Senza fase".',
        keyword: 'ELIMINA',
        button: 'Elimina sezione',
        danger: true,
        action: () => router.delete(route('projects.sections.destroy', [props.record.id, section.id]), {
            preserveScroll: true,
            preserveState: true,
            only: ['related', 'errors', 'flash'],
            onFinish: closeConfirm,
        }),
    });
}

function projectTaskDrawerPayload() {
    return {
        title: projectTaskDrawerForm.title,
        description: projectTaskDrawerForm.description || '',
        project_id: projectTaskDrawerForm.task_type === 'project' || projectTaskDrawerForm.task_type === 'task'
            ? (projectTaskDrawerForm.project_id || '')
            : '',
        client_id: projectTaskDrawerForm.client_id || projectForm.client_id || '',
        service_id: projectTaskDrawerForm.service_id || '',
        task_type: projectTaskDrawerForm.task_type || 'project',
        status: projectTaskDrawerForm.status || 'todo',
        priority: projectTaskDrawerForm.priority || 'medium',
        start_date: projectTaskDrawerForm.start_date || '',
        due_date: projectTaskDrawerForm.due_date || '',
        due_time: projectTaskDrawerForm.due_time || '',
        location: projectTaskDrawerForm.location || '',
        recurring_enabled: Boolean(projectTaskDrawerForm.recurring_enabled),
        recurring_interval_value: projectTaskDrawerForm.recurring_enabled ? (projectTaskDrawerForm.recurring_interval_value || 1) : '',
        recurring_interval_unit: projectTaskDrawerForm.recurring_enabled ? (projectTaskDrawerForm.recurring_interval_unit || 'week') : '',
        recurring_mode: projectTaskDrawerForm.recurring_enabled ? (projectTaskDrawerForm.recurring_mode || 'fixed') : '',
        recurring_weekday: projectTaskDrawerForm.recurring_enabled ? projectTaskDrawerForm.recurring_weekday : '',
        recurring_month_day: projectTaskDrawerForm.recurring_enabled ? projectTaskDrawerForm.recurring_month_day : '',
        assignee_ids: [...(projectTaskDrawerForm.assignee_ids || [])],
        follower_ids: [...(projectTaskDrawerForm.follower_ids || [])],
    };
}

function openProjectTaskDrawer(task, options = {}) {
    const normalizedTask = normalizeProjectDrawerTask(task);
    closeProjectDrawerFloatingMenus();
    if (options.pushCurrent && projectTaskDrawerTask.value) {
        projectTaskParentStack.value = [...projectTaskParentStack.value, normalizeProjectDrawerTask(projectTaskDrawerTask.value)];
    } else if (!options.keepStack) {
        projectTaskParentStack.value = [];
    }
    projectTaskDrawerTask.value = normalizedTask;
    hydrateProjectDrawerSubtaskDrafts(normalizedTask);
    projectTaskDrawerForm.defaults({
        title: normalizedTask.title || '',
        description: normalizedTask.description || '',
        project_id: normalizedTask.project_id || props.record.id,
        client_id: normalizedTask.client_id || projectForm.client_id || '',
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
    projectTaskDrawerForm.reset();
    projectTaskDrawerFeedTab.value = 'comments';
    projectDrawerDependencyDirection.value = 'blocked_by';
    projectDrawerDependencyToAdd.value = '';
    projectDrawerShowAllComments.value = false;
    projectDrawerShowAllActivity.value = false;
    projectDrawerSubtaskForm.reset();
    projectDrawerCommentForm.reset();
    nextTick(() => {
        if (projectTaskDrawerDescriptionEditor.value) {
            projectTaskDrawerDescriptionEditor.value.innerHTML = projectTaskDrawerForm.description || '';
        }
        if (projectDrawerCommentEditor.value) {
            projectDrawerCommentEditor.value.innerHTML = '';
        }
    });
    projectTaskDrawerOpen.value = true;
    document.body.classList.add('overflow-hidden');
}

function closeProjectDrawerFloatingMenus() {
    projectTaskActionMenuOpen.value = false;
    projectSectionActionMenuOpen.value = null;
    subtaskCreateAssigneeMenuOpen.value = false;
    subtaskAssigneeMenuOpen.value = null;
    projectDrawerDependencyToAdd.value = '';
}

function closeProjectTaskDrawer() {
    projectTaskDrawerOpen.value = false;
    projectTaskDrawerTask.value = null;
    projectTaskParentStack.value = [];
    projectTaskActionMenuOpen.value = false;
    window.clearTimeout(projectTaskDrawerAutosaveTimer);
    document.body.classList.remove('overflow-hidden');
}

function openProjectDrawerSubtask(subtask) {
    openProjectTaskDrawer(subtask, { pushCurrent: true });
    nextTick(() => {
        const drawerBody = document.querySelector('[data-project-task-drawer-body]');
        drawerBody?.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function returnToProjectDrawerParentTask() {
    const parent = projectTaskParentStack.value.at(-1);
    if (!parent) return;

    projectTaskParentStack.value = projectTaskParentStack.value.slice(0, -1);
    openProjectTaskDrawer(findProjectTaskInRelated(parent.id) || parent, { keepStack: true });
    nextTick(() => {
        const drawerBody = document.querySelector('[data-project-task-drawer-body]');
        drawerBody?.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function updateProjectTaskDrawerDescription() {
    projectTaskDrawerForm.description = projectTaskDrawerDescriptionEditor.value?.innerHTML || '';
}

function runProjectTaskDrawerCommand(command, value = null) {
    projectTaskDrawerDescriptionEditor.value?.focus();
    document.execCommand(command, false, value);
    updateProjectTaskDrawerDescription();
    saveProjectTaskDrawer();
}

function saveProjectTaskDrawer(delay = AUTOSAVE_IDLE_DELAY) {
    if (!projectTaskDrawerTask.value?.id || !projectTaskDrawerForm.title) return;
    window.clearTimeout(projectTaskDrawerAutosaveTimer);
    projectTaskDrawerAutosaveTimer = window.setTimeout(() => {
        projectTaskDrawerForm.transform(() => projectTaskDrawerPayload()).put(route('tasks.update', projectTaskDrawerTask.value.id), {
            preserveScroll: true,
            preserveState: true,
            only: ['related', 'errors', 'flash'],
            onSuccess: () => {
                projectTaskDrawerTask.value = {
                    ...projectTaskDrawerTask.value,
                    ...projectTaskDrawerPayload(),
                };
            },
            onFinish: () => projectTaskDrawerForm.transform((data) => data),
        });
    }, autosaveDelay(delay));
}

function projectDrawerComments() {
    return projectTaskDrawerTask.value?.comments || [];
}

function projectDrawerActivity() {
    return projectTaskDrawerTask.value?.activity || [];
}

function visibleProjectDrawerComments() {
    return projectDrawerShowAllComments.value ? projectDrawerComments() : projectDrawerComments().slice(0, 3);
}

function visibleProjectDrawerActivity() {
    return projectDrawerShowAllActivity.value ? projectDrawerActivity() : projectDrawerActivity().slice(0, 3);
}

function toggleProjectTaskType(type) {
    projectTaskDrawerForm.task_type = type;
    if (type === 'meeting') {
        projectTaskDrawerForm.project_id = '';
        projectTaskDrawerForm.due_time = projectTaskDrawerForm.due_time || '09:00';
        projectTaskDrawerForm.recurring_enabled = false;
    } else {
        projectTaskDrawerForm.location = '';
        if (type === 'ongoing') projectTaskDrawerForm.project_id = '';
    }
    saveProjectTaskDrawer(0);
}

function toggleProjectDrawerTaskPerson(field, userId) {
    const values = [...(projectTaskDrawerForm[field] || [])];
    const index = values.indexOf(userId);
    if (index >= 0) values.splice(index, 1);
    else values.push(userId);
    projectTaskDrawerForm[field] = values;

    const type = field === 'assignee_ids' ? 'assignees' : 'followers';
    router.put(route('tasks.people.sync', [projectTaskDrawerTask.value.id, type]), { user_ids: values }, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
        onSuccess: () => {
            projectTaskDrawerTask.value = { ...projectTaskDrawerTask.value, [field]: values };
        },
    });
}

function addProjectDrawerDependency(taskId, direction = 'blocked_by') {
    if (!taskId) return;
    if ((projectTaskDrawerForm.dependency_ids || []).includes(taskId) || (projectTaskDrawerForm.dependent_ids || []).includes(taskId)) return;

    if (direction === 'blocks') {
        projectTaskDrawerForm.dependent_ids = [...new Set([...(projectTaskDrawerForm.dependent_ids || []), taskId])];
    } else {
        projectTaskDrawerForm.dependency_ids = [...new Set([...(projectTaskDrawerForm.dependency_ids || []), taskId])];
    }
    projectDrawerDependencyToAdd.value = '';
    syncProjectDrawerDependencies();
}

function syncProjectDrawerDependencies() {
    router.put(route('tasks.dependencies.sync', projectTaskDrawerTask.value.id), {
        dependency_ids: projectTaskDrawerForm.dependency_ids,
        dependent_ids: projectTaskDrawerForm.dependent_ids,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
    });
}

function removeProjectDrawerDependency(taskId) {
    projectTaskDrawerForm.dependency_ids = (projectTaskDrawerForm.dependency_ids || []).filter((id) => id !== taskId);
    syncProjectDrawerDependencies();
}

function removeProjectDrawerDependent(taskId) {
    projectTaskDrawerForm.dependent_ids = (projectTaskDrawerForm.dependent_ids || []).filter((id) => id !== taskId);
    syncProjectDrawerDependencies();
}

function toggleProjectTaskComplete() {
    if (!projectTaskDrawerTask.value?.id) return;
    const nextStatus = projectTaskDrawerForm.status === 'done' ? 'todo' : 'done';
    projectTaskDrawerForm.status = nextStatus;
    router.patch(route('tasks.status.update', projectTaskDrawerTask.value.id), { status: nextStatus }, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
        onSuccess: () => {
            projectTaskDrawerTask.value = { ...projectTaskDrawerTask.value, status: nextStatus };
            if (nextStatus === 'done') window.dispatchEvent(new CustomEvent('centro:task-completed'));
        },
    });
}

function addProjectDrawerSubtask() {
    if (!projectTaskDrawerTask.value?.id || !projectDrawerSubtaskForm.title) return;
    if (projectTaskDrawerTask.value?.parent_task_id) return;

    const payload = {
        title: projectDrawerSubtaskForm.title,
        priority: projectDrawerSubtaskForm.priority || 'medium',
        due_date: projectDrawerSubtaskForm.due_date || '',
        assignee_ids: [...projectDrawerCreateSubtaskAssigneeIds.value],
    };

    router.post(route('tasks.subtasks.store', projectTaskDrawerTask.value.id), payload, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
        onSuccess: () => {
            refreshProjectTaskDrawerFromServer(projectTaskDrawerTask.value.id);
            projectDrawerSubtaskForm.reset();
            projectDrawerCreateSubtaskAssigneeIds.value = [];
            subtaskCreateAssigneeMenuOpen.value = false;
        },
    });
}

function removeProjectDrawerSubtask(subtask) {
    if (!canDeleteTaskRecord(subtask)) return;
    const parentTaskId = projectTaskDrawerTask.value?.id;
    openConfirm({
        title: 'Eliminare questa sottoattività?',
        description: subtask.title || 'Sottoattività',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('tasks.destroy', subtask.id), {
            data: { stay: true },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (projectTaskDrawerTask.value) {
                    projectTaskDrawerTask.value = normalizeProjectDrawerTask({
                        ...projectTaskDrawerTask.value,
                        subtasks: projectDrawerSubtasks().filter((item) => item.id !== subtask.id),
                    });
                    hydrateProjectDrawerSubtaskDrafts(projectTaskDrawerTask.value);
                }
                refreshProjectTaskDrawerFromServer(parentTaskId);
            },
            onFinish: closeConfirm,
        }),
    });
}

function updateProjectDrawerCommentFromEditor() {
    projectDrawerCommentForm.content = projectDrawerCommentEditor.value?.innerHTML || '';
}

function addProjectDrawerComment() {
    if (!projectTaskDrawerTask.value?.id) return;
    updateProjectDrawerCommentFromEditor();
    if (!String(projectDrawerCommentForm.content || '').trim()) return;
    projectDrawerCommentForm.post(route('tasks.comments.store', projectTaskDrawerTask.value.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
        onSuccess: () => {
            projectDrawerCommentForm.reset();
            if (projectDrawerCommentEditor.value) projectDrawerCommentEditor.value.innerHTML = '';
        },
    });
}

function toggleProjectTaskActionMenu(event = null) {
    const nextOpen = !projectTaskActionMenuOpen.value;
    if (nextOpen) requestFloatingUiClose();
    projectTaskActionMenuStyle.value = dropdownMenuStyleFromEvent(event, 220);
    projectTaskActionMenuOpen.value = nextOpen;
}

async function copyProjectDrawerTaskLink() {
    projectTaskActionMenuOpen.value = false;
    if (!projectTaskDrawerTask.value?.id) return;

    const href = route('tasks.show', projectTaskDrawerTask.value.id);
    const absoluteHref = href.startsWith('http') ? href : `${window.location.origin}${href}`;

    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(absoluteHref);
    }
}

function duplicateProjectDrawerTask() {
    if (!projectTaskDrawerTask.value?.id) return;
    projectTaskActionMenuOpen.value = false;
    router.post(route('tasks.duplicate', projectTaskDrawerTask.value.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
    });
}

function printProjectDrawerTask() {
    projectTaskActionMenuOpen.value = false;
    window.print();
}

function removeProjectDrawerTask() {
    if (!projectTaskDrawerTask.value?.id) return;
    if (!canDeleteTaskRecord(projectTaskDrawerTask.value)) return;
    projectTaskActionMenuOpen.value = false;
    openConfirm({
        title: 'Eliminare questa task?',
        description: projectTaskDrawerForm.title || 'Task',
        keyword: 'ELIMINA',
        button: 'Elimina',
        danger: true,
        action: () => router.delete(route('tasks.destroy', projectTaskDrawerTask.value.id), {
            data: { stay: true },
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeProjectTaskDrawer,
            onFinish: closeConfirm,
        }),
    });
}

function setProjectTaskDraft(sectionId, value) {
    projectTaskDrafts.value = {
        ...projectTaskDrafts.value,
        [sectionId]: value,
    };
}

function addProjectTask(section) {
    if (isGuest.value) return;
    const title = String(projectTaskDrafts.value[section.id] || '').trim();
    if (!title) return;
    const currentProjectId = String(props.record.id || '');

    router.post(route('tasks.store'), {
        title,
        task_type: 'project',
        status: 'todo',
        priority: 'medium',
        project_id: currentProjectId,
        project_section_id: section.virtual ? '' : section.id,
        client_id: projectForm.client_id || '',
        recurring_enabled: false,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => setProjectTaskDraft(section.id, ''),
    });
}

function startProjectTaskDrag(task) {
    if (isGuest.value) return;
    draggedProjectTaskId.value = task.id;
}

function dragOverProjectSection(section, clearTaskTarget = false) {
    if (!draggedProjectTaskId.value) return;
    projectTaskDropSectionId.value = section.id;
    if (clearTaskTarget) {
        projectTaskDropTarget.value = null;
    }
    if (!projectTaskDropTarget.value) {
        projectTaskDropPlacement.value = 'after';
    }
}

function leaveProjectTaskSection(section, event) {
    if (!draggedProjectTaskId.value) return;
    if (event.currentTarget?.contains?.(event.relatedTarget)) return;
    if (projectTaskDropSectionId.value === section.id) {
        projectTaskDropSectionId.value = null;
    }
}

function dragOverProjectTask(task, event) {
    if (!draggedProjectTaskId.value || draggedProjectTaskId.value === task.id) return;
    const rect = event.currentTarget.getBoundingClientRect();
    projectTaskDropTarget.value = task.id;
    projectTaskDropPlacement.value = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
}

function dropProjectTask(section, targetTask = null) {
    if (isGuest.value) return;
    const fromId = draggedProjectTaskId.value;
    const placement = projectTaskDropPlacement.value || 'after';
    draggedProjectTaskId.value = null;
    projectTaskDropTarget.value = null;
    projectTaskDropPlacement.value = null;
    projectTaskDropSectionId.value = null;
    if (!fromId) return;

    const current = projectTasksForSection(section).filter((task) => task.id !== fromId);
    const moved = parentTaskRows(props.related.tasks).find((task) => task.id === fromId);
    if (!moved) return;

    if (!targetTask) {
        current.push(moved);
    } else {
        let index = current.findIndex((task) => task.id === targetTask.id);
        if (index < 0) index = current.length;
        if (placement === 'after') index += 1;
        current.splice(index, 0, moved);
    }

    router.put(route('projects.tasks.reorder', props.record.id), {
        section_id: section.virtual ? null : section.id,
        ids: current.map((task) => task.id),
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['related', 'errors', 'flash'],
    });
}

function endProjectTaskDrag() {
    draggedProjectTaskId.value = null;
    projectTaskDropTarget.value = null;
    projectTaskDropPlacement.value = null;
    projectTaskDropSectionId.value = null;
}

function addProjectSection() {
    if (isGuest.value) return;
    const name = projectNewSectionName.value.trim();
    if (!name) return;

    router.post(route('projects.sections.store', props.record.id), { name }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            projectNewSectionName.value = '';
            projectNewSectionOpen.value = false;
        },
    });
}

function projectTaskStatusClass(status) {
    return {
        todo: 'bg-gray-100 text-gray-700',
        in_progress: 'bg-sky-100 text-sky-700',
        in_review: 'bg-indigo-100 text-indigo-700',
        done: 'bg-emerald-100 text-emerald-700',
    }[status] || 'bg-gray-100 text-gray-700';
}

function projectTaskPriorityClass(priority) {
    return {
        low: 'bg-gray-100 text-gray-700',
        medium: 'bg-amber-100 text-amber-700',
        high: 'bg-rose-100 text-rose-700',
        urgent: 'bg-red-100 text-red-700',
    }[priority] || 'bg-gray-100 text-gray-700';
}

function taskPriorityColor(priority) {
    return {
        urgent: '#dc2626',
        high: '#f97316',
        medium: '#f59e0b',
        low: '#10b981',
    }[priority] || '#64748b';
}

function projectTaskTypeButtonClass(type) {
    const active = projectTaskDrawerForm.task_type === type || (type === 'project' && projectTaskDrawerForm.task_type === 'task');
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

function personAvatarClass(selected) {
    return [
        'group/person relative inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300',
        selected
            ? 'bg-indigo-50 ring-2 ring-indigo-500 ring-offset-2 ring-offset-white'
            : 'bg-white/70 ring-1 ring-gray-200 hover:-translate-y-0.5 hover:ring-indigo-200 hover:shadow-[0_10px_24px_rgba(79,70,229,0.10)]',
    ];
}

function toggleSubtaskAssigneeMenu(subtaskId, event = null) {
    const nextOpen = subtaskAssigneeMenuOpen.value === subtaskId ? null : subtaskId;
    if (nextOpen) requestFloatingUiClose();
    subtaskAssigneeMenuStyle.value = floatingMenuStyleFromEvent(event);
    subtaskAssigneeMenuOpen.value = nextOpen;
}

function closeSubtaskAssigneeMenuOnOutside(event) {
    closeTaskActionMenuOnOutside(event);
    if (projectSectionActionMenuOpen.value && !(event.target instanceof Element && event.target.closest('[data-project-section-menu]'))) {
        projectSectionActionMenuOpen.value = null;
    }
    if (subtaskCreateAssigneeMenuOpen.value && !(event.target instanceof Element && event.target.closest('[data-subtask-create-assignees]'))) {
        subtaskCreateAssigneeMenuOpen.value = false;
    }
    if (!subtaskAssigneeMenuOpen.value) return;
    if (event.target instanceof Element && event.target.closest(`[data-subtask-assignees="${subtaskAssigneeMenuOpen.value}"]`)) return;

    subtaskAssigneeMenuOpen.value = null;
}

function toggleSubtaskAssignee(subtask, userId) {
    if (!subtaskDrafts.value[subtask.id]) return;

    const values = [...(subtaskDrafts.value[subtask.id].assignee_ids || [])];
    const index = values.indexOf(userId);
    if (index >= 0) {
        values.splice(index, 1);
    } else {
        values.push(userId);
    }

    subtaskDrafts.value = {
        ...subtaskDrafts.value,
        [subtask.id]: {
            ...subtaskDrafts.value[subtask.id],
            assignee_ids: values,
        },
    };
    router.put(route('tasks.people.sync', [subtask.id, 'assignees']), {
        user_ids: values,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            subtaskDrafts.value = {
                ...subtaskDrafts.value,
                [subtask.id]: {
                    ...subtaskDrafts.value[subtask.id],
                    assignee_ids: subtask.assignee_ids || [],
                },
            };
        },
    });
}

function pulseSubtaskStatus(subtaskId) {
    subtaskStatusPulse.value = subtaskId;
    window.setTimeout(() => {
        if (subtaskStatusPulse.value === subtaskId) {
            subtaskStatusPulse.value = null;
        }
    }, 360);
}

function addSubtask() {
    subtaskForm.post(route('tasks.subtasks.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            subtaskForm.reset();
            subtaskCreateAssigneeMenuOpen.value = false;
        },
    });
}

function paymentTermsLabel(days) {
    if (days === null || days === undefined || days === '') return null;
    if (Number(days) === 0) return 'A vista';
    return `${days} giorni`;
}

function fullClientAddress(record) {
    return [
        [record.street, record.street_number].filter(Boolean).join(' '),
        [record.postal_code, record.city, record.province ? `(${record.province})` : null].filter(Boolean).join(' '),
        record.country,
    ].filter(Boolean).join(' - ') || record.address;
}

function subscriptionFrequency(subscription) {
    const unit = subscription.frequency_unit === 'year' ? 'anno' : 'mese';
    const plural = Number(subscription.frequency_value) > 1 ? (subscription.frequency_unit === 'year' ? 'anni' : 'mesi') : unit;
    return `ogni ${subscription.frequency_value} ${plural}`;
}

function activityText(activity) {
    return formatActivityText(activity, labels);
}

function docTypeLabel(type) {
    return {
        preventivo: 'Preventivo',
        proforma: 'Proforma',
        fattura: 'Fattura',
        nota_credito: 'Nota credito',
    }[type] || type;
}

function docStatusLabel(status) {
    return {
        draft: 'Bozza',
        sent: 'Inviato',
        accepted: 'Accettato',
        rejected: 'Rifiutato',
        paid: 'Pagato',
        partially_paid: 'Parziale',
        overdue: 'Scaduto',
        cancelled: 'Annullato',
    }[status] || status;
}

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

function remainingAmount() {
    return Number(props.record.total_amount || 0) - Number(props.record.total_paid || 0);
}

watch(
    () => [
        taskForm.title,
        taskForm.description,
        taskForm.project_id,
        taskForm.client_id,
        taskForm.service_id,
        taskForm.priority,
        taskForm.start_date,
        taskForm.due_date,
        taskForm.due_time,
        taskForm.location,
        taskForm.recurring_enabled,
        taskForm.recurring_interval_value,
        taskForm.recurring_interval_unit,
        taskForm.recurring_mode,
        taskForm.recurring_weekday,
        taskForm.recurring_month_day,
    ],
    () => saveTaskInline(),
);

watch(
    () => [
        projectForm.name,
        projectForm.description,
        projectForm.client_id,
        projectForm.status,
        projectForm.color,
    ],
    () => saveProjectInline(),
);

watch(
    () => projectWorkspaceTab.value,
    (tab) => {
        if (tab === 'overview') refreshProjectDescriptionEditor();
    },
);

watch(
    () => props.related?.sections || [],
    (sections) => {
        const next = {};
        for (const sectionRow of sections) {
            next[sectionRow.id] = projectSectionDrafts.value[sectionRow.id] ?? sectionRow.name;
        }
        projectSectionDrafts.value = next;
    },
    { immediate: true },
);

watch(
    () => [
        projectTaskDrawerForm.title,
        projectTaskDrawerForm.status,
        projectTaskDrawerForm.priority,
        projectTaskDrawerForm.task_type,
        projectTaskDrawerForm.start_date,
        projectTaskDrawerForm.due_date,
        projectTaskDrawerForm.due_time,
        projectTaskDrawerForm.location,
        projectTaskDrawerForm.project_id,
        projectTaskDrawerForm.client_id,
        projectTaskDrawerForm.service_id,
        projectTaskDrawerForm.recurring_enabled,
        projectTaskDrawerForm.recurring_interval_value,
        projectTaskDrawerForm.recurring_interval_unit,
        projectTaskDrawerForm.recurring_mode,
        projectTaskDrawerForm.recurring_weekday,
        projectTaskDrawerForm.recurring_month_day,
    ],
    () => {
        if (projectTaskDrawerOpen.value) saveProjectTaskDrawer();
    },
);

watch(
    () => [
        clientForm.name,
        clientForm.legal_name,
        clientForm.vat_number,
        clientForm.tax_code,
        clientForm.legal_form,
        clientForm.business_sector,
        clientForm.source,
        clientForm.country,
        clientForm.street,
        clientForm.street_number,
        clientForm.postal_code,
        clientForm.city,
        clientForm.province,
        clientForm.email,
        clientForm.phone,
        clientForm.website,
        clientForm.pec,
        clientForm.sdi_code,
        clientForm.iban,
        clientForm.bic_swift,
        clientForm.vat_treatment,
        clientForm.payment_terms_days,
        clientForm.is_pa,
        clientForm.notes,
    ],
    () => saveClientInline(),
);

watch(
    () => [
        documentForm.issue_date,
        documentForm.due_date,
        documentForm.status,
        documentForm.payment_method,
        documentForm.payment_terms_days,
        documentForm.causale,
        documentForm.notes,
        documentForm.footer_notes,
        documentForm.withholding_pct,
        documentForm.pension_fund_pct,
        documentForm.pension_fund_label,
    ],
    () => saveDocumentInline(),
);

watch(
    () => props.related?.lines || [],
    (lines) => {
        const next = {};
        for (const line of lines) {
            next[line.id] = {
                ...(lineDrafts.value[line.id] || {}),
                description: line.description || '',
                quantity: line.quantity ?? 0,
                unit_price: line.unit_price ?? 0,
                vat_rate: line.vat_rate ?? 0,
                discount_pct: line.discount_pct ?? 0,
                subtotal: line.subtotal ?? 0,
            };
        }
        lineDrafts.value = next;
    },
    { immediate: true },
);

watch(
    () => props.related?.payments || [],
    (payments) => {
        const next = {};
        for (const payment of payments) {
            next[payment.id] = {
                ...(paymentDrafts.value[payment.id] || {}),
                amount: payment.amount ?? 0,
                paid_at: payment.paid_at || '',
                method: payment.method || '',
                notes: payment.notes || '',
            };
        }
        paymentDrafts.value = next;
    },
    { immediate: true },
);

watch(
    () => props.related?.contacts || [],
    (contacts) => {
        const next = {};
        for (const contact of contacts) {
            next[contact.id] = {
                ...(contactDrafts.value[contact.id] || {}),
                first_name: contact.first_name || '',
                last_name: contact.last_name || '',
                email: contact.email || '',
                phone: contact.phone || '',
                role: contact.role || '',
                notes: contact.notes || '',
            };
        }
        contactDrafts.value = next;
    },
    { immediate: true },
);

watch(
    () => props.related?.clientServices || [],
    (services) => {
        clientServiceIds.value = [...services];
    },
    { immediate: true },
);

watch(
    () => props.related?.subtasks || [],
    (subtasks) => {
        const next = {};
        orderedSubtasks.value = [...subtasks];
        for (const subtask of subtasks) {
            next[subtask.id] = {
                ...(subtaskDrafts.value[subtask.id] || {}),
                title: subtask.title || '',
                task_type: subtask.task_type || 'task',
                status: subtask.status || 'todo',
                priority: subtask.priority || 'medium',
                project_id: subtask.project_id || '',
                client_id: subtask.client_id || '',
                service_id: subtask.service_id || '',
                assignee_ids: subtask.assignee_ids || [],
                start_date: subtask.start_date || '',
                due_date: subtask.due_date || '',
                due_time: subtask.due_time ? String(subtask.due_time).slice(0, 5) : '',
                location: subtask.location || '',
                description: subtask.description || '',
            };
        }
        subtaskDrafts.value = next;
    },
    { immediate: true },
);

watch(
    () => props.related?.comments || [],
    (comments) => {
        const next = {};
        for (const comment of comments) {
            next[comment.id] = {
                ...(commentDrafts.value[comment.id] || {}),
                content: comment.content || '',
            };
        }
        commentDrafts.value = next;
    },
    { immediate: true },
);

watch(
    () => [
        userForm.name,
        userForm.email,
        userForm.role,
        userForm.employee_code,
        userForm.job_title,
        userForm.phone,
        userForm.bio,
        userForm.password,
    ],
    () => saveUserInline(),
);

onMounted(() => {
    document.addEventListener('pointerdown', closeSubtaskAssigneeMenuOnOutside, true);
    window.addEventListener('centro:close-floating-ui', closeCentroShowFloatingUi);
    refreshProjectDescriptionEditor();
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeSubtaskAssigneeMenuOnOutside, true);
    window.removeEventListener('centro:close-floating-ui', closeCentroShowFloatingUi);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <Head :title="section === 'users' ? userForm.name : (record.name || record.title || title)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <Link
                        :href="backHref()"
                        class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 transition hover:text-indigo-600"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" :stroke-width="1.8" />
                        {{ backLabel() }}
                    </Link>
                    <div class="mt-1 flex items-center gap-2">
                        <span v-if="section === 'projects'" class="h-3 w-3 rounded-full" :style="{ backgroundColor: normalizeHexColor(projectForm.color) }"></span>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">
                            {{ section === 'projects' ? projectForm.name : (section === 'clients' ? clientForm.name : (section === 'users' ? userForm.name : (section === 'absences' ? 'Richiesta assenza' : (record.name || record.title || record.number)))) }}
                        </h2>
                    </div>
                </div>
                <div v-if="section === 'tasks'" class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        :class="['btn btn-outline status-action-button', taskForm.status !== 'done' && blockedDependencyCount() ? 'cursor-not-allowed opacity-60' : '']"
                        :disabled="taskForm.status !== 'done' && blockedDependencyCount() > 0"
                        :title="taskForm.status !== 'done' && blockedDependencyCount() ? 'Task bloccata da dipendenze' : ''"
                        @click="toggleTaskComplete"
                    >
                        <Check class="h-4 w-4" :stroke-width="1.7" />
                        {{ taskForm.status === 'done' ? 'Riapri' : 'Completa' }}
                    </button>
                    <button type="button" class="icon-btn h-10 w-10" data-task-actions-menu title="Azioni task" @click.stop="toggleTaskActionMenu($event)">
                        <MoreHorizontal class="h-5 w-5" :stroke-width="1.8" />
                    </button>
                    <Teleport to="body">
                        <div v-if="taskActionMenuOpen" class="fixed inset-0 z-[7600] bg-transparent" data-task-actions-menu @click.self="taskActionMenuOpen = false">
                            <div class="app-popover field-dropdown-menu fixed w-56 p-2" :style="taskActionMenuStyle" @click.stop>
                                <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="copyTaskLink">
                                    <Copy class="h-4 w-4" :stroke-width="1.7" />
                                    Copia link
                                </button>
                                <button v-if="!isGuest" type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="duplicateTask">
                                    <Copy class="h-4 w-4" :stroke-width="1.7" />
                                    Duplica
                                </button>
                                <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printTask">
                                    <Printer class="h-4 w-4" :stroke-width="1.7" />
                                    Stampa
                                </button>
                                <button v-if="canDeleteCurrentTask" type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50" @click="deleteTaskFromDetail">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    Elimina
                                </button>
                            </div>
                        </div>
                    </Teleport>
                </div>
                <div v-else-if="section === 'projects' && canDeleteProject" class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn btn-danger" @click="deleteProjectFromDetail">
                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                        Elimina
                    </button>
                </div>
            </div>
        </template>

        <div v-if="confirmAction" class="fixed inset-0 z-[7000] flex items-center justify-center bg-transparent px-4 py-6" @click.self="closeConfirm">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ confirmAction.title }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    <span v-if="confirmAction.description">{{ confirmAction.description }}</span>
                    <span v-if="confirmAction.danger" class="mt-2 block">Questa azione e' irreversibile.</span>
                    Digita <span class="font-mono font-semibold text-gray-900">{{ confirmAction.keyword }}</span> per confermare.
                </p>
                <input v-model="confirmText" class="form-control font-mono" :placeholder="confirmAction.keyword" autocomplete="off" />
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="closeConfirm">
                        <X class="h-4 w-4" :stroke-width="1.7" />
                        Annulla
                    </button>
                    <button
                        type="button"
                        :class="[
                            'btn text-white',
                            confirmAction.danger ? 'bg-red-600 hover:bg-red-500' : 'bg-indigo-600 hover:bg-indigo-500',
                        ]"
                        :disabled="confirmText !== confirmAction.keyword"
                        @click="runConfirmAction"
                    >
                        <Trash2 v-if="confirmAction.danger" class="h-4 w-4" :stroke-width="1.7" />
                        <Check v-else class="h-4 w-4" :stroke-width="1.7" />
                        {{ confirmAction.button }}
                    </button>
                </div>
            </div>
        </div>

        <div class="detail-page py-8">
            <div v-if="section === 'billing'" class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4 rounded-md bg-white p-5 shadow-sm">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-gray-900">{{ docTypeLabel(record.doc_type) }}</h2>
                            <span class="font-mono text-sm text-gray-500">{{ record.number || 'bozza' }}</span>
                            <span :class="['rounded-full px-2 py-1 text-xs font-medium', statusClass(record.status)]">{{ docStatusLabel(record.status) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ related.client?.legal_name || related.client?.name || 'Cliente' }} · {{ dateIt(record.issue_date) }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-if="documentAutosaveState !== 'idle'"
                            :class="[
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition',
                                documentAutosaveState === 'saving' || documentAutosaveState === 'queued' ? 'bg-sky-50 text-sky-700' : '',
                                documentAutosaveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : '',
                                documentAutosaveState === 'error' ? 'bg-red-50 text-red-700' : '',
                            ]"
                        >
                            <span v-if="documentAutosaveState === 'queued'">In attesa...</span>
                            <span v-else-if="documentAutosaveState === 'saving'">Salvataggio...</span>
                            <span v-else-if="documentAutosaveState === 'saved'">Salvato</span>
                            <span v-else>{{ documentAutosaveError || 'Errore salvataggio' }}</span>
                        </div>
                        <button v-if="!record.number" type="button" class="btn border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100" @click="issueDocument"><Send class="h-4 w-4" :stroke-width="1.7" />Emetti</button>
                        <a :href="route('billing.pdf', record.id)" class="btn btn-outline"><Download class="h-4 w-4" :stroke-width="1.7" />PDF</a>
                        <a v-if="['fattura', 'nota_credito'].includes(record.doc_type)" :href="route('billing.xml', record.id)" class="btn btn-outline"><FileText class="h-4 w-4" :stroke-width="1.7" />XML</a>
                        <button type="button" class="btn btn-outline" @click="printDocument"><Printer class="h-4 w-4" :stroke-width="1.7" />Stampa</button>
                        <button type="button" class="btn btn-outline" @click="duplicateDocument"><Copy class="h-4 w-4" :stroke-width="1.7" />Duplica</button>
                        <button v-if="record.doc_type === 'preventivo'" type="button" class="btn btn-outline" @click="convertDocument('fattura')">Converti fattura</button>
                        <button v-if="record.doc_type === 'proforma'" type="button" class="btn btn-outline" @click="convertDocument('fattura')">Converti fattura</button>
                        <button v-if="record.doc_type === 'fattura'" type="button" class="btn btn-outline" @click="convertDocument('nota_credito')">Nota credito</button>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <div class="space-y-6">
                        <section class="surface rounded-md p-5">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dati documento</h3>
                                <p class="mt-1 text-sm text-gray-500">Le modifiche ai dati documento si salvano automaticamente.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Data emissione</label>
                                    <AppDateInput v-model="documentForm.issue_date" @change="saveDocumentInline(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                                    <AppDateInput v-model="documentForm.due_date" @change="saveDocumentInline(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stato</label>
                                    <AppSelect v-model="documentForm.status" :options="documentStatusOptions" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Termini pagamento</label>
                                    <input v-model="documentForm.payment_terms_days" type="number" min="0" class="form-control" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Metodo pagamento</label>
                                    <input v-model="documentForm.payment_method" class="form-control" placeholder="Bonifico, MP05..." />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cassa previdenziale %</label>
                                    <input v-model="documentForm.pension_fund_pct" type="number" step="0.01" min="0" class="form-control" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Ritenuta %</label>
                                    <input v-model="documentForm.withholding_pct" type="number" step="0.01" min="0" class="form-control" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Causale</label>
                                    <input v-model="documentForm.causale" class="form-control" />
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Note interne</label>
                                    <textarea v-model="documentForm.notes" rows="2" class="form-control"></textarea>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Note visibili al cliente</label>
                                    <textarea v-model="documentForm.footer_notes" rows="3" class="form-control"></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <div class="mb-5 flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Righe documento</h3>
                                <div class="text-sm font-semibold text-gray-900">{{ money(record.total_amount) }}</div>
                            </div>
                            <form class="mb-5 grid gap-3 md:grid-cols-[1fr_90px_120px_90px_90px_auto]" @submit.prevent="addLine">
                                <input v-model="lineForm.description" class="form-control mt-0" placeholder="Descrizione" required />
                                <input v-model="lineForm.quantity" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <input v-model="lineForm.unit_price" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <input v-model="lineForm.vat_rate" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <input v-model="lineForm.discount_pct" class="form-control mt-0" type="number" step="0.01" min="0" max="100" />
                                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                            </form>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Descrizione</th>
                                            <th class="px-3 py-2 text-right">Qta</th>
                                            <th class="px-3 py-2 text-right">Prezzo</th>
                                            <th class="px-3 py-2 text-right">Sconto</th>
                                            <th class="px-3 py-2 text-right">IVA</th>
                                            <th class="px-3 py-2 text-right">Subtotale</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="line in related.lines" :key="line.id" class="align-top">
                                            <td class="min-w-[260px] px-3 py-2">
                                                <input
                                                    v-if="lineDrafts[line.id]"
                                                    v-model="lineDrafts[line.id].description"
                                                    class="form-control mt-0"
                                                    @input="saveLineInline(line)"
                                                />
                                                <div v-if="lineAutosaveStates[line.id] && lineAutosaveStates[line.id] !== 'idle'" :class="['mt-1 text-[11px] font-medium', lineAutosaveStates[line.id] === 'error' ? 'text-red-600' : 'text-gray-400']">
                                                    {{ autosaveLabel(lineAutosaveStates[line.id], lineAutosaveErrors[line.id]) }}
                                                </div>
                                            </td>
                                            <td class="w-24 px-3 py-2">
                                                <input
                                                    v-if="lineDrafts[line.id]"
                                                    v-model="lineDrafts[line.id].quantity"
                                                    class="form-control mt-0 text-right"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    @input="saveLineInline(line)"
                                                />
                                            </td>
                                            <td class="w-32 px-3 py-2">
                                                <input
                                                    v-if="lineDrafts[line.id]"
                                                    v-model="lineDrafts[line.id].unit_price"
                                                    class="form-control mt-0 text-right"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    @input="saveLineInline(line)"
                                                />
                                            </td>
                                            <td class="w-24 px-3 py-2">
                                                <input
                                                    v-if="lineDrafts[line.id]"
                                                    v-model="lineDrafts[line.id].discount_pct"
                                                    class="form-control mt-0 text-right"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    @input="saveLineInline(line)"
                                                />
                                            </td>
                                            <td class="w-24 px-3 py-2">
                                                <input
                                                    v-if="lineDrafts[line.id]"
                                                    v-model="lineDrafts[line.id].vat_rate"
                                                    class="form-control mt-0 text-right"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    @input="saveLineInline(line)"
                                                />
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium text-gray-900">{{ money(line.subtotal) }}</td>
                                            <td class="px-3 py-2 text-right">
                                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50 hover:text-red-700" aria-label="Elimina riga" @click="removeLine(line)">
                                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!related.lines?.length">
                                            <td colspan="7" class="px-3 py-8 text-center text-gray-500">Nessuna riga.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Pagamenti</h3>
                            <form class="mb-5 grid gap-3 md:grid-cols-[120px_150px_1fr_auto]" @submit.prevent="addPayment">
                                <input v-model="paymentForm.amount" class="form-control mt-0" type="number" step="0.01" min="0" required />
                                <AppDateInput v-model="paymentForm.paid_at" />
                                <input v-model="paymentForm.method" class="form-control mt-0" placeholder="Metodo" />
                                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Registra</button>
                            </form>
                            <div class="space-y-2">
                                <div v-for="payment in related.payments" :key="payment.id" class="grid gap-2 rounded-md border border-gray-100 bg-gray-50 p-3 text-sm transition hover:border-indigo-100 hover:bg-white sm:grid-cols-[120px_150px_1fr_auto]">
                                    <input
                                        v-if="paymentDrafts[payment.id]"
                                        v-model="paymentDrafts[payment.id].amount"
                                        class="form-control mt-0 text-right"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        @input="savePaymentInline(payment)"
                                    />
                                    <AppDateInput
                                        v-if="paymentDrafts[payment.id]"
                                        v-model="paymentDrafts[payment.id].paid_at"
                                        @change="savePaymentInline(payment, 0)"
                                    />
                                    <div class="space-y-1">
                                        <input
                                            v-if="paymentDrafts[payment.id]"
                                            v-model="paymentDrafts[payment.id].method"
                                            class="form-control mt-0"
                                            placeholder="Metodo"
                                            @input="savePaymentInline(payment)"
                                        />
                                        <input
                                            v-if="paymentDrafts[payment.id]"
                                            v-model="paymentDrafts[payment.id].notes"
                                            class="form-control mt-0 text-xs"
                                            placeholder="Note pagamento"
                                            @input="savePaymentInline(payment)"
                                        />
                                        <div v-if="paymentAutosaveStates[payment.id] && paymentAutosaveStates[payment.id] !== 'idle'" :class="['text-[11px] font-medium', paymentAutosaveStates[payment.id] === 'error' ? 'text-red-600' : 'text-gray-400']">
                                            {{ autosaveLabel(paymentAutosaveStates[payment.id], paymentAutosaveErrors[payment.id]) }}
                                        </div>
                                    </div>
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center justify-self-end rounded-md text-red-600 transition hover:bg-red-50 hover:text-red-700" aria-label="Elimina pagamento" @click="removePayment(payment)">
                                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                                <p v-if="!related.payments?.length" class="text-sm text-gray-500">Nessun pagamento registrato.</p>
                            </div>
                        </section>
                    </div>

                    <aside class="space-y-6">
                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Riepilogo</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span>Imponibile</span><span>{{ money(record.total_taxable) }}</span></div>
                                <div class="flex justify-between"><span>IVA</span><span>{{ money(record.total_vat) }}</span></div>
                                <div class="flex justify-between"><span>Cassa</span><span>{{ money(record.total_pension_fund) }}</span></div>
                                <div class="flex justify-between"><span>Ritenuta</span><span class="text-red-600">-{{ money(record.total_withholding) }}</span></div>
                                <div class="my-3 border-t border-gray-100"></div>
                                <div class="flex justify-between text-lg font-semibold"><span>Totale</span><span>{{ money(record.total_amount) }}</span></div>
                                <div class="flex justify-between"><span>Pagato</span><span>{{ money(record.total_paid) }}</span></div>
                                <div class="flex justify-between font-semibold"><span>Residuo</span><span :class="remainingAmount() > 0 ? 'text-red-600' : 'text-emerald-600'">{{ money(remainingAmount()) }}</span></div>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Anteprima</h3>
                            <div class="rounded-md border border-gray-200 bg-white p-5 text-sm">
                                <div class="flex justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-gray-900">Centro LU3G</div>
                                        <div class="text-xs text-gray-500">Documento commerciale</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-gray-900">{{ docTypeLabel(record.doc_type) }}</div>
                                        <div class="font-mono text-xs text-gray-500">{{ record.number || 'bozza' }}</div>
                                    </div>
                                </div>
                                <div class="mt-6 rounded-md bg-gray-50 p-3">
                                    <div class="text-xs uppercase tracking-wide text-gray-400">Cliente</div>
                                    <div class="mt-1 font-medium text-gray-900">{{ related.client?.legal_name || related.client?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ related.client?.vat_number || related.client?.tax_code || '' }}</div>
                                </div>
                                <div class="mt-5 space-y-2">
                                    <div v-for="line in related.lines?.slice(0, 4)" :key="line.id" class="flex justify-between gap-3 border-b border-gray-100 pb-2">
                                        <span class="truncate">{{ line.description }}</span>
                                        <span class="font-medium">{{ money(Number(line.subtotal) + (Number(line.subtotal) * Number(line.vat_rate || 0) / 100)) }}</span>
                                    </div>
                                    <div v-if="related.lines?.length > 4" class="text-xs text-gray-500">altre {{ related.lines.length - 4 }} righe...</div>
                                </div>
                                <div class="mt-5 flex justify-between border-t border-gray-200 pt-3 text-base font-semibold">
                                    <span>Totale</span>
                                    <span>{{ money(record.total_amount) }}</span>
                                </div>
                            </div>
                        </section>

                        <section class="surface rounded-md p-5">
                            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Invio email</h3>
                            <form class="space-y-3" @submit.prevent="sendDocumentEmail">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Destinatario</label>
                                    <input v-model="emailForm.recipient" type="email" class="form-control" required />
                                    <div v-if="emailForm.errors.recipient" class="mt-1 text-sm text-red-600">{{ emailForm.errors.recipient }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">CC</label>
                                    <input v-model="emailForm.cc" class="form-control" placeholder="email1, email2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Oggetto</label>
                                    <input v-model="emailForm.subject" class="form-control" :placeholder="`${docTypeLabel(record.doc_type)} ${record.number || 'bozza'}`" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Messaggio</label>
                                    <textarea v-model="emailForm.message" rows="4" class="form-control" placeholder="Messaggio opzionale..."></textarea>
                                </div>
                                <label v-if="['fattura', 'nota_credito'].includes(record.doc_type)" class="flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="emailForm.include_xml" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Allega XML
                                </label>
                                <p class="text-xs text-gray-500">Viene sempre allegato il PDF. L'invio usa il mailer configurato in Laravel/Plesk.</p>
                                <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="emailForm.processing">
                                    Invia documento
                                </button>
                            </form>
                        </section>
                    </aside>
                </div>
            </div>

            <div v-else class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8">
                <section v-if="section === 'clients'" class="space-y-6">
                    <section v-if="canEditClient" class="surface rounded-md p-5">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Anagrafica cliente</h3>
                                <p class="mt-1 text-sm text-gray-500">Dati fiscali, contatti, indirizzo e note si salvano automaticamente.</p>
                            </div>
                            <div
                                v-if="clientAutosaveState !== 'idle'"
                                :class="[
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition',
                                    clientAutosaveState === 'saving' || clientAutosaveState === 'queued' ? 'bg-sky-50 text-sky-700' : '',
                                    clientAutosaveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : '',
                                    clientAutosaveState === 'error' ? 'bg-red-50 text-red-700' : '',
                                ]"
                            >
                                <span v-if="clientAutosaveState === 'queued'">In attesa...</span>
                                <span v-else-if="clientAutosaveState === 'saving'">Salvataggio...</span>
                                <span v-else-if="clientAutosaveState === 'saved'">Salvato</span>
                                <span v-else>{{ clientAutosaveError || 'Errore salvataggio' }}</span>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Anagrafica</h4>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Nome *</label>
                                        <input v-model="clientForm.name" class="form-control" required />
                                        <div v-if="clientForm.errors.name" class="mt-1 text-sm text-red-600">{{ clientForm.errors.name }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Ragione sociale</label>
                                        <input v-model="clientForm.legal_name" class="form-control" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Partita IVA</label>
                                        <input v-model="clientForm.vat_number" class="form-control" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Codice fiscale</label>
                                        <input v-model="clientForm.tax_code" class="form-control" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Natura giuridica</label>
                                        <AppSelect v-model="clientForm.legal_form" :options="clientSelectFieldOptions('legal_form')" searchable />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Settore</label>
                                        <AppSelect v-model="clientForm.business_sector" :options="clientSelectFieldOptions('business_sector')" searchable />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Sorgente</label>
                                        <AppSelect v-model="clientForm.source" :options="clientSelectFieldOptions('source')" searchable />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Sito web</label>
                                        <input v-model="clientForm.website" class="form-control" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Contatti e indirizzo</h4>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email</label>
                                        <input v-model="clientForm.email" type="email" class="form-control" />
                                        <div v-if="clientForm.errors.email" class="mt-1 text-sm text-red-600">{{ clientForm.errors.email }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Telefono</label>
                                        <input v-model="clientForm.phone" class="form-control" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Paese</label>
                                        <AppSelect v-model="clientForm.country" :options="clientSelectFieldOptions('country')" searchable />
                                    </div>
                                    <div class="grid gap-3 grid-cols-[1fr_110px]">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Via</label>
                                            <input v-model="clientForm.street" class="form-control" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">N.</label>
                                            <input v-model="clientForm.street_number" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="grid gap-3 grid-cols-[110px_1fr_90px]">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">CAP</label>
                                            <input v-model="clientForm.postal_code" class="form-control" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Citta</label>
                                            <input v-model="clientForm.city" class="form-control" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Prov.</label>
                                            <input v-model="clientForm.province" class="form-control" maxlength="2" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <button
                                    type="button"
                                    class="mb-3 flex w-full items-center justify-between rounded-[var(--radius-sm)] px-1 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 transition hover:bg-gray-50 hover:text-gray-700"
                                    :aria-expanded="clientFiscalOpen"
                                    @click="clientFiscalOpen = !clientFiscalOpen"
                                >
                                    <span>Dati fiscali e bancari</span>
                                    <ChevronDown :class="['h-4 w-4 transition-transform', clientFiscalOpen ? 'rotate-180' : '']" :stroke-width="1.8" />
                                </button>
                                <div v-show="clientFiscalOpen" class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">PEC</label>
                                        <input v-model="clientForm.pec" type="email" class="form-control" />
                                        <div v-if="clientForm.errors.pec" class="mt-1 text-sm text-red-600">{{ clientForm.errors.pec }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Codice SDI</label>
                                        <input v-model="clientForm.sdi_code" class="form-control" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700">IBAN</label>
                                        <input v-model="clientForm.iban" class="form-control" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">BIC / SWIFT</label>
                                        <input v-model="clientForm.bic_swift" class="form-control" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Trattamento IVA</label>
                                        <AppSelect v-model="clientForm.vat_treatment" :options="clientSelectFieldOptions('vat_treatment')" searchable />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Termini pagamento</label>
                                        <AppSelect v-model="clientForm.payment_terms_days" :options="clientSelectFieldOptions('payment_terms_days')" />
                                    </div>
                                    <label class="mt-7 flex items-center gap-2 text-sm text-gray-700">
                                        <input v-model="clientForm.is_pa" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                        Pubblica amministrazione
                                    </label>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700">Note</label>
                                        <textarea v-model="clientForm.notes" rows="4" class="form-control"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-else class="surface rounded-md p-5">
                        <div class="mb-5">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Anagrafica cliente</h3>
                            <p class="mt-1 text-sm text-gray-500">Consultazione dati cliente.</p>
                        </div>
                        <dl class="grid gap-4 md:grid-cols-2">
                            <div v-for="[label, value] in [
                                ['Nome', clientForm.name],
                                ['Ragione sociale', clientForm.legal_name],
                                ['Partita IVA', clientForm.vat_number],
                                ['Codice fiscale', clientForm.tax_code],
                                ['Email', clientForm.email],
                                ['Telefono', clientForm.phone],
                                ['Sito web', clientForm.website],
                                ['Città', [clientForm.city, clientForm.province].filter(Boolean).join(', ')],
                                ['PEC', clientForm.pec],
                                ['Codice SDI', clientForm.sdi_code],
                                ['IBAN', clientForm.iban],
                                ['Trattamento IVA', displayValue(clientForm.vat_treatment)],
                            ]" :key="label" class="rounded-md border border-gray-100 bg-gray-50 px-4 py-3">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ label }}</dt>
                                <dd class="mt-1 min-h-5 text-sm font-medium text-gray-900">{{ value || '-' }}</dd>
                            </div>
                            <div class="rounded-md border border-gray-100 bg-gray-50 px-4 py-3 md:col-span-2">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Note</dt>
                                <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ clientForm.notes || '-' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="surface rounded-md p-5">
                        <div class="mb-5 flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Referenti</h3>
                            <span class="text-xs text-gray-500">{{ related.contacts?.length || 0 }} contatti</span>
                        </div>

                        <form v-if="canEditClient" class="mb-5 grid gap-3 md:grid-cols-6" @submit.prevent="addContact">
                            <input v-model="contactForm.first_name" class="form-control mt-0" placeholder="Nome" required />
                            <input v-model="contactForm.last_name" class="form-control mt-0" placeholder="Cognome" required />
                            <input v-model="contactForm.email" class="form-control mt-0" type="email" placeholder="Email" />
                            <input v-model="contactForm.phone" class="form-control mt-0" placeholder="Telefono" />
                            <input v-model="contactForm.role" class="form-control mt-0" placeholder="Ruolo" />
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                        </form>

                        <div v-if="related.contacts?.length" class="divide-y divide-gray-100">
                            <article v-for="contact in related.contacts" :key="contact.id" class="py-3 first:pt-0 last:pb-0">
                                <div class="grid items-center gap-2 md:grid-cols-[minmax(150px,1fr)_minmax(120px,0.8fr)_minmax(150px,1fr)_minmax(110px,0.7fr)_minmax(160px,1fr)_40px]">
                                    <template v-if="canEditClient">
                                        <div class="grid min-w-0 gap-2 sm:grid-cols-2">
                                            <input
                                                v-if="contactDrafts[contact.id]"
                                                v-model="contactDrafts[contact.id].first_name"
                                                class="form-control mt-0"
                                                placeholder="Nome"
                                                @input="saveContactInline(contact)"
                                            />
                                            <input
                                                v-if="contactDrafts[contact.id]"
                                                v-model="contactDrafts[contact.id].last_name"
                                                class="form-control mt-0"
                                                placeholder="Cognome"
                                                @input="saveContactInline(contact)"
                                            />
                                        </div>
                                        <input
                                            v-if="contactDrafts[contact.id]"
                                            v-model="contactDrafts[contact.id].role"
                                            class="form-control mt-0"
                                            placeholder="Ruolo"
                                            @input="saveContactInline(contact)"
                                        />
                                        <input
                                            v-if="contactDrafts[contact.id]"
                                            v-model="contactDrafts[contact.id].email"
                                            class="form-control mt-0"
                                            type="email"
                                            placeholder="Email"
                                            @input="saveContactInline(contact)"
                                        />
                                        <input
                                            v-if="contactDrafts[contact.id]"
                                            v-model="contactDrafts[contact.id].phone"
                                            class="form-control mt-0"
                                            placeholder="Telefono"
                                            @input="saveContactInline(contact)"
                                        />
                                        <input
                                            v-if="contactDrafts[contact.id]"
                                            v-model="contactDrafts[contact.id].notes"
                                            class="form-control mt-0"
                                            placeholder="Note"
                                            @input="saveContactInline(contact)"
                                        />
                                        <button v-if="canEditClient" type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50 hover:text-red-700" aria-label="Elimina referente" @click="removeContact(contact)">
                                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <div v-if="contactAutosaveStates[contact.id] && contactAutosaveStates[contact.id] !== 'idle'" :class="['md:col-span-6 text-[11px] font-medium', contactAutosaveStates[contact.id] === 'error' ? 'text-red-600' : 'text-gray-400']">
                                            {{ autosaveLabel(contactAutosaveStates[contact.id], contactAutosaveErrors[contact.id]) }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="min-w-0 truncate text-sm font-semibold text-gray-900">{{ [contact.first_name, contact.last_name].filter(Boolean).join(' ') || 'Referente' }}</div>
                                        <div class="min-w-0 truncate text-sm text-gray-600">{{ contact.role || '-' }}</div>
                                        <div class="min-w-0 truncate text-sm text-gray-600">{{ contact.email || '-' }}</div>
                                        <div class="min-w-0 truncate text-sm text-gray-600">{{ contact.phone || '-' }}</div>
                                        <div class="min-w-0 truncate text-sm text-gray-500">{{ contact.notes || '-' }}</div>
                                    </template>
                                </div>
                            </article>
                        </div>
                        <p v-else class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500">
                            Nessun referente inserito.
                        </p>
                    </section>

                    <section class="surface rounded-md p-5">
                        <div
                            class="mb-5 flex cursor-pointer flex-wrap items-center justify-between gap-3 rounded-[var(--radius-sm)] px-1 py-1 transition hover:bg-gray-50"
                            role="button"
                            tabindex="0"
                            :aria-expanded="subscriptionsOpen"
                            @click="subscriptionsOpen = !subscriptionsOpen"
                            @keydown.enter.prevent="subscriptionsOpen = !subscriptionsOpen"
                            @keydown.space.prevent="subscriptionsOpen = !subscriptionsOpen"
                        >
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Abbonamenti</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ related.subscriptions?.length || 0 }} ricorrenze collegate al cliente</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button v-if="canEditClient && editingSubscription" type="button" class="text-sm font-medium text-gray-500 hover:text-gray-800" @click.stop="resetSubscriptionForm">
                                    Annulla modifica
                                </button>
                                <span class="icon-btn pointer-events-none h-9 w-9">
                                    <ChevronDown :class="['h-4 w-4 transition-transform', subscriptionsOpen ? 'rotate-180' : '']" :stroke-width="1.8" />
                                </span>
                            </div>
                        </div>

                        <div v-show="subscriptionsOpen" class="space-y-5">
                        <form v-if="canEditClient" class="grid gap-3 rounded-md border border-gray-100 bg-gray-50 p-4 md:grid-cols-4" @submit.prevent="saveSubscription">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Nome *</label>
                                <input v-model="subscriptionForm.name" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Importo</label>
                                <input v-model="subscriptionForm.amount" type="number" step="0.01" min="0" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">IVA %</label>
                                <input v-model="subscriptionForm.vat_rate" type="number" step="0.01" min="0" class="form-control" required />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Descrizione riga fattura</label>
                                <input v-model="subscriptionForm.description" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ogni</label>
                                <input v-model="subscriptionForm.frequency_value" type="number" min="1" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Unita'</label>
                                <AppSelect v-model="subscriptionForm.frequency_unit" :options="subscriptionFrequencyOptions" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Inizio</label>
                                <AppDateInput v-model="subscriptionForm.start_date" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fine</label>
                                <AppDateInput v-model="subscriptionForm.end_date" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Prossima emissione</label>
                                <AppDateInput v-model="subscriptionForm.next_invoice_date" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Termini pagamento</label>
                                <input v-model="subscriptionForm.payment_terms_days" type="number" min="0" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Natura IVA</label>
                                <input v-model="subscriptionForm.vat_nature_code" class="form-control" placeholder="N2.2, N4..." />
                            </div>
                            <div class="flex items-end gap-4">
                                <label class="mb-2 flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="subscriptionForm.auto_generate" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Auto
                                </label>
                                <label class="mb-2 flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="subscriptionForm.active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    Attivo
                                </label>
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">Note interne</label>
                                <input v-model="subscriptionForm.notes" class="form-control" />
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50" :disabled="subscriptionForm.processing">
                                    {{ editingSubscription ? 'Salva' : 'Crea' }}
                                </button>
                            </div>
                        </form>

                        <div class="space-y-3">
                            <article
                                v-for="subscription in related.subscriptions || []"
                                :key="subscription.id"
                                :class="['rounded-md border p-4', subscription.active ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-70']"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="font-semibold text-gray-900">{{ subscription.name }}</h4>
                                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', subscription.active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600']">
                                                {{ subscription.active ? 'Attivo' : 'In pausa' }}
                                            </span>
                                            <span v-if="subscription.auto_generate" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">automatico</span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ money(subscription.amount) }} + IVA {{ subscription.vat_rate }}% · {{ subscriptionFrequency(subscription) }} · prossima {{ dateIt(subscription.next_invoice_date) }}
                                        </p>
                                        <p v-if="subscription.description" class="mt-1 text-sm text-gray-600">{{ subscription.description }}</p>
                                    </div>
                                    <div v-if="canEditClient" class="flex flex-wrap gap-2">
                                        <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="generateSubscription(subscription)">
                                            Genera fattura
                                        </button>
                                        <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="editSubscription(subscription)">
                                            Modifica
                                        </button>
                                        <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="toggleSubscription(subscription)">
                                            {{ subscription.active ? 'Pausa' : 'Riattiva' }}
                                        </button>
                                        <button type="button" class="rounded-md border border-red-100 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50" @click="removeSubscription(subscription)">
                                            Elimina
                                        </button>
                                    </div>
                                </div>
                            </article>
                            <p v-if="!(related.subscriptions || []).length" class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500">
                                Nessun abbonamento ricorrente per questo cliente.
                            </p>
                        </div>
                        </div>
                    </section>
                </section>

                <section v-if="section === 'projects'" class="space-y-6">
                    <section class="surface rounded-md p-5">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Panoramica progetto</h3>
                                <p class="mt-1 text-sm text-gray-500">Ogni campo si salva automaticamente mentre lavori.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span :class="['rounded-full px-3 py-1 text-xs font-semibold', projectForm.status === 'active' ? 'bg-emerald-100 text-emerald-700' : projectForm.status === 'completed' ? 'bg-indigo-100 text-indigo-700' : projectForm.status === 'on_hold' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600']">
                                    {{ displayValue(projectForm.status) }}
                                </span>
                                <div
                                    v-if="projectAutosaveState !== 'idle'"
                                    :class="[
                                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition',
                                        projectAutosaveState === 'saving' || projectAutosaveState === 'queued' ? 'bg-sky-50 text-sky-700' : '',
                                        projectAutosaveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : '',
                                        projectAutosaveState === 'error' ? 'bg-red-50 text-red-700' : '',
                                    ]"
                                >
                                    <span v-if="projectAutosaveState === 'queued'">In attesa...</span>
                                    <span v-else-if="projectAutosaveState === 'saving'">Salvataggio...</span>
                                    <span v-else-if="projectAutosaveState === 'saved'">Salvato</span>
                                    <span v-else>{{ projectAutosaveError || 'Errore salvataggio' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nome</label>
                                <input v-model="projectForm.name" class="form-control" required :readonly="!canEditProject" />
                                <div v-if="projectForm.errors.name" class="mt-1 text-sm text-red-600">{{ projectForm.errors.name }}</div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cliente</label>
                                    <AppSelect
                                        v-model="projectForm.client_id"
                                        :options="namedOptions(related.projectClients, { value: '', label: 'Nessun cliente' })"
                                        searchable
                                        :disabled="!canEditProject"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stato</label>
                                    <AppSelect v-model="projectForm.status" :options="projectStatusOptions" :disabled="!canEditProject" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Colore</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="color in projectColors"
                                        :key="color"
                                        type="button"
                                        :class="['h-8 w-8 rounded-full border-2', projectForm.color === color ? 'border-gray-900 ring-2 ring-gray-300' : 'border-white']"
                                        :style="{ backgroundColor: color }"
                                        :aria-label="`Colore ${color}`"
                                        :disabled="!canEditProject"
                                        @click="projectForm.color = color; saveProjectInline(0)"
                                    ></button>
                                    <label :class="['relative inline-flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white shadow-sm ring-1 ring-gray-200 transition', canEditProject ? 'cursor-pointer hover:ring-gray-300' : 'cursor-default opacity-70']" :style="{ backgroundColor: normalizeHexColor(projectForm.color) }">
                                        <span class="sr-only">Scegli colore custom</span>
                                        <input v-model="projectForm.color" type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" :disabled="!canEditProject" />
                                    </label>
                                    <input v-model="projectForm.color" type="text" class="form-control mt-0 w-28 font-mono text-xs" :readonly="!canEditProject" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="surface rounded-md p-5">
                        <div class="mb-5 space-y-3">
                            <div class="flex w-full flex-wrap gap-2">
                                <button
                                    v-for="tab in [
                                        { id: 'overview', label: 'Panoramica' },
                                        { id: 'tasks', label: 'Task' },
                                        { id: 'messages', label: 'Messaggi' },
                                        { id: 'files', label: 'File' },
                                    ]"
                                    :key="tab.id"
                                    type="button"
                                    :class="['settings-tab w-full sm:flex-1', projectWorkspaceTab === tab.id ? 'settings-tab-active' : '']"
                                    @click="projectWorkspaceTab = tab.id"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>
                            <div v-if="projectWorkspaceTab === 'tasks'" class="flex justify-end">
                                <span class="text-xs text-gray-500">{{ parentTaskRows(related.tasks).length }} elementi</span>
                            </div>
                        </div>

                        <div v-if="projectWorkspaceTab === 'overview'" class="mt-8 space-y-5">
                            <div>
                                <div class="toolbar mb-2">
                                    <button type="button" class="toolbar-btn" @click="runProjectDescriptionCommand('bold')"><Bold class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="runProjectDescriptionCommand('italic')"><Italic class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="runProjectDescriptionCommand('underline')"><Underline class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="runProjectDescriptionCommand('insertUnorderedList')"><List class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="runProjectDescriptionCommand('insertOrderedList')"><ListOrdered class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="addProjectDescriptionLink"><Link2 class="h-4 w-4" /></button>
                                </div>
                                <div
                                    ref="projectDescriptionEditor"
                                    contenteditable="true"
                                    class="form-control min-h-48 px-4 py-3 wysiwyg-content"
                                    data-placeholder="Descrizione del progetto..."
                                    @input="updateProjectDescriptionFromEditor"
                                    @blur="saveProjectInline(0)"
                                ></div>
                            </div>
                            <aside class="rounded-[var(--radius)] border border-gray-100 bg-gray-50/70 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Risorse progetto</h3>
                                        <p class="mt-1 text-xs text-gray-500">{{ related.resources?.length || 0 }} file collegati</p>
                                    </div>
                                    <button type="button" class="btn btn-outline px-3 py-1.5 text-xs" :disabled="projectFileForm.processing" @click="chooseProjectFile('resource')">
                                        <Paperclip class="h-3.5 w-3.5" :stroke-width="1.7" />
                                        Allega
                                    </button>
                                    <input ref="projectResourceInput" type="file" class="hidden" @change="uploadProjectFileFromInput($event, 'resource')" />
                                </div>
                                <div class="space-y-2">
                                    <div v-for="file in related.resources || []" :key="file.id" class="group flex items-center gap-3 rounded-[var(--radius-sm)] bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-100 transition hover:ring-indigo-100">
                                        <FileText class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
                                        <a :href="route('projects.files.download', [record.id, file.id])" class="min-w-0 flex-1 truncate font-medium text-gray-800 hover:text-indigo-700">
                                            {{ file.original_name }}
                                        </a>
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 opacity-0 transition hover:bg-red-50 group-hover:opacity-100" aria-label="Elimina file" @click="removeProjectFile(file)">
                                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                    </div>
                                    <p v-if="!(related.resources || []).length" class="rounded-[var(--radius-sm)] border border-dashed border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500">
                                        Nessuna risorsa allegata.
                                    </p>
                                </div>
                            </aside>
                        </div>

                        <div v-else-if="projectWorkspaceTab === 'tasks'" class="mt-8 overflow-visible">
                            <div class="hidden grid-cols-[24px_minmax(0,1.7fr)_minmax(140px,0.7fr)_140px_120px_120px] border-y border-gray-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400 md:grid">
                                <span></span>
                                <span>Nome</span>
                                <span>Incaricato</span>
                                <span>Scadenza</span>
                                <span>Stato</span>
                                <span>Priorità</span>
                            </div>
                            <div
                                v-for="sectionRow in projectTaskSections()"
                                :key="sectionRow.id"
                                :class="['border-b border-gray-100 transition last:border-b-0', draggedProjectTaskId && projectTaskDropSectionId === sectionRow.id ? 'bg-indigo-50/35 ring-1 ring-inset ring-indigo-200' : '']"
                                @dragover.prevent="dragOverProjectSection(sectionRow)"
                                @dragleave="leaveProjectTaskSection(sectionRow, $event)"
                                @drop.prevent="dropProjectTask(sectionRow)"
                            >
                                <div class="group/project-section flex w-full items-center gap-2 px-3 py-3 text-left text-sm font-semibold text-gray-800">
                                    <button type="button" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-50 hover:text-gray-700" :aria-expanded="!projectSectionCollapsed[sectionRow.id]" @click="toggleProjectSection(sectionRow.id)">
                                        <ChevronDown :class="['h-4 w-4 transition-transform', projectSectionCollapsed[sectionRow.id] ? '-rotate-90' : '']" :stroke-width="1.8" />
                                    </button>
                                    <input
                                        :value="projectSectionName(sectionRow)"
                                        :readonly="sectionRow.virtual"
                                        class="min-w-0 flex-1 cursor-pointer rounded-md border border-transparent bg-transparent px-2 py-1 text-sm font-semibold outline-none transition hover:border-gray-200 hover:bg-white focus:cursor-text focus:border-indigo-200 focus:bg-white focus:shadow-sm"
                                        @input="setProjectSectionName(sectionRow, $event.target.value)"
                                        @blur="saveProjectSectionName(sectionRow)"
                                        @keydown.enter.prevent="$event.target.blur()"
                                    />
                                    <div v-if="!sectionRow.virtual && canEditProject" class="relative shrink-0" data-project-section-menu>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 opacity-0 transition hover:bg-gray-50 hover:text-gray-800 group-hover/project-section:opacity-100 focus:opacity-100"
                                            aria-label="Azioni sezione"
                                            @click.stop="toggleProjectSectionActionMenu(sectionRow, $event)"
                                        >
                                            <MoreHorizontal class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                        <div
                                            v-if="projectSectionActionMenuOpen === sectionRow.id"
                                            :class="[
                                                'app-popover field-dropdown-menu absolute right-0 z-[7600] w-56 p-2',
                                                projectSectionActionMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2',
                                            ]"
                                            @click.stop
                                        >
                                            <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="duplicateProjectSection(sectionRow)">
                                                <Copy class="h-4 w-4" :stroke-width="1.7" />
                                                Duplica sezione
                                            </button>
                                            <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="collapseProjectSectionFromMenu(sectionRow)">
                                                <ChevronDown :class="['h-4 w-4 transition-transform', projectSectionCollapsed[sectionRow.id] ? '-rotate-90' : '']" :stroke-width="1.7" />
                                                {{ projectSectionCollapsed[sectionRow.id] ? 'Espandi' : 'Comprimi' }}
                                            </button>
                                            <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50" @click="removeProjectSection(sectionRow)">
                                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                                Elimina sezione
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-show="!projectSectionCollapsed[sectionRow.id]">
                                    <div
                                        v-for="task in projectTasksForSection(sectionRow)"
                                        :key="task.id"
                                        draggable="true"
                                        :class="['group/project-task relative grid w-full cursor-pointer gap-3 border-t border-gray-100 px-3 py-2.5 text-left text-sm transition hover:bg-indigo-50/40 md:grid-cols-[24px_minmax(0,1.7fr)_minmax(140px,0.7fr)_140px_120px_120px] md:items-center', task.status === 'done' ? 'opacity-60' : '', draggedProjectTaskId && draggedProjectTaskId !== task.id ? 'outline-offset-[-1px]' : '', projectTaskDropTarget === task.id ? (projectTaskDropPlacement === 'before' ? 'before:absolute before:left-3 before:right-3 before:top-0 before:h-1 before:rounded-full before:bg-indigo-500 before:shadow-[0_0_0_4px_rgba(99,102,241,0.12)]' : 'after:absolute after:bottom-0 after:left-3 after:right-3 after:h-1 after:rounded-full after:bg-indigo-500 after:shadow-[0_0_0_4px_rgba(99,102,241,0.12)]') : '']"
                                        @click="openProjectTaskDrawer(task)"
                                        @dragstart="startProjectTaskDrag(task)"
                                        @dragover.prevent="dragOverProjectTask(task, $event)"
                                        @drop.prevent.stop="dropProjectTask(sectionRow, task)"
                                        @dragend="endProjectTaskDrag"
                                    >
                                        <span class="hidden cursor-grab text-gray-300 transition group-hover/project-task:text-gray-500 md:inline-flex">
                                            <GripVertical class="h-4 w-4" :stroke-width="1.7" />
                                        </span>
                                        <button type="button" class="block w-full min-w-0 max-w-full text-left font-medium text-indigo-700" @click.stop="openProjectTaskDrawer(task)">
                                            <span :class="['block truncate', task.status === 'done' ? 'line-through' : '']">{{ task.title }}</span>
                                            <span v-if="projectTaskDependencyBadges(task).length" class="mt-1 inline-flex items-center gap-1.5 text-xs font-normal text-gray-500">
                                                <span
                                                    v-for="badge in projectTaskDependencyBadges(task)"
                                                    :key="`project-task-dependency-${task.id}-${badge.key}`"
                                                    :class="['group/dependency relative inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full ring-1', badge.class]"
                                                >
                                                    <component :is="badge.icon" class="h-3.5 w-3.5" :stroke-width="1.8" />
                                                    <span class="pointer-events-none absolute bottom-full left-1/2 z-[7800] mb-2 hidden w-max max-w-[240px] -translate-x-1/2 rounded-[var(--radius-sm)] bg-gray-950 px-2.5 py-1.5 text-xs font-semibold leading-4 text-white shadow-lg group-hover/dependency:block">
                                                        {{ badge.label }}
                                                    </span>
                                                </span>
                                            </span>
                                        </button>
                                        <div class="flex min-w-0 items-center gap-2 text-xs text-gray-600">
                                            <span v-if="task.assignees?.length" class="flex -space-x-2">
                                                <UserAvatar v-for="user in task.assignees.slice(0, 3)" :key="`project-task-user-${task.id}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                            </span>
                                            <span class="truncate">{{ task.assignees?.[0]?.name || task.assignees?.[0]?.email || 'Non assegnata' }}</span>
                                        </div>
                                        <span class="text-xs font-medium text-gray-500">{{ task.due_date ? dateIt(task.due_date) : '-' }}</span>
                                        <span>
                                            <span :class="['rounded-full px-2 py-1 text-xs font-semibold', projectTaskStatusClass(task.status)]">{{ displayValue(task.status) }}</span>
                                        </span>
                                        <span>
                                            <span :class="['rounded-full px-2 py-1 text-xs font-semibold', projectTaskPriorityClass(task.priority)]">{{ displayValue(task.priority) }}</span>
                                        </span>
                                    </div>
                                    <form :class="['border-t border-gray-100 px-3 py-2.5 transition', draggedProjectTaskId && projectTaskDropSectionId === sectionRow.id && !projectTaskDropTarget ? 'bg-indigo-50/70 ring-1 ring-inset ring-indigo-200' : '']" @submit.prevent="addProjectTask(sectionRow)" @dragover.prevent="dragOverProjectSection(sectionRow, true)" @drop.prevent.stop="dropProjectTask(sectionRow)">
                                    <input
                                        v-if="canEditProject"
                                        :value="projectTaskDrafts[sectionRow.id] || ''"
                                            class="subtask-line-control font-medium"
                                            placeholder="Aggiungi attività..."
                                            @input="setProjectTaskDraft(sectionRow.id, $event.target.value)"
                                            @keydown.enter.prevent="addProjectTask(sectionRow)"
                                            @blur="addProjectTask(sectionRow)"
                                        />
                                        <p v-else class="px-1 py-2 text-sm text-gray-400">Solo consultazione.</p>
                                    </form>
                                </div>
                            </div>
                            <div class="group/add-section px-3 py-3">
                                <form v-if="canEditProject && projectNewSectionOpen" class="mb-2 flex max-w-lg items-center gap-2 rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 px-3 py-2" @submit.prevent="addProjectSection">
                                    <Plus class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
                                    <input
                                        ref="projectNewSectionInput"
                                        v-model="projectNewSectionName"
                                        class="subtask-line-control font-medium"
                                        placeholder="Nome sezione"
                                        @keydown.enter.prevent="addProjectSection"
                                        @blur="addProjectSection"
                                    />
                                </form>
                                <button
                                    v-if="canEditProject"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-semibold text-gray-400 opacity-80 transition hover:bg-gray-50 hover:text-indigo-600 group-hover/add-section:opacity-100"
                                    @click="showProjectSectionInput"
                                >
                                    <Plus class="h-4 w-4" :stroke-width="1.7" />
                                    Aggiungi sezione
                                </button>
                            </div>
                        </div>

                        <div v-else-if="projectWorkspaceTab === 'messages'" class="mt-8 space-y-4">
                            <form class="space-y-3" @submit.prevent="submitProjectMessage">
                                <div class="toolbar mb-2">
                                    <button type="button" class="toolbar-btn" @click="runProjectMessageCommand('bold')"><Bold class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="runProjectMessageCommand('italic')"><Italic class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="runProjectMessageCommand('insertUnorderedList')"><List class="h-4 w-4" /></button>
                                    <button type="button" class="toolbar-btn" @click="addProjectMessageLink"><Link2 class="h-4 w-4" /></button>
                                </div>
                                <div
                                    ref="projectMessageEditor"
                                    contenteditable="true"
                                    class="form-control min-h-28 px-4 py-3 wysiwyg-content bg-white"
                                    data-placeholder="Scrivi un messaggio per il progetto..."
                                    @input="updateProjectMessageFromEditor"
                                ></div>
                                <div class="mt-3 flex justify-end">
                                    <button type="submit" class="btn btn-primary px-4 py-2 text-sm" :disabled="projectMessageForm.processing">
                                        <Send class="h-4 w-4" :stroke-width="1.7" />
                                        Pubblica
                                    </button>
                                </div>
                            </form>

                            <article v-for="message in related.messages || []" :key="message.id" class="rounded-md border border-gray-100 bg-white p-4 shadow-sm">
                                <div class="mb-3 flex items-center gap-3">
                                    <UserAvatar :user="{ name: message.user_name, email: message.user_email, avatar_url: message.user_avatar_url }" size="sm" />
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ message.user_name || message.user_email || 'Utente' }}</p>
                                        <p class="text-xs text-gray-500">{{ dateTimeIt(message.created_at) }}</p>
                                    </div>
                                </div>
                                <div class="wysiwyg-content text-sm text-gray-700" v-html="message.content"></div>
                            </article>
                            <p v-if="!(related.messages || []).length" class="rounded-md border border-dashed border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                                Nessun messaggio nel progetto.
                            </p>
                        </div>

                        <div v-else class="mt-8 space-y-4">
                            <div
                                :class="['rounded-md border border-dashed p-8 text-center transition', projectFileDragActive ? 'border-indigo-300 bg-indigo-50/70' : 'border-gray-200 bg-gray-50/70 hover:border-indigo-200 hover:bg-indigo-50/40']"
                                @dragover.prevent="projectFileDragActive = true"
                                @dragleave.prevent="projectFileDragActive = false"
                                @drop.prevent="dropProjectFile"
                            >
                                <UploadCloud class="mx-auto h-8 w-8 text-gray-400" :stroke-width="1.6" />
                                <p class="mt-3 text-sm font-semibold text-gray-800">Trascina qui i file del progetto</p>
                                <p class="mt-1 text-xs text-gray-500">Oppure selezionali dal tuo computer.</p>
                                <button type="button" class="btn btn-outline mt-4 px-4 py-2 text-sm" :disabled="projectFileForm.processing" @click="chooseProjectFile('file')">
                                    <Paperclip class="h-4 w-4" :stroke-width="1.7" />
                                    Carica file
                                </button>
                                <input ref="projectFileInput" type="file" class="hidden" @change="uploadProjectFileFromInput($event, 'file')" />
                            </div>

                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                <article v-for="file in related.files || []" :key="file.id" class="group rounded-md border border-gray-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-100 hover:shadow-md">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-500">
                                            <FileText class="h-5 w-5" :stroke-width="1.7" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <a :href="route('projects.files.download', [record.id, file.id])" class="block truncate text-sm font-semibold text-gray-900 hover:text-indigo-700">
                                                {{ file.original_name }}
                                            </a>
                                            <p class="mt-1 text-xs text-gray-500">{{ fileSize(file.size) }} · {{ file.uploaded_by_name || 'Utente' }} · {{ dateIt(file.created_at) }}</p>
                                        </div>
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 opacity-0 transition hover:bg-red-50 group-hover:opacity-100" aria-label="Elimina file" @click="removeProjectFile(file)">
                                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                    </div>
                                </article>
                            </div>
                            <p v-if="!(related.files || []).length" class="rounded-md border border-dashed border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500">
                                Nessun file caricato nel progetto.
                            </p>
                        </div>
                    </section>
                </section>

                <section v-if="section === 'tasks'" class="surface rounded-md p-5">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dettagli task</h3>
                            <p class="mt-1 text-sm text-gray-500">Ogni campo si salva automaticamente mentre lavori.</p>
                        </div>
                        <div
                            v-if="taskAutosaveState !== 'idle'"
                            :class="[
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition',
                                taskAutosaveState === 'saving' || taskAutosaveState === 'queued' ? 'bg-sky-50 text-sky-700' : '',
                                taskAutosaveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : '',
                                taskAutosaveState === 'error' ? 'bg-red-50 text-red-700' : '',
                            ]"
                        >
                            <span v-if="taskAutosaveState === 'queued'">In attesa...</span>
                            <span v-else-if="taskAutosaveState === 'saving'">Salvataggio...</span>
                            <span v-else-if="taskAutosaveState === 'saved'">Salvato</span>
                            <span v-else>{{ taskAutosaveError || 'Errore salvataggio' }}</span>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Titolo</label>
                            <input v-model="taskForm.title" class="form-control" required />
                            <div v-if="taskForm.errors.title" class="mt-1 text-sm text-red-600">{{ taskForm.errors.title }}</div>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-3">
                            <button
                                type="button"
                                :class="[
                                    'rounded-[var(--radius-sm)] border px-3 py-2 text-left text-sm font-semibold transition',
                                    taskForm.task_type === 'project' || taskForm.task_type === 'task'
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
                                        : 'border-gray-200 bg-white text-gray-700 opacity-45 hover:bg-gray-50 hover:opacity-80',
                                ]"
                                @click="setTaskType('project')"
                            >
                                <span class="block">Task</span>
                                <span class="mt-0.5 block text-xs font-normal text-gray-500">Attivita di progetto</span>
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'rounded-[var(--radius-sm)] border px-3 py-2 text-left text-sm font-semibold transition',
                                    taskForm.task_type === 'ongoing'
                                        ? 'border-amber-500 bg-amber-50 text-amber-800 shadow-sm'
                                        : 'border-amber-200 bg-white text-amber-700 opacity-45 hover:bg-amber-50 hover:opacity-80',
                                ]"
                                @click="setTaskType('ongoing')"
                            >
                                <span class="block">Continuativa</span>
                                <span class="mt-0.5 block text-xs font-normal text-gray-500">Ricorrente o operativa</span>
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'rounded-[var(--radius-sm)] border px-3 py-2 text-left text-sm font-semibold transition',
                                    taskForm.task_type === 'meeting'
                                        ? 'border-violet-500 bg-violet-50 text-violet-800 shadow-sm'
                                        : 'border-violet-200 bg-white text-violet-700 opacity-45 hover:bg-violet-50 hover:opacity-80',
                                ]"
                                @click="setTaskType('meeting')"
                            >
                                <span class="block">Meeting</span>
                                <span class="mt-0.5 block text-xs font-normal text-gray-500">Data, ora e luogo</span>
                            </button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stato</label>
                                <AppSelect v-model="taskForm.status" :options="taskStatusOptions" @change="setTaskStatus" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Priorità</label>
                                <AppSelect v-model="taskForm.priority" :options="priorityOptions" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Inizio</label>
                                <AppDateInput v-model="taskForm.start_date" @change="saveTaskInline(0)" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                                <AppDateInput v-model="taskForm.due_date" @change="saveTaskInline(0)" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ora</label>
                                <AppTimeInput v-model="taskForm.due_time" @change="saveTaskInline(0)" />
                            </div>
                            <div v-if="taskForm.task_type === 'project' || taskForm.task_type === 'task'">
                                <label class="block text-sm font-medium text-gray-700">Progetto</label>
                                <AppSelect
                                    v-model="taskForm.project_id"
                                    :options="namedOptions(related.taskProjects, { value: '', label: 'Nessun progetto' })"
                                    searchable
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Cliente</label>
                                <AppSelect
                                    v-model="taskForm.client_id"
                                    :options="namedOptions(related.taskClients, { value: '', label: 'Nessun cliente' })"
                                    searchable
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Servizio</label>
                                <AppSelect
                                    v-model="taskForm.service_id"
                                    :options="namedOptions(related.taskServices, { value: '', label: 'Nessun servizio' })"
                                    searchable
                                />
                            </div>
                            <div v-if="taskForm.task_type === 'meeting'" class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Luogo / link</label>
                                <input v-model="taskForm.location" class="form-control" placeholder="Sala riunioni o link meeting" />
                            </div>
                        </div>

                        <section class="rounded-md border border-gray-100 bg-gray-50 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dipendenze</h3>
                                    <p class="mt-1 text-xs text-gray-500">La task resta bloccata finché le task selezionate non sono completate.</p>
                                </div>
                                <span
                                    v-if="blockedDependencyCount()"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-100"
                                    title="Task bloccata"
                                >
                                    <GitBranch class="h-4 w-4" :stroke-width="1.8" />
                                </span>
                            </div>
                            <div class="grid gap-2 md:grid-cols-[170px_minmax(0,1fr)]">
                                <AppSelect
                                    v-model="taskDependencyDirection"
                                    :options="taskDependencyDirectionOptions"
                                    placeholder="Tipo relazione"
                                />
                                <AppSelect
                                    v-model="taskDependencyToAdd"
                                    :options="taskDependencySelectOptions()"
                                    :placeholder="taskDependencyDirection === 'blocks' ? 'Scegli task bloccata' : 'Scegli task bloccante'"
                                    searchable
                                    @change="addTaskDependency"
                                />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="dependency in selectedTaskDependencies()"
                                    :key="`dependency-${dependency.id}`"
                                    :class="['inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold', dependency.status === 'done' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']"
                                >
                                    <span class="truncate">{{ dependency.title }}</span>
                                    <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi dipendenza" @click="removeTaskDependency(dependency.id)">
                                        <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                    </button>
                                </span>
                                <span v-if="!selectedTaskDependencies().length" class="text-xs text-gray-500">Nessuna dipendenza.</span>
                            </div>
                            <div v-if="selectedTaskDependents().length" class="mt-3 border-t border-gray-100 pt-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Questa task blocca</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        v-for="dependent in selectedTaskDependents()"
                                        :key="`dependent-${dependent.id}`"
                                        :class="['inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset', dependent.status === 'done' ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-gray-100 text-gray-700 ring-gray-200']"
                                    >
                                        <span class="truncate">{{ dependent.title }}</span>
                                        <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi relazione" @click="removeTaskDependent(dependent.id)">
                                            <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <div v-if="taskForm.errors.dependencies || taskForm.errors.status" class="mt-2 text-sm text-red-600">
                                {{ taskForm.errors.dependencies || taskForm.errors.status }}
                            </div>
                        </section>

                        <div v-if="taskForm.task_type !== 'meeting'" class="rounded-md border border-gray-100 bg-gray-50 p-4">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input v-model="taskForm.recurring_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Ricorrente
                            </label>
                            <div v-if="taskForm.recurring_enabled" class="mt-3 grid gap-3 md:grid-cols-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500">Ogni</label>
                                    <input v-model="taskForm.recurring_interval_value" type="number" min="1" class="form-control" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500">Unita</label>
                                    <AppSelect v-model="taskForm.recurring_interval_unit" :options="recurrenceUnitOptions" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500">Modalita</label>
                                    <AppSelect v-model="taskForm.recurring_mode" :options="recurrenceModeOptions" />
                                </div>
                                <div v-if="taskForm.recurring_interval_unit === 'month' && taskForm.recurring_mode === 'fixed'">
                                    <label class="block text-xs font-medium text-gray-500">Giorno mese</label>
                                    <input v-model="taskForm.recurring_month_day" type="number" min="1" max="31" class="form-control" />
                                </div>
                                <div v-if="taskForm.recurring_interval_unit === 'week'">
                                    <label class="block text-xs font-medium text-gray-500">Giorno settimana</label>
                                    <AppSelect v-model="taskForm.recurring_weekday" :options="weekdayOptions" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                            <div class="mt-2 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                    <button type="button" class="icon-btn h-8 w-8" title="Titolo" @mousedown.prevent @click="runTaskEditorCommand('formatBlock', 'h3')">
                                        <Heading3 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8 text-xs font-bold" title="Testo normale" @mousedown.prevent @click="runTaskEditorCommand('formatBlock', 'p')">
                                        P
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runTaskEditorCommand('bold')">
                                        <Bold class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runTaskEditorCommand('italic')">
                                        <Italic class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runTaskEditorCommand('underline')">
                                        <Underline class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runTaskEditorCommand('insertUnorderedList')">
                                        <List class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runTaskEditorCommand('insertOrderedList')">
                                        <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Citazione" @mousedown.prevent @click="runTaskEditorCommand('formatBlock', 'blockquote')">
                                        <Quote class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addTaskEditorLink">
                                        <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                                <div
                                    ref="taskDescriptionEditor"
                                    class="min-h-[150px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                    contenteditable="true"
                                    data-placeholder="Aggiungi una descrizione..."
                                    v-html="taskForm.description"
                                    @input="updateTaskDescriptionFromEditor"
                                    @blur="updateTaskDescriptionFromEditor"
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="section === 'users'" class="space-y-6 lg:order-1">
                    <section class="surface rounded-md p-5">
                        <div class="flex flex-wrap items-center gap-4 rounded-md border border-gray-100 bg-gray-50 p-4">
                            <UserAvatar :user="userPreview()" size="lg" />
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-900">Foto personale</div>
                                <p class="mt-1 text-xs text-gray-500">JPG, PNG o WEBP fino a 2 MB. La foto comparira' ovunque viene usato il volto dell'utente.</p>
                                <div v-if="userAvatarForm.errors.avatar" class="mt-2 text-sm text-red-600">{{ userAvatarForm.errors.avatar }}</div>
                            </div>
                            <input ref="userAvatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadUserAvatar" />
                            <button type="button" class="btn btn-outline" :disabled="userAvatarForm.processing" @click="chooseUserAvatar">
                                {{ userAvatarForm.processing ? 'Caricamento...' : 'Carica foto' }}
                            </button>
                        </div>
                    </section>

                    <section class="surface rounded-md p-5">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Informazioni profilo</h3>
                                <p class="mt-1 text-sm text-gray-500">Le modifiche si salvano automaticamente mentre lavori.</p>
                            </div>
                            <div
                                v-if="userAutosaveState !== 'idle'"
                                :class="[
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition',
                                    userAutosaveState === 'saving' || userAutosaveState === 'queued' ? 'bg-sky-50 text-sky-700' : '',
                                    userAutosaveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : '',
                                    userAutosaveState === 'error' ? 'bg-red-50 text-red-700' : '',
                                ]"
                            >
                                <span v-if="userAutosaveState === 'queued'">In attesa...</span>
                                <span v-else-if="userAutosaveState === 'saving'">Salvataggio...</span>
                                <span v-else-if="userAutosaveState === 'saved'">Salvato</span>
                                <span v-else>{{ userAutosaveError || 'Errore salvataggio' }}</span>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nome</label>
                                <input v-model="userForm.name" class="form-control" required />
                                <div v-if="userForm.errors.name" class="mt-1 text-sm text-red-600">{{ userForm.errors.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input v-model="userForm.email" type="email" class="form-control" required />
                                <div v-if="userForm.errors.email" class="mt-1 text-sm text-red-600">{{ userForm.errors.email }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ruolo</label>
                                <AppSelect v-model="userForm.role" :options="primitiveOptions(related.roleOptions)" />
                                <div v-if="userForm.errors.role" class="mt-1 text-sm text-red-600">{{ userForm.errors.role }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Qualifica</label>
                                <input v-model="userForm.job_title" class="form-control" placeholder="Es. Account manager" />
                                <div v-if="userForm.errors.job_title" class="mt-1 text-sm text-red-600">{{ userForm.errors.job_title }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Matricola</label>
                                <input v-model="userForm.employee_code" class="form-control" placeholder="Es. 0001" />
                                <div v-if="userForm.errors.employee_code" class="mt-1 text-sm text-red-600">{{ userForm.errors.employee_code }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Telefono</label>
                                <input v-model="userForm.phone" class="form-control" />
                                <div v-if="userForm.errors.phone" class="mt-1 text-sm text-red-600">{{ userForm.errors.phone }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Animazione completamento</label>
                                <AppSelect v-model="userForm.completion_effect" :options="completionEffectOptions" @change="saveUserInline(0)" />
                                <div v-if="userForm.errors.completion_effect" class="mt-1 text-sm text-red-600">{{ userForm.errors.completion_effect }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Giorno smart working</label>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="day in smartworkingWeekdayOptions"
                                        :key="`smartworking-day-${day.value}`"
                                        type="button"
                                        :class="[
                                            'inline-flex h-10 w-10 items-center justify-center rounded-full border text-xs font-bold transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-[hsl(var(--primary-app)/0.24)]',
                                            userForm.smartworking_day === day.value
                                                ? 'border-[hsl(var(--primary-app))] bg-[hsl(var(--primary-app))] text-white shadow-[0_10px_24px_rgba(37,99,235,0.22)]'
                                                : 'border-gray-200 bg-white text-gray-500 hover:border-[hsl(var(--primary-app)/0.45)] hover:text-[hsl(var(--primary-app))]',
                                        ]"
                                        :aria-pressed="userForm.smartworking_day === day.value"
                                        :title="day.label"
                                        @click="selectSmartworkingDay(day.value)"
                                    >
                                        {{ smartworkingDayShortLabel(day.value) }}
                                    </button>
                                    <button
                                        type="button"
                                        :class="[
                                            'ml-1 inline-flex h-10 items-center rounded-full border px-3 text-xs font-semibold transition hover:-translate-y-0.5',
                                            userForm.smartworking_day === 'none'
                                                ? 'border-gray-300 bg-gray-100 text-gray-700'
                                                : 'border-gray-200 bg-white text-gray-400 hover:text-gray-700',
                                        ]"
                                        @click="selectSmartworkingDay('none')"
                                    >
                                        Nessuno
                                    </button>
                                </div>
                                <div v-if="userForm.errors.smartworking_day" class="mt-1 text-sm text-red-600">{{ userForm.errors.smartworking_day }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nuova password</label>
                                <input v-model="userForm.password" type="password" autocomplete="new-password" class="form-control" placeholder="Lascia vuoto per non cambiarla" />
                                <div v-if="userForm.errors.password" class="mt-1 text-sm text-red-600">{{ userForm.errors.password }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Bio</label>
                                <textarea v-model="userForm.bio" rows="5" class="form-control" placeholder="Note interne sul profilo..."></textarea>
                                <div v-if="userForm.errors.bio" class="mt-1 text-sm text-red-600">{{ userForm.errors.bio }}</div>
                            </div>
                        </div>
                    </section>
                </section>

                <section v-if="section === 'users'" class="surface rounded-md p-5 lg:order-3 lg:col-span-2">
                        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Andamento persona</h3>
                                <p class="mt-1 text-sm text-gray-500">Sintesi operativa su carico, scadenze, completamenti e responsabilità.</p>
                            </div>
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 px-3 py-2 text-right">
                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Efficienza 30 gg</span>
                                <span class="text-xl font-bold text-gray-900">{{ userPerformance.completionRate }}%</span>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                            <div
                                v-for="kpi in userPerformance.kpis"
                                :key="kpi.label"
                                :class="['rounded-[var(--radius-sm)] border p-4', userKpiClass(kpi.tone)]"
                            >
                                <div class="text-xs font-semibold uppercase tracking-wide opacity-75">{{ kpi.label }}</div>
                                <div class="mt-2 text-3xl font-bold leading-none">{{ kpi.value }}</div>
                                <div class="mt-2 text-xs font-medium opacity-80">{{ kpi.detail }}</div>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-5 xl:grid-cols-2">
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-900">Distribuzione task</h4>
                                    <span class="text-xs font-medium text-gray-400">Totale assegnate</span>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="row in userPerformance.status" :key="row.key" class="grid grid-cols-[90px_minmax(0,1fr)_32px] items-center gap-3 text-sm">
                                        <span class="font-medium text-gray-600">{{ row.label }}</span>
                                        <span class="h-2 overflow-hidden rounded-full bg-white">
                                            <span class="block h-full rounded-full bg-[hsl(var(--primary-app))]" :style="{ width: userStatBarWidth(row.value, userPerformance.status) }"></span>
                                        </span>
                                        <span class="text-right font-semibold text-gray-900">{{ row.value }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-900">Priorità aperte</h4>
                                    <span class="text-xs font-medium text-gray-400">Solo non completate</span>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="row in userPerformance.priority" :key="row.key" class="grid grid-cols-[90px_minmax(0,1fr)_32px] items-center gap-3 text-sm">
                                        <span class="font-medium text-gray-600">{{ row.label }}</span>
                                        <span class="h-2 overflow-hidden rounded-full bg-white">
                                            <span :class="['block h-full rounded-full', userPriorityClass(row.key)]" :style="{ width: userStatBarWidth(row.value, userPerformance.priority) }"></span>
                                        </span>
                                        <span class="text-right font-semibold text-gray-900">{{ row.value }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(260px,0.65fr)_minmax(260px,0.75fr)]">
                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-white/70 p-4">
                                <h4 class="text-sm font-semibold text-gray-900">Prossime scadenze</h4>
                                <div v-if="userPerformance.upcomingTasks.length" class="mt-3 divide-y divide-gray-100">
                                    <Link
                                        v-for="task in userPerformance.upcomingTasks"
                                        :key="task.id"
                                        :href="route('tasks.show', task.id)"
                                        class="group flex items-start justify-between gap-3 py-2.5 text-sm transition hover:text-[hsl(var(--primary-app))]"
                                    >
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-gray-900 transition group-hover:text-[hsl(var(--primary-app))]">{{ task.title }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-gray-500">{{ userTaskMeta(task) }}</span>
                                        </span>
                                        <span :class="['mt-1 h-2.5 w-2.5 shrink-0 rounded-full', userPriorityClass(task.priority)]"></span>
                                    </Link>
                                </div>
                                <p v-else class="mt-3 text-sm text-gray-500">Nessuna scadenza aperta.</p>
                            </div>

                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-white/70 p-4">
                                <h4 class="text-sm font-semibold text-gray-900">Presenze e letture</h4>
                                <dl class="mt-3 space-y-3 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-500">Assenze approvate anno</dt>
                                        <dd class="font-semibold text-gray-900">{{ userPerformance.absence.approvedDaysYear || 0 }} gg</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-500">Richieste assenza</dt>
                                        <dd class="font-semibold text-gray-900">{{ userPerformance.absence.approvedRequestsYear || 0 }} approvate</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-gray-500">In attesa</dt>
                                        <dd class="font-semibold text-gray-900">{{ userPerformance.absence.pendingRequests || 0 }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-[var(--radius-sm)] border border-gray-100 bg-white/70 p-4">
                                <h4 class="text-sm font-semibold text-gray-900">Ultime completate</h4>
                                <div v-if="userPerformance.recentCompletedTasks.length" class="mt-3 space-y-2">
                                    <Link
                                        v-for="task in userPerformance.recentCompletedTasks"
                                        :key="task.id"
                                        :href="route('tasks.show', task.id)"
                                        class="block rounded-[var(--radius-sm)] bg-gray-50 px-3 py-2 text-xs transition hover:bg-[hsl(var(--primary-app)/0.08)]"
                                    >
                                        <span class="block truncate font-semibold text-gray-900">{{ task.title }}</span>
                                        <span class="mt-0.5 block truncate text-gray-500">{{ [task.client_name, dateIt(task.updated_at)].filter(Boolean).join(' - ') }}</span>
                                    </Link>
                                </div>
                                <p v-else class="mt-3 text-xs text-gray-500">Nessuna task completata di recente.</p>
                            </div>
                        </div>
                </section>

                <section v-if="section === 'absences'" class="grid gap-6 lg:col-span-2 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <section class="surface rounded-md p-5">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Richiesta assenza</h3>
                                <p class="mt-1 text-sm text-gray-500">Le modifiche si salvano automaticamente mentre lavori.</p>
                            </div>
                            <div
                                v-if="absenceAutosaveState !== 'idle'"
                                :class="[
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition',
                                    absenceAutosaveState === 'saving' || absenceAutosaveState === 'queued' ? 'bg-sky-50 text-sky-700' : '',
                                    absenceAutosaveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : '',
                                    absenceAutosaveState === 'error' ? 'bg-red-50 text-red-700' : '',
                                ]"
                            >
                                <span v-if="absenceAutosaveState === 'queued'">In attesa...</span>
                                <span v-else-if="absenceAutosaveState === 'saving'">Salvataggio...</span>
                                <span v-else-if="absenceAutosaveState === 'saved'">Salvato</span>
                                <span v-else>{{ absenceAutosaveError || 'Errore salvataggio' }}</span>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3 2xl:grid-cols-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tipo richiesta</label>
                                <AppSelect v-model="absenceForm.type" :options="absenceTypeOptions" @change="saveAbsenceInline(0)" />
                                <div v-if="absenceForm.errors.type" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.type }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ absenceNeedsEndDate() ? 'Dal' : 'Giorno' }}</label>
                                <AppDateInput v-model="absenceForm.start_date" @change="saveAbsenceInline(0)" />
                                <div v-if="absenceForm.errors.start_date" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.start_date }}</div>
                            </div>
                            <div v-if="absenceNeedsEndDate()">
                                <label class="block text-sm font-medium text-gray-700">Al</label>
                                <AppDateInput v-model="absenceForm.end_date" @change="saveAbsenceInline(0)" />
                                <div v-if="absenceForm.errors.end_date" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.end_date }}</div>
                            </div>
                            <div v-if="absenceNeedsTime()">
                                <label class="block text-sm font-medium text-gray-700">Ora inizio</label>
                                <AppSelect v-model="absenceForm.start_time" :options="absenceHourOptions" placeholder="Seleziona ora" @change="saveAbsenceInline(0)" />
                            </div>
                            <div v-if="absenceNeedsTime()">
                                <label class="block text-sm font-medium text-gray-700">Ora fine</label>
                                <AppSelect v-model="absenceForm.end_time" :options="absenceHourOptions" placeholder="Seleziona ora" @change="saveAbsenceInline(0)" />
                            </div>
                            <div v-if="absenceForm.type === 'sickness'">
                                <label class="block text-sm font-medium text-gray-700">Codice INPS</label>
                                <input v-model="absenceForm.inps_code" class="form-control" placeholder="Codice INPS" @input="saveAbsenceInline()" />
                                <div v-if="absenceForm.errors.inps_code" class="mt-1 text-sm text-red-600">{{ absenceForm.errors.inps_code }}</div>
                            </div>
                            <div v-if="absenceForm.type === 'sickness'" class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Documento medico</label>
                                <input ref="absenceMedicalDocumentInput" type="file" accept=".pdf,image/jpeg,image/png,image/webp" class="hidden" @change="uploadAbsenceMedicalDocument" />
                                <div class="mt-1 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <a
                                        v-if="record.medical_document_path"
                                        :href="route('absences.medical-document.download', record.id)"
                                        class="group flex min-h-[38px] min-w-0 items-center gap-2 rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 px-3 text-sm font-semibold text-gray-700 transition hover:border-indigo-100 hover:bg-white hover:text-indigo-600"
                                    >
                                        <FileText class="h-4 w-4 shrink-0 text-indigo-500" :stroke-width="1.7" />
                                        <span class="truncate">{{ record.medical_document_name || 'Documento medico' }}</span>
                                    </a>
                                    <div v-else class="flex min-h-[38px] items-center gap-2 rounded-[var(--radius-sm)] border border-dashed border-gray-200 bg-gray-50 px-3 text-sm text-gray-500">
                                        <FileText class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
                                        Nessun documento caricato
                                    </div>
                                    <button type="button" class="btn btn-outline" :disabled="absenceMedicalDocumentForm.processing" @click="chooseAbsenceMedicalDocument">
                                        <Paperclip class="h-4 w-4" :stroke-width="1.7" />
                                        {{ record.medical_document_path ? 'Sostituisci' : 'Allega' }}
                                    </button>
                                </div>
                                <div v-if="absenceMedicalDocumentForm.errors.medical_document" class="mt-1 text-sm text-red-600">{{ absenceMedicalDocumentForm.errors.medical_document }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stato</label>
                                <AppSelect v-model="absenceForm.status" :options="absenceStatusOptions" @change="saveAbsenceInline(0)" />
                            </div>
                            <div class="md:col-span-3 2xl:col-span-4">
                                <label class="block text-sm font-medium text-gray-700">Note</label>
                                <div class="mt-2 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                    <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                        <button type="button" class="icon-btn h-8 w-8" title="Titolo" @mousedown.prevent @click="runAbsenceNotesCommand('formatBlock', 'h3')">
                                            <Heading3 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8 text-xs font-bold" title="Testo normale" @mousedown.prevent @click="runAbsenceNotesCommand('formatBlock', 'p')">
                                            P
                                        </button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runAbsenceNotesCommand('bold')">
                                            <Bold class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runAbsenceNotesCommand('italic')">
                                            <Italic class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runAbsenceNotesCommand('underline')">
                                            <Underline class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runAbsenceNotesCommand('insertUnorderedList')">
                                            <List class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runAbsenceNotesCommand('insertOrderedList')">
                                            <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Citazione" @mousedown.prevent @click="runAbsenceNotesCommand('formatBlock', 'blockquote')">
                                            <Quote class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addAbsenceNotesLink">
                                            <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                    </div>
                                    <div
                                        ref="absenceNotesEditor"
                                        class="min-h-[150px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                        contenteditable="true"
                                        data-placeholder="Aggiungi note..."
                                        v-html="absenceForm.notes"
                                        @input="updateAbsenceNotesFromEditor(); saveAbsenceInline()"
                                        @blur="updateAbsenceNotesFromEditor(); saveAbsenceInline(0)"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <aside class="space-y-4">
                        <section class="surface rounded-md p-5">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Azioni</h3>
                            <div class="mt-4 grid gap-2">
                                <button v-if="absenceForm.status === 'pending'" type="button" class="btn btn-primary justify-center" @click="setAbsenceStatus('approved')">Approva</button>
                                <button v-if="absenceForm.status === 'pending'" type="button" class="btn btn-outline justify-center" @click="setAbsenceStatus('rejected')">Rifiuta</button>
                                <button type="button" class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 justify-center" @click="deleteAbsenceFromDetail">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    Elimina
                                </button>
                            </div>
                        </section>
                        <section class="surface rounded-md p-5">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Persona</h3>
                            <div class="mt-4 flex items-center gap-3">
                                <UserAvatar :user="related.user" size="md" />
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-gray-900">{{ related.user?.name }}</div>
                                    <div class="truncate text-xs text-gray-500">{{ related.user?.email }}</div>
                                </div>
                            </div>
                        </section>
                    </aside>
                </section>

                <section v-if="section !== 'clients' && section !== 'tasks' && section !== 'projects' && section !== 'users' && section !== 'absences'" class="surface rounded-md p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Dettagli</h3>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div v-for="[key, value] in visibleEntries" :key="key" class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ labels[key] || key.replaceAll('_', ' ') }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ displayValue(value) }}</dd>
                        </div>
                    </dl>
                </section>

                <aside :class="['space-y-6', section === 'users' ? 'lg:order-2' : '']">
                    <section v-if="section === 'clients'" class="surface rounded-md p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Servizi collegati</h3>
                                <p class="mt-1 text-xs text-gray-500">Clicca un servizio per attivarlo o disattivarlo.</p>
                            </div>
                            <span class="whitespace-nowrap rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-500">{{ clientServiceIds.length }} attivi</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="service in related.services"
                                :key="service.id"
                                type="button"
                                :aria-pressed="clientHasService(service)"
                                :class="[
                                    'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition duration-200 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200',
                                    clientHasService(service)
                                        ? 'border-indigo-200 bg-indigo-50 text-indigo-700 shadow-[0_10px_24px_rgba(79,70,229,0.12)]'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-100 hover:bg-gray-50',
                                    serviceToggleStates[service.id] === 'saving' ? 'opacity-70' : '',
                                ]"
                                @click="toggleService(service)"
                            >
                                <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: service.color || '#2563eb' }"></span>
                                <Check :class="['h-3.5 w-3.5 shrink-0 transition', clientHasService(service) ? 'opacity-100' : 'opacity-0']" :stroke-width="2" />
                                {{ service.name }}
                            </button>
                        </div>
                        <p v-if="!related.services?.length" class="mt-3 text-sm text-gray-500">Nessun servizio configurato.</p>
                    </section>

                    <section v-if="section === 'tasks' && related.client" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Cliente</h3>
                        <Link
                            v-if="!isGuest"
                            :href="route('clients.show', related.client.id)"
                            class="group/item mt-2 block rounded-md border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-100 hover:bg-white hover:shadow-[0_12px_28px_rgba(28,42,73,0.10)] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                        >
                            <span class="block truncate font-semibold text-gray-900 transition group-hover/item:text-indigo-600">
                                {{ related.client.name }}
                            </span>
                            <span v-if="related.client.legal_name || related.client.vat_number || related.client.tax_code" class="mt-1 block truncate text-xs text-gray-500">
                                {{ [related.client.legal_name, related.client.vat_number || related.client.tax_code].filter(Boolean).join(' · ') }}
                            </span>
                        </Link>
                        <div v-else class="mt-2 block rounded-md border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm">
                            <span class="block truncate font-semibold text-gray-900">{{ related.client.name }}</span>
                            <span v-if="related.client.legal_name || related.client.vat_number || related.client.tax_code" class="mt-1 block truncate text-xs text-gray-500">
                                {{ [related.client.legal_name, related.client.vat_number || related.client.tax_code].filter(Boolean).join(' · ') }}
                            </span>
                        </div>
                    </section>

                    <section v-if="section === 'tasks'" class="surface rounded-md p-5">
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">Assegnatari</h3>
                            <p class="mt-1 text-xs text-gray-500">Clicca sui volti per aggiungere o rimuovere. Il salvataggio e' automatico.</p>
                        </div>
                        <div>
                            <div class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Persone</div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="user in related.users"
                                    :key="`assignee-${user.id}`"
                                    type="button"
                                    :class="personAvatarClass(selectedAssignees.includes(user.id))"
                                    :aria-pressed="selectedAssignees.includes(user.id)"
                                    :aria-label="`${selectedAssignees.includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                    :title="user.name || user.email"
                                    @click="toggleTaskPerson('assignees', user.id)"
                                >
                                    <UserAvatar :user="user" size="md" />
                                </button>
                                <p v-if="!related.users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="section === 'tasks'" class="surface rounded-md p-5">
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">Follower</h3>
                            <p class="mt-1 text-xs text-gray-500">Clicca sui volti per aggiungere o rimuovere. Il salvataggio e' automatico.</p>
                        </div>
                        <div>
                            <div class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Persone</div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="user in related.users"
                                    :key="`follower-${user.id}`"
                                    type="button"
                                    :class="personAvatarClass(selectedFollowers.includes(user.id))"
                                    :aria-pressed="selectedFollowers.includes(user.id)"
                                    :aria-label="`${selectedFollowers.includes(user.id) ? 'Rimuovi follower' : 'Aggiungi follower'} ${user.name || user.email}`"
                                    :title="user.name || user.email"
                                    @click="toggleTaskPerson('followers', user.id)"
                                >
                                    <UserAvatar :user="user" size="md" />
                                </button>
                                <p v-if="!related.users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="section === 'projects'" class="surface rounded-md p-5">
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">Membri del progetto</h3>
                            <p class="mt-1 text-xs text-gray-500">Clicca sui volti per aggiungere o rimuovere. Il salvataggio e' automatico.</p>
                        </div>
                        <div class="people-avatar-picker max-h-56">
                            <button
                                v-for="user in related.projectUsers"
                                :key="`project-member-${user.id}`"
                                type="button"
                                :class="personAvatarClass(selectedProjectFollowers.includes(user.id))"
                                :aria-pressed="selectedProjectFollowers.includes(user.id)"
                                :aria-label="`${selectedProjectFollowers.includes(user.id) ? 'Rimuovi dal progetto' : 'Aggiungi al progetto'} ${user.name || user.email}`"
                                :title="user.name || user.email"
                                @click="toggleProjectPerson(user.id)"
                            >
                                <UserAvatar :user="user" size="md" />
                            </button>
                            <p v-if="!related.projectUsers?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                        </div>
                    </section>

                    <section v-if="section === 'users'" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Profilo</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-gray-500">Ruolo attuale</dt>
                                <dd class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ displayValue(userForm.role) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-gray-500">Creato il</dt>
                                <dd class="font-medium text-gray-900">{{ dateIt(record.created_at) }}</dd>
                            </div>
                            <div v-if="record.updated_at" class="flex items-center justify-between gap-3">
                                <dt class="text-gray-500">Aggiornato il</dt>
                                <dd class="font-medium text-gray-900">{{ dateIt(record.updated_at) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-4 rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-xs leading-relaxed text-gray-500">
                            La password viene modificata solo quando compili il campo dedicato.
                        </p>
                    </section>

                    <section v-if="section !== 'tasks' && related.client" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Cliente</h3>
                        <Link
                            v-if="!isGuest"
                            :href="route('clients.show', related.client.id)"
                            class="group/item mt-2 block rounded-md border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-100 hover:bg-white hover:shadow-[0_12px_28px_rgba(28,42,73,0.10)] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                        >
                            <span class="block truncate font-semibold text-gray-900 transition group-hover/item:text-indigo-600">
                                {{ related.client.name }}
                            </span>
                            <span v-if="related.client.legal_name || related.client.vat_number || related.client.tax_code" class="mt-1 block truncate text-xs text-gray-500">
                                {{ [related.client.legal_name, related.client.vat_number || related.client.tax_code].filter(Boolean).join(' · ') }}
                            </span>
                        </Link>
                        <div v-else class="mt-2 block rounded-md border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm">
                            <span class="block truncate font-semibold text-gray-900">{{ related.client.name }}</span>
                            <span v-if="related.client.legal_name || related.client.vat_number || related.client.tax_code" class="mt-1 block truncate text-xs text-gray-500">
                                {{ [related.client.legal_name, related.client.vat_number || related.client.tax_code].filter(Boolean).join(' · ') }}
                            </span>
                        </div>
                    </section>

                    <section v-if="related.project" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Progetto</h3>
                        <Link v-if="canOpenRelatedProject(related.project)" :href="route('projects.show', related.project.id)" class="mt-2 block text-sm font-medium text-indigo-600">
                            {{ related.project.name }}
                        </Link>
                        <div v-else class="mt-2 text-sm font-medium text-gray-700">{{ related.project.name }}</div>
                    </section>

                    <section v-for="name in (section === 'projects' ? ['documents'] : ['projects', 'tasks', 'documents'])" :key="name" v-show="relatedItemsFor(name).length" class="surface rounded-md p-5">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ relatedSectionLabel(name) }}</h3>
                        <div class="space-y-2">
                            <component
                                v-for="item in relatedItemsFor(name)"
                                :key="item.id"
                                :is="canOpenRelatedItem(name) ? Link : 'div'"
                                :href="canOpenRelatedItem(name) ? relatedItemHref(name, item) : undefined"
                                :style="name === 'projects' ? relatedProjectStyle(item) : undefined"
                                :class="[
                                    'group/item block rounded-md border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm transition duration-200',
                                    canOpenRelatedItem(name) ? 'hover:-translate-y-0.5 hover:border-indigo-100 hover:bg-white hover:shadow-[0_12px_28px_rgba(28,42,73,0.10)] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200' : '',
                                ]"
                            >
                                <span :class="['block truncate font-semibold transition', name !== 'projects' ? 'text-gray-900' : '', canOpenRelatedItem(name) && name !== 'projects' ? 'group-hover/item:text-indigo-600' : '']">
                                    {{ relatedItemTitle(name, item) }}
                                </span>
                                <span class="mt-1 block truncate text-xs" :class="name !== 'projects' ? 'text-gray-500' : ''" :style="name === 'projects' ? relatedProjectMetaStyle(item) : undefined">
                                    {{ relatedItemMeta(name, item) }}
                                </span>
                            </component>
                            <button
                                v-if="name === 'tasks' && hiddenRelatedTaskCount()"
                                type="button"
                                class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700"
                                @click="clientRelatedTasksExpanded = true"
                            >
                                +{{ hiddenRelatedTaskCount() }} task
                            </button>
                        </div>
                    </section>
                </aside>

                <section v-if="section === 'tasks' && !related.parentTask" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sottoattività</h3>
                        <span class="text-xs text-gray-500">{{ related.subtasks?.length || 0 }} elementi</span>
                    </div>
                                <form class="mb-4 grid items-center gap-x-2 gap-y-2 md:grid-cols-[minmax(0,1fr)_48px_72px_auto]" data-subtask-create-assignees @submit.prevent="addSubtask">
                        <input v-model="subtaskForm.title" class="subtask-line-control font-medium" placeholder="Nuova sottoattività..." required />
                        <div class="relative" data-subtask-create-assignees>
                            <button type="button" class="subtask-line-people justify-end" @click.stop="toggleCreateSubtaskAssigneeMenu($event)">
                                <span v-if="createSubtaskAssignees().length" class="flex min-w-0 items-center -space-x-2">
                                    <UserAvatar v-for="user in createSubtaskAssignees().slice(0, 4)" :key="`new-subtask-assignee-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                    <span v-if="createSubtaskAssignees().length > 4" class="ml-3 text-xs font-semibold text-gray-500">+{{ createSubtaskAssignees().length - 4 }}</span>
                                </span>
                                <span v-else class="subtask-line-token">
                                    <UserRound class="h-4 w-4" :stroke-width="1.7" />
                                </span>
                            </button>
                            <Teleport to="body">
                                    <div v-if="subtaskCreateAssigneeMenuOpen" class="pointer-events-none fixed inset-0 z-[7600] bg-transparent" data-subtask-create-assignees>
                                        <div class="app-popover field-dropdown-menu pointer-events-auto fixed w-72 p-3" :style="subtaskCreateAssigneeMenuStyle" @click.stop>
                                        <div class="people-avatar-picker max-h-56">
                                            <button
                                                v-for="user in related.users"
                                                :key="`new-subtask-person-${user.id}`"
                                                type="button"
                                                :class="personAvatarClass((subtaskForm.assignee_ids || []).includes(user.id))"
                                                :aria-pressed="(subtaskForm.assignee_ids || []).includes(user.id)"
                                                :aria-label="`${(subtaskForm.assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                @click="toggleCreateSubtaskAssignee(user.id)"
                                            >
                                                <UserAvatar :user="user" size="md" />
                                            </button>
                                        </div>
                                        <p v-if="!related.users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                                    </div>
                                </div>
                            </Teleport>
                        </div>
                        <div class="relative flex items-center justify-end">
                            <AppDateInput
                                v-model="subtaskForm.due_date"
                                variant="token"
                                :label="shortDateIt(subtaskForm.due_date)"
                                placeholder="Scadenza"
                            />
                        </div>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                    </form>
                    <div class="space-y-2">
                        <div
                            v-for="subtask in orderedSubtasks"
                            :key="subtask.id"
                            draggable="true"
                            :class="[
                                'subtask-line md:grid-cols-[68px_minmax(0,1fr)_96px_72px_auto]',
                                subtaskAssigneeMenuOpen === subtask.id ? 'z-[6600]' : 'z-0',
                                draggedSubtaskId === subtask.id ? 'is-dragging' : '',
                                subtaskDropTarget === subtask.id && subtaskDropPlacement === 'before' ? 'drop-before' : '',
                                subtaskDropTarget === subtask.id && subtaskDropPlacement === 'after' ? 'drop-after' : '',
                            ]"
                            @dragstart="startSubtaskDrag(subtask)"
                            @dragover.prevent="dragOverSubtask(subtask, $event)"
                            @drop.prevent="dropSubtask(subtask)"
                            @dragend="endSubtaskDrag"
                        >
                            <div class="flex items-center gap-1">
                                <button type="button" class="inline-flex h-9 w-6 cursor-grab items-center justify-center text-gray-300 transition hover:text-gray-500 active:cursor-grabbing" title="Sposta sottoattività">
                                    <GripVertical class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button
                                    type="button"
                                    :class="['icon-btn status-action-button h-9 w-9', subtaskStatusPulse === subtask.id ? 'status-action-pulse' : '']"
                                    :title="(subtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'Riapri sottoattività' : 'Completa sottoattività'"
                                    @click="setSubtaskStatus(subtask, (subtaskDrafts[subtask.id]?.status || subtask.status) !== 'done')"
                                >
                                    <RotateCcw v-if="(subtaskDrafts[subtask.id]?.status || subtask.status) === 'done'" class="h-4 w-4" :stroke-width="1.7" />
                                    <Check v-else class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>
                            <div class="min-w-0">
                                <input
                                    v-if="subtaskDrafts[subtask.id]"
                                    v-model="subtaskDrafts[subtask.id].title"
                                    :class="['subtask-line-control font-medium', (subtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'text-gray-400 line-through' : '']"
                                    placeholder="Titolo sottoattività"
                                    @input="saveSubtaskInline(subtask)"
                                />
                            </div>
                            <div v-if="subtaskDrafts[subtask.id]" class="relative" :data-subtask-assignees="subtask.id">
                                <button type="button" class="subtask-line-people justify-end" @click.stop="toggleSubtaskAssigneeMenu(subtask.id, $event)">
                                    <span v-if="subtaskAssignees(subtask.id).length" class="flex min-w-0 items-center -space-x-2">
                                        <UserAvatar v-for="user in subtaskAssignees(subtask.id).slice(0, 4)" :key="`subtask-assignee-${subtask.id}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                        <span v-if="subtaskAssignees(subtask.id).length > 4" class="ml-3 text-xs font-semibold text-gray-500">+{{ subtaskAssignees(subtask.id).length - 4 }}</span>
                                    </span>
                                    <span v-else class="subtask-line-token">
                                        <UserRound class="h-4 w-4" :stroke-width="1.7" />
                                    </span>
                                </button>
                                <Teleport to="body">
                                    <div
                                        v-if="subtaskAssigneeMenuOpen === subtask.id"
                                        class="fixed inset-0 z-[7600] bg-transparent"
                                        :data-subtask-assignees="subtask.id"
                                        @click.self="subtaskAssigneeMenuOpen = null"
                                    >
                                        <div class="app-popover field-dropdown-menu fixed w-72 p-3" :style="subtaskAssigneeMenuStyle" @click.stop>
                                            <div class="people-avatar-picker max-h-56">
                                                <button
                                                    v-for="user in related.users"
                                                    :key="`subtask-person-${subtask.id}-${user.id}`"
                                                    type="button"
                                                    :class="personAvatarClass((subtaskDrafts[subtask.id].assignee_ids || []).includes(user.id))"
                                                    :aria-pressed="(subtaskDrafts[subtask.id].assignee_ids || []).includes(user.id)"
                                                    :aria-label="`${(subtaskDrafts[subtask.id].assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                    @click="toggleSubtaskAssignee(subtask, user.id)"
                                                >
                                                    <UserAvatar :user="user" size="md" />
                                                </button>
                                            </div>
                                            <p v-if="!related.users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                                        </div>
                                    </div>
                                </Teleport>
                            </div>
                            <div v-if="subtaskDrafts[subtask.id]" class="relative flex items-center justify-end">
                                <AppDateInput
                                    v-model="subtaskDrafts[subtask.id].due_date"
                                    variant="token"
                                    :label="shortDateIt(subtaskDrafts[subtask.id].due_date)"
                                    placeholder="Scadenza"
                                    @change="saveSubtaskInline(subtask, 0)"
                                />
                            </div>
                            <div class="subtask-actions">
                                <Link :href="route('tasks.show', subtask.id)" class="inline-flex h-9 items-center justify-center rounded-[var(--radius-sm)] px-3 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700">
                                    Apri
                                </Link>
                            </div>
                        </div>
                        <p v-if="!related.subtasks?.length" class="text-sm text-gray-500">Nessuna sottoattività.</p>
                    </div>
                </section>

                <section v-if="section === 'tasks'" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-4 flex items-center gap-4 border-b border-gray-100 pb-3">
                        <button
                            type="button"
                            :class="['text-sm font-semibold uppercase tracking-wide transition', taskFeedTab === 'comments' ? 'text-gray-900' : 'text-gray-400 hover:text-gray-700']"
                            @click="taskFeedTab = 'comments'"
                        >
                            Commenti
                        </button>
                        <button
                            type="button"
                            :class="['text-sm font-semibold uppercase tracking-wide transition', taskFeedTab === 'activity' ? 'text-gray-900' : 'text-gray-400 hover:text-gray-700']"
                            @click="taskFeedTab = 'activity'"
                        >
                            Attività
                        </button>
                    </div>
                    <div v-if="taskFeedTab === 'comments'">
                    <form class="mb-5" @submit.prevent="addComment">
                        <div class="overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                            <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runCommentEditorCommand('new', 'bold')">
                                    <Bold class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runCommentEditorCommand('new', 'italic')">
                                    <Italic class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runCommentEditorCommand('new', 'underline')">
                                    <Underline class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runCommentEditorCommand('new', 'insertUnorderedList')">
                                    <List class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runCommentEditorCommand('new', 'insertOrderedList')">
                                    <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addCommentEditorLink('new')">
                                    <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>
                            <div
                                class="min-h-[92px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                contenteditable="true"
                                data-task-comment-editor="new"
                                data-placeholder="Scrivi un commento..."
                                @input="updateCommentFromEditor('new')"
                                @blur="updateCommentFromEditor('new')"
                            ></div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Invia</button>
                        </div>
                    </form>
                    <div class="space-y-3">
                        <div v-for="comment in visibleTaskComments()" :key="comment.id" class="rounded-md border border-gray-100 bg-gray-50 px-3 py-3 text-sm transition hover:border-indigo-100 hover:bg-white">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <div class="text-xs font-medium text-gray-500">{{ comment.user_name || 'Utente' }} · {{ dateTimeIt(comment.created_at) }}</div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="text-xs font-semibold text-indigo-600 transition hover:text-indigo-500" @click="editComment(comment)">
                                        Modifica
                                    </button>
                                    <button type="button" class="text-xs font-semibold text-red-600 transition hover:text-red-500" @click="removeComment(comment)">
                                        Elimina
                                    </button>
                                </div>
                            </div>
                            <div v-if="editingCommentId !== comment.id" class="min-h-10 rounded-[var(--radius-sm)] bg-white/70 px-3 py-2 text-sm leading-6 text-gray-700" v-html="comment.content"></div>
                            <div v-else-if="commentDrafts[comment.id]" class="overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                    <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @mousedown.prevent @click="runCommentEditorCommand(comment.id, 'bold')">
                                        <Bold class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @mousedown.prevent @click="runCommentEditorCommand(comment.id, 'italic')">
                                        <Italic class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @mousedown.prevent @click="runCommentEditorCommand(comment.id, 'underline')">
                                        <Underline class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @mousedown.prevent @click="runCommentEditorCommand(comment.id, 'insertUnorderedList')">
                                        <List class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @mousedown.prevent @click="runCommentEditorCommand(comment.id, 'insertOrderedList')">
                                        <ListOrdered class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                    <button type="button" class="icon-btn h-8 w-8" title="Link" @mousedown.prevent @click="addCommentEditorLink(comment.id)">
                                        <Link2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                                <div
                                    class="min-h-[110px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]"
                                    contenteditable="true"
                                    :data-task-comment-editor="comment.id"
                                    data-placeholder="Commento..."
                                    @input="saveCommentInline(comment)"
                                    @blur="stopEditingComment(comment)"
                                ></div>
                            </div>
                        </div>
                        <button
                            v-if="!showAllTaskComments && hiddenTaskCommentsCount()"
                            type="button"
                            class="text-sm font-semibold text-indigo-600 transition hover:text-indigo-500"
                            @click="showAllTaskComments = true"
                        >
                            Mostra i {{ hiddenTaskCommentsCount() }} commenti precedenti
                        </button>
                        <p v-if="!related.comments?.length" class="text-sm text-gray-500">Nessun commento.</p>
                    </div>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="activity in visibleTaskActivity()" :key="activity.id" class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 px-3 py-3 text-sm transition hover:border-indigo-100 hover:bg-white">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-indigo-300"></span>
                                <div class="min-w-0">
                                    <div class="font-medium leading-6 text-gray-700">{{ activityText(activity) }}</div>
                                    <div class="text-xs text-gray-400">{{ dateTimeIt(activity.created_at) }}</div>
                                </div>
                            </div>
                        </div>
                        <button
                            v-if="!showAllTaskActivity && hiddenTaskActivityCount()"
                            type="button"
                            class="text-sm font-semibold text-indigo-600 transition hover:text-indigo-500"
                            @click="showAllTaskActivity = true"
                        >
                            Mostra i {{ hiddenTaskActivityCount() }} aggiornamenti precedenti
                        </button>
                        <p v-if="!related.activity?.length" class="text-sm text-gray-500">Nessuna attività registrata.</p>
                    </div>
                </section>

                <section v-if="section === 'billing'" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Righe documento</h3>
                        <div class="text-sm font-semibold text-gray-900">Totale: EUR {{ Number(record.total_amount || 0).toFixed(2) }}</div>
                    </div>
                    <form class="mb-5 grid gap-3 md:grid-cols-[1fr_90px_120px_90px_90px_auto]" @submit.prevent="addLine">
                        <input v-model="lineForm.description" class="form-control mt-0" placeholder="Descrizione" required />
                        <input v-model="lineForm.quantity" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <input v-model="lineForm.unit_price" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <input v-model="lineForm.vat_rate" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <input v-model="lineForm.discount_pct" class="form-control mt-0" type="number" step="0.01" min="0" max="100" />
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Descrizione</th>
                                    <th class="px-3 py-2 text-right">Qta</th>
                                    <th class="px-3 py-2 text-right">Prezzo</th>
                                    <th class="px-3 py-2 text-right">IVA</th>
                                    <th class="px-3 py-2 text-right">Subtotale</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="line in related.lines" :key="line.id">
                                    <td class="px-3 py-2">{{ line.description }}</td>
                                    <td class="px-3 py-2 text-right">{{ line.quantity }}</td>
                                    <td class="px-3 py-2 text-right">{{ line.unit_price }}</td>
                                    <td class="px-3 py-2 text-right">{{ line.vat_rate }}%</td>
                                    <td class="px-3 py-2 text-right">{{ Number(line.subtotal).toFixed(2) }}</td>
                                    <td class="px-3 py-2 text-right"><button class="text-red-600" @click="removeLine(line)">Elimina</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="section === 'billing'" class="surface rounded-md p-5 lg:col-span-2">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Pagamenti</h3>
                    <form class="mb-5 grid gap-3 md:grid-cols-[120px_150px_1fr_auto]" @submit.prevent="addPayment">
                        <input v-model="paymentForm.amount" class="form-control mt-0" type="number" step="0.01" min="0" required />
                        <AppDateInput v-model="paymentForm.paid_at" />
                        <input v-model="paymentForm.method" class="form-control mt-0" placeholder="Metodo" />
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Registra</button>
                    </form>
                    <div class="space-y-2">
                        <div v-for="payment in related.payments" :key="payment.id" class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 text-sm">
                            <span>EUR {{ Number(payment.amount).toFixed(2) }} · {{ payment.paid_at }} · {{ payment.method || '-' }}</span>
                            <button class="text-red-600" @click="removePayment(payment)">Elimina</button>
                        </div>
                        <p v-if="!related.payments?.length" class="text-sm text-gray-500">Nessun pagamento registrato.</p>
                    </div>
                </section>
            </div>
        </div>
        <Transition name="calendar-task-drawer">
            <div v-if="projectTaskDrawerOpen && projectTaskDrawerTask" class="fixed inset-0 z-[5200] bg-gray-950/20 backdrop-blur-[2px]" @click.self="closeProjectTaskDrawer">
                <aside class="calendar-task-drawer-panel absolute right-0 top-0 flex h-full w-full max-w-3xl flex-col border-l border-white/80 bg-white shadow-2xl sm:w-[62vw]">
                    <header class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                        <div class="min-w-0">
                            <button
                                v-if="projectTaskParentStack.length"
                                type="button"
                                class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-gray-500 transition hover:text-indigo-600"
                                @click="returnToProjectDrawerParentTask"
                            >
                                <ChevronLeft class="h-3.5 w-3.5" :stroke-width="1.8" />
                                Torna alla task genitore
                            </button>
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: taskPriorityColor(projectTaskDrawerForm.priority) }"></span>
                                <h3 class="truncate text-lg font-bold text-gray-950">Modifica task</h3>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Le modifiche si salvano automaticamente.</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" class="btn btn-outline status-action-button" :disabled="projectTaskDrawerForm.status !== 'done' && blockedDependencyCount(projectTaskDrawerTask) > 0" @click="toggleProjectTaskComplete">
                                <Check class="h-4 w-4" :stroke-width="1.7" />
                                {{ projectTaskDrawerForm.status === 'done' ? 'Riapri' : 'Completa' }}
                            </button>
                            <button type="button" class="icon-btn h-10 w-10" title="Azioni task" @click.stop="toggleProjectTaskActionMenu($event)">
                                <MoreHorizontal class="h-5 w-5" :stroke-width="1.8" />
                            </button>
                            <Teleport to="body">
                                <div v-if="projectTaskActionMenuOpen" class="fixed inset-0 z-[7600] bg-transparent" @click.self="projectTaskActionMenuOpen = false">
                                    <div class="app-popover field-dropdown-menu fixed w-56 p-2" :style="projectTaskActionMenuStyle" @click.stop>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="copyProjectDrawerTaskLink">
                                            <Copy class="h-4 w-4" :stroke-width="1.7" />
                                            Copia link
                                        </button>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="duplicateProjectDrawerTask">
                                            <Copy class="h-4 w-4" :stroke-width="1.7" />
                                            Duplica
                                        </button>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printProjectDrawerTask">
                                            <Printer class="h-4 w-4" :stroke-width="1.7" />
                                            Stampa
                                        </button>
                                        <button v-if="canDeleteTaskRecord(projectTaskDrawerTask)" type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50" @click="removeProjectDrawerTask">
                                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            Elimina
                                        </button>
                                    </div>
                                </div>
                            </Teleport>
                            <button type="button" class="icon-btn" @click="closeProjectTaskDrawer">
                                <X class="h-4 w-4" :stroke-width="1.8" />
                            </button>
                        </div>
                    </header>
                    <div class="flex-1 overflow-y-auto px-5 py-5" data-project-task-drawer-body>
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Titolo</label>
                                <input v-model="projectTaskDrawerForm.title" class="form-control" required />
                            </div>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <button
                                    v-for="option in taskEditTypeOptions"
                                    :key="`project-drawer-type-${option.value}`"
                                    type="button"
                                    :class="[
                                        'rounded-[var(--radius-sm)] border px-3 py-2 text-left text-sm font-semibold transition',
                                        projectTaskTypeButtonClass(option.value),
                                    ]"
                                    @click="toggleProjectTaskType(option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stato</label>
                                    <AppSelect v-model="projectTaskDrawerForm.status" :options="taskStatusOptions" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Priorità</label>
                                    <AppSelect v-model="projectTaskDrawerForm.priority" :options="priorityOptions" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Inizio</label>
                                    <AppDateInput v-model="projectTaskDrawerForm.start_date" @change="saveProjectTaskDrawer(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                                    <AppDateInput v-model="projectTaskDrawerForm.due_date" @change="saveProjectTaskDrawer(0)" />
                                </div>
                                <div v-if="projectTaskDrawerForm.task_type === 'meeting'">
                                    <label class="block text-sm font-medium text-gray-700">Ora</label>
                                    <AppTimeInput v-model="projectTaskDrawerForm.due_time" @change="saveProjectTaskDrawer(0)" />
                                </div>
                                <div v-if="projectTaskDrawerForm.task_type === 'meeting'">
                                    <label class="block text-sm font-medium text-gray-700">Luogo / link</label>
                                    <input v-model="projectTaskDrawerForm.location" class="form-control" placeholder="Sala riunioni o link meeting" />
                                </div>
                                <div v-if="projectTaskDrawerForm.task_type === 'project' || projectTaskDrawerForm.task_type === 'task'">
                                    <label class="block text-sm font-medium text-gray-700">Progetto</label>
                                    <AppSelect v-model="projectTaskDrawerForm.project_id" :options="namedOptions(related.taskProjects, { value: '', label: 'Nessun progetto' })" searchable @change="saveProjectTaskDrawer(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cliente</label>
                                    <AppSelect v-model="projectTaskDrawerForm.client_id" :options="namedOptions(related.taskClients, { value: '', label: 'Nessun cliente' })" searchable @change="saveProjectTaskDrawer(0)" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Servizio</label>
                                    <AppSelect v-model="projectTaskDrawerForm.service_id" :options="namedOptions(related.taskServices, { value: '', label: 'Nessun servizio' })" searchable @change="saveProjectTaskDrawer(0)" />
                                </div>
                            </div>
                            <div v-if="projectTaskDrawerForm.task_type !== 'meeting'" class="rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/80 p-4">
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input v-model="projectTaskDrawerForm.recurring_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @change="saveProjectTaskDrawer(0)" />
                                    Ricorrente
                                </label>
                                <div v-if="projectTaskDrawerForm.recurring_enabled" class="mt-3 grid gap-3 md:grid-cols-2">
                                    <input v-model="projectTaskDrawerForm.recurring_interval_value" type="number" min="1" class="form-control" @input="saveProjectTaskDrawer()" />
                                    <AppSelect v-model="projectTaskDrawerForm.recurring_interval_unit" :options="recurrenceUnitOptions" @change="saveProjectTaskDrawer(0)" />
                                    <AppSelect v-model="projectTaskDrawerForm.recurring_mode" :options="recurrenceModeOptions" @change="saveProjectTaskDrawer(0)" />
                                    <input v-if="projectTaskDrawerForm.recurring_interval_unit === 'week'" v-model="projectTaskDrawerForm.recurring_weekday" type="number" min="1" max="7" class="form-control" @input="saveProjectTaskDrawer()" />
                                    <input v-if="projectTaskDrawerForm.recurring_interval_unit === 'month' && projectTaskDrawerForm.recurring_mode === 'fixed'" v-model="projectTaskDrawerForm.recurring_month_day" type="number" min="1" max="31" class="form-control" @input="saveProjectTaskDrawer()" />
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ projectTaskDrawerForm.task_type === 'meeting' ? 'Partecipanti' : 'Assegnatari' }}</div>
                                    <div class="people-avatar-picker max-h-36">
                                        <button v-for="user in related.users || []" :key="`project-drawer-assignee-${user.id}`" type="button" :class="personAvatarClass((projectTaskDrawerForm.assignee_ids || []).includes(user.id))" @click="toggleProjectDrawerTaskPerson('assignee_ids', user.id)">
                                            <UserAvatar :user="user" size="md" />
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Follower</div>
                                    <div class="people-avatar-picker max-h-36">
                                        <button v-for="user in related.users || []" :key="`project-drawer-follower-${user.id}`" type="button" :class="personAvatarClass((projectTaskDrawerForm.follower_ids || []).includes(user.id))" @click="toggleProjectDrawerTaskPerson('follower_ids', user.id)">
                                            <UserAvatar :user="user" size="md" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <section v-if="!projectTaskDrawerTask.parent_task_id" class="content-card rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dipendenze</h4>
                                        <p class="mt-1 text-xs text-gray-500">Questa task resta bloccata finché le dipendenze non sono completate.</p>
                                    </div>
                                    <span
                                        v-if="blockedDependencyCount(projectTaskDrawerTask)"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-100"
                                        title="Task bloccata"
                                    >
                                        <GitBranch class="h-4 w-4" :stroke-width="1.8" />
                                    </span>
                                </div>
                                <div class="grid gap-2 md:grid-cols-[170px_minmax(0,1fr)]">
                                    <AppSelect v-model="projectDrawerDependencyDirection" :options="taskDependencyDirectionOptions" placeholder="Tipo relazione" />
                                    <AppSelect
                                        v-model="projectDrawerDependencyToAdd"
                                        :options="projectDrawerDependencyOptions(projectDrawerDependencyDirection)"
                                        :placeholder="projectDrawerDependencyDirection === 'blocks' ? 'Scegli task bloccata' : 'Scegli task bloccante'"
                                        searchable
                                        @change="addProjectDrawerDependency(projectDrawerDependencyToAdd, projectDrawerDependencyDirection)"
                                    />
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="dependency in selectedProjectDrawerDependencies()"
                                        :key="`project-drawer-dep-${dependency.id}`"
                                        :class="['inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold', dependency.status === 'done' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']"
                                    >
                                        <span class="truncate">{{ dependency.title }}</span>
                                        <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi dipendenza" @click="removeProjectDrawerDependency(dependency.id)">
                                            <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                        </button>
                                    </span>
                                    <span v-if="!selectedProjectDrawerDependencies().length" class="text-xs text-gray-500">Nessuna dipendenza.</span>
                                </div>
                                <div v-if="selectedProjectDrawerDependents().length" class="mt-3 border-t border-gray-100 pt-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Blocca</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="dependent in selectedProjectDrawerDependents()"
                                            :key="`project-drawer-blocks-${dependent.id}`"
                                            :class="['inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold', dependent.status === 'done' ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-600']"
                                        >
                                            <span class="truncate">{{ dependent.title }}</span>
                                            <button type="button" class="text-current opacity-60 transition hover:opacity-100" title="Rimuovi relazione" @click="removeProjectDrawerDependent(dependent.id)">
                                                <X class="h-3.5 w-3.5" :stroke-width="1.8" />
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </section>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                                <div class="mt-1 overflow-hidden rounded-[var(--radius-sm)] border border-gray-200 bg-white/90 shadow-inner">
                                    <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 bg-gray-50/80 p-2">
                                        <button type="button" class="icon-btn h-8 w-8" title="Grassetto" @click="runProjectTaskDrawerCommand('bold')"><Bold class="h-4 w-4" :stroke-width="1.7" /></button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Corsivo" @click="runProjectTaskDrawerCommand('italic')"><Italic class="h-4 w-4" :stroke-width="1.7" /></button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Sottolineato" @click="runProjectTaskDrawerCommand('underline')"><Underline class="h-4 w-4" :stroke-width="1.7" /></button>
                                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                                        <button type="button" class="icon-btn h-8 w-8" title="Titolo" @click="runProjectTaskDrawerCommand('formatBlock', 'h3')"><Heading3 class="h-4 w-4" :stroke-width="1.7" /></button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco puntato" @click="runProjectTaskDrawerCommand('insertUnorderedList')"><List class="h-4 w-4" :stroke-width="1.7" /></button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Elenco numerato" @click="runProjectTaskDrawerCommand('insertOrderedList')"><ListOrdered class="h-4 w-4" :stroke-width="1.7" /></button>
                                        <button type="button" class="icon-btn h-8 w-8" title="Citazione" @click="runProjectTaskDrawerCommand('formatBlock', 'blockquote')"><Quote class="h-4 w-4" :stroke-width="1.7" /></button>
                                    </div>
                                    <div
                                        ref="projectTaskDrawerDescriptionEditor"
                                        contenteditable="true"
                                        class="min-h-[150px] px-4 py-3 text-sm leading-6 text-gray-800 outline-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)] wysiwyg-content"
                                        data-placeholder="Aggiungi una descrizione..."
                                        @input="updateProjectTaskDrawerDescription(); saveProjectTaskDrawer()"
                                        @blur="saveProjectTaskDrawer(0)"
                                    ></div>
                                </div>
                            </div>
                            <section v-if="!projectTaskDrawerTask.parent_task_id" class="content-card rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sottoattività</h4>
                                    <span class="text-xs text-gray-500">{{ projectDrawerSubtasks().length }}</span>
                                </div>
                                <form class="mb-4 grid items-center gap-x-2 gap-y-2 md:grid-cols-[minmax(0,1fr)_48px_72px_auto]" data-subtask-create-assignees @submit.prevent="addProjectDrawerSubtask">
                                    <input v-model="projectDrawerSubtaskForm.title" class="subtask-line-control font-medium" placeholder="Nuova sottoattività..." required />
                                    <div class="relative" data-subtask-create-assignees>
                                        <button type="button" class="subtask-line-people justify-end" @click.stop="toggleProjectDrawerCreateSubtaskAssigneeMenu($event)">
                                            <span v-if="projectDrawerCreateSubtaskAssignees().length" class="flex min-w-0 items-center -space-x-2">
                                                <UserAvatar v-for="user in projectDrawerCreateSubtaskAssignees().slice(0, 4)" :key="`project-drawer-new-subtask-assignee-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                                <span v-if="projectDrawerCreateSubtaskAssignees().length > 4" class="ml-3 text-xs font-semibold text-gray-500">+{{ projectDrawerCreateSubtaskAssignees().length - 4 }}</span>
                                            </span>
                                            <span v-else class="subtask-line-token">
                                                <UserRound class="h-4 w-4" :stroke-width="1.7" />
                                            </span>
                                        </button>
                                        <Teleport to="body">
                                            <div v-if="subtaskCreateAssigneeMenuOpen" class="pointer-events-none fixed inset-0 z-[7600] bg-transparent" data-subtask-create-assignees>
                                                <div class="app-popover field-dropdown-menu pointer-events-auto fixed w-72 p-3" :style="subtaskCreateAssigneeMenuStyle" @click.stop>
                                                    <div class="people-avatar-picker max-h-56">
                                                        <button
                                                            v-for="user in related.users"
                                                            :key="`project-drawer-new-subtask-person-${user.id}`"
                                                            type="button"
                                                            :class="personAvatarClass(projectDrawerCreateSubtaskAssigneeIds.includes(user.id))"
                                                            :aria-pressed="projectDrawerCreateSubtaskAssigneeIds.includes(user.id)"
                                                            :aria-label="`${projectDrawerCreateSubtaskAssigneeIds.includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                            @click="toggleProjectDrawerCreateSubtaskAssignee(user.id)"
                                                        >
                                                            <UserAvatar :user="user" size="md" />
                                                        </button>
                                                    </div>
                                                    <p v-if="!related.users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                                                </div>
                                            </div>
                                        </Teleport>
                                    </div>
                                    <div class="relative flex items-center justify-end">
                                        <AppDateInput v-model="projectDrawerSubtaskForm.due_date" variant="token" :label="shortDateIt(projectDrawerSubtaskForm.due_date)" placeholder="Scadenza" />
                                    </div>
                                    <button type="submit" class="btn btn-primary justify-center px-4" :disabled="projectDrawerSubtaskForm.processing">
                                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </form>
                                <div class="space-y-2">
                                    <div
                                        v-for="subtask in projectDrawerSubtasks()"
                                        :key="`project-drawer-subtask-${subtask.id}`"
                                        :class="['subtask-line md:grid-cols-[68px_minmax(0,1fr)_96px_72px_auto]', subtaskAssigneeMenuOpen === subtask.id ? 'z-[6600]' : 'z-0']"
                                    >
                                        <div class="flex items-center gap-1">
                                            <span class="inline-flex h-9 w-6 items-center justify-center text-gray-300">
                                                <GripVertical class="h-4 w-4" :stroke-width="1.7" />
                                            </span>
                                            <button
                                                type="button"
                                                :class="['icon-btn status-action-button h-9 w-9', subtaskStatusPulse === subtask.id ? 'status-action-pulse' : '']"
                                                :title="(subtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'Riapri sottoattività' : 'Completa sottoattività'"
                                                @click="setSubtaskStatus(subtask, (subtaskDrafts[subtask.id]?.status || subtask.status) !== 'done')"
                                            >
                                                <RotateCcw v-if="(subtaskDrafts[subtask.id]?.status || subtask.status) === 'done'" class="h-4 w-4" :stroke-width="1.7" />
                                                <Check v-else class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                        </div>
                                        <div class="min-w-0">
                                            <input
                                                v-if="subtaskDrafts[subtask.id]"
                                                v-model="subtaskDrafts[subtask.id].title"
                                                :class="['subtask-line-control font-medium', (subtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'text-gray-400 line-through' : '']"
                                                placeholder="Titolo sottoattività"
                                                @input="saveSubtaskInline(subtask)"
                                            />
                                        </div>
                                        <div v-if="subtaskDrafts[subtask.id]" class="relative" :data-subtask-assignees="subtask.id">
                                            <button type="button" class="subtask-line-people justify-end" @click.stop="toggleSubtaskAssigneeMenu(subtask.id, $event)">
                                                <span v-if="subtaskAssignees(subtask.id).length" class="flex min-w-0 items-center -space-x-2">
                                                    <UserAvatar v-for="user in subtaskAssignees(subtask.id).slice(0, 4)" :key="`project-drawer-subtask-assignee-${subtask.id}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                                    <span v-if="subtaskAssignees(subtask.id).length > 4" class="ml-3 text-xs font-semibold text-gray-500">+{{ subtaskAssignees(subtask.id).length - 4 }}</span>
                                                </span>
                                                <span v-else class="subtask-line-token">
                                                    <UserRound class="h-4 w-4" :stroke-width="1.7" />
                                                </span>
                                            </button>
                                            <Teleport to="body">
                                                <div
                                                    v-if="subtaskAssigneeMenuOpen === subtask.id"
                                                    class="fixed inset-0 z-[7600] bg-transparent"
                                                    :data-subtask-assignees="subtask.id"
                                                    @click.self="subtaskAssigneeMenuOpen = null"
                                                >
                                                    <div class="app-popover field-dropdown-menu fixed w-72 p-3" :style="subtaskAssigneeMenuStyle" @click.stop>
                                                        <div class="people-avatar-picker max-h-56">
                                                            <button
                                                                v-for="user in related.users"
                                                                :key="`project-drawer-subtask-person-${subtask.id}-${user.id}`"
                                                                type="button"
                                                                :class="personAvatarClass((subtaskDrafts[subtask.id].assignee_ids || []).includes(user.id))"
                                                                :aria-pressed="(subtaskDrafts[subtask.id].assignee_ids || []).includes(user.id)"
                                                                :aria-label="`${(subtaskDrafts[subtask.id].assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                                @click="toggleSubtaskAssignee(subtask, user.id)"
                                                            >
                                                                <UserAvatar :user="user" size="md" />
                                                            </button>
                                                        </div>
                                                        <p v-if="!related.users?.length" class="text-sm text-gray-500">Nessun utente disponibile.</p>
                                                    </div>
                                                </div>
                                            </Teleport>
                                        </div>
                                        <div v-if="subtaskDrafts[subtask.id]" class="relative flex items-center justify-end">
                                            <AppDateInput
                                                v-model="subtaskDrafts[subtask.id].due_date"
                                                variant="token"
                                                :label="shortDateIt(subtaskDrafts[subtask.id].due_date)"
                                                placeholder="Scadenza"
                                                @change="saveSubtaskInline(subtask, 0)"
                                            />
                                        </div>
                                        <div class="subtask-actions">
                                            <button type="button" class="icon-btn h-9 w-9" title="Apri sottoattività" @click="openProjectDrawerSubtask(subtask)">
                                                <ExternalLink class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                            <button v-if="canDeleteTaskRecord(subtask)" type="button" class="icon-btn h-9 w-9 text-red-600 hover:bg-red-50" title="Elimina sottoattività" @click="removeProjectDrawerSubtask(subtask)">
                                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="!projectDrawerSubtasks().length" class="text-sm text-gray-500">Nessuna sottoattività.</p>
                                </div>
                            </section>
                            <section class="content-card rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 p-4">
                                <div class="mb-4 flex items-center gap-4 border-b border-gray-100 pb-3">
                                    <button type="button" :class="['text-sm font-semibold uppercase tracking-wide transition', projectTaskDrawerFeedTab === 'comments' ? 'text-gray-900' : 'text-gray-400 hover:text-gray-700']" @click="projectTaskDrawerFeedTab = 'comments'">
                                        Commenti
                                    </button>
                                    <button type="button" :class="['text-sm font-semibold uppercase tracking-wide transition', projectTaskDrawerFeedTab === 'activity' ? 'text-gray-900' : 'text-gray-400 hover:text-gray-700']" @click="projectTaskDrawerFeedTab = 'activity'">
                                        Attività
                                    </button>
                                </div>
                                <div v-if="projectTaskDrawerFeedTab === 'comments'" class="space-y-3">
                                    <form class="space-y-2" @submit.prevent="addProjectDrawerComment">
                                        <div
                                            ref="projectDrawerCommentEditor"
                                            contenteditable="true"
                                            class="form-control min-h-24 px-4 py-3 wysiwyg-content bg-white"
                                            data-placeholder="Scrivi un commento..."
                                            @input="updateProjectDrawerCommentFromEditor"
                                        ></div>
                                        <div class="flex justify-end">
                                            <button type="submit" class="btn btn-primary px-4 py-2 text-sm">Invia</button>
                                        </div>
                                    </form>
                                    <div v-for="comment in visibleProjectDrawerComments()" :key="`project-drawer-comment-${comment.id}`" class="rounded-[var(--radius-sm)] border border-gray-100 bg-white px-3 py-3 text-sm">
                                        <div class="mb-2 text-xs font-medium text-gray-500">{{ comment.user_name || 'Utente' }} · {{ dateTimeIt(comment.created_at) }}</div>
                                        <div class="wysiwyg-content text-sm text-gray-700" v-html="comment.content"></div>
                                    </div>
                                    <button v-if="!projectDrawerShowAllComments && projectDrawerComments().length > 3" type="button" class="text-sm font-semibold text-indigo-600" @click="projectDrawerShowAllComments = true">
                                        Mostra i {{ projectDrawerComments().length - 3 }} commenti precedenti
                                    </button>
                                    <p v-if="!projectDrawerComments().length" class="text-sm text-gray-500">Nessun commento.</p>
                                </div>
                                <div v-else class="space-y-3">
                                    <div v-for="activity in visibleProjectDrawerActivity()" :key="`project-drawer-activity-${activity.id}`" class="rounded-[var(--radius-sm)] border border-gray-100 bg-white px-3 py-3 text-sm">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-indigo-300"></span>
                                            <div class="min-w-0">
                                                <div class="font-medium leading-6 text-gray-700">{{ activityText(activity) }}</div>
                                                <div class="text-xs text-gray-400">{{ dateTimeIt(activity.created_at) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <button v-if="!projectDrawerShowAllActivity && projectDrawerActivity().length > 3" type="button" class="text-sm font-semibold text-indigo-600" @click="projectDrawerShowAllActivity = true">
                                        Mostra i {{ projectDrawerActivity().length - 3 }} aggiornamenti precedenti
                                    </button>
                                    <p v-if="!projectDrawerActivity().length" class="text-sm text-gray-500">Nessuna attività registrata.</p>
                                </div>
                            </section>
                        </div>
                    </div>
                </aside>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
