<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Banknote,
    Briefcase,
    Building2,
    CalendarClock,
    ChevronDown,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    DatabaseBackup,
    ExternalLink,
    FileText,
    Filter,
    Mail,
    Pencil,
    Plus,
    Receipt,
    RefreshCw,
    RotateCcw,
    Save,
    Search,
    Settings,
    ShieldCheck,
    TrendingUp,
    Trash2,
    UserCog,
    UserPlus,
    Users,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
    billingStats: Object,
    clientStats: Object,
    documentSettings: Object,
    emailSettings: Object,
    numberings: Array,
    backupRuns: Array,
    serviceName: String,
});

const editing = ref(null);
const formOpen = ref(false);
const deleteTarget = ref(null);
const deleteConfirmText = ref('');
const updateDrafts = ref({});
const savingUpdateKeys = ref([]);
const page = usePage();
const canWrite = computed(() => props.fields.length > 0);
const billingSearch = ref('');
const billingType = ref('all');
const billingStatus = ref('all');
const currentCalendarDate = ref(new Date());
const calendarType = ref('all');
const compactWeekend = ref(false);
const calendarCreateDate = ref(null);
const calendarDraggedTask = ref(null);
const calendarDropDate = ref(null);
const calendarExpanded = ref(false);
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
const settingsTab = ref('personalizzazione');
const userRoleFilter = ref('all');

const docSettingDefaults = {
    company_name: 'Centro LU3G',
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
    smtp_secure: true,
    smtp_from_email: '',
    smtp_from_name: '',
    smtp_reply_to: '',
    pec_username: '',
    pec_password: '',
};

const documentSettingsForm = useForm({ ...docSettingDefaults, ...(props.documentSettings || {}) });
const emailSettingsForm = useForm({
    ...emailSettingDefaults,
    ...(props.emailSettings || {}),
    smtp_password: '',
    pec_password: '',
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
    }

    return base;
});

const form = useForm({ ...defaults.value });
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
    ['backup', 'Backup', DatabaseBackup],
    ['gestione', 'Gestione', Settings],
];

const valueLabels = {
    active: 'Attivo',
    completed: 'Completato',
    on_hold: 'In pausa',
    archived: 'Archiviato',
    todo: 'Da fare',
    in_progress: 'In corso',
    in_review: 'Review',
    done: 'Fatte',
    low: 'Bassa',
    medium: 'Media',
    high: 'Alta',
    urgent: 'Urgente',
    project: 'Task',
    task: 'Task',
    ongoing: 'Continuativa',
    meeting: 'Meeting',
    draft: 'Bozza',
    sent: 'Inviato',
    accepted: 'Accettato',
    rejected: 'Rifiutato',
    paid: 'Pagato',
    partially_paid: 'Parziale',
    overdue: 'Scaduto',
    cancelled: 'Annullato',
    week: 'Settimana',
    month: 'Mese',
    fixed: 'Fissa',
    relative: 'Relativa',
    on_request: 'Su richiesta',
    weekly: 'Settimanale',
    biweekly: 'Bisettimanale',
    monthly: 'Mensile',
    srl: 'SRL',
    srls: 'SRLS',
    spa: 'SPA',
    sas: 'SAS',
    snc: 'SNC',
    ditta_individuale: 'Ditta individuale',
    libero_professionista: 'Libero professionista',
    associazione: 'Associazione',
    ente_pubblico: 'Ente pubblico',
    ecommerce: 'E-commerce',
    retail: 'Retail',
    servizi: 'Servizi',
    immobiliare: 'Immobiliare',
    turismo: 'Turismo',
    ristorazione: 'Ristorazione',
    salute_benessere: 'Salute e benessere',
    formazione: 'Formazione',
    industria: 'Industria',
    no_profit: 'No profit',
    passaparola: 'Passaparola',
    sito_web: 'Sito web',
    social: 'Social',
    campagna_adv: 'Campagna ADV',
    evento: 'Evento',
    partner: 'Partner',
    chiamata: 'Chiamata',
    ordinario: 'IVA ordinaria',
    split_payment: 'Split payment',
    reverse_charge: 'Reverse charge',
    esente: 'Esente IVA',
    non_imponibile: 'Non imponibile',
    fuori_campo: 'Fuori campo IVA',
    forfettario: 'Regime forfettario',
    altro: 'Altro',
    IT: 'Italia',
    SM: 'San Marino',
    VA: 'Citta del Vaticano',
    FR: 'Francia',
    DE: 'Germania',
    ES: 'Spagna',
    CH: 'Svizzera',
    AT: 'Austria',
    GB: 'Regno Unito',
    US: 'Stati Uniti',
};

