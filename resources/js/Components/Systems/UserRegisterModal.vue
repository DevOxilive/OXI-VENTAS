<script setup>
import { computed, ref, watch } from 'vue'

import FormPanel from '@/Components/Cards/FormPanel.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import SelectionGridSection from '@/Components/Forms/SelectionGridSection.vue'
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
        ? 'Nueva contraseña (opcional)'
        : 'Contraseña'
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
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-secondary/80 p-4 pb-24 sm:p-5 sm:pb-28 md:p-6">
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <FormPanel
                        title="Cuenta de acceso"
                        :description="isEditing ? 'Edita el usuario y su acceso al sistema.' : 'Crea el usuario y define su acceso al sistema.'"
                        panel-class="rounded-3xl bg-background shadow-sm sm:p-5 md:p-6 xl:col-span-3"
                    >
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
                                label="Correo electrónico"
                                field="email"
                                type="email"
                                placeholder="Correo electrónico"
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
                                label="Contraseña"
                                field="password"
                                type="password"
                                :placeholder="passwordPlaceholder"
                                :error="errors.password"
                            />

                            <InputField
                                v-model="form.password_confirmation"
                                label="Confirmar contraseña"
                                field="password_confirmation"
                                type="password"
                                placeholder="Confirmar contraseña"
                                :error="errors.password_confirmation"
                            />
                        </div>
                    </FormPanel>

                    <FormPanel
                        title="Módulos de acceso"
                        panel-class="rounded-3xl bg-background shadow-sm sm:p-5 md:max-h-[calc(90dvh-13rem)] md:overflow-y-auto md:pr-3 md:p-6 xl:col-span-5"
                        body-class="space-y-4"
                    >
                        <div class="space-y-4">
                            <div v-if="canAssignBranches" class="rounded-2xl border border-secondary bg-secondary p-4">
                                <SelectionGridSection
                                    title="Sucursales asignadas"
                                    description="Estas sucursales definen a cuales puede entrar el usuario cuando tenga permisos relacionados con sucursales, stock, auditorias o ventas."
                                    grid-class="grid grid-cols-1 gap-2"
                                >
                                    <template #default>
                                        <p v-if="!form.role_id" class="mb-3 text-xs text-accent">
                                            Primero selecciona un rol para definir si estas sucursales serán obligatorias para este usuario.
                                        </p>

                                        <p v-else-if="!requiresBranchAccess" class="mb-3 text-xs text-text opacity-70">
                                            Para el rol y permisos actuales, esta asignación queda guardada como alcance disponible, aunque todavía no sea obligatoria.
                                        </p>

                                        <SelectionCheckboxCard
                                            v-for="branch in branches"
                                            :key="branch.id"
                                            compact
                                            variant="soft"
                                            :checked="form.branch_ids.includes(branch.id)"
                                            :title="branch.name"
                                            description="Haz clic para asignar esta sucursal"
                                            @toggle="toggleAssignedBranch(branch.id)"
                                        />
                                    </template>
                                </SelectionGridSection>

                                <p v-if="requiresBranchAccess" class="mt-2 text-xs text-text opacity-70">
                                    Para la combinación actual de rol y permisos, este usuario necesita al menos una sucursal asignada.
                                </p>

                                <p v-if="errors.branch_ids" class="mt-2 text-xs text-red-500">
                                    {{ errors.branch_ids }}
                                </p>

                                <p v-if="form.errors.branch_ids" class="mt-2 text-xs text-red-500">
                                    {{ form.errors.branch_ids }}
                                </p>
                            </div>

                            <SelectionGridSection
                                title="Módulos principales"
                                grid-class="grid grid-cols-1 gap-2"
                            >
                                <template #aside>
                                    <span class="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-text opacity-80">
                                        {{ form.permissions.length }} seleccionados
                                    </span>
                                </template>

                                <button
                                    v-for="section in availableSections"
                                    :key="section.key"
                                    type="button"
                                    class="rounded-2xl border px-3 py-3 text-left transition"
                                    :class="activePermissionSection === section.key
                                        ? 'border-primary bg-background shadow-sm'
                                        : 'border-secondary bg-secondary hover:border-primary hover:bg-background'"
                                    @click="selectPermissionSection(section.key)"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-text">
                                                {{ sectionLabel(section.key) }}
                                            </p>

                                            <p class="mt-1 text-sm text-text opacity-70">
                                                {{ getSectionSelectedCount(section) }} de {{ getSectionPermissionCount(section) }} permisos activos
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            </SelectionGridSection>

                            <div
                                v-if="activeSection"
                                class="rounded-2xl border border-secondary bg-secondary p-4"
                            >
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-text">
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
                                            ? 'border-primary bg-background shadow-sm'
                                            : 'border-secondary bg-background hover:border-primary'"
                                        @click="selectPermissionModule(module.key)"
                                    >
                                        <p class="text-sm font-medium text-text">
                                            {{ moduleLabel(module.key) }}
                                        </p>

                                        <p class="mt-1 text-sm text-text opacity-70">
                                            {{ getSelectedCount(module.permissions) }} de {{ module.permissions.length }} permisos activos
                                        </p>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </FormPanel>

                    <FormPanel
                        panel-class="rounded-3xl bg-background shadow-sm sm:p-5 md:p-6 xl:col-span-4"
                    >
                        <div
                            v-if="activeModule"
                            class="space-y-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                                <div>
                                    <h3 class="text-base font-bold text-text">
                                        {{ moduleLabel(activeModule.key) }}
                                    </h3>

                                    <p class="mt-1 text-sm text-text opacity-70">
                                        {{ getSelectedCount(activeModule.permissions) }} de {{ activeModule.permissions.length }} permisos activos en este submodulo
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full border border-primary bg-secondary px-3 py-1.5 text-sm font-medium text-primary transition hover:bg-background"
                                        @click="$emit('enable-module', activeModule.key)"
                                    >
                                        Activar submodulo
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-full border border-secondary bg-background px-3 py-1.5 text-sm font-medium text-text transition hover:bg-secondary"
                                        @click="$emit('disable-module', activeModule.key)"
                                    >
                                        Limpiar submodulo
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                <SelectionCheckboxCard
                                    v-for="permission in activeModule.permissions"
                                    :key="permission.id"
                                    compact
                                    variant="soft"
                                    :checked="form.permissions.includes(permission.id)"
                                    :title="permissionLabel(permission.name)"
                                    :badge="isPermissionLocked(permission.id) ? 'Rol' : ''"
                                    @toggle="$emit('toggle-permission', permission.id)"
                                />
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex min-h-[18rem] items-center justify-center rounded-2xl border border-dashed border-secondary bg-secondary px-4 py-6 text-center text-sm text-text opacity-70"
                        >
                            Selecciona un modulo y después un submodulo para ver sus permisos.
                        </div>
                    </FormPanel>
                </div>
            </div>
        </template>
    </GlobalModal>
</template>
