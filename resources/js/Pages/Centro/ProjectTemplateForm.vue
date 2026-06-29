<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, CopyPlus, Plus, Save, Trash2, X } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    template: Object,
});

const priorityOptions = [
    { value: 'low', label: 'Bassa' },
    { value: 'medium', label: 'Media' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];
const taskTypeOptions = [
    { value: 'project', label: 'Task' },
    { value: 'ongoing', label: 'Continuativa' },
    { value: 'meeting', label: 'Meeting' },
];

const editing = computed(() => Boolean(props.template?.id));

const form = useForm(templatePayload(props.template));
const totalTasks = computed(() => form.sections.reduce((sum, section) => sum + section.tasks.length, 0));

function blankTask(title = '', dayOffset = 0) {
    return {
        title,
        description: '',
        day_offset: dayOffset,
        duration_days: 1,
        priority: 'medium',
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
                title: task.title || '',
                description: task.description || '',
                day_offset: Number(task.day_offset || 0),
                duration_days: Number(task.duration_days || 1),
                priority: task.priority || 'medium',
                task_type: task.task_type || 'project',
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

function saveTemplate() {
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
                            <label class="form-control mt-0 flex h-[38px] min-h-[38px] cursor-pointer items-center gap-2">
                                <input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                                <span class="text-sm font-semibold text-gray-700">Attivo</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-1.5">
                        <label class="block text-sm font-medium leading-5 text-gray-700">Descrizione</label>
                        <textarea v-model="form.description" rows="3" class="form-control mt-0" placeholder="Quando usare questo modello..."></textarea>
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

                    <div class="space-y-4 p-5">
                        <div v-for="(section, sectionIndex) in form.sections" :key="`section-${sectionIndex}`" class="overflow-hidden rounded-[var(--radius)] border border-gray-100 bg-white/58">
                            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3">
                                <input v-model="section.name" class="min-w-0 flex-1 bg-transparent text-sm font-semibold text-gray-900 outline-none" placeholder="Nome fase" />
                                <button type="button" class="icon-btn" title="Aggiungi task" @click="addTask(section)">
                                    <Plus class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                                <button type="button" class="icon-btn" title="Elimina fase" @click="removeSection(sectionIndex)">
                                    <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                                </button>
                            </div>

                            <div class="divide-y divide-gray-100">
                                <div class="hidden gap-3 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400 xl:grid xl:grid-cols-[minmax(0,1.4fr)_96px_96px_150px_150px_36px]">
                                    <span>Task</span>
                                    <span>Giorno</span>
                                    <span>Durata</span>
                                    <span>Priorità</span>
                                    <span>Tipo</span>
                                    <span></span>
                                </div>

                                <div v-for="(task, taskIndex) in section.tasks" :key="`task-${sectionIndex}-${taskIndex}`" class="grid items-start gap-3 px-4 py-3 xl:grid-cols-[minmax(0,1.4fr)_96px_96px_150px_150px_36px]">
                                    <div class="flex flex-col">
                                        <input v-model="task.title" class="form-control mt-0 h-[38px] min-h-[38px]" placeholder="Titolo task" />
                                        <textarea v-model="task.description" rows="2" class="form-control mt-2 text-sm" placeholder="Descrizione opzionale"></textarea>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Giorno</label>
                                        <input v-model.number="task.day_offset" type="number" min="0" class="form-control mt-0 h-[38px] min-h-[38px]" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Durata</label>
                                        <input v-model.number="task.duration_days" type="number" min="1" class="form-control mt-0 h-[38px] min-h-[38px]" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Priorità</label>
                                        <AppSelect v-model="task.priority" :options="priorityOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400 xl:hidden">Tipo</label>
                                        <AppSelect v-model="task.task_type" :options="taskTypeOptions" />
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
