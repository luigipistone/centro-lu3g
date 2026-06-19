<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifica email" />

        <div class="mb-4 text-sm text-gray-600">
            Prima di iniziare verifica il tuo indirizzo email cliccando sul link che ti abbiamo inviato. Se non l'hai ricevuto, puoi richiederne uno nuovo.
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            Ti abbiamo inviato un nuovo link di verifica all'indirizzo email indicato in fase di registrazione.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Reinvia email di verifica
                </PrimaryButton>

                <a
                    href="/esci-app"
                    class="rounded-md text-sm text-gray-600 underline hover:text-[hsl(var(--primary-app-dark))] focus:outline-none focus:ring-2 focus:ring-[hsl(var(--primary-app)/0.35)] focus:ring-offset-2"
                    >Esci</a
                >
            </div>
        </form>
    </GuestLayout>
</template>
