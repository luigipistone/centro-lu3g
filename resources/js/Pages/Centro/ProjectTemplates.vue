<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, CopyPlus, Layers3, Plus, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    templates: Array,
});

const selectedTemplateId = ref(null);
const deleteTarget = ref(null);
const deleteText = ref('');
const projectColors = ['#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#475569'];
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

const form = useForm(blankTemplate());

const templates = computed(() => props.templates || []);
const selectedTemplate = computed(() => templates.value.find((template) => template.id === selectedTemplateId.value));
const totalTasks = computed(() => form.sections.reduce((sum, section) => sum + section.tasks.length, 0));

function blankTemplate() {
    return {
        name: '',
        description: '',
        color: '#2563eb',
        active: true,
        sections: [
            {
                name: 'Fase preliminare',
                tasks: [
                    blankTask('Prima attività', 0),
                ],
            },
        ],
    };
}

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

function resetForm() {
    selectedTemplateId.value = null;
    form.defaults(blankTemplate());
    form.reset();
    form.clearErrors();
}

function editTemplate(template) {
    selectedTemplateId.value = template.id;
    const payload = {
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
    form.defaults(payload);
    form.reset();
    form.clearErrors();
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
        onSuccess: () => resetForm(),
    };

    if (selectedTemplateId.value) {
        form.put(route('project-templates.update', selectedTemplateId.value), options);
        return;
    }

    form.post(route('project-templates.store'), options);
}

function requestDelete(template) {
    deleteTarget.value = template;
    deleteText.value = '';
}

function confirmDelete() {
    if (!deleteTarget.value || deleteText.value !== 'ELIMINA') return;

    router.delete(route('project-templates.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedTemplateId.value === deleteTarget.value.id) resetForm();
            deleteTarget.value = null;
            deleteText.value = '';
        },
    });
}
</script>

<template>
    <Head title="Modelli progetto" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <Link :href="route('projects.index')" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                    Progetti
                </Link>
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Modelli progetto</h2>
                    <p class="text-sm text-gray-500">Crea cronoprogrammi riutilizzabili con fasi e task preimpostate.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:px-8">
                <section class="space-y-3">
                    <button type="button" class="btn btn-primary" @click="resetForm">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo modello
                    </button>

                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        :class="['surface group w-full p-4 text-left transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(28,42,73,0.10)]', selectedTemplateId === template.id ? 'ring-2 ring-[hsl(var(--primary-app)/0.22)]' : '']"
                        @click="editTemplate(template)"
                    >
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] text-white" :style="{ backgroundColor: template.color || '#2563eb' }">
                                <Layers3 class="h-5 w-5" :stroke-width="1.7" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-900">{{ template.name }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ template.sections?.length || 0 }} fasi · {{ template.tasks_count || 0 }} task</span>
                                <span v-if="!template.active" class="mt-2 inline-flex rounded-full bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-500">Disattivo</span>
                            </span>
                            <button type="button" class="icon-btn opacity-0 transition group-hover:opacity-100" title="Elimina" @click.stop="requestDelete(template)">
                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                            </button>
                        </div>
                    </button>

                    <div v-if="!templates.length" class="surface p-5 text-sm text-gray-500">
                        Nessun modello creato.
                    </div>
                </section>

                <section class="surface overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-white/60 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ selectedTemplate ? 'Modifica modello' : 'Nuovo modello' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ form.sections.length }} fasi · {{ totalTasks }} task</p>
                        </div>
                        <button type="button" class="btn btn-primary" :disabled="form.processing" @click="saveTemplate">
                            <CopyPlus class="h-4 w-4" :stroke-width="1.7" />
                            Salva modello
                        </button>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_120px_auto]">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nome modello</label>
                                <input v-model="form.name" class="form-control" placeholder="Es. Sito web standard" />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Colore</label>
                                <div class="mt-2 flex items-center gap-2">
                                    <label class="relative inline-flex h-9 w-9 cursor-pointer overflow-hidden rounded-[var(--radius-sm)] border border-white shadow-sm" :style="{ backgroundColor: form.color }">
                                        <input v-model="form.color" type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" />
                                    </label>
                                    <input v-model="form.color" class="form-control mt-0 w-24 font-mono text-xs" />
                                </div>
                            </div>
                            <label class="flex items-end gap-2 pb-2 text-sm font-semibold text-gray-700">
                                <input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-[hsl(var(--primary-app))]" />
                                Attivo
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                            <textarea v-model="form.description" rows="3" class="form-control" placeholder="Quando usare questo modello..."></textarea>
                        </div>

                        <div class="space-y-4">
                            <div v-for="(section, sectionIndex) in form.sections" :key="`section-${sectionIndex}`" class="rounded-[var(--radius)] border border-gray-100 bg-white/58">
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
                                    <div v-for="(task, taskIndex) in section.tasks" :key="`task-${sectionIndex}-${taskIndex}`" class="grid items-start gap-3 px-4 py-3 md:grid-cols-[minmax(0,1.3fr)_90px_90px_140px_140px_36px]">
                                        <div>
                                            <input v-model="task.title" class="form-control mt-0" placeholder="Titolo task" />
                                            <textarea v-model="task.description" rows="2" class="form-control mt-2 text-sm" placeholder="Descrizione opzionale"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Giorno</label>
                                            <input v-model.number="task.day_offset" type="number" min="0" class="form-control mt-1" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Durata</label>
                                            <input v-model.number="task.duration_days" type="number" min="1" class="form-control mt-1" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Priorità</label>
                                            <AppSelect v-model="task.priority" :options="priorityOptions" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Tipo</label>
                                            <AppSelect v-model="task.task_type" :options="taskTypeOptions" />
                                        </div>
                                        <button type="button" class="icon-btn mt-6" title="Elimina task" @click="removeTask(section, taskIndex)">
                                            <X class="h-4 w-4" :stroke-width="1.7" />
                                        </button>
                                    </div>
                                    <button v-if="!section.tasks.length" type="button" class="w-full px-4 py-4 text-left text-sm font-semibold text-gray-400 transition hover:text-[hsl(var(--primary-app))]" @click="addTask(section)">
                                        + Aggiungi task
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline" @click="addSection">
                            <Plus class="h-4 w-4" :stroke-width="1.7" />
                            Aggiungi fase
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 z-[5300] flex items-center justify-center bg-transparent px-4" @click.self="deleteTarget = null">
            <section class="w-full max-w-md rounded-[var(--radius)] bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.20)]">
                <h3 class="text-base font-semibold text-gray-900">Eliminare modello?</h3>
                <p class="mt-2 text-sm text-gray-500">Digita <span class="font-mono font-semibold">ELIMINA</span> per confermare.</p>
                <input v-model="deleteText" class="form-control mt-4 font-mono" placeholder="ELIMINA" />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn btn-outline" @click="deleteTarget = null">Annulla</button>
                    <button type="button" class="btn btn-danger" :disabled="deleteText !== 'ELIMINA'" @click="confirmDelete">Elimina</button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
