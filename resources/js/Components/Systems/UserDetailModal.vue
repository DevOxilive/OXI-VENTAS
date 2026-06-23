<script setup>
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { userDetailModalConfig } from '@/config/ModalConfigs/userDetailModalConfig'

defineProps({
    user: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close'])

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

                <div v-if="user.role?.name === 'Ventas'" class="sm:col-span-2">
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

            <div v-if="user.permissions?.length" class="flex flex-wrap gap-2">
                <span
                    v-for="permission in user.permissions"
                    :key="permission.id"
                    class="rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs text-slate-700"
                >
                    {{ permission.name }}
                </span>
            </div>

            <p v-else class="text-sm text-gray-500">
                Este usuario no tiene permisos activados.
            </p>
        </section>
    </GlobalModal>
</template>
