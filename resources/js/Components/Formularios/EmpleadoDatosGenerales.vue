<script setup>
import InputField from '@/Components/Formularios/InputField.vue'
import SelectField from '@/Components/Formularios/SelectField.vue'

defineProps({
    empleado: Object,
    frontendErrors: Object
})

defineEmits(['validate'])
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
        <h3 class="font-bold text-lg border-b pb-3 mb-5">Datos generales</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <InputField label="Nombre" v-model="empleado.nombre"
                :error="frontendErrors.nombre || empleado.errors.nombre" @validate="$emit('validate', 'nombre')" />

            <InputField label="Apellido" v-model="empleado.apellido"
                :error="frontendErrors.apellido || empleado.errors.apellido"
                @validate="$emit('validate', 'apellido')" />

            <div class="sm:col-span-2">
                <InputField label="Correo electrónico" v-model="empleado.correo"
                    :error="frontendErrors.correo || empleado.errors.correo" @validate="$emit('validate', 'correo')" />
            </div>

            <div class="sm:col-span-2">
                <InputField label="Teléfono" v-model="empleado.telefono"
                    :error="frontendErrors.telefono || empleado.errors.telefono"
                    @validate="$emit('validate', 'telefono')" />
            </div>

            <div class="sm:col-span-2">
                <InputField label="Domicilio" v-model="empleado.domicilio"
                    :error="frontendErrors.domicilio || empleado.errors.domicilio"
                    @validate="$emit('validate', 'domicilio')" />
            </div>

            <InputField type="date" label="Fecha de ingreso" v-model="empleado.fechaInicio"
                :error="frontendErrors.fechaInicio || empleado.errors.fechaInicio"
                @validate="$emit('validate', 'fechaInicio')" />

            <SelectField label="Estatus" v-model="empleado.estado" :options="['Activo', 'Inactivo']" />

        </div>
    </div>
</template>