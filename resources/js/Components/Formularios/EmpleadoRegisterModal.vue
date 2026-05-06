<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue'
import { useEmpleadoForm } from '@/Composables/useEmpleadoForm'
import InputField from '@/Components/Formularios/InputField.vue'
import SelectField from '@/Components/Formularios/SelectField.vue'

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

function cerrar() {
    emit('close')
}

const textoBotonGuardar = computed(() => {
    if (empleado.processing) return 'Procesando...'

    return props.modo === 'create'
        ? 'Guardar empleado'
        : 'Actualizar empleado'
})

const totalErrores = computed(() => resumenErrores.value.length)

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

            <!-- HEADER -->
            <div
                class="sticky top-0 bg-white border-b px-5 py-4 md:px-8 md:py-5 flex justify-between items-center z-10">

                <div>
                    <h2 class="text-lg md:text-2xl font-bold text-slate-800">
                        {{ modo === 'create' ? 'Registro corporativo de empleado' : 'Actualizar empleado' }}
                    </h2>

                    <p v-if="totalErrores > 0" class="text-xs md:text-sm text-red-500 mt-1 font-medium">
                        {{ totalErrores }} campo{{ totalErrores > 1 ? 's' : '' }} pendiente{{ totalErrores > 1 ? 's' :
                            '' }} por validar
                    </p>
                </div>

                <button @click="cerrar" class="text-2xl text-slate-500 hover:text-black transition">
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 md:p-8 bg-slate-50">

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                    <!-- DATOS GENERALES -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                        <h3 class="font-bold text-lg border-b pb-3 mb-5">Datos generales</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <InputField label="Nombre" v-model="empleado.nombre"
                                :error="frontendErrors.nombre || empleado.errors.nombre"
                                @validate="validarCampo('nombre')" />

                            <InputField label="Apellido" v-model="empleado.apellido"
                                :error="frontendErrors.apellido || empleado.errors.apellido"
                                @validate="validarCampo('apellido')" />

                            <div class="sm:col-span-2">
                                <InputField label="Correo electrónico" v-model="empleado.correo"
                                    :error="frontendErrors.correo || empleado.errors.correo"
                                    @validate="validarCampo('correo')" />
                            </div>

                            <div class="sm:col-span-2">
                                <InputField label="Teléfono" v-model="empleado.telefono"
                                    :error="frontendErrors.telefono || empleado.errors.telefono"
                                    @validate="validarCampo('telefono')" />
                            </div>

                            <div class="sm:col-span-2">
                                <InputField label="Domicilio" v-model="empleado.domicilio"
                                    :error="frontendErrors.domicilio || empleado.errors.domicilio"
                                    @validate="validarCampo('domicilio')" />
                            </div>

                            <InputField type="date" label="Fecha de ingreso" v-model="empleado.fechaInicio"
                                :error="frontendErrors.fechaInicio || empleado.errors.fechaInicio"
                                @validate="validarCampo('fechaInicio')" />

                            <SelectField label="Estatus" v-model="empleado.estado" :options="['Activo', 'Inactivo']" />
                        </div>
                    </div>

                    <!-- DATOS LABORALES -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                        <h3 class="font-bold text-lg border-b pb-3 mb-5">Datos laborales y fiscales</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <InputField label="Puesto" v-model="empleado.puesto"
                                :error="frontendErrors.puesto || empleado.errors.puesto"
                                @validate="validarCampo('puesto')" />

                            <SelectField label="Departamento" v-model="empleado.departamento" :options="departamentos"
                                :error="frontendErrors.departamento || empleado.errors.departamento"
                                @validate="validarCampo('departamento')" />

                            <InputField label="Banco" v-model="empleado.banco"
                                :error="frontendErrors.banco || empleado.errors.banco"
                                @validate="validarCampo('banco')" />

                            <InputField label="Cuenta bancaria" v-model="empleado.cuenta"
                                :error="frontendErrors.cuenta || empleado.errors.cuenta"
                                @validate="validarCampo('cuenta')" />

                            <InputField label="Grado de estudios" v-model="empleado.grado"
                                :error="frontendErrors.grado || empleado.errors.grado"
                                @validate="validarCampo('grado')" />

                            <InputField label="Especialidad" v-model="empleado.especialidad"
                                :error="frontendErrors.especialidad || empleado.errors.especialidad"
                                @validate="validarCampo('especialidad')" />

                            <InputField label="Tipo de contrato" v-model="empleado.tipoContrato"
                                :error="frontendErrors.tipoContrato || empleado.errors.tipoContrato"
                                @validate="validarCampo('tipoContrato')" />

                            <InputField label="Antigüedad" v-model="empleado.antiguedad" readonly />

                            <InputField label="NSS" v-model="empleado.nss"
                                :error="frontendErrors.nss || empleado.errors.nss" @validate="validarCampo('nss')" />

                            <InputField label="RFC" v-model="empleado.rfc"
                                :error="frontendErrors.rfc || empleado.errors.rfc" @validate="validarCampo('rfc')" />
                        </div>
                    </div>

                </div>
            </div>

            <!-- FOOTER -->
            <div class="sticky bottom-0 bg-white border-t p-4 flex flex-col sm:flex-row gap-3 justify-end">
                <button @click="guardar" :disabled="empleado.processing"
                    class="bg-[#1f1d2b] text-white px-8 py-3 rounded-full w-full sm:w-auto disabled:opacity-50">
                    {{ textoBotonGuardar }}
                </button>

                <button @click="cerrar" class="bg-gray-200 px-8 py-3 rounded-full w-full sm:w-auto">
                    Cancelar
                </button>
            </div>

        </div>
    </div>
</template>