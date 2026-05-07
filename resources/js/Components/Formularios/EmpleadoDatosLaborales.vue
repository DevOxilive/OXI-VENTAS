<script setup>
import InputField from '@/Components/Formularios/InputField.vue'
import SelectField from '@/Components/Formularios/SelectField.vue'

defineProps({
    empleado: Object,
    frontendErrors: Object,
    departamentos: Array
})

defineEmits(['validate'])
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
        <h3 class="font-bold text-lg border-b pb-3 mb-5">Datos laborales y fiscales</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <InputField label="Puesto" v-model="empleado.puesto"
                :error="frontendErrors.puesto || empleado.errors.puesto" @validate="$emit('validate', 'puesto')" />

            <SelectField label="Departamento" v-model="empleado.departamento" :options="departamentos"
                :error="frontendErrors.departamento || empleado.errors.departamento"
                @validate="$emit('validate', 'departamento')" />

            <InputField label="Banco" v-model="empleado.banco" :error="frontendErrors.banco || empleado.errors.banco"
                @validate="$emit('validate', 'banco')" />

            <InputField label="Cuenta bancaria" v-model="empleado.cuenta"
                :error="frontendErrors.cuenta || empleado.errors.cuenta" @validate="$emit('validate', 'cuenta')" />

            <InputField label="Grado de estudios" v-model="empleado.grado"
                :error="frontendErrors.grado || empleado.errors.grado" @validate="$emit('validate', 'grado')" />

            <InputField label="Especialidad" v-model="empleado.especialidad"
                :error="frontendErrors.especialidad || empleado.errors.especialidad"
                @validate="$emit('validate', 'especialidad')" />

            <InputField label="Tipo de contrato" v-model="empleado.tipoContrato"
                :error="frontendErrors.tipoContrato || empleado.errors.tipoContrato"
                @validate="$emit('validate', 'tipoContrato')" />

            <InputField label="Antigüedad" v-model="empleado.antiguedad" readonly />

            <InputField label="NSS" v-model="empleado.nss" :error="frontendErrors.nss || empleado.errors.nss"
                @validate="$emit('validate', 'nss')" />

            <InputField label="RFC" v-model="empleado.rfc" :error="frontendErrors.rfc || empleado.errors.rfc"
                @validate="$emit('validate', 'rfc')" />

        </div>
    </div>
</template>