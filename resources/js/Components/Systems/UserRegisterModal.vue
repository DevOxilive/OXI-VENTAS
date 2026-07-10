<script setup>
import { computed, ref, watch } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { getUserModalConfig } from '@/config/ModalConfigs/userModalConfig'

const props = defineProps({
    form: Object,
    errors: Object,
    roles: Array,
    branches: Array,
    permissionSections: {
        type: Array,
        default: () => [],
    },
    isEditing: Boolean,
    canSave: Boolean,
    requiresBranchAccess: Boolean,
    canAssignBranches: Boolean,
    lockedPermissionIds: {
        type: Array,
        default: () => [],
    },
    moduleLabel: {
        type: Function,
        required: true,
    },
    sectionLabel: {
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

const activePermissionSection = ref(null)
const activePermissionModule = ref(null)

const availableSections = computed(() => {
    return props.permissionSections || []
})

const activeSection = computed(() => {
    return availableSections.value.find((section) => section.key === activePermissionSection.value) || null
})

const activeModule = computed(() => {
    return activeSection.value?.modules?.find((module) => module.key === activePermissionModule.value) || null
})

const passwordPlaceholder = computed(() => {
    return props.isEditing
        ? 'Nueva contrasena (opcional)'
        : 'Contrasena'
})

watch(availableSections, (sections) => {
    if (!sections.length) {
        activePermissionSection.value = null
        activePermissionModule.value = null
        return
    }

    const hasActiveSection = sections.some((section) => section.key === activePermissionSection.value)

    if (!hasActiveSection) {
        activePermissionSection.value = sections[0].key
    }
}, { immediate: true })

watch(activeSection, (section) => {
    if (!section?.modules?.length) {
        activePermissionModule.value = null
        return
    }

    const hasActiveModule = section.modules.some((module) => module.key === activePermissionModule.value)

    if (!hasActiveModule) {
        activePermissionModule.value = section.modules[0].key
    }
}, { immediate: true })

function getSelectedCount(permissions = []) {
    return permissions.filter((permission) => props.form.permissions.includes(permission.id)).length
}

function getSectionSelectedCount(section) {
    return (section.modules || []).reduce((total, module) => {
        return total + getSelectedCount(module.permissions || [])
    }, 0)
}

function getSectionPermissionCount(section) {
    return (section.modules || []).reduce((total, module) => {
        return total + (module.permissions?.length || 0)
    }, 0)
}

function selectPermissionSection(sectionKey) {
    activePermissionSection.value = sectionKey
}

function selectPermissionModule(moduleKey) {
    activePermissionModule.value = moduleKey
}

function isPermissionLocked(permissionId) {
    return props.lockedPermissionIds.includes(permissionId)
}

function toggleAssignedBranch(branchId) {
    if (props.form.branch_ids.includes(branchId)) {
        props.form.branch_ids = props.form.branch_ids.filter((id) => id !== branchId)
        return
    }

    props.form.branch_ids = [...props.form.branch_ids, branchId]
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
                                <div v-if="canAssignBranches" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="mb-3 text-sm font-semibold text-slate-700">
                                        Sucursales asignadas
                                    </p>

                                    <p class="mb-3 text-xs text-slate-500">
                                        Estas sucursales definen a cuales puede entrar el usuario cuando tenga permisos relacionados con sucursales, stock, auditorias o ventas.
                                    </p>

                                    <p v-if="!form.role_id" class="mb-3 text-xs text-amber-600">
                                        Primero selecciona un rol para definir si estas sucursales seran obligatorias para este usuario.
                                    </p>

                                    <p v-else-if="!requiresBranchAccess" class="mb-3 text-xs text-slate-500">
                                        Para el rol y permisos actuales, esta asignacion queda guardada como alcance disponible, aunque todavia no sea obligatoria.
                                    </p>

                                <div class="grid grid-cols-1 gap-2">
                                    <SelectionCheckboxCard
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        compact
                                        :checked="form.branch_ids.includes(branch.id)"
                                        :title="branch.name"
                                        description="Haz clic para asignar esta sucursal"
                                        @toggle="toggleAssignedBranch(branch.id)"
                                    />
                                </div>

                                <p v-if="requiresBranchAccess" class="mt-2 text-xs text-slate-500">
                                    Para la combinacion actual de rol y permisos, este usuario necesita al menos una sucursal asignada.
                                </p>

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
                                        Modulos principales
                                    </p>
                                </div>

                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ form.permissions.length }} seleccionados
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                <button
                                    v-for="section in availableSections"
                                    :key="section.key"
                                    type="button"
                                    class="rounded-2xl border px-3 py-3 text-left transition"
                                    :class="activePermissionSection === section.key
                                        ? 'border-emerald-300 bg-emerald-50 shadow-sm'
                                        : 'border-slate-200 bg-slate-50 hover:border-slate-300 hover:bg-white'"
                                    @click="selectPermissionSection(section.key)"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">
                                                {{ sectionLabel(section.key) }}
                                            </p>

                                            <p class="mt-1 text-sm text-slate-500">
                                                {{ getSectionSelectedCount(section) }} de {{ getSectionPermissionCount(section) }} permisos activos
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            </div>

                            <div
                                v-if="activeSection"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-slate-700">
                                        Submodulos de {{ sectionLabel(activeSection.key) }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <button
                                        v-for="module in activeSection.modules"
                                        :key="module.key"
                                        type="button"
                                        class="rounded-2xl border px-3 py-3 text-left transition"
                                        :class="activePermissionModule === module.key
                                            ? 'border-emerald-300 bg-white shadow-sm'
                                            : 'border-slate-200 bg-white hover:border-slate-300'"
                                        @click="selectPermissionModule(module.key)"
                                    >
                                        <p class="text-sm font-medium text-slate-800">
                                            {{ moduleLabel(module.key) }}
                                        </p>

                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ getSelectedCount(module.permissions) }} de {{ module.permissions.length }} permisos activos
                                        </p>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:p-6 xl:col-span-4">
                        <div
                            v-if="activeModule"
                            class="space-y-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        {{ moduleLabel(activeModule.key) }}
                                    </h3>

                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ getSelectedCount(activeModule.permissions) }} de {{ activeModule.permissions.length }} permisos activos en este submodulo
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100"
                                        @click="$emit('enable-module', activeModule.key)"
                                    >
                                        Activar submodulo
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100"
                                        @click="$emit('disable-module', activeModule.key)"
                                    >
                                        Limpiar submodulo
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                <button
                                    v-for="permission in activeModule.permissions"
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
                            Selecciona un modulo y despues un submodulo para ver sus permisos.
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </GlobalModal>
</template>
