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
                Create API Token
            </template>

            <template #description>
                API tokens allow third-party services to authenticate with our application on your behalf.
            </template>

            <template #form>
                <!-- Token Name -->
                <div class="col-span-6 sm:col-span-4">
                    <InputField
                        v-model="createApiTokenForm.name"
                        label="Name"
                        field="name"
                        type="text"
                        :error="createApiTokenForm.errors.name"
                        autofocus
                    />
                </div>

                <!-- Token Permissions -->
                <SelectionGridSection
                    v-if="availablePermissions.length > 0"
                    title="Permissions"
                    description="Select the actions this token will be allowed to perform."
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
                    Create
                </AppButton>
            </template>
        </FormSection>

        <div v-if="tokens.length > 0">
            <SectionBorder />

            <!-- Manage API Tokens -->
            <div class="mt-10 sm:mt-0">
                <ActionSection>
                    <template #title>
                        Manage API Tokens
                    </template>

                    <template #description>
                        You may delete any of your existing tokens if they are no longer needed.
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
                                        Last used {{ token.last_used_ago }}
                                    </div>

                                    <button
                                        v-if="availablePermissions.length > 0"
                                        class="cursor-pointer ms-6 text-sm text-gray-400 underline"
                                        @click="manageApiTokenPermissions(token)"
                                    >
                                        Permissions
                                    </button>

                                    <button class="cursor-pointer ms-6 text-sm text-red-500" @click="confirmApiTokenDeletion(token)">
                                        Delete
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
            title="API Token"
            :show-header="true"
            :show-footer="false"
            :show-save="false"
            size="lg"
            @close="displayingToken = false"
        >
            <template #content>
                <div class="space-y-4 px-5 py-5 md:px-6">
                    <p class="text-sm text-slate-600">
                        Please copy your new API token. For your security, it won't be shown again.
                    </p>

                    <div v-if="$page.props.jetstream.flash.token" class="rounded-xl bg-slate-100 px-4 py-3 font-mono text-sm text-slate-600 break-all">
                        {{ $page.props.jetstream.flash.token }}
                    </div>
                </div>
            </template>

            <template #footer="{ close }">
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 md:px-6">
                    <AppButton variant="secondary" @click="close">
                        Close
                    </AppButton>
                </div>
            </template>
        </GlobalModal>

        <GlobalModal
            v-if="managingPermissionsFor != null"
            title="API Token Permissions"
            :processing="updateApiTokenForm.processing"
            :show-header="true"
            :show-footer="false"
            :show-save="false"
            size="xl"
            @close="managingPermissionsFor = null"
        >
            <template #content>
                <SelectionGridSection
                    title="Permissions"
                    description="Adjust the abilities that will remain active for this token."
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
                        Cancel
                    </AppButton>

                    <AppButton
                        variant="primary"
                        :class="{ 'opacity-25': updateApiTokenForm.processing }"
                        :disabled="updateApiTokenForm.processing"
                        @click="updateApiToken"
                    >
                        Save
                    </AppButton>
                </div>
            </template>
        </GlobalModal>

        <GlobalModal
            v-if="apiTokenBeingDeleted != null"
            title="Delete API Token"
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
                        Are you sure you would like to delete this API token?
                    </p>
                </div>
            </template>

            <template #footer="{ close }">
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 md:px-6">
                    <AppButton variant="secondary" @click="close">
                        Cancel
                    </AppButton>

                    <AppButton
                        variant="danger"
                        :class="{ 'opacity-25': deleteApiTokenForm.processing }"
                        :disabled="deleteApiTokenForm.processing"
                        @click="deleteApiToken"
                    >
                        Delete
                    </AppButton>
                </div>
            </template>
        </GlobalModal>
    </div>
</template>
