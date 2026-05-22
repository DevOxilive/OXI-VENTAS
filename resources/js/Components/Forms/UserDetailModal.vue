<script setup>
import { onMounted, onBeforeUnmount } from 'vue'

import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'

defineProps({
    usuario: Object,
})

const emit = defineEmits(['close'])

function cerrar() {
    emit('close')
}

function handleEsc(e) {
    if (e.key === 'Escape') cerrar()
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="cerrar"></div>

        <div
            class="relative bg-white w-full h-[100dvh] md:h-[90vh] md:w-[92%] md:max-w-4xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader :title="editando ? 'Actualizar usuario' : 'Registrar usuario'"
                subtitle="Configuración de acceso y permisos" :total-errors="totalErrores" :mode="modo"
                @close="cerrar" />

            <GeneralModalContent :columns="1">
                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Datos del usuario
                    </h3>

                    <div v-if="usuario" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <p><strong>ID:</strong> {{ usuario.id }}</p>
                        <p><strong>Nombre:</strong> {{ usuario.name || '—' }}</p>
                        <p><strong>Correo:</strong> {{ usuario.email || '—' }}</p>
                        <p><strong>Empleado ID:</strong> {{ usuario.employee_id || '—' }}</p>
                        <p><strong>Rol:</strong> {{ usuario.role?.name || 'Sin rol' }}</p>

                        <div v-if="usuario.role?.name === 'Ventas'" class="sm:col-span-2">
                            <strong>Sucursales:</strong>

                            <div class="flex flex-wrap gap-2 mt-2">
                                <span v-for="branch in usuario.branches" :key="branch.id"
                                    class="inline-block px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs border border-green-300">
                                    {{ branch.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Permisos activados
                    </h3>

                    <div v-if="usuario?.permissions?.length" class="flex flex-wrap gap-2">
                        <span v-for="permiso in usuario.permissions" :key="permiso.id"
                            class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs border border-green-300">
                            {{ permiso.name }}
                        </span>
                    </div>

                    <p v-else class="text-sm text-gray-500">
                        Este usuario no tiene permisos activados.
                    </p>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing" :save-button-text="textoBotonGuardar" :mode="modo"
                @save="$emit('guardar')" @close="cerrar" />
        </div>
    </div>
</template>