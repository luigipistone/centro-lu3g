<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTimeInput from '@/Components/AppTimeInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, CopyPlus, Plus, Save, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';

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
const taskDrawerKey = ref(null);

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
    const task = blankTask('', previous ? Number(previous.day_offset || 0) + 1 : 0);
    section.tasks.push(task);
    openTaskDrawer(task);
}

function removeTask(section, index) {
    section.tasks.splice(index, 1);
    if (!drawerTask.value) {
        taskDrawerKey.value = null;
    }
}

function allTemplateTasks() {
    return form.sections.flatMap((section) => section.tasks || []);
}

const drawerTask = computed(() => allTemplateTasks().find((task) => task.template_key === taskDrawerKey.value) || null);

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

function openTaskDrawer(task) {
    taskDrawerKey.value = task.template_key;
}

function closeTaskDrawer() {
    taskDrawerKey.value = null;
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
        'rounded-full p-0.5 transition hover:-translate-y-0.5 hover:ring-2 hover:ring-indigo-200',
        selected ? 'bg-indigo-100 ring-2 ring-indigo-400' : 'bg-white/70 ring-1 ring-gray-200',
    ];
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
                                <div class="hidden gap-3 border-b border-gray-100 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400 lg:grid lg:grid-cols-[minmax(220px,1fr)_132px_minmax(220px,1fr)_120px_116px_36px]">
                                    <span>Task</span>
                                    <span>Assegnatari</span>
                                    <span>Quando</span>
                                    <span>Stato</span>
                                    <span>Tipo</span>
                                    <span></span>
                                </div>

                                <div v-for="(task, taskIndex) in section.tasks" :key="`task-${sectionIndex}-${taskIndex}`" class="grid cursor-pointer items-center gap-3 border-b border-gray-100 py-3 transition hover:bg-white/50 last:border-b-0 lg:grid-cols-[minmax(220px,1fr)_132px_minmax(220px,1fr)_120px_116px_36px]" @click="openTaskDrawer(task)">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ task.title || 'Task senza titolo' }}</p>
                                        <p class="mt-1 truncate text-xs text-gray-500">{{ selectedServiceLabel(task) }} · {{ optionLabel(priorityOptions, task.priority, 'Media') }}</p>
                                    </div>
                                    <div>
                                        <span v-if="selectedAssignees(task).length" class="-space-x-2 whitespace-nowrap">
                                            <UserAvatar v-for="user in selectedAssignees(task).slice(0, 4)" :key="`template-row-assignee-${task.template_key}-${user.id}`" :user="user" size="xs" class="ring-2 ring-white" />
                                        </span>
                                        <span v-else class="text-xs font-medium text-gray-400">Nessuno</span>
                                    </div>
                                    <p class="min-w-0 truncate text-sm text-gray-600">{{ dateRuleLabel(task) }}</p>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ optionLabel(statusOptions, task.status, 'Da fare') }}</span>
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ optionLabel(taskTypeOptions, task.task_type, 'Task') }}</span>
                                    <button type="button" class="icon-btn" title="Elimina task" @click.stop="removeTask(section, taskIndex)">
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
                                        <UserAvatar :user="user" size="sm" />
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

                    <div class="flex justify-end border-t border-gray-100 px-5 py-4">
                        <button type="button" class="btn btn-outline" @click="closeTaskDrawer">Chiudi</button>
                    </div>
                </aside>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>
