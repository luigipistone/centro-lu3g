<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Eye, EyeOff } from '@lucide/vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});
const passwordVisible = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
            passwordVisible.value = false;
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Accesso" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        :type="passwordVisible ? 'text' : 'password'"
                        class="block w-full pr-12"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-500 transition hover:text-gray-800 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[hsl(var(--primary-app)/0.5)]"
                        :aria-label="passwordVisible ? 'Nascondi password' : 'Mostra password'"
                        :aria-pressed="passwordVisible"
                        :title="passwordVisible ? 'Nascondi password' : 'Mostra password'"
                        @click="passwordVisible = !passwordVisible"
                    >
                        <EyeOff v-if="passwordVisible" class="h-4 w-4" :stroke-width="1.8" />
                        <Eye v-else class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600"
                        >Ricordami</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-[hsl(var(--primary-app-dark))] focus:outline-none focus:ring-2 focus:ring-[hsl(var(--primary-app)/0.35)] focus:ring-offset-2"
                >
                    Password dimenticata?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Accedi
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
