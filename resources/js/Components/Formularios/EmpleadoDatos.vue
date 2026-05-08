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

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <!-- DATOS PERSONALES -->
            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Datos personales
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Nombre" field="nombre" v-model="empleado.nombre"
                        :error="frontendErrors.nombre || empleado.errors.nombre"
                        @validate="$emit('validate', 'nombre')" />

                    <InputField label="Apellido" field="apellido" v-model="empleado.apellido"
                        :error="frontendErrors.apellido || empleado.errors.apellido"
                        @validate="$emit('validate', 'apellido')" />

                    <InputField label="Correo electrónico" field="correo" v-model="empleado.correo"
                        :error="frontendErrors.correo || empleado.errors.correo"
                        @validate="$emit('validate', 'correo')" />

                    <InputField label="Teléfono" field="telefono" v-model="empleado.telefono"
                        :error="frontendErrors.telefono || empleado.errors.telefono"
                        @validate="$emit('validate', 'telefono')" />

                    <InputField type="date" label="Fecha de ingreso" field="fechaInicio" v-model="empleado.fechaInicio"
                        :error="frontendErrors.fechaInicio || empleado.errors.fechaInicio"
                        @validate="$emit('validate', 'fechaInicio')" />

                    <SelectField label="Estatus" field="estado" v-model="empleado.estado"
                        :options="['Activo', 'Inactivo']" :error="frontendErrors.estado || empleado.errors.estado"
                        @validate="$emit('validate', 'estado')" />
                </div>
            </section>

            <!-- DOMICILIO -->
            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Domicilio
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
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
                        :error="frontendErrors.colonia || empleado.errors.colonia"
                        @validate="$emit('validate', 'colonia')" />

                    <InputField label="Municipio" field="municipio" v-model="empleado.municipio"
                        :error="frontendErrors.municipio || empleado.errors.municipio"
                        @validate="$emit('validate', 'municipio')" />

                    <InputField label="Estado" field="estadoDomicilio" v-model="empleado.estadoDomicilio"
                        :error="frontendErrors.estadoDomicilio || empleado.errors.estadoDomicilio"
                        @validate="$emit('validate', 'estadoDomicilio')" />

                    <InputField label="URL Google Maps" field="urlMaps" v-model="empleado.urlMaps"
                        :error="frontendErrors.urlMaps || empleado.errors.urlMaps"
                        @validate="$emit('validate', 'urlMaps')" />
                </div>
            </section>

            <!-- LABORAL / FISCAL -->
            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">
                    Laboral y fiscal
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Puesto" field="puesto" v-model="empleado.puesto"
                        :error="frontendErrors.puesto || empleado.errors.puesto"
                        @validate="$emit('validate', 'puesto')" />

                    <SelectField label="Departamento" field="departamento" v-model="empleado.departamento"
                        :options="departamentos" :error="frontendErrors.departamento || empleado.errors.departamento"
                        @validate="$emit('validate', 'departamento')" />

                    <InputField label="Banco" field="banco" v-model="empleado.banco"
                        :error="frontendErrors.banco || empleado.errors.banco" @validate="$emit('validate', 'banco')" />

                    <InputField label="Cuenta bancaria" field="cuenta" v-model="empleado.cuenta"
                        :error="frontendErrors.cuenta || empleado.errors.cuenta"
                        @validate="$emit('validate', 'cuenta')" />

                    <InputField label="Grado de estudios" field="grado" v-model="empleado.grado"
                        :error="frontendErrors.grado || empleado.errors.grado" @validate="$emit('validate', 'grado')" />

                    <InputField label="Especialidad" field="especialidad" v-model="empleado.especialidad"
                        :error="frontendErrors.especialidad || empleado.errors.especialidad"
                        @validate="$emit('validate', 'especialidad')" />

                    <SelectField label="Tipo de contrato" field="tipoContrato" v-model="empleado.tipoContrato" :options="[
                        { value: 'Planta/Indefinido', label: 'Planta/Indefinido' },
                        { value: 'Temporal', label: 'Temporal' },
                        { value: 'Por honorarios', label: 'Por honorarios' }
                    ]" :error="frontendErrors.tipoContrato || empleado.errors.tipoContrato"
                        @validate="$emit('validate', 'tipoContrato')" />

                    <InputField label="Antigüedad" field="antiguedad" v-model="empleado.antiguedad" readonly />

                    <InputField label="NSS" field="nss" v-model="empleado.nss"
                        :error="frontendErrors.nss || empleado.errors.nss" @validate="$emit('validate', 'nss')" />

                    <InputField label="RFC" field="rfc" v-model="empleado.rfc"
                        :error="frontendErrors.rfc || empleado.errors.rfc" @validate="$emit('validate', 'rfc')" />
                </div>
            </section>

        </div>
    </div>
</template>