<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTimeInput from '@/Components/AppTimeInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronLeft, CopyPlus, Plus, Save, Trash2, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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

const editing = computed(() => Boolean(props.template?.id));
const assigneeMenuKey = ref(null);

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

function addSection() {
    form.sections.push({
        name: `Nuova fase ${form.sections.length + 1}`,
        tasks: [],
    });
}

function removeSection(index) {
    form.sections.splice(index, 1);
    if (!form.sections.length) addSection();
}

function addTask(section) {
    const previous = section.tasks.at(-1);
    section.tasks.push(blankTask('', previous ? Number(previous.day_offset || 0) + 1 : 0));
}

function removeTask(section, index) {
    section.tasks.splice(index, 1);
}

function allTemplateTasks() {
    return form.sections.flatMap((section) => section.tasks || []);
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
        'rounded-full p-0.5 transition hover:-translate-y-0.5 hover:ring-2 hover:ring-indigo-200',
        selected ? 'bg-indigo-100 ring-2 ring-indigo-400' : 'bg-white/70 ring-1 ring-gray-200',
    ];
}

function closeAssigneeMenuOnOutside(event) {
    if (!assigneeMenuKey.value) return;
    if (event.target instanceof Element && event.target.closest('[data-template-assignees]')) return;
    assigneeMenuKey.value = null;
}

function normalizeTemplatePayload() {
    form.sections.forEach((section) => {
        section.tasks.forEach((task) => {
            handleReferenceChange(task);
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
    document.addEventListener('pointerdown', closeAssigneeMenuOnOutside, true);
});

onUnmounted(() => {
    document.removeEventListener('pointerdown', closeAssigneeMenuOnOutside, true);
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

                <section class="surface overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-white/60 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Cronoprogramma</h3>
                            <p class="mt-1 text-sm text-gray-500">I giorni sono relativi alla data di avvio scelta quando crei il progetto.</p>
                        </div>
                        <button type="button" class="btn btn-outline" @click="addSection">
                            <Plus class="h-4 w-4" :stroke-width="1.7" />
                            Aggiungi fase
                        </button>
                    </div>

                    <div class="space-y-6 p-5">
                        <div v-for="(section, sectionIndex) in form.sections" :key="`section-${sectionIndex}`" class="overflow-visible">
                            <div class="mb-4 flex items-center gap-2 border-b border-gray-100 py-3">
                                <input v-model="section.name" class="min-w-0 flex-1 bg-transparent text-sm font-semibold text-gray-900 outline-none" placeholder="Nome fase" />
                                <button type="button" class="icon-btn" title="Aggiungi task" @click="addTask(section)">
                                    <Plus class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button type="button" class="icon-btn" title="Elimina fase" @click="removeSection(sectionIndex)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>

                            <div>
                                <div class="hidden gap-3 border-b border-gray-100 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400 xl:grid xl:grid-cols-[minmax(190px,1fr)_132px_minmax(270px,1.1fr)_82px_124px_124px_118px_36px]">
                                    <span>Task</span>
                                    <span>Assegnatari</span>
                                    <span>Quando</span>
                                    <span>Durata</span>
                                    <span>Servizio</span>
                                    <span>Stato</span>
                                    <span>Priorità</span>
                                    <span>Tipo</span>
                                    <span></span>
                                </div>

                                <div v-for="(task, taskIndex) in section.tasks" :key="`task-${sectionIndex}-${taskIndex}`" class="grid items-start gap-3 border-b border-gray-100 py-3 last:border-b-0 xl:grid-cols-[minmax(190px,1fr)_132px_minmax(270px,1.1fr)_82px_124px_124px_118px_36px]">
                                    <div class="flex flex-col">
                                        <input v-model="task.title" class="form-control mt-0 h-[38px] min-h-[38px]" placeholder="Titolo task" />
                                        <div v-if="task.task_type === 'meeting'" class="mt-2 grid gap-2 sm:grid-cols-[132px_minmax(0,1fr)]">
                                            <AppTimeInput v-model="task.due_time" placeholder="Ora" />
                                            <input v-model="task.location" class="form-control mt-0 h-[38px] min-h-[38px]" placeholder="Luogo meeting" />
                                        </div>
                                    </div>
                                    <div class="relative flex flex-col gap-1.5" data-template-assignees>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Assegnatari</label>
                                        <button type="button" class="form-control mt-0 flex h-[38px] items-center justify-between gap-2 text-left" @click.stop="assigneeMenuKey = assigneeMenuKey === task.template_key ? null : task.template_key">
                                            <span v-if="selectedAssignees(task).length" class="-space-x-2 whitespace-nowrap">
                                                <UserAvatar v-for="user in selectedAssignees(task).slice(0, 3)" :key="`template-assignee-preview-${task.template_key}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                            </span>
                                            <span class="min-w-0 truncate text-sm text-gray-500">{{ assigneeLabel(task) }}</span>
                                            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition', assigneeMenuKey === task.template_key ? 'rotate-180 text-indigo-500' : '']" :stroke-width="1.7" />
                                        </button>
                                        <div v-if="assigneeMenuKey === task.template_key" class="app-popover field-dropdown-menu absolute left-0 top-full z-[7200] mt-2 w-64 p-3" @click.stop>
                                            <div class="people-avatar-picker max-h-52">
                                                <button
                                                    v-for="user in users"
                                                    :key="`template-assignee-${task.template_key}-${user.id}`"
                                                    type="button"
                                                    :class="personAvatarClass((task.assignee_ids || []).includes(user.id))"
                                                    :aria-pressed="(task.assignee_ids || []).includes(user.id)"
                                                    :aria-label="`${(task.assignee_ids || []).includes(user.id) ? 'Rimuovi' : 'Assegna'} ${user.name || user.email}`"
                                                    @click="toggleAssignee(task, user.id)"
                                                >
                                                    <UserAvatar :user="user" size="sm" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Quando</label>
                                        <div class="grid gap-2 sm:grid-cols-[72px_86px_minmax(0,1fr)] xl:grid-cols-[64px_76px_minmax(0,1fr)]">
                                            <input v-model.number="task.day_offset" type="number" min="0" class="form-control mt-0 h-[38px] min-h-[38px]" />
                                            <AppSelect v-model="task.date_offset_direction" :options="directionOptions" />
                                            <AppSelect v-model="task.date_reference_value" :options="referenceOptions(task)" searchable @change="handleReferenceChange(task)" />
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Durata</label>
                                        <input v-model.number="task.duration_days" type="number" min="1" class="form-control mt-0 h-[38px] min-h-[38px]" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Servizio</label>
                                        <AppSelect v-model="task.service_id" :options="serviceOptions" searchable />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Stato</label>
                                        <AppSelect v-model="task.status" :options="statusOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Priorità</label>
                                        <AppSelect v-model="task.priority" :options="priorityOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Tipo</label>
                                        <AppSelect v-model="task.task_type" :options="taskTypeOptions" @change="handleTaskTypeChange(task)" />
                                    </div>
                                    <button type="button" class="icon-btn self-end xl:self-start" title="Elimina task" @click="removeTask(section, taskIndex)">
                                        <X class="h-4 w-4" :stroke-width="1.7" />
                                    </button>
                                </div>

                                <button v-if="!section.tasks.length" type="button" class="w-full px-4 py-4 text-left text-sm font-semibold text-gray-400 transition hover:text-[hsl(var(--primary-app))]" @click="addTask(section)">
                                    + Aggiungi task
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
