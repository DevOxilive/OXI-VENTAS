<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import ActionSection from '@/Components/Cards/ActionSection.vue';
import InputField from '@/Components/Forms/InputField.vue';
import GlobalModal from '@/Components/Modales/GlobalModal.vue';

const props = defineProps({
    requiresConfirmation: Boolean,
});

const page = usePage();
const enabling = ref(false);
const confirming = ref(false);
const disabling = ref(false);
const qrCode = ref(null);
const setupKey = ref(null);
const recoveryCodes = ref([]);

const confirmationForm = useForm({
    code: '',
});
const passwordConfirmationForm = useForm({
    password: '',
});
const confirmingPassword = ref(false);
const pendingPasswordAction = ref(null);
const passwordInput = ref(null);

const twoFactorEnabled = computed(
    () => ! enabling.value && page.props.auth.user?.two_factor_enabled,
);

watch(twoFactorEnabled, () => {
    if (! twoFactorEnabled.value) {
        confirmationForm.reset();
        confirmationForm.clearErrors();
    }
});

const enableTwoFactorAuthentication = () => {
    enabling.value = true;

    router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: () => Promise.all([
            showQrCode(),
            showSetupKey(),
            showRecoveryCodes(),
        ]),
        onFinish: () => {
            enabling.value = false;
            confirming.value = props.requiresConfirmation;
        },
    });
};

const showQrCode = () => {
    return axios.get(route('two-factor.qr-code')).then(response => {
        qrCode.value = response.data.svg;
    });
};

const showSetupKey = () => {
    return axios.get(route('two-factor.secret-key')).then(response => {
        setupKey.value = response.data.secretKey;
    });
}

const showRecoveryCodes = () => {
    return axios.get(route('two-factor.recovery-codes')).then(response => {
        recoveryCodes.value = response.data;
    });
};

const confirmTwoFactorAuthentication = () => {
    confirmationForm.post(route('two-factor.confirm'), {
        errorBag: "confirmTwoFactorAuthentication",
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirming.value = false;
            qrCode.value = null;
            setupKey.value = null;
        },
    });
};

const regenerateRecoveryCodes = () => {
    axios
        .post(route('two-factor.recovery-codes'))
        .then(() => showRecoveryCodes());
};

const disableTwoFactorAuthentication = () => {
    disabling.value = true;

    router.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            disabling.value = false;
            confirming.value = false;
        },
    });
};

const passwordActions = {
    enable: enableTwoFactorAuthentication,
    confirm: confirmTwoFactorAuthentication,
    regenerate: regenerateRecoveryCodes,
    showRecovery: showRecoveryCodes,
    disable: disableTwoFactorAuthentication,
};

const requestPasswordConfirmation = (action) => {
    axios.get(route('password.confirmation')).then((response) => {
        if (response.data.confirmed) {
            passwordActions[action]?.();
            return;
        }

        pendingPasswordAction.value = action;
        confirmingPassword.value = true;

        nextTick(() => passwordInput.value?.focus());
    });
};

const submitPasswordConfirmation = () => {
    passwordConfirmationForm.post(route('password.confirm'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const action = pendingPasswordAction.value;
            closePasswordConfirmationModal();
            passwordActions[action]?.();
        },
        onError: () => passwordInput.value?.focus(),
    });
};

const closePasswordConfirmationModal = () => {
    confirmingPassword.value = false;
    pendingPasswordAction.value = null;
    passwordConfirmationForm.reset();
    passwordConfirmationForm.clearErrors();
};
</script>

