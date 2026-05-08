<script setup>
import InputField from '@/Components/Formularios/InputField.vue'
import SelectField from '@/Components/Formularios/SelectField.vue'

defineProps({
    empleado: Object,
    frontendErrors: Object,
    departamentos: Array,
    readonly: {
        type: Boolean,
        default: false
    }
})

defineEmits(['validate'])
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">Datos personales</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Nombre" field="nombre" v-model="empleado.nombre" :readonly="readonly"
                        :error="frontendErrors.nombre || empleado.errors.nombre"
                        @validate="$emit('validate', 'nombre')" />

                    <InputField label="Apellido" field="apellido" v-model="empleado.apellido" :readonly="readonly"
                        :error="frontendErrors.apellido || empleado.errors.apellido"
                        @validate="$emit('validate', 'apellido')" />

                    <InputField label="Correo electrónico" field="correo" v-model="empleado.correo" :readonly="readonly"
                        :error="frontendErrors.correo || empleado.errors.correo"
                        @validate="$emit('validate', 'correo')" />

                    <InputField label="Teléfono" field="telefono" v-model="empleado.telefono" :readonly="readonly"
                        :error="frontendErrors.telefono || empleado.errors.telefono"
                        @validate="$emit('validate', 'telefono')" />

                    <InputField type="date" label="Fecha de ingreso" field="fechaInicio" v-model="empleado.fechaInicio"
                        :readonly="readonly" :error="frontendErrors.fechaInicio || empleado.errors.fechaInicio"
                        @validate="$emit('validate', 'fechaInicio')" />

                    <SelectField label="Estatus" field="estado" v-model="empleado.estado"
                        :options="['Activo', 'Inactivo']" :disabled="readonly"
                        :error="frontendErrors.estado || empleado.errors.estado"
                        @validate="$emit('validate', 'estado')" />
                </div>
            </section>

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">Domicilio</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Calle" field="calle" v-model="empleado.calle" :readonly="readonly"
                        :error="frontendErrors.calle || empleado.errors.calle" @validate="$emit('validate', 'calle')" />

                    <InputField label="Número exterior" field="numeroExterior" v-model="empleado.numeroExterior"
                        :readonly="readonly" :error="frontendErrors.numeroExterior || empleado.errors.numeroExterior"
                        @validate="$emit('validate', 'numeroExterior')" />

                    <InputField label="Número interior" field="numeroInterior" v-model="empleado.numeroInterior"
                        :readonly="readonly" :error="frontendErrors.numeroInterior || empleado.errors.numeroInterior"
                        @validate="$emit('validate', 'numeroInterior')" />

                    <InputField label="Código postal" field="codigoPostal" v-model="empleado.codigoPostal"
                        :readonly="readonly" :error="frontendErrors.codigoPostal || empleado.errors.codigoPostal"
                        @validate="$emit('validate', 'codigoPostal')" />

                    <InputField label="Colonia" field="colonia" v-model="empleado.colonia" :readonly="readonly"
                        :error="frontendErrors.colonia || empleado.errors.colonia"
                        @validate="$emit('validate', 'colonia')" />

                    <InputField label="Municipio" field="municipio" v-model="empleado.municipio" :readonly="readonly"
                        :error="frontendErrors.municipio || empleado.errors.municipio"
                        @validate="$emit('validate', 'municipio')" />

                    <InputField label="Estado" field="estadoDomicilio" v-model="empleado.estadoDomicilio"
                        :readonly="readonly" :error="frontendErrors.estadoDomicilio || empleado.errors.estadoDomicilio"
                        @validate="$emit('validate', 'estadoDomicilio')" />

                    <InputField label="URL Google Maps" field="urlMaps" v-model="empleado.urlMaps" :readonly="readonly"
                        :error="frontendErrors.urlMaps || empleado.errors.urlMaps"
                        @validate="$emit('validate', 'urlMaps')" />
                </div>
            </section>

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">Laboral y fiscal</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Puesto" field="puesto" v-model="empleado.puesto" :readonly="readonly"
                        :error="frontendErrors.puesto || empleado.errors.puesto"
                        @validate="$emit('validate', 'puesto')" />

                    <SelectField label="Departamento" field="departamento" v-model="empleado.departamento"
                        :options="departamentos" :disabled="readonly"
                        :error="frontendErrors.departamento || empleado.errors.departamento"
                        @validate="$emit('validate', 'departamento')" />

                    <InputField label="Banco" field="banco" v-model="empleado.banco" :readonly="readonly"
                        :error="frontendErrors.banco || empleado.errors.banco" @validate="$emit('validate', 'banco')" />

                    <InputField label="Cuenta bancaria" field="cuenta" v-model="empleado.cuenta" :readonly="readonly"
                        :error="frontendErrors.cuenta || empleado.errors.cuenta"
                        @validate="$emit('validate', 'cuenta')" />

                    <InputField label="Grado de estudios" field="grado" v-model="empleado.grado" :readonly="readonly"
                        :error="frontendErrors.grado || empleado.errors.grado" @validate="$emit('validate', 'grado')" />

                    <InputField label="Especialidad" field="especialidad" v-model="empleado.especialidad"
                        :readonly="readonly" :error="frontendErrors.especialidad || empleado.errors.especialidad"
                        @validate="$emit('validate', 'especialidad')" />

                    <SelectField label="Tipo de contrato" field="tipoContrato" v-model="empleado.tipoContrato" :options="[
                        { value: 'Planta/Indefinido', label: 'Planta/Indefinido' },
                        { value: 'Temporal', label: 'Temporal' },
                        { value: 'Por honorarios', label: 'Por honorarios' }
                    ]" :disabled="readonly" :error="frontendErrors.tipoContrato || empleado.errors.tipoContrato"
                        @validate="$emit('validate', 'tipoContrato')" />

                    <InputField label="Antigüedad" field="antiguedad" v-model="empleado.antiguedad" readonly />

                    <InputField label="NSS" field="nss" v-model="empleado.nss" :readonly="readonly"
                        :error="frontendErrors.nss || empleado.errors.nss" @validate="$emit('validate', 'nss')" />

                    <InputField label="RFC" field="rfc" v-model="empleado.rfc" :readonly="readonly"
                        :error="frontendErrors.rfc || empleado.errors.rfc" @validate="$emit('validate', 'rfc')" />
                </div>
            </section>

        </div>
    </div>
</template>