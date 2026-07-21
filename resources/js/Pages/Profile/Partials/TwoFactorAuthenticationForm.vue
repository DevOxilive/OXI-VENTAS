<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import ActionSection from '@/Components/Cards/ActionSection.vue';
import InputField from '@/Components/Forms/InputField.vue';
import GlobalModal from '@/Components/Modales/GlobalModal.vue';
import { t } from '@/i18n/es';

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
            {{ t('profile.twoFactor.title') }}
        </template>

        <template #description>
            {{ t('profile.twoFactor.description') }}
        </template>

        <template #content>
            <h3 v-if="twoFactorEnabled && ! confirming" class="text-lg font-medium text-gray-900">
                {{ t('profile.twoFactor.enabled') }}
            </h3>

            <h3 v-else-if="twoFactorEnabled && confirming" class="text-lg font-medium text-gray-900">
                {{ t('profile.twoFactor.finishing') }}
            </h3>

            <h3 v-else class="text-lg font-medium text-gray-900">
                {{ t('profile.twoFactor.disabled') }}
            </h3>

            <div class="mt-3 max-w-xl text-sm text-gray-600">
                <p>
                    {{ t('profile.twoFactor.explanation') }}
                </p>
            </div>

            <div v-if="twoFactorEnabled">
                <div v-if="qrCode">
                    <div class="mt-4 max-w-xl text-sm text-gray-600">
                        <p v-if="confirming" class="font-semibold">
                            {{ t('profile.twoFactor.scanConfirm') }}
                        </p>

                        <p v-else>
                            {{ t('profile.twoFactor.scan') }}
                        </p>
                    </div>

                    <div class="mt-4 p-2 inline-block bg-white" v-html="qrCode" />

                    <div v-if="setupKey" class="mt-4 max-w-xl text-sm text-gray-600">
                        <p class="font-semibold">
                            {{ t('profile.twoFactor.setupKey') }} <span v-html="setupKey"></span>
                        </p>
                    </div>

                    <div v-if="confirming" class="mt-4">
                        <InputField
                            v-model="confirmationForm.code"
                            :label="t('profile.twoFactor.code')"
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
                            {{ t('profile.twoFactor.recoveryHelp') }}
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
                        {{ t('profile.twoFactor.enable') }}
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
                        {{ t('common.confirm') }}
                    </AppButton>

                    <AppButton
                        v-if="recoveryCodes.length > 0 && ! confirming"
                        variant="secondary"
                        class="me-3"
                        @click="requestPasswordConfirmation('regenerate')"
                    >
                        {{ t('profile.twoFactor.regenerate') }}
                    </AppButton>

                    <AppButton
                        v-if="recoveryCodes.length === 0 && ! confirming"
                        variant="secondary"
                        class="me-3"
                        @click="requestPasswordConfirmation('showRecovery')"
                    >
                        {{ t('profile.twoFactor.show') }}
                    </AppButton>

                    <AppButton
                        v-if="confirming"
                        variant="secondary"
                        :class="{ 'opacity-25': disabling }"
                        :disabled="disabling"
                        @click="requestPasswordConfirmation('disable')"
                    >
                        {{ t('common.cancel') }}
                    </AppButton>

                    <AppButton
                        v-if="! confirming"
                        variant="danger"
                        :class="{ 'opacity-25': disabling }"
                        :disabled="disabling"
                        @click="requestPasswordConfirmation('disable')"
                    >
                        {{ t('profile.twoFactor.disable') }}
                    </AppButton>
                </div>
            </div>

            <GlobalModal
                v-if="confirmingPassword"
                :title="t('profile.twoFactor.confirmPasswordTitle')"
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
                            {{ t('profile.twoFactor.confirmPasswordHelp') }}
                        </p>

                        <div>
                            <InputField
                                ref="passwordInput"
                                v-model="passwordConfirmationForm.password"
                                :label="t('common.password')"
                                field="password"
                                type="password"
                                :placeholder="t('common.password')"
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
                            {{ t('common.cancel') }}
                        </AppButton>

                        <AppButton
                            variant="primary"
                            :class="{ 'opacity-25': passwordConfirmationForm.processing }"
                            :disabled="passwordConfirmationForm.processing"
                            @click="submitPasswordConfirmation"
                        >
                            {{ t('common.confirm') }}
                        </AppButton>
                    </div>
                </template>
            </GlobalModal>
        </template>
    </ActionSection>
</template>
