<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Bold,
    Check,
    ChevronLeft,
    Copy,
    Download,
    FileText,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Mail,
    Plus,
    Printer,
    Quote,
    RotateCcw,
    Send,
    Trash2,
    Underline,
    X,
} from '@lucide/vue';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    section: String,
    title: String,
    record: Object,
    related: Object,
});

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
    superadmin: 'Superadmin',
    admin: 'Admin',
    editor: 'Editor',
    guest: 'Guest',
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
    0: 'No',
    1: 'Si',
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
const subscriptionFrequencyOptions = [
    { value: 'month', label: 'Mese/i' },
    { value: 'year', label: 'Anno/i' },
];

function displayValue(value) {
    if (value === true) return 'Si';
    if (value === false) return 'No';
    return valueLabels[value] || value || '-';
}

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
});
const subtaskForm = useForm({
    title: '',
    priority: 'medium',
    due_date: '',
});
const subtaskDrafts = ref({});
const subtaskAutosaveStates = ref({});
const subtaskAutosaveErrors = ref({});
const subtaskAssigneeMenuOpen = ref(null);
const subtaskAutosaveTimers = {};
const subtaskAutosaveSequences = {};
const selectedAssignees = ref([...(props.related.assignees || [])]);
const selectedFollowers = ref([...(props.related.followers || [])]);
const taskAutosaveState = ref('idle');
const taskAutosaveError = ref('');
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
const projectColors = ['#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#475569'];
const userForm = useForm({
    name: props.record.name || '',
    email: props.record.email || '',
    role: props.record.role || 'guest',
    job_title: props.record.job_title || '',
    phone: props.record.phone || '',
    bio: props.record.bio || '',
    password: '',
});
const userAutosaveState = ref('idle');
const userAutosaveError = ref('');
let userAutosaveTimer = null;
let userAutosaveSequence = 0;
const userAvatarInput = ref(null);
const userAvatarPreview = ref(null);
const userAvatarForm = useForm({ avatar: null });

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

function saveCommentInline(comment, delay = 650) {
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
    }, delay);
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
    taskForm.status = status;
    taskAutosaveState.value = 'saving';
    taskAutosaveError.value = '';

    router.patch(route('tasks.status.update', props.record.id), { status }, {
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

function saveTaskInline(delay = 650) {
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
    }, delay);
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
    setTaskStatus(taskForm.status === 'done' ? 'todo' : 'done');
}

function duplicateTask() {
    router.post(route('tasks.duplicate', props.record.id));
}

function deleteTaskFromDetail() {
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

function priorityClass(priority) {
    return {
        urgent: 'bg-red-100 text-red-700',
        high: 'bg-orange-100 text-orange-700',
        medium: 'bg-amber-100 text-amber-700',
        low: 'bg-emerald-100 text-emerald-700',
    }[priority] || 'bg-gray-100 text-gray-700';
}

function setSubtaskStatus(subtask, done) {
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

function saveSubtaskInline(subtask, delay = 650) {
    if (props.section !== 'tasks') return;

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
    }, delay);
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

function saveLineInline(line, delay = 650) {
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
    }, delay);
}

function savePaymentInline(payment, delay = 650) {
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
    }, delay);
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

function saveDocumentInline(delay = 650) {
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
    }, delay);
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

function saveContactInline(contact, delay = 650) {
    if (props.section !== 'clients') return;

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
    }, delay);
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

function saveClientInline(delay = 650) {
    if (props.section !== 'clients') return;

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
    }, delay);
}

function removeContact(contact) {
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

function saveProjectInline(delay = 650) {
    if (props.section !== 'projects') return;

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
    }, delay);
}

function userPayload() {
    return {
        name: userForm.name,
        email: userForm.email,
        role: userForm.role,
        job_title: userForm.job_title,
        phone: userForm.phone,
        bio: userForm.bio,
        password: userForm.password,
    };
}

function saveUserInline(delay = 650) {
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
    }, delay);
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
    editingSubscription.value = null;
    subscriptionForm.clearErrors();
    subscriptionForm.defaults({ ...subscriptionDefaults });
    subscriptionForm.reset();
    Object.assign(subscriptionForm, { ...subscriptionDefaults });
}

function editSubscription(subscription) {
    editingSubscription.value = subscription;
    subscriptionForm.clearErrors();
    Object.keys(subscriptionDefaults).forEach((key) => {
        subscriptionForm[key] = subscription[key] ?? subscriptionDefaults[key];
    });
}

