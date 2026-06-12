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

const form = useForm({
    name: user.name,
    email: user.email,
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
