<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, Layers3, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

defineProps({
    templates: Array,
});

const deleteTarget = ref(null);
const deleteText = ref('');

function contrastColor(hex) {
    const value = String(hex || '#2563eb').replace('#', '');
    const full = value.length === 3 ? value.split('').map((part) => part + part).join('') : value;
    const int = Number.parseInt(full, 16);
    if (Number.isNaN(int)) return '#ffffff';

    const red = (int >> 16) & 255;
    const green = (int >> 8) & 255;
    const blue = int & 255;
    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

    return luminance > 0.62 ? '#111827' : '#ffffff';
}

function templateStyle(template) {
    const background = template.color || '#2563eb';

    return {
        backgroundColor: background,
        color: contrastColor(background),
    };
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
            <div class="flex flex-col gap-3">
                <Link :href="route('projects.index')" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 transition hover:text-[hsl(var(--primary-app))]">
                    <ChevronLeft class="h-4 w-4" :stroke-width="1.7" />
                    Progetti
                </Link>
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">Modelli progetto</h2>
                        <p class="mt-1 text-sm text-gray-500">Cronoprogrammi riutilizzabili con fasi e task preimpostate.</p>
                    </div>
                    <Link :href="route('project-templates.create')" class="btn btn-primary">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo modello
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <section v-if="templates?.length" class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="template in templates"
                        :key="template.id"
                        class="content-card project-preview-card group relative min-h-[190px] overflow-hidden border shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                        :style="templateStyle(template)"
                    >
                        <Link :href="route('project-templates.show', template.id)" class="flex h-full min-h-[190px] flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 pr-16">
                                    <h3 class="line-clamp-2 text-base font-semibold leading-5">{{ template.name }}</h3>
                                    <p v-if="!template.active" class="mt-2 inline-flex rounded-full bg-white/20 px-2 py-1 text-[11px] font-semibold">Disattivo</p>
                                </div>
                            </div>

                            <p v-if="template.description" class="mt-4 line-clamp-2 text-sm opacity-80">{{ template.description }}</p>

                            <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                                <div class="text-xs font-semibold opacity-80">
                                    {{ template.sections?.length || 0 }} fasi · {{ template.tasks_count || 0 }} task
                                </div>
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-white/18">
                                    <Layers3 class="h-4 w-4" :stroke-width="1.7" />
                                </span>
                            </div>
                        </Link>

                        <div class="absolute right-4 top-4 flex items-center gap-1 opacity-100">
                            <Link :href="route('project-templates.show', template.id)" class="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-sm)] bg-white/18 text-current transition hover:-translate-y-0.5 hover:bg-white/28" title="Modifica" @click.stop>
                                <Pencil class="h-4 w-4" :stroke-width="1.7" />
                            </Link>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-sm)] bg-white/18 text-current transition hover:-translate-y-0.5 hover:bg-white/28" title="Elimina" @click.stop="requestDelete(template)">
                                <Trash2 class="h-4 w-4" :stroke-width="1.7" />
                            </button>
                        </div>
                    </article>
                </section>

                <div v-else class="surface p-8 text-center">
                    <Layers3 class="mx-auto h-8 w-8 text-[hsl(var(--primary-app))]" :stroke-width="1.7" />
                    <h3 class="mt-3 text-base font-semibold text-gray-900">Nessun modello creato</h3>
                    <p class="mt-1 text-sm text-gray-500">Crea il primo modello per generare progetti con fasi e task già pronte.</p>
                    <Link :href="route('project-templates.create')" class="btn btn-primary mt-5">
                        <Plus class="h-4 w-4" :stroke-width="1.7" />
                        Nuovo modello
                    </Link>
                </div>
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
