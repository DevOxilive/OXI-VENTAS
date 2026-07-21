<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import AuthenticationCard from '@/Components/Login/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Login/AuthenticationCardLogo.vue';
import { t } from '@/i18n/es';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="t('auth.verifyEmail')" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-4 text-sm text-gray-600">
            {{ t('auth.verifyEmailHelp') }}
        </div>

        <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-green-600">
            {{ t('auth.verifyEmailSent') }}
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <AppButton variant="primary" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ t('auth.resendVerification') }}
                </AppButton>

                <div>
                    <Link
                        :href="route('profile.show')"
                        class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Editar perfil</Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ms-2"
                    >
                        {{ t('auth.logout') }}
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>
