<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import AuthenticationCard from '@/Components/Login/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Login/AuthenticationCardLogo.vue';
import InputField from '@/Components/Forms/InputField.vue';

const form = useForm({
    password: '',
});

const passwordInput = ref(null);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();

            passwordInput.value.focus();
        },
    });
};
</script>

<template>
    <Head title="Secure Area" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-4 text-sm text-gray-600">
            This is a secure area of the application. Please confirm your password before continuing.
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputField
                    ref="passwordInput"
                    v-model="form.password"
                    label="Password"
                    field="password"
                    type="password"
                    :error="form.errors.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
            </div>

            <div class="flex justify-end mt-4">
                <AppButton variant="primary" class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Confirm
                </AppButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
