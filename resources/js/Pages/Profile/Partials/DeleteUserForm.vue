<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import ActionSection from '@/Components/Cards/ActionSection.vue';
import InputField from '@/Components/Forms/InputField.vue';
import GlobalModal from '@/Components/Modales/GlobalModal.vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    setTimeout(() => passwordInput.value.focus(), 250);
};

const deleteUser = () => {
    form.delete(route('current-user.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.reset();
};
</script>

<template>
    <ActionSection>
        <template #title>
            Delete Account
        </template>

        <template #description>
            Permanently delete your account.
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-gray-600">
                Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
            </div>

            <div class="mt-5">
                <AppButton variant="danger" @click="confirmUserDeletion">
                    Delete Account
                </AppButton>
            </div>

            <GlobalModal
                v-if="confirmingUserDeletion"
                title="Delete Account"
                :processing="form.processing"
                :show-header="true"
                :show-footer="false"
                :show-save="false"
                size="lg"
                @close="closeModal"
            >
                <template #content>
                    <div class="space-y-4 px-5 py-5 md:px-6">
                        <p class="text-sm text-slate-600">
                            Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
                        </p>

                        <div>
                            <InputField
                                ref="passwordInput"
                                v-model="form.password"
                                label="Password"
                                field="password"
                                type="password"
                                placeholder="Password"
                                :error="form.errors.password"
                                autocomplete="current-password"
                                @keyup.enter="deleteUser"
                            />
                        </div>
                    </div>
                </template>

                <template #footer="{ close }">
                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 md:px-6">
                        <AppButton variant="secondary" @click="close">
                            Cancel
                        </AppButton>

                        <AppButton
                            variant="danger"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="deleteUser"
                        >
                            Delete Account
                        </AppButton>
                    </div>
                </template>
            </GlobalModal>
        </template>
    </ActionSection>
</template>
