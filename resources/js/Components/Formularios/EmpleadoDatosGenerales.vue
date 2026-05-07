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

            <InputField label="Nombre" field="nombre" v-model="empleado.nombre"
                :error="frontendErrors.nombre || empleado.errors.nombre" @validate="$emit('validate', 'nombre')" />

            <InputField label="Apellido" field="apellido" v-model="empleado.apellido"
                :error="frontendErrors.apellido || empleado.errors.apellido"
                @validate="$emit('validate', 'apellido')" />

            <div class="sm:col-span-2">
                <InputField label="Correo electrónico" field="correo" v-model="empleado.correo"
                    :error="frontendErrors.correo || empleado.errors.correo" @validate="$emit('validate', 'correo')" />
            </div>

            <div class="sm:col-span-2">
                <InputField label="Teléfono" field="telefono" v-model="empleado.telefono"
                    :error="frontendErrors.telefono || empleado.errors.telefono"
                    @validate="$emit('validate', 'telefono')" />
            </div>

            <div class="sm:col-span-2 border-t pt-5 mt-2">
                <h4 class="font-semibold text-slate-700 mb-3">Domicilio</h4>
            </div>

            <InputField label="Calle" field="calle" v-model="empleado.calle"
                :error="frontendErrors.calle || empleado.errors.calle" @validate="$emit('validate', 'calle')" />

            <InputField label="Número exterior" field="numeroExterior" v-model="empleado.numeroExterior"
                :error="frontendErrors.numeroExterior || empleado.errors.numeroExterior"
                @validate="$emit('validate', 'numeroExterior')" />

            <InputField label="Número interior" field="numeroInterior" v-model="empleado.numeroInterior"
                :error="frontendErrors.numeroInterior || empleado.errors.numeroInterior"
                @validate="$emit('validate', 'numeroInterior')" />

            <InputField label="Código postal" field="codigoPostal" v-model="empleado.codigoPostal"
                :error="frontendErrors.codigoPostal || empleado.errors.codigoPostal"
                @validate="$emit('validate', 'codigoPostal')" />

            <InputField label="Colonia" field="colonia" v-model="empleado.colonia"
                :error="frontendErrors.colonia || empleado.errors.colonia" @validate="$emit('validate', 'colonia')" />

            <InputField label="Municipio" field="municipio" v-model="empleado.municipio"
                :error="frontendErrors.municipio || empleado.errors.municipio"
                @validate="$emit('validate', 'municipio')" />

            <InputField label="Estado" field="estadoDomicilio" v-model="empleado.estadoDomicilio"
                :error="frontendErrors.estadoDomicilio || empleado.errors.estadoDomicilio"
                @validate="$emit('validate', 'estadoDomicilio')" />

            <div class="sm:col-span-2">
                <InputField label="URL Google Maps" field="urlMaps" v-model="empleado.urlMaps"
                    :error="frontendErrors.urlMaps || empleado.errors.urlMaps"
                    @validate="$emit('validate', 'urlMaps')" />
            </div>

            <InputField type="date" label="Fecha de ingreso" field="fechaInicio" v-model="empleado.fechaInicio"
                :error="frontendErrors.fechaInicio || empleado.errors.fechaInicio"
                @validate="$emit('validate', 'fechaInicio')" />

            <SelectField label="Estatus" field="estado" v-model="empleado.estado" :options="['Activo', 'Inactivo']"
                :error="frontendErrors.estado || empleado.errors.estado" @validate="$emit('validate', 'estado')" />
        </div>
    </div>
</template>