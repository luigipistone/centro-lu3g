<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const profile = usePage().props.profile || {};
const notificationPreferences = usePage().props.notificationPreferences || [];

const completionEffectOptions = [
    { value: 'balloons', label: 'Palloncini' },
    { value: 'fireworks', label: "Fuochi d'artificio" },
    { value: 'snow', label: 'Nevicata' },
    { value: 'glitch', label: 'Glitch' },
];
const completionEffectValues = completionEffectOptions.map((option) => option.value);
const currentCompletionEffect = completionEffectValues.includes(profile.completion_effect || user.completion_effect)
    ? (profile.completion_effect || user.completion_effect)
    : 'balloons';

const form = useForm({
    name: user.name,
    email: user.email,
    completion_effect: currentCompletionEffect,
    notification_preferences: notificationPreferences.map((preference) => ({
        category: preference.category,
        label: preference.label,
        in_app: Boolean(preference.in_app),
        browser: Boolean(preference.browser),
        mail: Boolean(preference.mail),
    })),
});

const avatarInput = ref(null);
const avatarPreview = ref(null);
const avatarForm = useForm({
    avatar: null,
});

const previewUser = computed(() => ({
    ...user,
    avatar_url: avatarPreview.value || user.avatar_url,
}));

function chooseAvatar() {
    avatarInput.value?.click();
}

function uploadAvatar(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    avatarForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
    avatarForm.post(route('profile.avatar.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function channelLabel(channel) {
    return {
        in_app: 'In app',
        browser: 'Browser',
        mail: 'Email',
    }[channel];
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Informazioni profilo
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Aggiorna dati personali, email e foto profilo.
            </p>
        </header>

        <div class="mt-6 flex flex-wrap items-center gap-4 rounded-md border border-gray-100 bg-gray-50 p-4">
            <UserAvatar :user="previewUser" size="lg" />
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-gray-900">Foto personale</div>
                <div class="mt-1 text-xs text-gray-500">JPG, PNG o WEBP fino a 2 MB.</div>
                <InputError class="mt-2" :message="avatarForm.errors.avatar" />
            </div>
            <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadAvatar" />
            <button type="button" class="btn btn-outline" :disabled="avatarForm.processing" @click="chooseAvatar">
                {{ avatarForm.processing ? 'Caricamento...' : 'Carica foto' }}
            </button>
        </div>

        <form @submit.prevent="form.patch(route('profile.update'))" class="mt-6 space-y-6">
            <div>
                <InputLabel for="name" value="Nome" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Il tuo indirizzo email non e verificato.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Invia di nuovo l'email di verifica.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Email di verifica inviata.
                </div>
            </div>

            <div>
                <InputLabel for="completion_effect" value="Animazione completamento task" />

                <select
                    id="completion_effect"
                    v-model="form.completion_effect"
                    class="form-control"
                >
                    <option v-for="option in completionEffectOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <p class="mt-1 text-xs text-gray-500">Viene mostrata a fullscreen per qualche secondo quando completi una task.</p>
                <InputError class="mt-2" :message="form.errors.completion_effect" />
            </div>

            <div>
                <div class="mb-3">
                    <h3 class="text-sm font-semibold text-gray-900">Preferenze notifiche</h3>
                    <p class="mt-1 text-xs text-gray-500">Scegli per ogni area quali canali usare per ricevere gli aggiornamenti.</p>
                </div>

                <div class="overflow-hidden rounded-[var(--radius-sm)] border border-gray-100 bg-gray-50/70">
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-b border-gray-100 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 sm:grid-cols-[minmax(0,1fr)_repeat(3,6.5rem)]">
                        <span>Area</span>
                        <span class="hidden text-center sm:block">In app</span>
                        <span class="hidden text-center sm:block">Browser</span>
                        <span class="hidden text-center sm:block">Email</span>
                    </div>

                    <div
                        v-for="(preference, index) in form.notification_preferences"
                        :key="preference.category"
                        class="grid gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_repeat(3,6.5rem)] sm:items-center"
                    >
                        <input type="hidden" v-model="preference.category" />
                        <div class="text-sm font-semibold text-gray-900">{{ preference.label }}</div>

                        <label
                            v-for="channel in ['in_app', 'browser', 'mail']"
                            :key="channel"
                            class="flex items-center justify-between gap-3 rounded-[var(--radius-sm)] bg-white/70 px-3 py-2 text-xs font-semibold text-gray-600 sm:justify-center sm:bg-transparent sm:px-0 sm:py-0"
                        >
                            <span class="sm:hidden">{{ channelLabel(channel) }}</span>
                            <input
                                v-model="form.notification_preferences[index][channel]"
                                type="checkbox"
                                class="sr-only"
                            />
                            <span
                                class="relative h-6 w-11 rounded-full transition"
                                :class="form.notification_preferences[index][channel] ? 'bg-[var(--brand-primary,#3b82f6)]' : 'bg-gray-200'"
                            >
                                <span
                                    class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow-sm transition"
                                    :class="form.notification_preferences[index][channel] ? 'translate-x-5' : 'translate-x-0'"
                                ></span>
                            </span>
                        </label>
                    </div>
                </div>

                <InputError class="mt-2" :message="form.errors.notification_preferences" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Salva</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Salvato.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
