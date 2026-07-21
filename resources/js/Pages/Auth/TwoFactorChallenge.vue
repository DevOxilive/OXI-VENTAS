<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import AuthenticationCard from '@/Components/Login/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/Login/AuthenticationCardLogo.vue';
import InputField from '@/Components/Forms/InputField.vue';
import { t } from '@/i18n/es';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post(route('two-factor.login'));
};
</script>

<template>
    <Head :title="t('auth.twoFactorTitle')" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="mb-4 text-sm text-gray-600">
            <template v-if="! recovery">
                {{ t('auth.twoFactorCodeHelp') }}
            </template>

            <template v-else>
                {{ t('auth.twoFactorRecoveryHelp') }}
            </template>
        </div>

        <form @submit.prevent="submit">
            <div v-if="! recovery">
                <InputField
                    ref="codeInput"
                    v-model="form.code"
                    :label="t('profile.twoFactor.code')"
                    field="code"
                    type="text"
                    inputmode="numeric"
                    :error="form.errors.code"
                    autofocus
                    autocomplete="one-time-code"
                />
            </div>

            <div v-else>
                <InputField
                    ref="recoveryCodeInput"
                    v-model="form.recovery_code"
                    :label="t('auth.recoveryCode')"
                    field="recovery_code"
                    type="text"
                    :error="form.errors.recovery_code"
                    autocomplete="one-time-code"
                />
            </div>

            <div class="flex items-center justify-end mt-4">
                <button type="button" class="text-sm text-gray-600 hover:text-gray-900 underline cursor-pointer" @click.prevent="toggleRecovery">
                    <template v-if="! recovery">
                        {{ t('auth.useRecoveryCode') }}
                    </template>

                    <template v-else>
                        {{ t('auth.useAuthenticationCode') }}
                    </template>
                </button>

                <AppButton variant="primary" class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ t('auth.login') }}
                </AppButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
