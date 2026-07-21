<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import ActionSection from '@/Components/Cards/ActionSection.vue';
import InputField from '@/Components/Forms/InputField.vue';
import GlobalModal from '@/Components/Modales/GlobalModal.vue';
import { t } from '@/i18n/es';

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
            {{ t('profile.delete.title') }}
        </template>

        <template #description>
            {{ t('profile.delete.description') }}
        </template>

        <template #content>
            <div class="max-w-xl text-sm text-gray-600">
                {{ t('profile.delete.help') }}
            </div>

            <div class="mt-5">
                <AppButton variant="danger" @click="confirmUserDeletion">
                    {{ t('profile.delete.title') }}
                </AppButton>
            </div>

            <GlobalModal
                v-if="confirmingUserDeletion"
                :title="t('profile.delete.title')"
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
                            {{ t('profile.delete.confirmHelp') }}
                        </p>

                        <div>
                            <InputField
                                ref="passwordInput"
                                v-model="form.password"
                                :label="t('common.password')"
                                field="password"
                                type="password"
                                :placeholder="t('common.password')"
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
                            {{ t('common.cancel') }}
                        </AppButton>

                        <AppButton
                            variant="danger"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="deleteUser"
                        >
                            {{ t('profile.delete.title') }}
                        </AppButton>
                    </div>
                </template>
            </GlobalModal>
        </template>
    </ActionSection>
</template>
