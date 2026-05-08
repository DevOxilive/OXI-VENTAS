<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue'
import { useEmpleadoForm } from '@/Composables/Recursos-humanos/useEmpleadoForm'

import EmpleadoModalHeader from '@/Components/Formularios/EmpleadoModalHeader.vue'
import EmpleadoModalFooter from '@/Components/Formularios/EmpleadoModalFooter.vue'
import ModalContent from '@/Components/Formularios/ModalContent.vue'
import EmpleadoDatos from '@/Components/Formularios/EmpleadoDatos.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    modo: String,
    empleadoEditar: Object
})

const {
    empleado,
    frontendErrors,
    departamentos,
    resumenErrores,
    validarCampo,
    guardar,
    cargarDatosEdicion
} = useEmpleadoForm(props, emit)

const textoBotonGuardar = computed(() => {
    if (empleado.processing) return 'Procesando...'

    return props.modo === 'crear'
        ? 'Guardar empleado'
        : 'Actualizar empleado'
})

const totalErrores = computed(() => resumenErrores.value.length)

function cerrar() {
    emit('close')
}

function handleEsc(e) {
    if (e.key === 'Escape') cerrar()
}

onMounted(() => {
    cargarDatosEdicion()
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
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <EmpleadoModalHeader :modo="modo" :totalErrores="totalErrores" @close="cerrar" />

            <ModalContent :columns="1">
                <EmpleadoDatos :empleado="empleado" :frontendErrors="frontendErrors" :departamentos="departamentos"
                    @validate="validarCampo" />
            </ModalContent>

            <EmpleadoModalFooter :empleado="empleado" :textoBotonGuardar="textoBotonGuardar" @guardar="guardar"
                @close="cerrar" />
        </div>
    </div>
</template>