function saveSubscription() {
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
    router.patch(route('clients.subscriptions.active', [props.record.id, subscription.id]), { active: !subscription.active }, { preserveScroll: true });
}

function generateSubscription(subscription) {
    router.post(route('clients.subscriptions.generate', [props.record.id, subscription.id]));
}

function removeSubscription(subscription) {
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

function personAvatarClass(selected) {
    return [
        'group/person relative inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300',
        selected
            ? 'bg-indigo-50 ring-2 ring-indigo-500 ring-offset-2 ring-offset-white'
            : 'bg-white/70 ring-1 ring-gray-200 hover:-translate-y-0.5 hover:ring-indigo-200 hover:shadow-[0_10px_24px_rgba(79,70,229,0.10)]',
    ];
}

function toggleSubtaskAssigneeMenu(subtaskId) {
    subtaskAssigneeMenuOpen.value = subtaskAssigneeMenuOpen.value === subtaskId ? null : subtaskId;
}

function closeSubtaskAssigneeMenuOnOutside(event) {
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

function addSubtask() {
    subtaskForm.post(route('tasks.subtasks.store', props.record.id), {
        preserveScroll: true,
        onSuccess: () => subtaskForm.reset(),
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

function money(value) {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
}

function subscriptionFrequency(subscription) {
    const unit = subscription.frequency_unit === 'year' ? 'anno' : 'mese';
    const plural = Number(subscription.frequency_value) > 1 ? (subscription.frequency_unit === 'year' ? 'anni' : 'mesi') : unit;
    return `ogni ${subscription.frequency_value} ${plural}`;
}

function dateIt(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('it-IT');
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
        userForm.job_title,
        userForm.phone,
        userForm.bio,
        userForm.password,
    ],
    () => saveUserInline(),
);

onMounted(() => {
    document.addEventListener('pointerdown', closeSubtaskAssigneeMenuOnOutside, true);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeSubtaskAssigneeMenuOnOutside, true);
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
                            {{ section === 'projects' ? projectForm.name : (section === 'clients' ? clientForm.name : (section === 'users' ? userForm.name : (record.name || record.title || record.number))) }}
                        </h2>
                    </div>
                </div>
                <div v-if="section === 'tasks'" class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="toggleTaskComplete">
                        <Check class="h-4 w-4" :stroke-width="1.7" />
                        {{ taskForm.status === 'done' ? 'Riapri' : 'Completa' }}
                    </button>
                    <button type="button" class="btn btn-outline" @click="duplicateTask">
                        <Copy class="h-4 w-4" :stroke-width="1.7" />
                        Duplica
                    </button>
                    <button type="button" class="btn btn-danger" @click="deleteTaskFromDetail">
                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                        Elimina
                    </button>
                </div>
                <div v-else-if="section === 'projects'" class="flex flex-wrap justify-end gap-2">
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
                                    <input v-model="documentForm.issue_date" type="date" class="form-control" required />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                                    <input v-model="documentForm.due_date" type="date" class="form-control" />
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
                                <input v-model="paymentForm.paid_at" class="form-control mt-0" type="date" required />
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
                                    <input
                                        v-if="paymentDrafts[payment.id]"
                                        v-model="paymentDrafts[payment.id].paid_at"
                                        class="form-control mt-0"
                                        type="date"
                                        @input="savePaymentInline(payment)"
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
                    <section class="surface rounded-md p-5">
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
                                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Dati fiscali e bancari</h4>
                                <div class="grid gap-4 md:grid-cols-2">
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

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-md bg-white p-5 shadow-sm">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Progetti</div>
                            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ related.projects?.length || 0 }}</div>
                        </div>
                        <div class="rounded-md bg-white p-5 shadow-sm">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Task aperti</div>
                            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ related.tasks?.length || 0 }}</div>
                        </div>
                        <div class="rounded-md bg-white p-5 shadow-sm">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Documenti</div>
                            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ related.documents?.length || 0 }}</div>
                        </div>
                    </div>

                    <section class="surface rounded-md p-5">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Abbonamenti</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ related.subscriptions?.length || 0 }} ricorrenze collegate al cliente</p>
                            </div>
                            <button v-if="editingSubscription" type="button" class="text-sm font-medium text-gray-500 hover:text-gray-800" @click="resetSubscriptionForm">
                                Annulla modifica
                            </button>
                        </div>

                        <form class="grid gap-3 rounded-md border border-gray-100 bg-gray-50 p-4 md:grid-cols-4" @submit.prevent="saveSubscription">
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
                                <input v-model="subscriptionForm.start_date" type="date" class="form-control" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fine</label>
                                <input v-model="subscriptionForm.end_date" type="date" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Prossima emissione</label>
                                <input v-model="subscriptionForm.next_invoice_date" type="date" class="form-control" required />
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

                        <div class="mt-5 space-y-3">
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
                                    <div class="flex flex-wrap gap-2">
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
                                <input v-model="projectForm.name" class="form-control" required />
                                <div v-if="projectForm.errors.name" class="mt-1 text-sm text-red-600">{{ projectForm.errors.name }}</div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cliente</label>
                                    <AppSelect
                                        v-model="projectForm.client_id"
                                        :options="namedOptions(related.projectClients, { value: '', label: 'Nessun cliente' })"
                                        searchable
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stato</label>
                                    <AppSelect v-model="projectForm.status" :options="projectStatusOptions" />
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
                                        @click="projectForm.color = color; saveProjectInline(0)"
                                    ></button>
                                    <label class="relative inline-flex h-8 w-8 cursor-pointer items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white shadow-sm ring-1 ring-gray-200 transition hover:ring-gray-300" :style="{ backgroundColor: normalizeHexColor(projectForm.color) }">
                                        <span class="sr-only">Scegli colore custom</span>
                                        <input v-model="projectForm.color" type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                                    </label>
                                    <input v-model="projectForm.color" type="text" class="form-control mt-0 w-28 font-mono text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                                <textarea v-model="projectForm.description" rows="5" class="form-control"></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="surface rounded-md p-5">
                        <div class="mb-5 flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Task progetto</h3>
                            <span class="text-xs text-gray-500">{{ related.tasks?.length || 0 }} elementi</span>
                        </div>
                        <div class="space-y-2">
                            <Link
                                v-for="task in related.tasks || []"
                                :key="task.id"
                                :href="route('tasks.show', task.id)"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm hover:border-indigo-100 hover:bg-indigo-50"
                            >
                                <span class="font-medium text-indigo-700">{{ task.title }}</span>
                                <span class="flex items-center gap-2 text-xs text-gray-500">
                                    <span>{{ displayValue(task.status) }}</span>
                                    <span v-if="task.due_date">{{ dateIt(task.due_date) }}</span>
                                </span>
                            </Link>
                            <p v-if="!related.tasks?.length" class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500">
                                Nessuna task collegata a questo progetto.
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
                                        : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
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
                                        : 'border-amber-200 bg-white text-amber-700 hover:bg-amber-50',
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
                                        : 'border-violet-200 bg-white text-violet-700 hover:bg-violet-50',
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
                                <input v-model="taskForm.start_date" type="date" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Scadenza</label>
                                <input v-model="taskForm.due_date" type="date" class="form-control" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ora</label>
                                <input v-model="taskForm.due_time" type="time" class="form-control" />
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

                <section v-if="section === 'users'" class="space-y-6">
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
                                <label class="block text-sm font-medium text-gray-700">Telefono</label>
                                <input v-model="userForm.phone" class="form-control" />
                                <div v-if="userForm.errors.phone" class="mt-1 text-sm text-red-600">{{ userForm.errors.phone }}</div>
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

                <section v-if="section !== 'clients' && section !== 'tasks' && section !== 'projects' && section !== 'users'" class="surface rounded-md p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Dettagli</h3>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div v-for="[key, value] in visibleEntries" :key="key" class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ labels[key] || key.replaceAll('_', ' ') }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ displayValue(value) }}</dd>
                        </div>
                    </dl>
                </section>

                <aside class="space-y-6">
                    <section v-if="section === 'clients'" class="surface rounded-md p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Servizi collegati</h3>
                                <p class="mt-1 text-xs text-gray-500">Clicca un servizio per attivarlo o disattivarlo.</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-500">{{ clientServiceIds.length }} attivi</span>
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
                    </section>

                    <section v-if="related.project" class="surface rounded-md p-5">
                        <h3 class="text-sm font-semibold text-gray-900">Progetto</h3>
                        <Link :href="route('projects.show', related.project.id)" class="mt-2 block text-sm font-medium text-indigo-600">
                            {{ related.project.name }}
                        </Link>
                    </section>

                    <section v-for="name in (section === 'projects' ? ['documents'] : ['projects', 'tasks', 'documents'])" :key="name" v-show="related[name]?.length" class="surface rounded-md p-5">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ relatedSectionLabel(name) }}</h3>
                        <div class="space-y-2">
                            <Link
                                v-for="item in related[name]"
                                :key="item.id"
                                :href="relatedItemHref(name, item)"
                                class="group/item block rounded-md border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-100 hover:bg-white hover:shadow-[0_12px_28px_rgba(28,42,73,0.10)] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                            >
                                <span class="block truncate font-semibold text-gray-900 transition group-hover/item:text-indigo-600">
                                    {{ relatedItemTitle(name, item) }}
                                </span>
                                <span class="mt-1 block truncate text-xs text-gray-500">
                                    {{ relatedItemMeta(name, item) }}
                                </span>
                            </Link>
                        </div>
                    </section>
                </aside>

                <section v-if="section === 'clients'" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Referenti</h3>
                        <span class="text-xs text-gray-500">{{ related.contacts?.length || 0 }} contatti</span>
                    </div>

                    <form class="mb-5 grid gap-3 md:grid-cols-6" @submit.prevent="addContact">
                        <input v-model="contactForm.first_name" class="form-control mt-0" placeholder="Nome" required />
                        <input v-model="contactForm.last_name" class="form-control mt-0" placeholder="Cognome" required />
                        <input v-model="contactForm.email" class="form-control mt-0" type="email" placeholder="Email" />
                        <input v-model="contactForm.phone" class="form-control mt-0" placeholder="Telefono" />
                        <input v-model="contactForm.role" class="form-control mt-0" placeholder="Ruolo" />
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                    </form>

                    <div v-if="related.contacts?.length" class="grid gap-3 md:grid-cols-2">
                        <article v-for="contact in related.contacts" :key="contact.id" class="rounded-md border border-gray-100 bg-gray-50 p-4 transition hover:border-indigo-100 hover:bg-white">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <div class="grid gap-2 sm:grid-cols-2">
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
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <input
                                            v-if="contactDrafts[contact.id]"
                                            v-model="contactDrafts[contact.id].role"
                                            class="form-control mt-0"
                                            placeholder="Ruolo"
                                            @input="saveContactInline(contact)"
                                        />
                                        <input
                                            v-if="contactDrafts[contact.id]"
                                            v-model="contactDrafts[contact.id].phone"
                                            class="form-control mt-0"
                                            placeholder="Telefono"
                                            @input="saveContactInline(contact)"
                                        />
                                    </div>
                                    <input
                                        v-if="contactDrafts[contact.id]"
                                        v-model="contactDrafts[contact.id].email"
                                        class="form-control mt-0"
                                        type="email"
                                        placeholder="Email"
                                        @input="saveContactInline(contact)"
                                    />
                                    <textarea
                                        v-if="contactDrafts[contact.id]"
                                        v-model="contactDrafts[contact.id].notes"
                                        rows="2"
                                        class="form-control mt-0"
                                        placeholder="Note"
                                        @input="saveContactInline(contact)"
                                    ></textarea>
                                    <div v-if="contactAutosaveStates[contact.id] && contactAutosaveStates[contact.id] !== 'idle'" :class="['text-[11px] font-medium', contactAutosaveStates[contact.id] === 'error' ? 'text-red-600' : 'text-gray-400']">
                                        {{ autosaveLabel(contactAutosaveStates[contact.id], contactAutosaveErrors[contact.id]) }}
                                    </div>
                                </div>
                                <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50 hover:text-red-700" aria-label="Elimina referente" @click="removeContact(contact)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>
                        </article>
                    </div>
                    <p v-else class="text-sm text-gray-500">Nessun referente inserito.</p>
                </section>

                <section v-if="section === 'tasks' && !related.parentTask" class="surface rounded-md p-5 lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sottoattività</h3>
                        <span class="text-xs text-gray-500">{{ related.subtasks?.length || 0 }} elementi</span>
                    </div>
                    <form class="mb-4 grid gap-3 md:grid-cols-[1fr_150px_150px_auto]" @submit.prevent="addSubtask">
                        <input v-model="subtaskForm.title" class="form-control mt-0" placeholder="Nuova sottoattività..." required />
                        <AppSelect v-model="subtaskForm.priority" :options="priorityOptions" />
                        <input v-model="subtaskForm.due_date" class="form-control mt-0" type="date" />
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi</button>
                    </form>
                    <div class="space-y-2">
                        <div
                            v-for="subtask in related.subtasks"
                            :key="subtask.id"
                            :class="[
                                'relative grid gap-3 overflow-visible rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50 px-3 py-3 text-sm transition hover:border-indigo-100 hover:bg-white md:grid-cols-[minmax(0,1fr)_160px_150px_auto]',
                                subtaskAssigneeMenuOpen === subtask.id ? 'z-[6600]' : 'z-0',
                            ]"
                        >
                            <div class="min-w-0">
                                <input
                                    v-if="subtaskDrafts[subtask.id]"
                                    v-model="subtaskDrafts[subtask.id].title"
                                    :class="['form-control mt-0', (subtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'text-gray-400 line-through' : '']"
                                    placeholder="Titolo sottoattività"
                                    @input="saveSubtaskInline(subtask)"
                                />
                            </div>
                            <div v-if="subtaskDrafts[subtask.id]" class="relative" :data-subtask-assignees="subtask.id">
                                <button type="button" class="form-control mt-0 flex items-center justify-between gap-2 text-left" @click.stop="toggleSubtaskAssigneeMenu(subtask.id)">
                                    <span class="flex min-w-0 items-center -space-x-2">
                                        <UserAvatar v-for="user in subtaskAssignees(subtask.id).slice(0, 3)" :key="`subtask-assignee-${subtask.id}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                        <span v-if="!subtaskAssignees(subtask.id).length" class="truncate text-gray-500">Assegnatari</span>
                                        <span v-else-if="subtaskAssignees(subtask.id).length > 3" class="ml-3 text-xs font-semibold text-gray-500">+{{ subtaskAssignees(subtask.id).length - 3 }}</span>
                                    </span>
                                    <span class="text-xs font-semibold text-gray-400">{{ subtaskAssignees(subtask.id).length }}</span>
                                </button>
                                <Teleport to="body">
                                    <div
                                        v-if="subtaskAssigneeMenuOpen === subtask.id"
                                        class="fixed inset-0 z-[7600] bg-transparent"
                                        :data-subtask-assignees="subtask.id"
                                        @click.self="subtaskAssigneeMenuOpen = null"
                                    >
                                        <div class="app-popover field-dropdown-menu fixed right-6 top-1/2 w-72 -translate-y-1/2 p-3" @click.stop>
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
                            <input
                                v-if="subtaskDrafts[subtask.id]"
                                v-model="subtaskDrafts[subtask.id].due_date"
                                class="form-control mt-0"
                                type="date"
                                @input="saveSubtaskInline(subtask)"
                            />
                            <div class="flex self-center items-center justify-end gap-1">
                                <button
                                    type="button"
                                    class="icon-btn h-9 w-9"
                                    :title="(subtaskDrafts[subtask.id]?.status || subtask.status) === 'done' ? 'Riapri sottoattività' : 'Completa sottoattività'"
                                    @click="setSubtaskStatus(subtask, (subtaskDrafts[subtask.id]?.status || subtask.status) !== 'done')"
                                >
                                    <RotateCcw v-if="(subtaskDrafts[subtask.id]?.status || subtask.status) === 'done'" class="h-4 w-4" :stroke-width="1.7" />
                                    <Check v-else class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <Link :href="route('tasks.show', subtask.id)" class="inline-flex h-9 items-center justify-center rounded-[var(--radius-sm)] px-3 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700">
                                    Apri
                                </Link>
                            </div>
                        </div>
                        <p v-if="!related.subtasks?.length" class="text-sm text-gray-500">Nessuna sottoattività.</p>
                    </div>
                </section>

                <section v-if="section === 'tasks'" class="surface rounded-md p-5 lg:col-span-2">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Commenti</h3>
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
                        <div v-for="comment in related.comments" :key="comment.id" class="rounded-md border border-gray-100 bg-gray-50 px-3 py-3 text-sm transition hover:border-indigo-100 hover:bg-white">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <div class="text-xs font-medium text-gray-500">{{ comment.user_name || 'Utente' }} · {{ comment.created_at }}</div>
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
                        <p v-if="!related.comments?.length" class="text-sm text-gray-500">Nessun commento.</p>
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
                        <input v-model="paymentForm.paid_at" class="form-control mt-0" type="date" required />
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
    </AuthenticatedLayout>
</template>
