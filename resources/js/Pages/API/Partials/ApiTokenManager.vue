<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '@/Components/Buttons/AppButton.vue';
import ActionSection from '@/Components/Cards/ActionSection.vue';
import FormSection from '@/Components/Cards/FormSection.vue';
import InputField from '@/Components/Forms/InputField.vue';
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue';
import SelectionGridSection from '@/Components/Forms/SelectionGridSection.vue';
import SectionBorder from '@/Components/Layout/SectionBorder.vue';
import GlobalModal from '@/Components/Modales/GlobalModal.vue';
import { SuccessAlert } from '@/Components/Modales/UniversalActionModal';
import { t } from '@/i18n/es';

const props = defineProps({
    tokens: Array,
    availablePermissions: Array,
    defaultPermissions: Array,
});

const createApiTokenForm = useForm({
    name: '',
    permissions: props.defaultPermissions,
});

const updateApiTokenForm = useForm({
    permissions: [],
});

const deleteApiTokenForm = useForm({});

const displayingToken = ref(false);
const managingPermissionsFor = ref(null);
const apiTokenBeingDeleted = ref(null);

const createApiToken = () => {
    createApiTokenForm.post(route('api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => {
            displayingToken.value = true;
            createApiTokenForm.reset();
            SuccessAlert({
                title: 'Token creado',
                message: 'El token API se creó correctamente.',
            });
        },
    });
};

const manageApiTokenPermissions = (token) => {
    updateApiTokenForm.permissions = token.abilities;
    managingPermissionsFor.value = token;
};

const updateApiToken = () => {
    updateApiTokenForm.put(route('api-tokens.update', managingPermissionsFor.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            managingPermissionsFor.value = null;
            SuccessAlert({
                title: 'Permisos actualizados',
                message: 'Los permisos del token se actualizaron correctamente.',
            });
        },
    });
};

const confirmApiTokenDeletion = (token) => {
    apiTokenBeingDeleted.value = token;
};

const deleteApiToken = () => {
    deleteApiTokenForm.delete(route('api-tokens.destroy', apiTokenBeingDeleted.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            apiTokenBeingDeleted.value = null;
            SuccessAlert({
                title: 'Token eliminado',
                message: 'El token API se eliminó correctamente.',
            });
        },
    });
};

const toggleCreatePermission = (permission) => {
    if (createApiTokenForm.permissions.includes(permission)) {
        createApiTokenForm.permissions = createApiTokenForm.permissions.filter((item) => item !== permission);
        return;
    }

    createApiTokenForm.permissions = [...createApiTokenForm.permissions, permission];
};

const toggleUpdatePermission = (permission) => {
    if (updateApiTokenForm.permissions.includes(permission)) {
        updateApiTokenForm.permissions = updateApiTokenForm.permissions.filter((item) => item !== permission);
        return;
    }

    updateApiTokenForm.permissions = [...updateApiTokenForm.permissions, permission];
};
</script>

