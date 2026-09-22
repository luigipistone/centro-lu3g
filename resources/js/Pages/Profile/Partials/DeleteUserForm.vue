<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

defineProps({ request: Object });

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
    reason: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.post(route('profile.archive-request'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Archiviazione account
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Puoi inviare una richiesta motivata all’amministrazione. Fino all’approvazione il tuo account resta attivo e nessun dato viene eliminato.
            </p>
        </header>

        <div v-if="request?.status === 'pending'" class="rounded-[var(--radius-sm)] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Richiesta inviata e in attesa di approvazione.</div>
        <div v-else-if="request?.status === 'rejected'" class="rounded-[var(--radius-sm)] border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">L’ultima richiesta è stata rifiutata. Puoi inviarne una nuova con maggiori informazioni.</div>
        <DangerButton v-if="request?.status !== 'pending'" @click="confirmUserDeletion">Richiedi archiviazione</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900"
                >
                    Richiedi l’archiviazione dell’account
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Spiega il motivo della richiesta. Un Superadmin controllerà gli elementi collegati prima di approvarla.
                </p>

                <div class="mt-5">
                    <InputLabel for="archive-reason" value="Motivazione" />
                    <textarea id="archive-reason" v-model="form.reason" rows="4" class="form-control mt-1" placeholder="Descrivi perché desideri archiviare l’account"></textarea>
                    <InputError :message="form.errors.reason" class="mt-2" />
                </div>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="Password"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Annulla
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Invia richiesta
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
