<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getUserModalConfig } from '@/config/ModalConfigs/userModalConfig'

const props = defineProps({
    form: Object,
    errors: Object,
    roles: Array,
    branches: Array,
    groupedPermissions: Object,
    isEditing: Boolean,
    canSave: Boolean,
    isSalesRole: Boolean,
    moduleLabel: {
        type: Function,
        required: true,
    },
    permissionLabel: {
        type: Function,
        required: true,
    },
})

const emit = defineEmits([
    'close',
    'save',
    'toggle-permission',
    'change-role',
])

const totalErrors = computed(() => {
    return Object.keys(props.errors || {}).length
})

const modalConfig = computed(() => getUserModalConfig({
    isEditing: props.isEditing,
    canSave: props.canSave,
    totalErrors: totalErrors.value,
    processing: Boolean(props.form?.processing),
}))

function closeModal() {
    emit('close')
}
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="$emit('save')"
        @close="closeModal"
    >
        <template #content>
            <div class="flex-1 overflow-hidden p-4 sm:p-5 md:p-6">
                <div class="grid h-full grid-cols-1 gap-6 overflow-hidden md:grid-cols-2">
                    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
                        <h3 class="mb-4 border-b pb-3 text-base font-bold">
                            Datos de usuario
                        </h3>

                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <input
                                    v-model="form.name"
                                    maxlength="50"
                                    minlength="1"
                                    placeholder="Nombre completo"
                                    class="w-full rounded-xl border px-4 py-3"
                                >

                                <p v-if="errors.name" class="mt-1 text-xs text-red-500">
                                    {{ errors.name }}
                                </p>
                            </div>

                            <div>
                                <input
                                    v-model="form.email"
                                    placeholder="Correo electronico"
                                    class="w-full rounded-xl border bg-gray-100 px-4 py-3"
                                >

                                <p v-if="errors.email" class="mt-1 text-xs text-red-500">
                                    {{ errors.email }}
                                </p>
                            </div>

                            <div>
                                <select
                                    v-model="form.role_id"
                                    class="w-full rounded-xl border bg-white px-4 py-3"
                                    @change="$emit('change-role')"
                                >
                                    <option value="">
                                        Seleccionar rol
                                    </option>

                                    <option
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="role.id"
                                    >
                                        {{ role.name }}
                                    </option>
                                </select>

                                <p v-if="errors.role_id" class="mt-1 text-xs text-red-500">
                                    {{ errors.role_id }}
                                </p>
                            </div>

                            <div>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    maxlength="15"
                                    minlength="7"
                                    placeholder="Contrasena"
                                    class="w-full rounded-xl border px-4 py-3"
                                >

                                <p v-if="errors.password" class="mt-1 text-xs text-red-500">
                                    {{ errors.password }}
                                </p>
                            </div>

                            <div>
                                <input
                                    v-model="form.password_confirmation"
                                    type="password"
                                    maxlength="15"
                                    minlength="7"
                                    placeholder="Confirmar contrasena"
                                    class="w-full rounded-xl border px-4 py-3"
                                >

                                <p v-if="errors.password_confirmation" class="mt-1 text-xs text-red-500">
                                    {{ errors.password_confirmation }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="h-full overflow-y-auto rounded-3xl border border-slate-200 bg-white p-4 pr-3 shadow-sm sm:p-5 md:p-6">
                        <h3 class="mb-4 border-b pb-3 text-base font-bold">
                            Accesos
                        </h3>

                        <div v-if="isSalesRole" class="mb-6">
                            <p class="mb-3 text-sm font-semibold text-slate-700">
                                Sucursales permitidas para este vendedor
                            </p>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <label
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-3 hover:bg-slate-50"
                                >
                                    <input
                                        v-model="form.branch_ids"
                                        type="checkbox"
                                        :value="branch.id"
                                    >

                                    <span>{{ branch.name }}</span>
                                </label>
                            </div>

                            <p v-if="errors.branch_ids" class="mt-1 text-xs text-red-500">
                                {{ errors.branch_ids }}
                            </p>

                            <p v-if="form.errors.branch_ids" class="mt-1 text-xs text-red-500">
                                {{ form.errors.branch_ids }}
                            </p>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-700">
                                    Permisos
                                </p>

                                <span class="text-xs text-gray-500">
                                    {{ form.permissions.length }} seleccionados
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div
                                    v-for="(group, module) in groupedPermissions"
                                    :key="module"
                                    class="rounded-2xl border bg-gray-50 p-4"
                                >
                                    <div class="mb-3 flex items-center justify-between">
                                        <h4 class="font-semibold capitalize text-slate-700">
                                            {{ moduleLabel(module) }}
                                        </h4>

                                        <span class="text-xs text-gray-500">
                                            {{ group.filter(permission => form.permissions.includes(permission.id)).length }}
                                            /
                                            {{ group.length }}
                                        </span>
                                    </div>

                                    <div class="space-y-2">
                                        <div
                                            v-for="permission in group"
                                            :key="permission.id"
                                            class="flex items-center justify-between rounded-xl border bg-white px-3 py-2"
                                        >
                                            <span class="text-sm">
                                                {{ permissionLabel(permission.name) }}
                                            </span>

                                            <div
                                                class="flex h-5 w-10 cursor-pointer items-center rounded-full p-1 transition"
                                                :class="form.permissions.includes(permission.id)
                                                    ? 'bg-green-500'
                                                    : 'bg-gray-300'"
                                                @click="$emit('toggle-permission', permission.id)"
                                            >
                                                <div
                                                    class="h-4 w-4 rounded-full bg-white shadow transition"
                                                    :class="form.permissions.includes(permission.id)
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0'"
                                                />
                                            </div>
                                        </div>

                                        <p v-if="!group.length" class="py-2 text-center text-xs text-gray-400">
                                            Sin permisos registrados.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </GlobalModal>
</template>
