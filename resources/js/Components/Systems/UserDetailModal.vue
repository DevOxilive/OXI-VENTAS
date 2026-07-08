<script setup>
import { computed } from 'vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { userDetailModalConfig } from '@/config/ModalConfigs/userDetailModalConfig'
import { usePermissionLabels } from '@/Composables/usePermissionLabels'

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close'])
const permissionSource = computed(() => props.permissions ?? [])
const { permissionLabel, moduleLabel } = usePermissionLabels(permissionSource)

const effectivePermissions = computed(() => {
    const rolePermissions = props.user?.role?.permissions || []
    const allowedPermissions = (props.user?.permissions || []).filter(
        (permission) => (permission.pivot?.mode || 'allow') === 'allow'
    )
    const deniedPermissionIds = new Set(
        (props.user?.permissions || [])
            .filter((permission) => permission.pivot?.mode === 'deny')
            .map((permission) => Number(permission.id))
    )

    return [...rolePermissions, ...allowedPermissions]
        .filter((permission, index, permissions) => {
            const permissionId = Number(permission.id)

            return (
                !deniedPermissionIds.has(permissionId) &&
                permissions.findIndex((item) => Number(item.id) === permissionId) === index
            )
        })
})

const groupedEffectivePermissions = computed(() => {
    return effectivePermissions.value.reduce((groups, permission) => {
        const permissionName = permission.name || ''
        const module = permissionName.startsWith('inventory.products.')
            ? 'inventory.products'
            : permissionName.startsWith('inventory.branches.')
                ? 'inventory.branches'
                : permissionName.startsWith('inventory.purchase-reports.')
                    ? 'inventory.purchase-reports'
                    : permissionName.split('.')[0]?.toLowerCase() || 'general'

        if (!groups[module]) {
            groups[module] = []
        }

        groups[module].push(permission)

        return groups
    }, {})
})

const showsSalesBranches = computed(() => {
    if (props.user?.role?.name === 'Ventas') {
        return true
    }

    return effectivePermissions.value.some((permission) => (permission.name || '').startsWith('sales.'))
})

function closeModal() {
    emit('close')
}
</script>

<template>
    <GlobalModal
        v-bind="userDetailModalConfig"
        @close="closeModal"
    >
        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
            <h3 class="mb-4 border-b pb-3 text-base font-bold">
                Datos del usuario
            </h3>

            <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <p>
                    <strong>ID:</strong>
                    {{ user.id }}
                </p>

                <p>
                    <strong>Nombre:</strong>
                    {{ user.name || '-' }}
                </p>

                <p>
                    <strong>Correo:</strong>
                    {{ user.email || '-' }}
                </p>

                <p>
                    <strong>Empleado ID:</strong>
                    {{ user.employee_id || '-' }}
                </p>

                <p>
                    <strong>Rol:</strong>
                    {{ user.role?.name || 'Sin rol' }}
                </p>

                <p>
                    <strong>Estado:</strong>
                    {{ user.is_active === false || user.employee?.employment_status === 'Inactivo' ? 'Inactivo' : 'Activo' }}
                </p>

                <div v-if="showsSalesBranches" class="sm:col-span-2">
                    <strong>Sucursales:</strong>

                    <div v-if="user.branches?.length" class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="branch in user.branches"
                            :key="branch.id"
                            class="inline-block rounded-full border border-green-300 bg-green-100 px-3 py-1 text-xs text-green-700"
                        >
                            {{ branch.name }}
                        </span>
                    </div>

                    <p v-else class="mt-2 text-sm text-slate-500">
                        No tiene sucursales asignadas.
                    </p>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
            <h3 class="mb-4 border-b pb-3 text-base font-bold">
                Permisos activados
            </h3>

            <div v-if="effectivePermissions.length" class="space-y-4">
                <div
                    v-for="(permissionsGroup, module) in groupedEffectivePermissions"
                    :key="module"
                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold text-slate-800">
                            {{ moduleLabel(module) }}
                        </h4>

                        <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-500">
                            {{ permissionsGroup.length }} permiso(s)
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="permission in permissionsGroup"
                            :key="permission.id"
                            class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700"
                        >
                            {{ permissionLabel(permission.name) }}
                        </span>
                    </div>
                </div>
            </div>

            <p v-else class="text-sm text-gray-500">
                Este usuario no tiene permisos activados.
            </p>
        </section>
    </GlobalModal>
</template>
