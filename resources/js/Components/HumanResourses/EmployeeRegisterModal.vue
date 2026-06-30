<script setup>
import { computed, watch } from 'vue'
import { useEmployeeForm } from '@/Composables/HumanResources/useEmployeeForm'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { getEmployeeModalConfig } from '@/config/ModalConfigs/employeeModalConfig'

const emit = defineEmits(['close'])

const props = defineProps({
    mode: String,
    employeeToEdit: Object,
})

const {
    employee,
    frontendErrors,
    departments,
    errorSummary,
    validateField,
    saveEmployee,
    loadEditData,
} = useEmployeeForm(props, emit)

const isReadOnly = computed(() => props.mode === 'view')
const totalErrors = computed(() => errorSummary.value.length)

const modalConfig = computed(() => getEmployeeModalConfig({
    mode: props.mode,
    totalErrors: totalErrors.value,
    processing: Boolean(employee.processing),
}))

const fullAddress = computed(() => {
    return [
        employee.street,
        employee.externalNumber,
        employee.internalNumber,
        employee.neighborhood,
        employee.municipality,
        employee.addressState,
        employee.postalCode,
    ].filter(Boolean).join(', ')
})

const mapsUrl = computed(() => {
    const registeredUrl = employee.mapsUrl?.trim()

    if (registeredUrl) return registeredUrl
    if (!fullAddress.value) return ''

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(fullAddress.value)}`
})

const canOpenMaps = computed(() => Boolean(mapsUrl.value))

function openGoogleMaps() {
    if (!mapsUrl.value) return

    window.open(mapsUrl.value, '_blank', 'noopener,noreferrer')
}

watch(
    () => employee?.hasImss,
    (hasImss) => {
        if (!hasImss && employee) {
            employee.nss = ''
        }
    },
)

watch(
    () => [props.mode, props.employeeToEdit],
    () => {
        loadEditData()
    },
    {
        immediate: true,
        deep: true,
    },
)
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="saveEmployee"
        @close="$emit('close')"
    >
        <div class="space-y-6 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
            <section class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                <h3 class="mb-6 border-b border-slate-200 pb-4 text-center text-2xl font-black tracking-tight text-[#1f1d2b]">
                    Datos del empleado
                </h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <InputField label="Nombre(s)" field="firstName" v-model="employee.firstName" :readonly="isReadOnly"
                        :error="frontendErrors.firstName || employee.errors.firstName" @validate="validateField('firstName')" />

                    <InputField label="Apellido(s)" field="lastName" v-model="employee.lastName" :readonly="isReadOnly"
                        :error="frontendErrors.lastName || employee.errors.lastName" @validate="validateField('lastName')" />

                    <InputField label="Correo electronico" field="email" v-model="employee.email" :readonly="isReadOnly"
                        :error="frontendErrors.email || employee.errors.email" @validate="validateField('email')" />

                    <InputField label="Telefono" field="phone" v-model="employee.phone" :readonly="isReadOnly"
                        :error="frontendErrors.phone || employee.errors.phone" @validate="validateField('phone')" />

                    <InputField type="date" label="Fecha de ingreso" field="startDate" v-model="employee.startDate"
                        :readonly="isReadOnly" :error="frontendErrors.startDate || employee.errors.startDate"
                        @validate="validateField('startDate')" />

                    <SelectField label="Estatus" field="employmentStatus" v-model="employee.employmentStatus"
                        :options="['Activo', 'Inactivo']" :disabled="isReadOnly"
                        :error="frontendErrors.employmentStatus || employee.errors.employmentStatus"
                        @validate="validateField('employmentStatus')" />
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                <h3 class="mb-4 border-b border-slate-200 pb-3 text-center text-2xl font-black tracking-tight text-[#1f1d2b]">
                    Domicilio
                </h3>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_320px]">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <InputField label="Calle" field="street" v-model="employee.street" :readonly="isReadOnly"
                            :error="frontendErrors.street || employee.errors.street" @validate="validateField('street')" />

                        <InputField label="Numero exterior" field="externalNumber" v-model="employee.externalNumber"
                            :readonly="isReadOnly" :error="frontendErrors.externalNumber || employee.errors.externalNumber"
                            @validate="validateField('externalNumber')" />

                        <InputField label="Numero interior" field="internalNumber" v-model="employee.internalNumber"
                            :readonly="isReadOnly" :error="frontendErrors.internalNumber || employee.errors.internalNumber"
                            @validate="validateField('internalNumber')" />

                        <InputField label="Código postal" field="postalCode" v-model="employee.postalCode"
                            :readonly="isReadOnly" :error="frontendErrors.postalCode || employee.errors.postalCode"
                            @validate="validateField('postalCode')" />

                        <InputField label="Colonia" field="neighborhood" v-model="employee.neighborhood"
                            :readonly="isReadOnly" :error="frontendErrors.neighborhood || employee.errors.neighborhood"
                            @validate="validateField('neighborhood')" />

                        <InputField label="Municipio" field="municipality" v-model="employee.municipality"
                            :readonly="isReadOnly" :error="frontendErrors.municipality || employee.errors.municipality"
                            @validate="validateField('municipality')" />

                        <InputField label="Estado" field="addressState" v-model="employee.addressState" :readonly="isReadOnly"
                            :error="frontendErrors.addressState || employee.errors.addressState"
                            @validate="validateField('addressState')" />

                        <InputField label="URL Google Maps" field="mapsUrl" v-model="employee.mapsUrl" :readonly="isReadOnly"
                            :error="frontendErrors.mapsUrl || employee.errors.mapsUrl" @validate="validateField('mapsUrl')" />
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="flex flex-col items-center gap-2 border-b border-slate-200 px-4 py-3 text-center">
                            <p class="text-2xl font-black tracking-tight text-[#1f1d2b]">
                                Ubicacion
                            </p>

                            <button type="button"
                                class="text-xs font-bold text-[#1f1d2b] disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!canOpenMaps" @click="openGoogleMaps">
                                Abrir mapa
                            </button>
                        </div>

                        <iframe v-if="fullAddress" class="h-44 w-full" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            :src="`https://www.google.com/maps?q=${encodeURIComponent(fullAddress)}&output=embed`" />

                        <div v-else class="flex h-44 items-center justify-center p-4 text-center">
                            <p class="text-sm font-semibold text-slate-400">
                                Captura el domicilio para previsualizar el mapa.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                <h3 class="mb-4 border-b border-slate-200 pb-3 text-center text-2xl font-black tracking-tight text-[#1f1d2b]">
                    Laboral y fiscal
                </h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <InputField label="Puesto" field="position" v-model="employee.position" :readonly="isReadOnly"
                        :error="frontendErrors.position || employee.errors.position" @validate="validateField('position')" />

                    <SelectField label="Departamento" field="department" v-model="employee.department"
                        :options="departments" :disabled="isReadOnly"
                        :error="frontendErrors.department || employee.errors.department" @validate="validateField('department')" />

                    <SelectField label="Tipo de contrato" field="contractType" v-model="employee.contractType" :options="[
                        { value: 'Planta/Indefinido', label: 'Planta/Indefinido' },
                        { value: 'Temporal', label: 'Temporal' },
                        { value: 'Por honorarios', label: 'Por honorarios' },
                    ]" :disabled="isReadOnly" :error="frontendErrors.contractType || employee.errors.contractType"
                        @validate="validateField('contractType')" />

                    <InputField label="Antiguedad" field="seniority" v-model="employee.seniority" readonly />

                    <InputField label="Grado de estudios" field="educationLevel" v-model="employee.educationLevel"
                        :readonly="isReadOnly" :error="frontendErrors.educationLevel || employee.errors.educationLevel"
                        @validate="validateField('educationLevel')" />

                    <InputField label="Especialidad" field="specialty" v-model="employee.specialty" :readonly="isReadOnly"
                        :error="frontendErrors.specialty || employee.errors.specialty" @validate="validateField('specialty')" />

                    <InputField label="Banco" field="bank" v-model="employee.bank" :readonly="isReadOnly"
                        :error="frontendErrors.bank || employee.errors.bank" @validate="validateField('bank')" />

                    <InputField label="Cuenta bancaria" field="accountNumber" v-model="employee.accountNumber"
                        :readonly="isReadOnly" :error="frontendErrors.accountNumber || employee.errors.accountNumber"
                        @validate="validateField('accountNumber')" />

                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Esta dado de alta en IMSS?</p>
                                <p class="text-xs text-slate-500">Activa el NSS solo cuando aplique</p>
                            </div>

                            <button type="button"
                                class="relative inline-flex h-7 w-14 items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-50"
                                :class="employee.hasImss ? 'bg-[#1f1d2b]' : 'bg-slate-300'" :disabled="isReadOnly"
                                @click="employee.hasImss = !employee.hasImss">
                                <span class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                                    :class="employee.hasImss ? 'translate-x-7' : 'translate-x-1'" />
                            </button>
                        </div>
                    </div>

                    <InputField label="NSS" field="nss" v-model="employee.nss" :readonly="isReadOnly || !employee.hasImss"
                        :error="employee.hasImss ? (frontendErrors.nss || employee.errors.nss) : ''"
                        @validate="validateField('nss')" />

                    <InputField label="RFC" field="rfc" v-model="employee.rfc" :readonly="isReadOnly"
                        placeholder="El sistema autocompleta el prefijo; captura el resto"
                        :error="frontendErrors.rfc || employee.errors.rfc" @validate="validateField('rfc')" />
                </div>
            </section>
        </div>
    </GlobalModal>
</template>
