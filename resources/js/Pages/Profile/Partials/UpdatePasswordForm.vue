<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import FormSection from '@/Components/Cards/FormSection.vue';
import InputField from '@/Components/Forms/InputField.vue';
import { SuccessAlert } from '@/Components/Modales/UniversalActionModal';
import { t } from '@/i18n/es';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            SuccessAlert({
                title: t('profile.password.updated.title'),
                message: t('profile.password.updated.message'),
            });
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <FormSection @submitted="updatePassword">
        <template #title>
            {{ t('profile.password.title') }}
        </template>

        <template #description>
            {{ t('profile.password.description') }}
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <InputField
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    :label="t('profile.password.current')"
                    field="current_password"
                    type="password"
                    :error="form.errors.current_password"
                    autocomplete="current-password"
                />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputField
                    ref="passwordInput"
                    v-model="form.password"
                    :label="t('profile.password.new')"
                    field="password"
                    type="password"
                    :error="form.errors.password"
                    autocomplete="new-password"
                />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputField
                    v-model="form.password_confirmation"
                    :label="t('profile.password.confirm')"
                    field="password_confirmation"
                    type="password"
                    :error="form.errors.password_confirmation"
                    autocomplete="new-password"
                />
            </div>
        </template>

        <template #actions>
            <AppButton variant="primary" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ t('common.save') }}
            </AppButton>
        </template>
    </FormSection>
</template>