<template>
    <ActionSection>
        <template #title>
            Two Factor Authentication
        </template>

        <template #description>
            Add additional security to your account using two factor authentication.
        </template>

        <template #content>
            <h3 v-if="twoFactorEnabled && ! confirming" class="text-lg font-medium text-gray-900">
                You have enabled two factor authentication.
            </h3>

            <h3 v-else-if="twoFactorEnabled && confirming" class="text-lg font-medium text-gray-900">
                Finish enabling two factor authentication.
            </h3>

            <h3 v-else class="text-lg font-medium text-gray-900">
                You have not enabled two factor authentication.
            </h3>

            <div class="mt-3 max-w-xl text-sm text-gray-600">
                <p>
                    When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone's Google Authenticator application.
                </p>
            </div>

            <div v-if="twoFactorEnabled">
                <div v-if="qrCode">
                    <div class="mt-4 max-w-xl text-sm text-gray-600">
                        <p v-if="confirming" class="font-semibold">
                            To finish enabling two factor authentication, scan the following QR code using your phone's authenticator application or enter the setup key and provide the generated OTP code.
                        </p>

                        <p v-else>
                            Two factor authentication is now enabled. Scan the following QR code using your phone's authenticator application or enter the setup key.
                        </p>
                    </div>

                    <div class="mt-4 p-2 inline-block bg-white" v-html="qrCode" />

                    <div v-if="setupKey" class="mt-4 max-w-xl text-sm text-gray-600">
                        <p class="font-semibold">
                            Setup Key: <span v-html="setupKey"></span>
                        </p>
                    </div>

                    <div v-if="confirming" class="mt-4">
                        <InputField
                            v-model="confirmationForm.code"
                            label="Code"
                            field="code"
                            type="text"
                            name="code"
                            class="w-full md:w-1/2"
                            :error="confirmationForm.errors.code"
                            inputmode="numeric"
                            autofocus
                            autocomplete="one-time-code"
                            @keyup.enter="confirmTwoFactorAuthentication"
                        />
                    </div>
                </div>

                <div v-if="recoveryCodes.length > 0 && ! confirming">
                    <div class="mt-4 max-w-xl text-sm text-gray-600">
                        <p class="font-semibold">
                            Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost.
                        </p>
                    </div>

                    <div class="grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-gray-100 rounded-lg">
                        <div v-for="code in recoveryCodes" :key="code">
                            {{ code }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <div v-if="! twoFactorEnabled">
                    <AppButton variant="primary" type="button" :class="{ 'opacity-25': enabling }" :disabled="enabling" @click="requestPasswordConfirmation('enable')">
                        Enable
                    </AppButton>
                </div>

                <div v-else>
                    <AppButton
                        v-if="confirming"
                        variant="primary"
                        type="button"
                        class="me-3"
                        :class="{ 'opacity-25': enabling || confirmationForm.processing }"
                        :disabled="enabling || confirmationForm.processing"
                        @click="requestPasswordConfirmation('confirm')"
                    >
                        Confirm
                    </AppButton>

                    <AppButton
                        v-if="recoveryCodes.length > 0 && ! confirming"
                        variant="secondary"
                        class="me-3"
                        @click="requestPasswordConfirmation('regenerate')"
                    >
                        Regenerate Recovery Codes
                    </AppButton>

                    <AppButton
                        v-if="recoveryCodes.length === 0 && ! confirming"
                        variant="secondary"
                        class="me-3"
                        @click="requestPasswordConfirmation('showRecovery')"
                    >
                        Show Recovery Codes
                    </AppButton>

                    <AppButton
                        v-if="confirming"
                        variant="secondary"
                        :class="{ 'opacity-25': disabling }"
                        :disabled="disabling"
                        @click="requestPasswordConfirmation('disable')"
                    >
                        Cancel
                    </AppButton>

                    <AppButton
                        v-if="! confirming"
                        variant="danger"
                        :class="{ 'opacity-25': disabling }"
                        :disabled="disabling"
                        @click="requestPasswordConfirmation('disable')"
                    >
                        Disable
                    </AppButton>
                </div>
            </div>

            <GlobalModal
                v-if="confirmingPassword"
                title="Confirm Password"
                :processing="passwordConfirmationForm.processing"
                :show-header="true"
                :show-footer="false"
                :show-save="false"
                size="lg"
                @close="closePasswordConfirmationModal"
            >
                <template #content>
                    <div class="space-y-4 px-5 py-5 md:px-6">
                        <p class="text-sm text-slate-600">
                            For your security, please confirm your password to continue.
                        </p>

                        <div>
                            <InputField
                                ref="passwordInput"
                                v-model="passwordConfirmationForm.password"
                                label="Password"
                                field="password"
                                type="password"
                                placeholder="Password"
                                :error="passwordConfirmationForm.errors.password"
                                autocomplete="current-password"
                                @keyup.enter="submitPasswordConfirmation"
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
                            variant="primary"
                            :class="{ 'opacity-25': passwordConfirmationForm.processing }"
                            :disabled="passwordConfirmationForm.processing"
                            @click="submitPasswordConfirmation"
                        >
                            Confirm
                        </AppButton>
                    </div>
                </template>
            </GlobalModal>
        </template>
    </ActionSection>
</template>