<template>
    <div>
        <!-- Generate API Token -->
        <FormSection @submitted="createApiToken">
            <template #title>
                {{ t('api.create.title') }}
            </template>

            <template #description>
                {{ t('api.create.description') }}
            </template>

            <template #form>
                <!-- Token Name -->
                <div class="col-span-6 sm:col-span-4">
                    <InputField
                        v-model="createApiTokenForm.name"
                        :label="t('common.name')"
                        field="name"
                        type="text"
                        :error="createApiTokenForm.errors.name"
                        autofocus
                    />
                </div>

                <!-- Permisos del token -->
                <SelectionGridSection
                    v-if="availablePermissions.length > 0"
                    :title="t('api.permissions')"
                    :description="t('api.permissions.select')"
                    wrapper-class="col-span-6"
                    grid-class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <template #default>
                        <SelectionCheckboxCard
                            v-for="permission in availablePermissions"
                            :key="permission"
                            compact
                            :checked="createApiTokenForm.permissions.includes(permission)"
                            :title="permission"
                            @toggle="toggleCreatePermission(permission)"
                        />
                    </template>
                </SelectionGridSection>
            </template>

            <template #actions>
                <AppButton variant="primary" :class="{ 'opacity-25': createApiTokenForm.processing }" :disabled="createApiTokenForm.processing">
                    {{ t('common.create') }}
                </AppButton>
            </template>
        </FormSection>

        <div v-if="tokens.length > 0">
            <SectionBorder />

    <!-- Administrar tokens de API -->
            <div class="mt-10 sm:mt-0">
                <ActionSection>
                    <template #title>
                        {{ t('api.manage.title') }}
                    </template>

                    <template #description>
                        {{ t('api.manage.description') }}
                    </template>

                    <!-- API Token List -->
                    <template #content>
                        <div class="space-y-6">
                            <div v-for="token in tokens" :key="token.id" class="flex items-center justify-between">
                                <div class="break-all">
                                    {{ token.name }}
                                </div>

                                <div class="flex items-center ms-2">
                                    <div v-if="token.last_used_ago" class="text-sm text-gray-400">
                                        {{ t('api.lastUsed') }}: {{ token.last_used_ago }}
                                    </div>

                                    <button
                                        v-if="availablePermissions.length > 0"
                                        class="cursor-pointer ms-6 text-sm text-gray-400 underline"
                                        @click="manageApiTokenPermissions(token)"
                                    >
                                        {{ t('api.permissions') }}
                                    </button>

                                    <button class="cursor-pointer ms-6 text-sm text-red-500" @click="confirmApiTokenDeletion(token)">
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </ActionSection>
            </div>
        </div>

        <GlobalModal
            v-if="displayingToken"
            :title="t('api.token')"
            :show-header="true"
            :show-footer="false"
            :show-save="false"
            size="lg"
            @close="displayingToken = false"
        >
            <template #content>
                <div class="space-y-4 px-5 py-5 md:px-6">
                    <p class="text-sm text-slate-600">
                        {{ t('api.copy') }}
                    </p>

                    <div v-if="$page.props.jetstream.flash.token" class="rounded-xl bg-slate-100 px-4 py-3 font-mono text-sm text-slate-600 break-all">
                        {{ $page.props.jetstream.flash.token }}
                    </div>
                </div>
            </template>

            <template #footer="{ close }">
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 md:px-6">
                    <AppButton variant="secondary" @click="close">
                        {{ t('common.close') }}
                    </AppButton>
                </div>
            </template>
        </GlobalModal>

        <GlobalModal
            v-if="managingPermissionsFor != null"
            :title="t('api.permissions.title')"
            :processing="updateApiTokenForm.processing"
            :show-header="true"
            :show-footer="false"
            :show-save="false"
            size="xl"
            @close="managingPermissionsFor = null"
        >
            <template #content>
                <SelectionGridSection
                    :title="t('api.permissions')"
                    :description="t('api.permissions.adjust')"
                    wrapper-class="px-5 py-5 md:px-6"
                    grid-class="grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <SelectionCheckboxCard
                        v-for="permission in availablePermissions"
                        :key="permission"
                        compact
                        :checked="updateApiTokenForm.permissions.includes(permission)"
                        :title="permission"
                        @toggle="toggleUpdatePermission(permission)"
                    />
                </SelectionGridSection>
            </template>

            <template #footer="{ close }">
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 md:px-6">
                    <AppButton variant="secondary" @click="close">
                        {{ t('common.cancel') }}
                    </AppButton>

                    <AppButton
                        variant="primary"
                        :class="{ 'opacity-25': updateApiTokenForm.processing }"
                        :disabled="updateApiTokenForm.processing"
                        @click="updateApiToken"
                    >
                        {{ t('common.save') }}
                    </AppButton>
                </div>
            </template>
        </GlobalModal>

        <GlobalModal
            v-if="apiTokenBeingDeleted != null"
            :title="t('api.delete.title')"
            :processing="deleteApiTokenForm.processing"
            :show-header="true"
            :show-footer="false"
            :show-save="false"
            size="lg"
            @close="apiTokenBeingDeleted = null"
        >
            <template #content>
                <div class="px-5 py-5 md:px-6">
                    <p class="text-sm text-slate-600">
                        {{ t('api.delete.confirm') }}
                    </p>
                </div>
            </template>

            <template #footer="{ close }">
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 md:px-6">
                    <AppButton variant="secondary" @click="close">
                        {{ t('common.cancel') }}
                    </AppButton>

                    <AppButton
                        variant="danger"
                        :class="{ 'opacity-25': deleteApiTokenForm.processing }"
                        :disabled="deleteApiTokenForm.processing"
                        @click="deleteApiToken"
                    >
                        {{ t('common.delete') }}
                    </AppButton>
                </div>
            </template>
        </GlobalModal>
    </div>
</template>
