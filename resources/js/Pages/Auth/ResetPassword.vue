<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import AuthenticationCard from '@/Components/Login/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Login/AuthenticationCardLogo.vue';
import InputField from '@/Components/Forms/InputField.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <form @submit.prevent="submit">
            <div>
                <InputField
                    v-model="form.email"
                    label="Email"
                    field="email"
                    type="email"
                    :error="form.errors.email"
                    required
                    autofocus
                    autocomplete="username"
                />
            </div>

            <div class="mt-4">
                <InputField
                    v-model="form.password"
                    label="Password"
                    field="password"
                    type="password"
                    :error="form.errors.password"
                    required
                    autocomplete="new-password"
                />
            </div>

            <div class="mt-4">
                <InputField
                    v-model="form.password_confirmation"
                    label="Confirm Password"
                    field="password_confirmation"
                    type="password"
                    :error="form.errors.password_confirmation"
                    required
                    autocomplete="new-password"
                />
            </div>

            <div class="flex items-center justify-end mt-4">
                <AppButton variant="primary" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Reset Password
                </AppButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