const columnLabels = {
    name: 'Nome',
    title: 'Titolo',
    email: 'Email',
    phone: 'Telefono',
    website: 'Sito web',
    status: 'Stato',
    priority: 'Priorita',
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

function displayValue(value) {
    if (value === true) return 'Si';
    if (value === false) return 'No';
    return valueLabels[value] || value || '-';
}

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

function shouldShowField(field) {
    if (isUpdatesSection.value) {
        if (field.name === 'report_url') return showUpdateReport.value;
        if (['cadence', 'contact'].includes(field.name)) return showUpdateNewsletter.value;
    }
    if (props.section !== 'tasks') return true;
    if (field.name === 'task_type') return false;
    if (['recurring_interval_value', 'recurring_interval_unit', 'recurring_mode', 'recurring_weekday', 'recurring_month_day'].includes(field.name)) {
        return Boolean(form.recurring_enabled) && form.task_type !== 'meeting';
    }
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
    formOpen.value = true;
}

function resetForm() {
    editing.value = null;
    formOpen.value = false;
    form.clearErrors();
    form.defaults({ ...defaults.value });
    form.reset();
    Object.assign(form, { ...defaults.value });
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
    form.clearErrors();
    props.fields.forEach((field) => {
        form[field.name] = row[field.name] ?? (field.type === 'checkbox' ? false : '');
    });
    if (props.section === 'tasks') {
        form.assignee_ids = [...(row.assignee_ids || [])];
        form.follower_ids = [...(row.follower_ids || [])];
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
    documentSettingsForm.put(route('settings.document.update'), { preserveScroll: true });
}

function saveEmailSettings() {
    emailSettingsForm.put(route('settings.email.update'), { preserveScroll: true });
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

function remove(row) {
    deleteTarget.value = row;
    deleteConfirmText.value = '';
}

function deleteTargetName() {
    return deleteTarget.value?.name || deleteTarget.value?.title || deleteTarget.value?.number || deleteTarget.value?.email || deleteTarget.value?.client_name || 'elemento';
}

function cancelDelete() {
    deleteTarget.value = null;
    deleteConfirmText.value = '';
}

function confirmDelete() {
    if (!deleteTarget.value || deleteConfirmText.value !== 'ELIMINA') return;
    router.delete(route(`${routeBase.value}.destroy`, deleteTarget.value.id), {
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

function money(value) {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
}

function dateIt(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('it-IT');
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

function plainText(value) {
    return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
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
    guest: 'Guest',
};

const roleOrder = ['superadmin', 'admin', 'editor', 'guest'];

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
        'group/person relative inline-flex h-12 w-12 items-center justify-center rounded-full transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300',
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
const usersByRole = computed(() => roleOrder
    .map((role) => ({ role, rows: userRows.value.filter((row) => (row.role || 'guest') === role) }))
    .filter((group) => group.rows.length));

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
    const search = taskSearch.value.trim().toLowerCase();
    const matchesSearch = !search
        || (row.title || '').toLowerCase().includes(search)
        || (row.client_name || '').toLowerCase().includes(search)
        || (row.project_name || '').toLowerCase().includes(search)
        || (row.service_name || '').toLowerCase().includes(search);
    const matchesStatus = taskStatus.value === 'all' || row.status === taskStatus.value;
    const matchesPriority = taskPriority.value === 'all' || row.priority === taskPriority.value;
    const matchesType = taskType.value === 'all' || (row.task_type || 'task') === taskType.value;

    return matchesSearch && matchesStatus && matchesPriority && matchesType;
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
const calendarDays = computed(() => new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate());
const calendarOffset = computed(() => (new Date(calendarYear.value, calendarMonth.value, 1).getDay() + 6) % 7);
const calendarGrid = computed(() => {
    const cells = [];
    for (let index = 0; index < calendarOffset.value; index += 1) {
        cells.push({ key: `empty-${index}`, empty: true });
    }
    for (let day = 1; day <= calendarDays.value; day += 1) {
        const date = formatCalendarDate(calendarYear.value, calendarMonth.value, day);
        const weekday = (new Date(calendarYear.value, calendarMonth.value, day).getDay() + 6) % 7;
        cells.push({
            key: date,
            day,
            date,
            weekday,
            weekend: weekday >= 5,
            today: isCalendarToday(day),
            tasks: tasksForDay(date),
        });
    }
    return cells;
});

function formatCalendarDate(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function isCalendarToday(day) {
    const today = new Date();
    return today.getFullYear() === calendarYear.value && today.getMonth() === calendarMonth.value && today.getDate() === day;
}

function changeMonth(delta) {
    calendarCreateDate.value = null;
    currentCalendarDate.value = new Date(calendarYear.value, calendarMonth.value + delta, 1);
}

function taskTypeLabel(type) {
    return {
        task: 'Task',
        project: 'Task',
        ongoing: 'Continuativa',
        meeting: 'Meeting',
    }[type || 'task'] || type;
}

function createTaskHref(type, date) {
    return `${route('tasks.index')}?create=${type}&date=${date}`;
}

function openCalendarCreateMenu(date) {
    calendarCreateDate.value = calendarCreateDate.value === date ? null : date;
}

function closeCalendarCreateMenuOnOutside() {
    calendarCreateDate.value = null;
}

onMounted(() => {
    document.addEventListener('click', closeCalendarCreateMenuOnOutside);
    document.addEventListener('click', closeProjectPeopleMenuOnOutside);
});
onUnmounted(() => {
    document.removeEventListener('click', closeCalendarCreateMenuOnOutside);
    document.removeEventListener('click', closeProjectPeopleMenuOnOutside);
    cancelClientServicesDrag();
});

function toggleTaskDone(task) {
    router.patch(route('tasks.status.update', task.id), {
        status: task.status === 'done' ? 'todo' : 'done',
    }, { preserveScroll: true });
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
    if (task.spanRole === 'start') return 'rounded-l-xl rounded-r-none border-r-0';
    if (task.spanRole === 'middle') return 'rounded-none border-x-0 opacity-80';
    if (task.spanRole === 'end') return 'rounded-l-none rounded-r-xl border-l-0';
    return 'rounded-xl';
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
    return props.rows
        .filter((row) => taskSpansDate(row, date))
        .filter((row) => calendarType.value === 'all' || (row.task_type || 'task') === calendarType.value)
        .map((row) => ({ ...row, spanRole: taskSpanRole(row, date) }))
        .sort((a, b) => `${a.due_time || '99:99'}${a.title}`.localeCompare(`${b.due_time || '99:99'}${b.title}`));
}

function visibleCalendarTasks(cell) {
    if (calendarExpanded.value) return cell.tasks;
    return cell.tasks.slice(0, 4);
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
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-md bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">{{ formTitle }}</h3>
                    <button type="button" class="icon-btn" @click="resetForm">
                        <span class="sr-only">Chiudi</span>
                        <X class="h-4 w-4" :stroke-width="1.7" />
                    </button>
                </div>

                <form :class="section === 'clients' || section === 'billing' ? 'grid gap-4 p-5 md:grid-cols-3' : 'space-y-4 p-5'" @submit.prevent="submit">
                    <div v-if="section === 'tasks'" class="grid gap-2 sm:grid-cols-3">
                        <button
                            type="button"
                            :class="[
                                'rounded-md border px-3 py-2 text-left text-sm font-semibold transition',
                                form.task_type === 'project' || form.task_type === 'task'
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
                                    : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
                            ]"
                            @click="setTaskFormType('project')"
                        >
                            <span class="flex items-center gap-2"><Briefcase class="h-4 w-4" :stroke-width="1.7" />Task</span>
                            <span class="mt-0.5 block text-xs font-normal text-gray-500">Attivita di progetto</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md border px-3 py-2 text-left text-sm font-semibold transition',
                                form.task_type === 'ongoing'
                                    ? 'border-amber-500 bg-amber-50 text-amber-800 shadow-sm'
                                    : 'border-amber-200 bg-white text-amber-700 hover:bg-amber-50',
                            ]"
                            @click="setTaskFormType('ongoing')"
                        >
                            <span class="flex items-center gap-2"><RefreshCw class="h-4 w-4" :stroke-width="1.7" />Continuativa</span>
                            <span class="mt-0.5 block text-xs font-normal text-gray-500">Ricorrente o operativa</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md border px-3 py-2 text-left text-sm font-semibold transition',
                                form.task_type === 'meeting'
                                    ? 'border-violet-500 bg-violet-50 text-violet-800 shadow-sm'
                                    : 'border-violet-200 bg-white text-violet-700 hover:bg-violet-50',
                            ]"
                            @click="setTaskFormType('meeting')"
                        >
                            <span class="flex items-center gap-2"><CalendarClock class="h-4 w-4" :stroke-width="1.7" />Meeting</span>
                            <span class="mt-0.5 block text-xs font-normal text-gray-500">Data, ora e luogo</span>
                        </button>
                    </div>

                    <div
                        v-for="field in fields"
                        v-show="shouldShowField(field)"
                        :key="field.name"
                        :class="field.type === 'textarea' || ['description', 'notes', 'footer_notes'].includes(field.name) ? 'md:col-span-3' : ''"
                    >
                        <label class="block text-sm font-medium text-gray-700">{{ field.label }}</label>
                        <textarea v-if="field.type === 'textarea'" v-model="form[field.name]" rows="4" class="form-control" />
                        <select v-else-if="['select', 'client', 'project', 'service'].includes(field.type)" v-model="form[field.name]" class="form-control" :required="field.required">
                            <option value="">-</option>
                            <option v-for="option in optionsFor(field)" :key="option.id" :value="option.id">{{ optionLabel(field, option) }}</option>
                        </select>
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
                        <input v-else v-model="form[field.name]" :type="field.type" class="form-control" :required="field.required" />
                        <div v-if="form.errors[field.name]" class="mt-1 text-sm text-red-600">{{ form.errors[field.name] }}</div>
                    </div>

                    <div v-if="section === 'tasks'" class="rounded-md border border-gray-100 bg-gray-50 p-3 md:col-span-3">
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
                                </div>
                            </div>
                            <div>
                                <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Follower</div>
                                <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto pr-1">
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
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 md:col-span-3">
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

        <div v-if="deleteTarget" class="fixed inset-0 z-[5100] flex items-center justify-center bg-gray-900/40 px-4 py-6" @click.self="cancelDelete">
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

        <div v-if="section === 'calendar'" class="py-8">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
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

                    <div class="flex flex-wrap items-center gap-3">
                        <select v-model="calendarType" class="form-control mt-0 w-44">
                            <option value="all">Tutti i tipi</option>
                            <option value="task">Task</option>
                            <option value="project">Task progetto</option>
                            <option value="ongoing">Continuativa</option>
                            <option value="meeting">Meeting</option>
                        </select>
                        <label class="inline-flex items-center gap-2 rounded-2xl border border-white/70 bg-white/58 px-3 py-2 text-sm font-medium text-gray-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.62)] backdrop-blur-xl">
                            <input v-model="compactWeekend" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            Weekend compatto
                        </label>
                        <button
                            v-if="calendarExpanded"
                            type="button"
                            class="btn btn-outline"
                            @click="calendarExpanded = false"
                        >
                            Compatta
                        </button>
                    </div>
                </div>

                <div class="surface overflow-hidden">
                    <div :class="['grid gap-px bg-white/42', compactWeekend ? 'grid-cols-[repeat(5,minmax(0,1fr))_minmax(58px,0.34fr)_minmax(58px,0.34fr)]' : 'grid-cols-7']">
                        <div
                            v-for="(day, index) in dayNames"
                            :key="day"
                            :class="['bg-white/46 px-2 py-3 text-center text-xs font-bold uppercase tracking-wide text-gray-500 backdrop-blur-xl', compactWeekend && index >= 5 ? 'text-[10px]' : '']"
                        >
                            {{ compactWeekend && index >= 5 ? day.slice(0, 1) : day }}
                        </div>
                    </div>

                    <div :class="['grid gap-px bg-white/42', compactWeekend ? 'grid-cols-[repeat(5,minmax(0,1fr))_minmax(58px,0.34fr)_minmax(58px,0.34fr)]' : 'grid-cols-7']">
                        <div
                            v-for="cell in calendarGrid"
                            :key="cell.key"
                            :class="[
                                'group min-h-[170px] bg-white/58 p-2 backdrop-blur-xl transition',
                                cell.empty ? 'bg-white/26' : '',
                                cell.today ? 'ring-2 ring-inset ring-indigo-500/70' : '',
                                calendarDropDate === cell.date ? 'bg-indigo-50/80' : '',
                                calendarDraggedTask && !cell.empty ? 'outline outline-1 outline-transparent transition hover:outline-indigo-200' : '',
                                compactWeekend && cell.weekend ? 'min-h-[170px] px-1' : '',
                            ]"
                            @dragover.prevent="!cell.empty && (calendarDropDate = cell.date)"
                            @dragleave="calendarDropDate === cell.date && (calendarDropDate = null)"
                            @drop.prevent="!cell.empty && moveCalendarTask(cell.date)"
                        >
                            <template v-if="!cell.empty">
                                <div class="mb-2 flex items-center justify-between">
                                    <span :class="['text-sm font-semibold', cell.today ? 'text-indigo-600' : 'text-gray-500']">{{ cell.day }}</span>
                                    <div class="relative">
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
                                            <Link
                                                :href="createTaskHref('project', cell.date)"
                                                class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-indigo-50/80"
                                            >
                                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                                Task
                                            </Link>
                                            <Link
                                                :href="createTaskHref('ongoing', cell.date)"
                                                class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-amber-50/80"
                                            >
                                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                                Continuativa
                                            </Link>
                                            <Link
                                                :href="createTaskHref('meeting', cell.date)"
                                                class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-violet-50/80"
                                            >
                                                <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                                                Meeting
                                            </Link>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="compactWeekend && cell.weekend" class="flex flex-wrap justify-center gap-1">
                                    <Link
                                        v-for="task in cell.tasks"
                                        :key="task.id"
                                        :href="route('tasks.show', task.id)"
                                        class="h-2.5 w-2.5 rounded-full"
                                        :style="{ backgroundColor: task.project_color || (task.priority === 'urgent' ? '#dc2626' : task.priority === 'high' ? '#f97316' : task.priority === 'low' ? '#10b981' : '#f59e0b') }"
                                        :title="task.title"
                                    />
                                </div>

                                <div v-else class="space-y-1.5">
                                    <div
                                        v-for="task in visibleCalendarTasks(cell)"
                                        :key="task.id"
                                        :class="[
                                            'cursor-grab border px-2 py-1.5 text-xs shadow-[inset_0_1px_0_rgba(255,255,255,0.68)] backdrop-blur-xl transition hover:border-indigo-300 hover:shadow-md active:cursor-grabbing',
                                            taskTypeClass(task.task_type),
                                            taskSpanClass(task),
                                            calendarDraggedTask?.id === task.id ? 'opacity-50' : '',
                                        ]"
                                        draggable="true"
                                        @dragstart="startCalendarDrag(task)"
                                        @dragend="endCalendarDrag"
                                    >
                                        <div class="flex items-start gap-1.5">
                                            <button
                                                type="button"
                                                :class="['mt-0.5 h-3.5 w-3.5 shrink-0 rounded-md border shadow-[inset_0_1px_0_rgba(255,255,255,0.65)]', task.status === 'done' ? 'border-emerald-500 bg-emerald-500' : 'border-gray-300 bg-white/78 hover:border-indigo-400']"
                                                :title="task.status === 'done' ? 'Riapri task' : 'Completa task'"
                                                @click.stop="toggleTaskDone(task)"
                                            >
                                                <span class="sr-only">{{ task.status === 'done' ? 'Riapri task' : 'Completa task' }}</span>
                                            </button>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1">
                                                    <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: task.project_color || task.service_color || '#2563eb' }"></span>
                                                    <span v-if="task.due_time" class="shrink-0 text-[10px] text-gray-500">{{ String(task.due_time).slice(0, 5) }}</span>
                                                    <Link :href="route('tasks.show', task.id)" :class="['truncate font-medium hover:text-indigo-600', task.status === 'done' ? 'line-through opacity-60' : '']">{{ task.title }}</Link>
                                                </div>
                                                <div class="mt-0.5 flex items-center justify-between gap-2 text-[10px] text-gray-500">
                                                    <span class="truncate">{{ task.client_name || task.project_name || task.service_name || taskTypeLabel(task.task_type) }}</span>
                                                    <span v-if="task.subtask_count">{{ task.subtask_count }} sub</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        v-if="!calendarExpanded && cell.tasks.length > 4"
                                        type="button"
                                        class="w-full rounded px-2 py-1 text-left text-[11px] font-medium text-gray-500 hover:bg-gray-50 hover:text-indigo-600"
                                        @click="calendarExpanded = true"
                                    >
                                        altre {{ cell.tasks.length - 4 }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
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
        </div>

        <div v-else-if="section === 'projects'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-3 lg:grid-cols-[1fr_190px_240px_auto_auto]">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" :stroke-width="1.7" />
                        <input v-model="projectSearch" class="form-control mt-0 pl-9" placeholder="Cerca per progetto, cliente o descrizione..." />
                    </div>
                    <select v-model="projectStatus" class="form-control mt-0">
                        <option value="all">Tutti gli stati</option>
                        <option value="active">Attivi</option>
                        <option value="completed">Completati</option>
                        <option value="on_hold">In pausa</option>
                        <option value="archived">Archiviati</option>
                    </select>
                    <div ref="projectPeopleMenu" class="relative z-30">
                        <button
                            type="button"
                            :class="[
                                'form-control mt-0 flex min-h-10 items-center justify-between gap-3 px-3 py-2 text-left',
                                projectPeopleMenuOpen ? 'border-indigo-300 ring-4 ring-indigo-500/10' : '',
                            ]"
                            :aria-expanded="projectPeopleMenuOpen"
                            @click.stop="projectPeopleMenuOpen = !projectPeopleMenuOpen"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <Users class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
                                <span class="truncate">{{ projectPeopleFilterLabel }}</span>
                            </span>
                            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', projectPeopleMenuOpen ? 'rotate-180' : '']" :stroke-width="1.7" />
                        </button>

                        <div
                            v-if="projectPeopleMenuOpen"
                            class="app-popover absolute left-0 right-0 top-full z-[5200] mt-2 rounded-md border border-white/80 bg-white/95 p-3 shadow-xl backdrop-blur-xl"
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
                            <div class="flex max-h-52 flex-wrap gap-2 overflow-y-auto pr-1">
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
                    <button type="button" class="btn btn-primary" @click="openCreate()">
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

        <div v-else-if="section === 'users'" class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="['rounded-md px-3 py-2 text-sm font-medium transition', userRoleFilter === 'all' ? 'bg-gray-900 text-white' : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50']"
                            @click="userRoleFilter = 'all'"
                        >
                            Tutti ({{ rows.length }})
                        </button>
                        <button
                            v-for="role in roleOrder"
                            :key="role"
                            type="button"
                            :class="['rounded-md px-3 py-2 text-sm font-medium transition', userRoleFilter === role ? 'bg-gray-900 text-white' : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50']"
                            @click="userRoleFilter = role"
                        >
                            {{ roleLabels[role] }} ({{ rows.filter((user) => (user.role || 'guest') === role).length }})
                        </button>
                    </div>
                    <button type="button" class="btn btn-primary" @click="openCreate()">
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
                                class="content-card rounded-md border border-gray-200 bg-white p-4 text-center shadow-sm transition hover:border-indigo-200 hover:shadow"
                            >
                                <Link :href="route('users.show', user.id)" class="block rounded-md p-2 transition hover:bg-gray-50">
                                    <UserAvatar :user="user" size="lg" class="mx-auto" />
                                    <div class="mt-3 min-w-0">
                                        <h4 class="truncate text-sm font-semibold text-gray-900">{{ user.name || 'Senza nome' }}</h4>
                                        <p class="mt-1 truncate text-xs text-gray-500">{{ user.email }}</p>
                                    </div>
                                </Link>
                                <div class="mt-3 flex items-center justify-center gap-3">
                                    <span :class="['rounded px-2 py-0.5 text-[11px] font-medium', roleClass(user.role || 'guest')]">{{ user.role || 'guest' }}</span>
                                    <Link :href="route('users.show', user.id)" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500"><ExternalLink class="h-3.5 w-3.5" :stroke-width="1.7" />Apri</Link>
                                    <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-500" @click="remove(user)"><Trash2 class="h-3.5 w-3.5" :stroke-width="1.7" />Elimina</button>
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
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" :stroke-width="1.7" />
                        <input v-model="clientSearch" class="form-control mt-0 pl-9" placeholder="Cerca per nome, ragione sociale, email, P.IVA o citta..." />
                    </div>
                    <select v-model="clientService" class="form-control mt-0">
                        <option value="all">Tutti i servizi</option>
                        <option v-for="service in services" :key="service.id" :value="service.id">{{ service.name }}</option>
                    </select>
                    <button type="button" class="btn btn-outline" @click="clientSearch = ''; clientService = 'all'"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                    <button type="button" class="btn btn-primary" @click="openCreate()"><Plus class="h-4 w-4" :stroke-width="1.7" />Nuovo Cliente</button>
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
                                <select v-else-if="['select', 'client', 'project', 'service'].includes(field.type)" v-model="form[field.name]" class="form-control" :required="field.required">
                                    <option value="">-</option>
                                    <option v-for="option in optionsFor(field)" :key="option.id" :value="option.id">{{ option.name }}</option>
                                </select>
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
                <div class="grid gap-3 md:grid-cols-[1fr_150px_150px_150px_auto_auto_auto_auto]">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" :stroke-width="1.7" />
                        <input v-model="taskSearch" class="form-control mt-0 pl-9" placeholder="Cerca task, cliente, progetto o servizio..." />
                    </div>
                    <select v-model="taskStatus" class="form-control mt-0">
                        <option value="all">Tutti gli stati</option>
                        <option value="todo">Da fare</option>
                        <option value="in_progress">In corso</option>
                        <option value="in_review">Review</option>
                        <option value="done">Fatte</option>
                    </select>
                    <select v-model="taskPriority" class="form-control mt-0">
                        <option value="all">Tutte priorita</option>
                        <option value="urgent">Urgente</option>
                        <option value="high">Alta</option>
                        <option value="medium">Media</option>
                        <option value="low">Bassa</option>
                    </select>
                    <select v-model="taskType" class="form-control mt-0">
                        <option value="all">Tutti i tipi</option>
                        <option value="task">Task</option>
                        <option value="project">Progetto</option>
                        <option value="ongoing">Continuativa</option>
                        <option value="meeting">Meeting</option>
                    </select>
                    <button type="button" class="btn btn-outline" @click="taskSearch = ''; taskStatus = 'all'; taskPriority = 'all'; taskType = 'all'"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                    <button type="button" class="btn btn-primary" @click="openCreate({ task_type: 'project' })"><Briefcase class="h-4 w-4" :stroke-width="1.7" />Task</button>
                    <button type="button" class="btn border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100" @click="openCreate({ task_type: 'ongoing' })"><RefreshCw class="h-4 w-4" :stroke-width="1.7" />Continuativa</button>
                    <button type="button" class="btn border border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100" @click="openCreate({ task_type: 'meeting', due_time: '09:00' })"><CalendarClock class="h-4 w-4" :stroke-width="1.7" />Meeting</button>
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
                                <select v-else-if="['select', 'client', 'project', 'service'].includes(field.type)" v-model="form[field.name]" class="form-control" :required="field.required">
                                    <option value="">-</option>
                                    <option v-for="option in optionsFor(field)" :key="option.id" :value="option.id">{{ option.name }}</option>
                                </select>
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
                                    class="content-card rounded-md border border-gray-200 bg-white p-3 shadow-sm transition hover:border-indigo-200 hover:shadow"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <Link :href="route('tasks.show', task.id)" class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span :class="['h-2 w-2 shrink-0 rounded-full', priorityClass(task.priority)]"></span>
                                                <h4 :class="['truncate text-sm font-semibold text-gray-900', task.status === 'done' ? 'line-through opacity-60' : '']">{{ task.title }}</h4>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-medium', taskTypeClass(task.task_type)]">{{ displayValue(task.task_type || 'task') }}</span>
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ displayValue(task.priority) }}</span>
                                            </div>
                                            <div class="mt-3 space-y-1 text-xs text-gray-500">
                                                <div v-if="task.project_name" class="truncate">Progetto: {{ task.project_name }}</div>
                                                <div v-if="task.client_name" class="truncate">Cliente: {{ task.client_name }}</div>
                                                <div v-if="task.service_name" class="truncate">
                                                    Servizio:
                                                    <span class="inline-flex items-center gap-1">
                                                        <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: task.service_color || '#64748b' }"></span>
                                                        {{ task.service_name }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span>{{ task.due_date ? dateIt(task.due_date) : 'Senza scadenza' }}</span>
                                                    <span v-if="task.due_time">{{ String(task.due_time).slice(0, 5) }}</span>
                                                </div>
                                            </div>
                                        </Link>
                                    </div>
                                    <div class="mt-3 flex justify-end gap-3 border-t border-gray-100 pt-2">
                                        <Link :href="route('tasks.show', task.id)" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500"><ExternalLink class="h-3.5 w-3.5" :stroke-width="1.7" />Apri</Link>
                                        <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-500" @click="remove(task)"><Trash2 class="h-3.5 w-3.5" :stroke-width="1.7" />Elimina</button>
                                    </div>
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
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="rounded-md border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ page.props.flash.status }}
                </div>

                <div class="surface grid gap-2 p-1 sm:grid-cols-4">
                    <button
                        v-for="tab in settingsTabs"
                        :key="tab[0]"
                        type="button"
                        :class="['inline-flex items-center justify-center gap-2 rounded px-3 py-2 text-sm font-medium transition', settingsTab === tab[0] ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900']"
                        @click="settingsTab = tab[0]"
                    >
                        <component :is="tab[2]" class="h-4 w-4" :stroke-width="1.7" />
                        {{ tab[1] }}
                    </button>
                </div>

                <section v-if="settingsTab === 'personalizzazione'" class="grid gap-6 lg:grid-cols-[1fr_320px]">
                    <form class="app-card" @submit.prevent="saveDocumentSettings">
                        <h3 class="section-title"><span class="section-icon"><Building2 class="h-4 w-4" :stroke-width="1.7" /></span>Identita aziendale</h3>
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
                            Salva identita
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
                            <input v-model="documentSettingsForm.city" class="form-control mt-0" placeholder="Citta" />
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

                    <form class="app-card" @submit.prevent="saveEmailSettings">
                        <h3 class="section-title"><span class="section-icon"><Mail class="h-4 w-4" :stroke-width="1.7" /></span>Email e SMTP</h3>
                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="emailSettingsForm.smtp_enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                SMTP attivo
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="emailSettingsForm.smtp_secure" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                TLS/SSL
                            </label>
                            <input v-model="emailSettingsForm.smtp_host" class="form-control mt-0" placeholder="Host SMTP" />
                            <input v-model="emailSettingsForm.smtp_port" type="number" class="form-control mt-0" placeholder="Porta" />
                            <input v-model="emailSettingsForm.smtp_username" class="form-control mt-0" placeholder="Username" />
                            <input v-model="emailSettingsForm.smtp_password" type="password" class="form-control mt-0" placeholder="Nuova password SMTP" />
                            <input v-model="emailSettingsForm.smtp_from_email" type="email" class="form-control mt-0" placeholder="Email mittente" />
                            <input v-model="emailSettingsForm.smtp_from_name" class="form-control mt-0" placeholder="Nome mittente" />
                            <input v-model="emailSettingsForm.smtp_reply_to" type="email" class="form-control mt-0" placeholder="Reply-to" />
                            <input v-model="emailSettingsForm.pec_username" class="form-control mt-0" placeholder="PEC username" />
                            <input v-model="emailSettingsForm.pec_password" type="password" class="form-control mt-0" placeholder="Nuova password PEC" />
                        </div>
                        <button type="submit" class="btn btn-primary mt-5" :disabled="emailSettingsForm.processing">
                            <Save class="h-4 w-4" :stroke-width="1.7" />
                            Salva email
                        </button>
                    </form>
                </section>

                <section v-else-if="settingsTab === 'backup'" class="grid gap-6 lg:grid-cols-[340px_1fr]">
                    <div class="app-card">
                        <h3 class="section-title"><span class="section-icon"><DatabaseBackup class="h-4 w-4" :stroke-width="1.7" /></span>Backup manuale</h3>
                        <p class="mt-2 text-sm text-gray-500">Registra un controllo backup nel portale. Il dump fisico resta gestito dal backup Plesk del dominio.</p>
                        <button type="button" class="btn btn-primary mt-5" @click="runBackup">
                            <DatabaseBackup class="h-4 w-4" :stroke-width="1.7" />
                            Avvia controllo backup
                        </button>
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
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="run in backupRuns || []" :key="run.id">
                                        <td class="px-3 py-3">{{ dateIt(run.started_at) }}</td>
                                        <td class="px-3 py-3">{{ run.frequency }}</td>
                                    <td class="px-3 py-3">{{ displayValue(run.status) }}</td>
                                        <td class="px-3 py-3">{{ run.tables_count || '-' }}</td>
                                    </tr>
                                    <tr v-if="!(backupRuns || []).length">
                                        <td colspan="4" class="px-3 py-8 text-center text-gray-500">Nessun backup registrato.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section v-else class="space-y-4">
                    <div class="flex justify-end">
                        <button type="button" class="btn btn-primary" @click="openCreate()">
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
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" class="action-link" @click="editRow(row)"><Pencil class="h-4 w-4" :stroke-width="1.7" />Modifica</button>
                                        <button type="button" class="danger-link ml-4" @click="remove(row)"><Trash2 class="h-4 w-4" :stroke-width="1.7" />Elimina</button>
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
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" :stroke-width="1.7" />
                            <input
                                v-model="billingSearch"
                                class="form-control mt-0 pl-9"
                                placeholder="Cerca per numero, cliente o note..."
                            />
                        </div>
                        <select v-model="billingType" class="form-control mt-0">
                            <option value="all">Tutti i tipi</option>
                            <option v-for="(label, value) in documentTypeLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <select v-model="billingStatus" class="form-control mt-0">
                            <option value="all">Tutti gli stati</option>
                            <option v-for="(label, value) in documentStatusLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <button type="button" class="btn btn-outline" @click="billingSearch = ''; billingType = 'all'; billingStatus = 'all'"><RotateCcw class="h-4 w-4" :stroke-width="1.7" />Reset</button>
                        <button type="button" class="btn btn-primary" @click="openCreate()"><Plus class="h-4 w-4" :stroke-width="1.7" />Nuovo documento</button>
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
                            <select v-else-if="['select', 'client', 'project', 'service'].includes(field.type)" v-model="form[field.name]" class="form-control" :required="field.required">
                                <option value="">-</option>
                                <option v-for="option in optionsFor(field)" :key="option.id" :value="option.id">{{ displayValue(option.name) }}</option>
                            </select>
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
                                        <select
                                            :value="draftValue(row, 'cadence')"
                                            class="form-control mt-0 min-w-[150px]"
                                            @change="setDraftValue(row, 'cadence', $event.target.value); saveServiceUpdateInline(row, { cadence: $event.target.value || null })"
                                        >
                                            <option value="">-</option>
                                            <option value="on_request">Su richiesta</option>
                                            <option value="weekly">Settimanale</option>
                                            <option value="biweekly">Bisettimanale</option>
                                            <option value="monthly">Mensile</option>
                                        </select>
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
                                            <select
                                                :value="draftValue(row, 'responsible_user_id')"
                                                class="form-control mt-0"
                                                @change="setDraftValue(row, 'responsible_user_id', $event.target.value); saveServiceUpdateInline(row, { responsible_user_id: $event.target.value || null })"
                                            >
                                                <option value="">Nessuno</option>
                                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name || user.email }}</option>
                                            </select>
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
                <div v-if="canWrite" class="flex justify-end">
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

                            <select
                                v-else-if="['select', 'client', 'project', 'service'].includes(field.type)"
                                v-model="form[field.name]"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :required="field.required"
                            >
                                <option value="">-</option>
                                <option v-for="option in optionsFor(field)" :key="option.id" :value="option.id">
                                    {{ option.name }}
                                </option>
                            </select>

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
                                        <button type="button" class="ml-4 text-sm font-medium text-red-600 hover:text-red-500" @click="remove(row)">Elimina</button>
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
