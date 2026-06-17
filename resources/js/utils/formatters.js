export const APP_TIME_ZONE = 'Europe/Rome';

export const valueLabels = {
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

export function displayValue(value) {
    if (value === true) return 'Si';
    if (value === false) return 'No';
    return valueLabels[value] || value || '-';
}

export function dateIt(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('it-IT', { timeZone: APP_TIME_ZONE });
}

export function shortDateIt(value) {
    if (!value) return '';
    const formatted = new Date(value).toLocaleDateString('it-IT', {
        timeZone: APP_TIME_ZONE,
        day: 'numeric',
        month: 'short',
    }).replace('.', '');

    return formatted.replace(/\b([a-zà-ù])/, (match) => match.toUpperCase());
}

export function dateTimeIt(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('it-IT', {
        timeZone: APP_TIME_ZONE,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function money(value) {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
}

export function plainText(value) {
    return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}

export function activityValue(value, field = null) {
    if (value === null || value === undefined || value === '') return 'vuoto';
    if (['start_date', 'due_date'].includes(field) && /^\d{4}-\d{2}-\d{2}/.test(String(value))) return dateIt(value);
    if (field === 'due_time') return String(value).slice(0, 5);
    if (value === '1') return 'Si';
    if (value === '0') return 'No';
    return displayValue(value);
}

export function activityFieldLabel(field, labels = {}) {
    if (field === 'assignee_ids') return 'assegnatari';
    if (field === 'follower_ids') return 'follower';
    if (field === 'content') return 'commento';
    return (labels[field] || field || 'dettaglio').toLowerCase();
}

export function activityText(activity, labels = {}) {
    const actor = activity.user_name || 'Qualcuno';
    const field = activityFieldLabel(activity.field, labels);

    if (activity.action === 'comment_created') return `${actor} ha aggiunto un commento`;
    if (activity.action === 'comment_updated') return `${actor} ha modificato un commento`;
    if (activity.action === 'comment_deleted') return `${actor} ha eliminato un commento`;
    if (activity.action === 'subtask_created') return `${actor} ha creato la sottoattività "${plainText(activity.new_value) || 'senza titolo'}"`;
    if (activity.action === 'task_created') return `${actor} ha creato questa attività`;
    if (activity.action === 'people_updated') return `${actor} ha aggiornato ${field}`;

    if (activity.old_value !== activity.new_value) {
        return `${actor} ha modificato ${field} da "${activityValue(activity.old_value, activity.field)}" a "${activityValue(activity.new_value, activity.field)}"`;
    }

    return `${actor} ha aggiornato ${field}`;
}
