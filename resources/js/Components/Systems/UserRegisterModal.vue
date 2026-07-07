<script setup>
import { computed, ref, watch } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { getUserModalConfig } from '@/config/ModalConfigs/userModalConfig'

const props = defineProps({
    form: Object,
    errors: Object,
    roles: Array,
    branches: Array,
    groupedPermissions: Object,
    isEditing: Boolean,
    canSave: Boolean,
    requiresSalesBranches: Boolean,
    lockedPermissionIds: {
        type: Array,
        default: () => [],
    },
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
    'enable-module',
    'disable-module',
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

const activePermissionModule = ref(null)

const permissionEntries = computed(() => {
    return Object.entries(props.groupedPermissions || {})
        .filter(([, group]) => Array.isArray(group) && group.length > 0)
})

const activePermissionsGroup = computed(() => {
    return permissionEntries.value.find(([module]) => module === activePermissionModule.value) || null
})

const passwordPlaceholder = computed(() => {
    return props.isEditing
        ? 'Nueva contrasena (opcional)'
        : 'Contrasena'
})

watch(permissionEntries, (entries) => {
    if (!entries.length) {
        activePermissionModule.value = null
        return
    }

    const hasActiveModule = entries.some(([module]) => module === activePermissionModule.value)

    if (!hasActiveModule) {
        activePermissionModule.value = entries[0][0]
    }
}, { immediate: true })

function getSelectedCount(group = []) {
    return group.filter((permission) => props.form.permissions.includes(permission.id)).length
}

function selectPermissionModule(module) {
    activePermissionModule.value = module
}

function isPermissionLocked(permissionId) {
    return props.lockedPermissionIds.includes(permissionId)
}

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
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-slate-50/80 p-4 pb-24 sm:p-5 sm:pb-28 md:p-6">
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:p-6 xl:col-span-3">
                        <div class="mb-4 border-b pb-3">
                            <h3 class="text-base font-bold">
                                Cuenta de acceso
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                {{ isEditing ? 'Edita el usuario y su acceso al sistema.' : 'Crea el usuario y define su acceso al sistema.' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <InputField
                                v-model="form.name"
                                label="Usuario"
                                field="name"
                                placeholder="Nombre de usuario"
                                :error="errors.name"
                            />

                            <InputField
                                v-model="form.email"
                                label="Correo electronico"
                                field="email"
                                type="email"
                                placeholder="Correo electronico"
                                :error="errors.email"
                            />

                            <SelectField
                                v-model="form.role_id"
                                label="Rol"
                                field="role_id"
                                placeholder="Seleccionar rol"
                                :options="roles.map(role => ({ label: role.name, value: role.id }))"
                                :error="errors.role_id"
                                @change="$emit('change-role')"
                            />

                            <InputField
                                v-model="form.password"
                                label="Contrasena"
                                field="password"
                                type="password"
                                :placeholder="passwordPlaceholder"
                                :error="errors.password"
                            />

                            <InputField
                                v-model="form.password_confirmation"
                                label="Confirmar contrasena"
                                field="password_confirmation"
                                type="password"
                                placeholder="Confirmar contrasena"
                                :error="errors.password_confirmation"
                            />
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:max-h-[calc(90dvh-13rem)] md:overflow-y-auto md:pr-3 md:p-6 xl:col-span-5">
                        <h3 class="mb-4 border-b pb-3 text-base font-bold">
                            Modulos de acceso
                        </h3>

                        <div class="space-y-4">
                            <div v-if="requiresSalesBranches" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="mb-3 text-sm font-semibold text-slate-700">
                                    Sucursales permitidas para este vendedor
                                </p>

                                <div class="grid grid-cols-1 gap-2">
                                    <label
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 hover:bg-slate-50"
                                    >
                                        <input
                                            v-model="form.branch_ids"
                                            type="checkbox"
                                            :value="branch.id"
                                        >

                                        <span>{{ branch.name }}</span>
                                    </label>
                                </div>

                                <p v-if="errors.branch_ids" class="mt-2 text-xs text-red-500">
                                    {{ errors.branch_ids }}
                                </p>

                                <p v-if="form.errors.branch_ids" class="mt-2 text-xs text-red-500">
                                    {{ form.errors.branch_ids }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">
                                        Permisos
                                    </p>
                                </div>

                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ form.permissions.length }} seleccionados
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button
                                    v-for="[module, group] in permissionEntries"
                                    :key="module"
                                    type="button"
                                    class="rounded-2xl border px-3 py-3 text-left transition"
                                    :class="activePermissionModule === module
                                        ? 'border-emerald-300 bg-emerald-50 shadow-sm'
                                        : 'border-slate-200 bg-slate-50 hover:border-slate-300 hover:bg-white'"
                                    @click="selectPermissionModule(module)"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">
                                                {{ moduleLabel(module) }}
                                            </p>

                                            <p class="mt-1 text-sm text-slate-500">
                                                {{ getSelectedCount(group) }} de {{ group.length }} permisos activos
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:p-6 xl:col-span-4">
                        <div
                            v-if="activePermissionsGroup"
                            class="space-y-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        {{ moduleLabel(activePermissionsGroup[0]) }}
                                    </h3>

                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ getSelectedCount(activePermissionsGroup[1]) }} de {{ activePermissionsGroup[1].length }} permisos activos en este modulo
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100"
                                        @click="$emit('enable-module', activePermissionsGroup[0])"
                                    >
                                        Activar modulo
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
                                        @click="$emit('disable-module', activePermissionsGroup[0])"
                                    >
                                        Limpiar modulo
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                <button
                                    v-for="permission in activePermissionsGroup[1]"
                                    :key="permission.id"
                                    type="button"
                                    class="flex min-h-[72px] items-center justify-between gap-3 rounded-2xl border px-4 py-4 text-left transition"
                                    :class="form.permissions.includes(permission.id)
                                        ? 'border-emerald-300 bg-emerald-50 shadow-sm'
                                        : 'border-slate-200 bg-slate-50 hover:border-slate-300 hover:bg-white'"
                                    @click="$emit('toggle-permission', permission.id)"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-800">
                                            {{ permissionLabel(permission.name) }}
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <span
                                            v-if="isPermissionLocked(permission.id)"
                                            class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600"
                                        >
                                            Rol
                                        </span>

                                        <div
                                            class="flex h-6 w-6 items-center justify-center rounded-full border transition"
                                            :class="form.permissions.includes(permission.id)
                                                ? 'border-emerald-500 bg-emerald-500 text-white'
                                                : 'border-slate-300 bg-white text-transparent'"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415 0l-3.25-3.25a1 1 0 111.414-1.42l2.543 2.544 6.543-6.544a1 1 0 011.415 0z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex min-h-[18rem] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500"
                        >
                            Selecciona un modulo para ver y editar sus permisos.
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </GlobalModal>
</template>
