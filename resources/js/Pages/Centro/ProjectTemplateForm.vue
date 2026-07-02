<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTimeInput from '@/Components/AppTimeInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ChevronDown, ChevronLeft, Copy, CopyPlus, GitBranch, GripVertical, MoreHorizontal, Plus, Save, Trash2, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    template: Object,
    services: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
});

const priorityOptions = [
    { value: 'low', label: 'Bassa' },
    { value: 'medium', label: 'Media' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];
const taskTypeOptions = [
    { value: 'project', label: 'Task' },
    { value: 'meeting', label: 'Meeting' },
];
const statusOptions = [
    { value: 'todo', label: 'Da fare' },
    { value: 'in_progress', label: 'In corso' },
    { value: 'in_review', label: 'In revisione' },
    { value: 'done', label: 'Fatta' },
];
const directionOptions = [
    { value: 'after', label: 'dopo' },
    { value: 'before', label: 'prima' },
];
const dependencyModeOptions = [
    { value: 'none', label: 'Nessuna dipendenza' },
    { value: 'blocked_by', label: 'Bloccata da' },
    { value: 'blocks', label: 'Bloccante per' },
];

const editing = computed(() => Boolean(props.template?.id));
const taskDrawerKey = ref(null);
const sectionCollapsed = ref({});
const sectionActionMenuOpen = ref(null);
const sectionActionMenuPlacement = ref('down');
const taskDrafts = ref({});
const newSectionOpen = ref(false);
const newSectionName = ref('');
const newSectionInput = ref(null);
const draggedTaskKey = ref(null);
const taskDropTargetKey = ref(null);
const taskDropPlacement = ref(null);
const taskDropSectionIndex = ref(null);
const dependencyMenuTaskKey = ref(null);
const dependencyMenuStyle = ref({});

const form = useForm(templatePayload(props.template));
const totalTasks = computed(() => form.sections.reduce((sum, section) => sum + section.tasks.length, 0));
const serviceOptions = computed(() => [
    { value: '', label: 'Nessun servizio' },
    ...props.services.map((service) => ({ value: service.id, label: service.name })),
]);

function newTemplateTaskKey() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return `task-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function blankTask(title = '', dayOffset = 0) {
    const templateKey = newTemplateTaskKey();

    return {
        template_key: templateKey,
        title,
        description: '',
        service_id: '',
        assignee_ids: [],
        day_offset: dayOffset,
        date_offset_direction: 'after',
        date_reference_type: 'project_start',
        date_reference_task_key: '',
        date_reference_value: 'project_start',
        dependency_mode: 'none',
        dependency_task_keys: [],
        duration_days: 1,
        due_time: '',
        location: '',
        priority: 'medium',
        status: 'todo',
        task_type: 'project',
    };
}

function templatePayload(template) {
    if (!template) {
        return {
            name: '',
            description: '',
            color: '#2563eb',
            active: true,
            sections: [
                {
                    name: 'Fase preliminare',
                    tasks: [blankTask('Prima attività', 0)],
                },
            ],
        };
    }

    return {
        name: template.name || '',
        description: template.description || '',
        color: template.color || '#2563eb',
        active: Boolean(template.active),
        sections: (template.sections || []).map((section) => ({
            name: section.name || '',
            tasks: (section.tasks || []).map((task) => ({
                template_key: task.template_key || task.id || newTemplateTaskKey(),
                title: task.title || '',
                description: task.description || '',
                service_id: task.service_id || '',
                assignee_ids: Array.isArray(task.assignee_ids) ? task.assignee_ids : JSON.parse(task.assignee_ids || '[]'),
                day_offset: Number(task.day_offset || 0),
                date_offset_direction: task.date_offset_direction || 'after',
                date_reference_type: task.date_reference_type === 'task' ? 'task' : 'project_start',
                date_reference_task_key: task.date_reference_task_key || '',
                date_reference_value: task.date_reference_type === 'task' && task.date_reference_task_key ? `task:${task.date_reference_task_key}` : 'project_start',
                dependency_mode: ['blocked_by', 'blocks'].includes(task.dependency_mode) ? task.dependency_mode : 'none',
                dependency_task_keys: Array.isArray(task.dependency_task_keys) ? task.dependency_task_keys : JSON.parse(task.dependency_task_keys || '[]'),
                duration_days: Number(task.duration_days || 1),
                due_time: task.due_time || '',
                location: task.location || '',
                priority: task.priority || 'medium',
                status: task.status || 'todo',
                task_type: task.task_type === 'meeting' ? 'meeting' : 'project',
            })),
        })),
    };
}

function showSectionInput() {
    newSectionOpen.value = true;
    nextTick(() => newSectionInput.value?.focus());
}

function pushSection(name = '') {
    form.sections.push({
        name: name || `Nuova fase ${form.sections.length + 1}`,
        tasks: [],
    });
}

function addSection() {
    const name = newSectionName.value.trim();
    if (!name) return;
    pushSection(name);
    newSectionName.value = '';
    newSectionOpen.value = false;
}

function removeSection(index) {
    form.sections.splice(index, 1);
    if (!form.sections.length) pushSection('Fase preliminare');
}

function duplicateSection(section) {
    sectionActionMenuOpen.value = null;
    const clone = {
        name: `${section.name || 'Fase'} copia`,
        tasks: (section.tasks || []).map((task) => ({
            ...task,
            template_key: newTemplateTaskKey(),
            date_reference_task_key: '',
            date_reference_value: 'project_start',
            date_reference_type: 'project_start',
            dependency_mode: 'none',
            dependency_task_keys: [],
        })),
    };
    form.sections.splice(form.sections.indexOf(section) + 1, 0, clone);
}

function toggleSection(index) {
    sectionCollapsed.value = {
        ...sectionCollapsed.value,
        [index]: !sectionCollapsed.value[index],
    };
}

function toggleSectionActionMenu(index, event = null) {
    const nextOpen = sectionActionMenuOpen.value === index ? null : index;
    if (nextOpen !== null) requestFloatingUiClose();
    const rect = event?.currentTarget?.getBoundingClientRect?.();
    sectionActionMenuPlacement.value = rect && window.innerHeight - rect.bottom < 170 ? 'up' : 'down';
    sectionActionMenuOpen.value = nextOpen;
}

function collapseSectionFromMenu(index) {
    sectionActionMenuOpen.value = null;
    toggleSection(index);
}

function setTaskDraft(sectionIndex, value) {
    taskDrafts.value = {
        ...taskDrafts.value,
        [sectionIndex]: value,
    };
}

function addTask(section, sectionIndex) {
    const title = String(taskDrafts.value[sectionIndex] || '').trim();
    if (!title) return;
    const previous = section.tasks.at(-1);
    const task = blankTask(title, previous ? Number(previous.day_offset || 0) + 1 : 0);
    section.tasks.push(task);
    setTaskDraft(sectionIndex, '');
}

function removeTask(section, index) {
    const removed = section.tasks.splice(index, 1)[0];
    if (removed?.template_key) {
        allTemplateTasks().forEach((task) => {
            task.dependency_task_keys = (task.dependency_task_keys || []).filter((key) => key !== removed.template_key);
            if (!(task.dependency_task_keys || []).length) {
                task.dependency_mode = 'none';
            }
            if (task.date_reference_task_key === removed.template_key) {
                task.date_reference_type = 'project_start';
                task.date_reference_task_key = '';
                task.date_reference_value = 'project_start';
            }
        });
    }
    if (!drawerTask.value) {
        taskDrawerKey.value = null;
    }
}

function removeTaskByKey(section, taskKey) {
    const index = section.tasks.findIndex((task) => task.template_key === taskKey);
    if (index >= 0) removeTask(section, index);
}

function allTemplateTasks() {
    return form.sections.flatMap((section) => section.tasks || []);
}

const drawerTask = computed(() => allTemplateTasks().find((task) => task.template_key === taskDrawerKey.value) || null);

function startTaskDrag(task) {
    draggedTaskKey.value = task.template_key;
}

function dragOverSection(sectionIndex, clearTaskTarget = false) {
    if (!draggedTaskKey.value) return;
    taskDropSectionIndex.value = sectionIndex;
    if (clearTaskTarget) {
        taskDropTargetKey.value = null;
    }
    if (!taskDropTargetKey.value) {
        taskDropPlacement.value = 'after';
    }
}

function leaveTaskSection(sectionIndex, event) {
    if (!draggedTaskKey.value) return;
    if (event.currentTarget?.contains?.(event.relatedTarget)) return;
    if (taskDropSectionIndex.value === sectionIndex) {
        taskDropSectionIndex.value = null;
    }
}

function dragOverTask(task, event) {
    if (!draggedTaskKey.value || draggedTaskKey.value === task.template_key) return;
    const rect = event.currentTarget.getBoundingClientRect();
    taskDropTargetKey.value = task.template_key;
    taskDropPlacement.value = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
}

function dropTask(section, sectionIndex, targetTask = null) {
    const fromKey = draggedTaskKey.value;
    const placement = taskDropPlacement.value || 'after';
    draggedTaskKey.value = null;
    taskDropTargetKey.value = null;
    taskDropPlacement.value = null;
    taskDropSectionIndex.value = null;
    if (!fromKey) return;

    let moved = null;
    form.sections.forEach((row) => {
        const index = row.tasks.findIndex((task) => task.template_key === fromKey);
        if (index >= 0) {
            moved = row.tasks.splice(index, 1)[0];
        }
    });
    if (!moved) return;

    const current = section.tasks;
    if (!targetTask) {
        current.push(moved);
        return;
    }

    let targetIndex = current.findIndex((task) => task.template_key === targetTask.template_key);
    if (targetIndex < 0) targetIndex = current.length;
    if (placement === 'after') targetIndex += 1;
    current.splice(targetIndex, 0, moved);
}

function endTaskDrag() {
    draggedTaskKey.value = null;
    taskDropTargetKey.value = null;
    taskDropPlacement.value = null;
    taskDropSectionIndex.value = null;
}

function referenceOptions(task) {
    return [
        { value: 'project_start', label: 'Creazione progetto' },
        ...allTemplateTasks()
            .filter((item) => item.template_key !== task.template_key)
            .map((item) => ({
                value: `task:${item.template_key}`,
                label: item.title || 'Task senza titolo',
            })),
    ];
}

function dependencyTaskOptions(task) {
    return allTemplateTasks()
        .filter((item) => item.template_key !== task.template_key)
        .map((item) => ({
            value: item.template_key,
            label: item.title || 'Task senza titolo',
        }));
}

function dependencyModeLabel(task) {
    if (!task || task.dependency_mode === 'none') return 'Nessuna dipendenza';
    const count = (task.dependency_task_keys || []).length;
    const prefix = optionLabel(dependencyModeOptions, task.dependency_mode, 'Dipendenza');
    return count ? `${prefix} · ${count}` : prefix;
}

function dependencyPreviewLabel(task) {
    const count = (task.dependency_task_keys || []).length;
    if (!task || task.dependency_mode === 'none' || !count) return '';
    const label = task.dependency_mode === 'blocks' ? 'Bloccante' : 'Bloccata';
    return `${label}: ${count} task`;
}

function dependencyPreviewBadge(task) {
    const count = (task?.dependency_task_keys || []).length;
    if (!task || task.dependency_mode === 'none' || !count) return null;

    if (task.dependency_mode === 'blocks') {
        return {
            icon: GitBranch,
            label: `Bloccante per ${count} task`,
            class: 'bg-indigo-50 text-indigo-700 ring-indigo-100',
        };
    }

    return {
        icon: AlertTriangle,
        label: `Bloccata da ${count} task`,
        class: 'bg-amber-50 text-amber-700 ring-amber-100',
    };
}

function toggleDependencyTask(task, taskKey) {
    const values = [...(task.dependency_task_keys || [])];
    const index = values.indexOf(taskKey);
    if (index >= 0) values.splice(index, 1);
    else values.push(taskKey);
    task.dependency_task_keys = values;
}

function handleDependencyModeChange(task) {
    if (task.dependency_mode === 'none') {
        task.dependency_task_keys = [];
        dependencyMenuTaskKey.value = null;
    }
}

function updateDependencyMenuPosition(event) {
    const rect = event?.currentTarget?.getBoundingClientRect?.();
    if (!rect) return;

    const viewportPadding = 12;
    const width = Math.min(Math.max(rect.width, 260), window.innerWidth - (viewportPadding * 2));
    const menuHeight = 260;
    const left = Math.min(Math.max(viewportPadding, rect.right - width), window.innerWidth - width - viewportPadding);
    const hasSpaceBelow = rect.bottom + 8 + menuHeight <= window.innerHeight - viewportPadding;
    const top = hasSpaceBelow
        ? rect.bottom + 8
        : Math.max(viewportPadding, rect.top - menuHeight - 8);

    dependencyMenuStyle.value = {
        left: `${left}px`,
        top: `${top}px`,
        width: `${width}px`,
    };
}

function toggleDependencyMenu(task, event = null) {
    if (task.dependency_mode === 'none') return;
    if (dependencyMenuTaskKey.value === task.template_key) {
        dependencyMenuTaskKey.value = null;
        return;
    }

    requestFloatingUiClose();
    updateDependencyMenuPosition(event);
    dependencyMenuTaskKey.value = task.template_key;
}

function handleReferenceChange(task) {
    if (task.date_reference_value?.startsWith('task:')) {
        task.date_reference_type = 'task';
        task.date_reference_task_key = task.date_reference_value.replace('task:', '');
        return;
    }

    task.date_reference_type = 'project_start';
    task.date_reference_task_key = '';
    task.date_reference_value = 'project_start';
}

function handleTaskTypeChange(task) {
    if (task.task_type !== 'meeting') {
        task.due_time = '';
        task.location = '';
    }
}

function openTaskDrawer(task) {
    taskDrawerKey.value = task.template_key;
}

function closeTaskDrawer() {
    taskDrawerKey.value = null;
}

function removeDrawerTask() {
    const task = drawerTask.value;
    if (!task) return;
    form.sections.forEach((section) => {
        const index = section.tasks.findIndex((row) => row.template_key === task.template_key);
        if (index >= 0) {
            section.tasks.splice(index, 1);
        }
    });
    closeTaskDrawer();
}

function optionLabel(options, value, fallback = '') {
    return options.find((option) => String(option.value) === String(value))?.label || fallback;
}

function selectedServiceLabel(task) {
    return serviceOptions.value.find((option) => String(option.value) === String(task.service_id))?.label || 'Nessun servizio';
}

function dateRuleLabel(task) {
    const reference = referenceOptions(task).find((option) => option.value === task.date_reference_value)?.label || 'Creazione progetto';
    return `${Number(task.day_offset || 0)} giorni ${optionLabel(directionOptions, task.date_offset_direction, 'dopo')} ${reference}`;
}

function selectedAssignees(task) {
    return props.users.filter((user) => (task.assignee_ids || []).includes(user.id));
}

function assigneeLabel(task) {
    const count = (task.assignee_ids || []).length;
    if (!count) return 'Assegnatari';
    return `${count} assegnat${count === 1 ? 'ario' : 'ari'}`;
}

function toggleAssignee(task, userId) {
    const values = [...(task.assignee_ids || [])];
    const index = values.indexOf(userId);
    if (index >= 0) values.splice(index, 1);
    else values.push(userId);
    task.assignee_ids = values;
}

function personAvatarClass(selected) {
    return [
        'group/person relative inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300',
        selected
            ? 'bg-indigo-50 ring-2 ring-indigo-500 ring-offset-2 ring-offset-white'
            : 'bg-white/70 ring-1 ring-gray-200 hover:-translate-y-0.5 hover:ring-indigo-200 hover:shadow-[0_10px_24px_rgba(79,70,229,0.10)]',
    ];
}

function closeSectionActionMenuOnOutside(event) {
    if (sectionActionMenuOpen.value !== null && !(event.target instanceof Element && event.target.closest('[data-template-section-menu]'))) {
        sectionActionMenuOpen.value = null;
    }

    if (dependencyMenuTaskKey.value !== null && !(event.target instanceof Element && event.target.closest('[data-template-dependency-menu]'))) {
        dependencyMenuTaskKey.value = null;
    }
}

function requestFloatingUiClose() {
    window.dispatchEvent(new CustomEvent('centro:close-floating-ui'));
}

function closeTemplateFloatingUi() {
    sectionActionMenuOpen.value = null;
    dependencyMenuTaskKey.value = null;
}

function normalizeTemplatePayload() {
    form.sections.forEach((section) => {
        section.tasks.forEach((task) => {
            handleReferenceChange(task);
            task.dependency_mode = ['blocked_by', 'blocks'].includes(task.dependency_mode) ? task.dependency_mode : 'none';
            if (task.dependency_mode === 'none') {
                task.dependency_task_keys = [];
            } else {
                task.dependency_task_keys = [...new Set((task.dependency_task_keys || []).filter((key) => key && key !== task.template_key))];
            }
            if (task.task_type !== 'meeting') {
                task.due_time = '';
                task.location = '';
            }
        });
    });
}

function saveTemplate() {
    normalizeTemplatePayload();

    const options = {
        preserveScroll: true,
        onSuccess: () => form.clearErrors(),
    };

    if (editing.value) {
        form.put(route('project-templates.update', props.template.id), options);
        return;
    }

    form.post(route('project-templates.store'), options);
}

onMounted(() => {
    document.addEventListener('pointerdown', closeSectionActionMenuOnOutside, true);
    window.addEventListener('centro:close-floating-ui', closeTemplateFloatingUi);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeSectionActionMenuOnOutside, true);
    window.removeEventListener('centro:close-floating-ui', closeTemplateFloatingUi);
});
</script>

<template>
    <Head :title="editing ? `Modello ${template.name}` : 'Nuovo modello progetto'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('project-templates.index')" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                    Modelli progetto
                </Link>
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ editing ? form.name : 'Nuovo modello progetto' }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ form.sections.length }} fasi · {{ totalTasks }} task</p>
                    </div>
                    <button type="button" class="btn btn-primary" :disabled="form.processing" @click="saveTemplate">
                        <Save v-if="editing" class="h-4 w-4" :stroke-width="1.7" />
                        <CopyPlus v-else class="h-4 w-4" :stroke-width="1.7" />
                        {{ editing ? 'Salva modello' : 'Crea modello' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="surface p-5">
                    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_180px_150px]">
                        <div class="flex flex-col gap-1.5">
                            <label class="block text-sm font-medium leading-5 text-gray-700">Nome modello</label>
                            <input v-model="form.name" class="form-control mt-0 h-[38px] min-h-[38px]" placeholder="Es. Sito web standard" />
                            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="block text-sm font-medium leading-5 text-gray-700">Colore</label>
                            <div class="flex h-[38px] items-center gap-2">
                                <label class="relative inline-flex h-[38px] w-[38px] cursor-pointer overflow-hidden rounded-[var(--radius-sm)] border border-white shadow-sm" :style="{ backgroundColor: form.color }">
                                    <input v-model="form.color" type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                                </label>
                                <input v-model="form.color" class="form-control mt-0 h-[38px] min-h-[38px] w-28 font-mono text-xs" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <span class="block text-sm font-medium leading-5 text-gray-700">Stato</span>
                            <label class="mt-0 flex h-[38px] min-h-[38px] cursor-pointer items-center gap-2 px-1">
                                <input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                                <span class="text-sm font-semibold text-gray-700">Attivo</span>
                            </label>
                        </div>
                    </div>

                </section>

                <section class="surface overflow-visible">
                    <div class="flex items-center justify-between gap-3 border-b border-white/60 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Cronoprogramma</h3>
                            <p class="mt-1 text-sm text-gray-500">I giorni sono relativi alla data di avvio scelta quando crei il progetto.</p>
                        </div>
                    </div>

                    <div class="overflow-visible px-5 pb-5 pt-4">
                        <div class="hidden grid-cols-[24px_minmax(0,1.7fr)_minmax(140px,0.7fr)_170px_120px_120px_36px] border-y border-gray-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400 md:grid">
                            <span></span>
                            <span>Nome</span>
                            <span>Incaricato</span>
                            <span>Quando</span>
                            <span>Stato</span>
                            <span>Priorità</span>
                            <span></span>
                        </div>

                        <div
                            v-for="(sectionRow, sectionIndex) in form.sections"
                            :key="`section-${sectionIndex}`"
                            :class="['border-b border-gray-100 transition last:border-b-0', draggedTaskKey && taskDropSectionIndex === sectionIndex ? 'bg-indigo-50/35 ring-1 ring-inset ring-indigo-200' : '']"
                            @dragover.prevent="dragOverSection(sectionIndex)"
                            @dragleave="leaveTaskSection(sectionIndex, $event)"
                            @drop.prevent="dropTask(sectionRow, sectionIndex)"
                        >
                            <div class="group/project-section flex w-full items-center gap-2 px-3 py-3 text-left text-sm font-semibold text-gray-800">
                                <button type="button" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-50 hover:text-gray-700" :aria-expanded="!sectionCollapsed[sectionIndex]" @click="toggleSection(sectionIndex)">
                                    <ChevronDown :class="['h-4 w-4 transition-transform', sectionCollapsed[sectionIndex] ? '-rotate-90' : '']" :stroke-width="1.8" />
                                </button>
                                <input
                                    v-model="sectionRow.name"
                                    class="min-w-0 flex-1 cursor-pointer rounded-md border border-transparent bg-transparent px-2 py-1 text-sm font-semibold outline-none transition hover:border-gray-200 hover:bg-white focus:cursor-text focus:border-indigo-200 focus:bg-white focus:shadow-sm"
                                    placeholder="Nome fase"
                                />
                                <div class="relative shrink-0" data-template-section-menu>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 opacity-0 transition hover:bg-gray-50 hover:text-gray-800 group-hover/project-section:opacity-100 focus:opacity-100"
                                        aria-label="Azioni sezione"
                                        @click.stop="toggleSectionActionMenu(sectionIndex, $event)"
                                    >
                                        <MoreHorizontal class="h-4 w-4" :stroke-width="1.8" />
                                    </button>
                                    <div
                                        v-if="sectionActionMenuOpen === sectionIndex"
                                        :class="[
                                            'app-popover field-dropdown-menu absolute right-0 z-[7600] w-56 p-2',
                                            sectionActionMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2',
                                        ]"
                                        @click.stop
                                    >
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="duplicateSection(sectionRow)">
                                            <Copy class="h-4 w-4" :stroke-width="1.7" />
                                            Duplica sezione
                                        </button>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50" @click="collapseSectionFromMenu(sectionIndex)">
                                            <ChevronDown :class="['h-4 w-4 transition-transform', sectionCollapsed[sectionIndex] ? '-rotate-90' : '']" :stroke-width="1.7" />
                                            {{ sectionCollapsed[sectionIndex] ? 'Espandi' : 'Comprimi' }}
                                        </button>
                                        <button type="button" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50" @click="sectionActionMenuOpen = null; removeSection(sectionIndex)">
                                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                            Elimina sezione
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-show="!sectionCollapsed[sectionIndex]">
                                <div
                                    v-for="task in sectionRow.tasks"
                                    :key="task.template_key"
                                    draggable="true"
                                    :class="[
                                        'group/project-task relative grid w-full gap-3 border-t border-gray-100 px-3 py-2.5 text-left text-sm transition hover:bg-indigo-50/40 md:grid-cols-[24px_minmax(0,1.7fr)_minmax(140px,0.7fr)_170px_120px_120px_36px] md:items-center',
                                        task.status === 'done' ? 'opacity-60' : '',
                                        draggedTaskKey && draggedTaskKey !== task.template_key ? 'outline-offset-[-1px]' : '',
                                        taskDropTargetKey === task.template_key ? (taskDropPlacement === 'before' ? 'before:absolute before:left-3 before:right-3 before:top-0 before:h-1 before:rounded-full before:bg-indigo-500 before:shadow-[0_0_0_4px_rgba(99,102,241,0.12)]' : 'after:absolute after:bottom-0 after:left-3 after:right-3 after:h-1 after:rounded-full after:bg-indigo-500 after:shadow-[0_0_0_4px_rgba(99,102,241,0.12)]') : '',
                                    ]"
                                    @dragstart="startTaskDrag(task)"
                                    @dragover.prevent="dragOverTask(task, $event)"
                                    @drop.prevent.stop="dropTask(sectionRow, sectionIndex, task)"
                                    @dragend="endTaskDrag"
                                >
                                    <span class="hidden cursor-grab text-gray-300 transition group-hover/project-task:text-gray-500 md:inline-flex">
                                        <GripVertical class="h-4 w-4" :stroke-width="1.7" />
                                    </span>
                                    <button type="button" class="min-w-0 text-left font-medium text-indigo-700" @click="openTaskDrawer(task)">
                                        <span :class="['block truncate', task.status === 'done' ? 'line-through' : '']">{{ task.title || 'Task senza titolo' }}</span>
                                        <span class="mt-1 flex min-w-0 items-center gap-2 text-xs font-normal text-gray-500">
                                            <span class="truncate">{{ selectedServiceLabel(task) }} · {{ optionLabel(taskTypeOptions, task.task_type, 'Task') }}</span>
                                            <span
                                                v-if="dependencyPreviewLabel(task)"
                                                :class="['group/dependency relative inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full ring-1', dependencyPreviewBadge(task)?.class]"
                                            >
                                                <component :is="dependencyPreviewBadge(task)?.icon" class="h-3.5 w-3.5" :stroke-width="1.8" />
                                                <span class="pointer-events-none absolute bottom-full left-1/2 z-[7800] mb-2 hidden w-max max-w-[220px] -translate-x-1/2 rounded-[var(--radius-sm)] bg-gray-950 px-2.5 py-1.5 text-xs font-semibold leading-4 text-white shadow-lg group-hover/dependency:block">
                                                    {{ dependencyPreviewBadge(task)?.label }}
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                    <div class="flex min-w-0 items-center gap-2 text-xs text-gray-600">
                                        <span v-if="selectedAssignees(task).length" class="flex -space-x-2">
                                            <UserAvatar v-for="user in selectedAssignees(task).slice(0, 3)" :key="`template-row-assignee-${task.template_key}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                        </span>
                                        <span class="truncate">{{ selectedAssignees(task)[0]?.name || selectedAssignees(task)[0]?.email || 'Non assegnata' }}</span>
                                    </div>
                                    <span class="truncate text-xs font-medium text-gray-500">{{ dateRuleLabel(task) }}</span>
                                    <span>
                                        <span :class="['rounded-full px-2 py-1 text-xs font-semibold', task.status === 'done' ? 'bg-emerald-50 text-emerald-700' : task.status === 'in_progress' ? 'bg-sky-50 text-sky-700' : task.status === 'in_review' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600']">{{ optionLabel(statusOptions, task.status, 'Da fare') }}</span>
                                    </span>
                                    <span>
                                        <span :class="['rounded-full px-2 py-1 text-xs font-semibold', task.priority === 'urgent' ? 'bg-red-50 text-red-700' : task.priority === 'high' ? 'bg-orange-50 text-orange-700' : task.priority === 'low' ? 'bg-emerald-50 text-emerald-700' : 'bg-yellow-50 text-yellow-700']">{{ optionLabel(priorityOptions, task.priority, 'Media') }}</span>
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover/project-task:opacity-100 focus:opacity-100"
                                        aria-label="Elimina attività"
                                        @click.stop="removeTaskByKey(sectionRow, task.template_key)"
                                    >
                                        <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>
                                <form
                                    :class="['border-t border-gray-100 px-3 py-2.5 transition', draggedTaskKey && taskDropSectionIndex === sectionIndex && !taskDropTargetKey ? 'bg-indigo-50/70 ring-1 ring-inset ring-indigo-200' : '']"
                                    @submit.prevent="addTask(sectionRow, sectionIndex)"
                                    @dragover.prevent="dragOverSection(sectionIndex, true)"
                                    @drop.prevent.stop="dropTask(sectionRow, sectionIndex)"
                                >
                                    <input
                                        :value="taskDrafts[sectionIndex] || ''"
                                        class="subtask-line-control font-medium"
                                        placeholder="Aggiungi attività..."
                                        @input="setTaskDraft(sectionIndex, $event.target.value)"
                                        @keydown.enter.prevent="addTask(sectionRow, sectionIndex)"
                                        @blur="addTask(sectionRow, sectionIndex)"
                                    />
                                </form>
                            </div>
                        </div>

                        <div class="group/add-section px-3 py-3">
                            <form v-if="newSectionOpen" class="mb-2 flex max-w-lg items-center gap-2 rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70 px-3 py-2" @submit.prevent="addSection">
                                <Plus class="h-4 w-4 shrink-0 text-gray-400" :stroke-width="1.7" />
                                <input
                                    ref="newSectionInput"
                                    v-model="newSectionName"
                                    class="subtask-line-control font-medium"
                                    placeholder="Nome sezione"
                                    @keydown.enter.prevent="addSection"
                                    @blur="addSection"
                                />
                            </form>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-semibold text-gray-400 opacity-80 transition hover:bg-gray-50 hover:text-indigo-600 group-hover/add-section:opacity-100"
                                @click="showSectionInput"
                            >
                                <Plus class="h-4 w-4" :stroke-width="1.7" />
                                Aggiungi sezione
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <Transition name="calendar-task-drawer">
            <div v-if="drawerTask" class="fixed inset-0 z-[5200] bg-gray-950/20 backdrop-blur-[2px]" @click.self="closeTaskDrawer">
                <aside class="calendar-task-drawer-panel absolute right-0 top-0 flex h-full w-full max-w-3xl flex-col border-l border-white/80 bg-white shadow-2xl sm:w-[62vw]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Task modello</p>
                            <h3 class="mt-1 truncate text-lg font-semibold text-gray-900">{{ drawerTask.title || 'Task senza titolo' }}</h3>
                        </div>
                        <button type="button" class="icon-btn" @click="closeTaskDrawer">
                            <X class="h-4 w-4" :stroke-width="1.7" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 py-5">
                        <div class="space-y-5">
                            <section class="app-card p-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1.5 md:col-span-2">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Titolo</label>
                                        <input v-model="drawerTask.title" class="form-control mt-0 h-[38px] min-h-[38px]" placeholder="Titolo task" />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Tipo</label>
                                        <AppSelect v-model="drawerTask.task_type" :options="taskTypeOptions" @change="handleTaskTypeChange(drawerTask)" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Stato</label>
                                        <AppSelect v-model="drawerTask.status" :options="statusOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Priorità</label>
                                        <AppSelect v-model="drawerTask.priority" :options="priorityOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Servizio</label>
                                        <AppSelect v-model="drawerTask.service_id" :options="serviceOptions" searchable />
                                    </div>
                                </div>
                            </section>

                            <section class="app-card p-4">
                                <div class="mb-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Dipendenze</h4>
                                    <p class="mt-1 text-xs text-gray-500">Imposta se questa task blocca o viene bloccata da altre task del modello.</p>
                                </div>
                                <div class="grid items-start gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Tipo</label>
                                        <AppSelect v-model="drawerTask.dependency_mode" :options="dependencyModeOptions" @change="handleDependencyModeChange(drawerTask)" />
                                    </div>
                                    <div class="relative flex flex-col gap-1.5" data-template-dependency-menu>
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Task collegate</label>
                                        <button
                                            type="button"
                                            :class="[
                                                'form-control mt-0 flex h-[38px] items-center justify-between gap-3 text-left',
                                                drawerTask.dependency_mode === 'none' ? 'cursor-not-allowed bg-gray-50 text-gray-400' : '',
                                                dependencyMenuTaskKey === drawerTask.template_key ? 'border-indigo-300 ring-4 ring-indigo-500/10' : '',
                                            ]"
                                            :disabled="drawerTask.dependency_mode === 'none'"
                                            :aria-expanded="dependencyMenuTaskKey === drawerTask.template_key"
                                            @click.stop="toggleDependencyMenu(drawerTask, $event)"
                                        >
                                            <span :class="['truncate', (drawerTask.dependency_task_keys || []).length ? 'text-gray-800' : 'text-gray-400']">{{ dependencyModeLabel(drawerTask) }}</span>
                                            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', dependencyMenuTaskKey === drawerTask.template_key ? 'rotate-180 text-indigo-500' : '']" :stroke-width="1.7" />
                                        </button>
                                        <Teleport to="body">
                                            <div
                                                v-if="dependencyMenuTaskKey === drawerTask.template_key"
                                                class="app-popover field-dropdown-menu fixed z-[9000] p-3"
                                                :style="dependencyMenuStyle"
                                                data-template-dependency-menu
                                                @click.stop
                                            >
                                                <div class="max-h-56 overflow-y-auto pr-1">
                                                    <button
                                                        v-for="option in dependencyTaskOptions(drawerTask)"
                                                        :key="`dependency-option-${drawerTask.template_key}-${option.value}`"
                                                        type="button"
                                                        :class="[
                                                            'field-dropdown-option flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition hover:bg-indigo-50',
                                                            (drawerTask.dependency_task_keys || []).includes(option.value) ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700',
                                                        ]"
                                                        @click="toggleDependencyTask(drawerTask, option.value)"
                                                    >
                                                        <span class="truncate">{{ option.label }}</span>
                                                        <span v-if="(drawerTask.dependency_task_keys || []).includes(option.value)" class="h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                                                    </button>
                                                    <p v-if="!dependencyTaskOptions(drawerTask).length" class="px-3 py-2 text-sm text-gray-500">Nessun'altra task nel modello</p>
                                                </div>
                                            </div>
                                        </Teleport>
                                    </div>
                                </div>
                            </section>

                            <section class="app-card p-4">
                                <div class="mb-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Programmazione</h4>
                                    <p class="mt-1 text-xs text-gray-500">Definisci quando nasce questa task rispetto al progetto o a un'altra task del modello.</p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-[110px_140px_minmax(0,1fr)_110px]">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Giorni</label>
                                        <input v-model.number="drawerTask.day_offset" type="number" min="0" class="form-control mt-0 h-[38px] min-h-[38px]" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Direzione</label>
                                        <AppSelect v-model="drawerTask.date_offset_direction" :options="directionOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Riferimento</label>
                                        <AppSelect v-model="drawerTask.date_reference_value" :options="referenceOptions(drawerTask)" searchable @change="handleReferenceChange(drawerTask)" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Durata</label>
                                        <input v-model.number="drawerTask.duration_days" type="number" min="1" class="form-control mt-0 h-[38px] min-h-[38px]" />
                                    </div>
                                </div>
                            </section>

                            <section class="app-card p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Assegnatari</h4>
                                    <span class="text-xs font-medium text-gray-400">{{ assigneeLabel(drawerTask) }}</span>
                                </div>
                                <div class="people-avatar-picker max-h-52">
                                    <button
                                        v-for="user in users"
                                        :key="`drawer-template-assignee-${drawerTask.template_key}-${user.id}`"
                                        type="button"
                                        :class="personAvatarClass((drawerTask.assignee_ids || []).includes(user.id))"
                                        :aria-pressed="(drawerTask.assignee_ids || []).includes(user.id)"
                                        :aria-label="`${(drawerTask.assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                        @click="toggleAssignee(drawerTask, user.id)"
                                    >
                                        <UserAvatar :user="user" size="md" />
                                    </button>
                                </div>
                            </section>

                            <section v-if="drawerTask.task_type === 'meeting'" class="app-card p-4">
                                <div class="mb-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Meeting</h4>
                                </div>
                                <div class="grid gap-4 md:grid-cols-[160px_minmax(0,1fr)]">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Ora</label>
                                        <AppTimeInput v-model="drawerTask.due_time" placeholder="Ora" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-sm font-medium leading-5 text-gray-700">Luogo</label>
                                        <input v-model="drawerTask.location" class="form-control mt-0 h-[38px] min-h-[38px]" placeholder="Luogo meeting" />
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 px-5 py-4">
                        <button type="button" class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" @click="removeDrawerTask">
                            <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                            Elimina attività
                        </button>
                        <button type="button" class="btn btn-outline" @click="closeTaskDrawer">Chiudi</button>
                    </div>
                </aside>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
