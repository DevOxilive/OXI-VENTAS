<script setup>
import { computed, ref, watch } from 'vue'
import { useEmployeeForm } from '@/Composables/HumanResources/useEmployeeForm'

import FormPanel from '@/Components/Cards/FormPanel.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { getEmployeeModalConfig } from '@/config/ModalConfigs/employeeModalConfig'

const emit = defineEmits(['close'])

const props = defineProps({
    mode: String,
    employeeToEdit: Object,
    organizationOptions: {
        type: Object,
        default: () => ({}),
    },
})

const {
    employee,
    age,
    frontendErrors,
    departments,
    positions,
    errorSummary,
    validateField,
    saveEmployee,
    loadEditData,
} = useEmployeeForm(props, emit)

const isReadOnly = computed(() => props.mode === 'view')
const totalErrors = computed(() => errorSummary.value.length)
const activeStep = ref('employee')

const modalSections = [
    { id: 'employee', label: 'Datos del empleado' },
    { id: 'address', label: 'Domicilio' },
    { id: 'labor', label: 'Laboral y fiscal' },
]

const educationLevelOptions = [
    { value: 'No aplica', label: 'No aplica' },
    { value: 'Primaria', label: 'Primaria' },
    { value: 'Secundaria', label: 'Secundaria' },
    { value: 'Bachillerato', label: 'Bachillerato' },
    { value: 'Carrera tecnica', label: 'Carrera tecnica' },
    { value: 'Tecnico superior universitario', label: 'Tecnico superior universitario' },
    { value: 'Licenciatura', label: 'Licenciatura' },
    { value: 'Ingenieria', label: 'Ingenieria' },
    { value: 'Especialidad', label: 'Especialidad' },
    { value: 'Maestria', label: 'Maestria' },
    { value: 'Doctorado', label: 'Doctorado' },
    { value: 'Posdoctorado', label: 'Posdoctorado' },
]

const relationshipOptions = [
    { value: 'Madre', label: 'Madre' },
    { value: 'Padre', label: 'Padre' },
    { value: 'Abuela', label: 'Abuela' },
    { value: 'Abuelo', label: 'Abuelo' },
    { value: 'Tia', label: 'Tia' },
    { value: 'Tio', label: 'Tio' },
    { value: 'Hija', label: 'Hija' },
    { value: 'Hijo', label: 'Hijo' },
    { value: 'Hermana', label: 'Hermana' },
    { value: 'Hermano', label: 'Hermano' },
    { value: 'Conyuge', label: 'Conyuge' },
    { value: 'Pareja', label: 'Pareja' },
    { value: 'Otro', label: 'Otro' },
]

const activeStepIndex = computed(() => modalSections.findIndex((section) => section.id === activeStep.value))
const isFirstStep = computed(() => activeStepIndex.value <= 0)
const isLastStep = computed(() => activeStepIndex.value === modalSections.length - 1)

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

function goToStep(step) {
    const nextStep = modalSections.find((section) => String(section.id) === String(step))

    if (nextStep) {
        activeStep.value = nextStep.id
    }
}

function goToNextStep() {
    const nextStep = modalSections[activeStepIndex.value + 1]

    if (nextStep) {
        activeStep.value = nextStep.id
    }
}

function goToPreviousStep() {
    const previousStep = modalSections[activeStepIndex.value - 1]

    if (previousStep) {
        activeStep.value = previousStep.id
    }
}

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
        activeStep.value = 'employee'
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
        :sections="modalSections"
        :active-section="activeStep"
        @select-section="goToStep"
        @save="saveEmployee"
        @close="$emit('close')"
    >
        <div class="space-y-6 rounded-3xl border border-secondary bg-background p-4 shadow-sm sm:p-5 md:p-6">
            <FormPanel
                v-if="activeStep === 'employee'"
                title="Datos del empleado"
                centered-heading
                :heading-border="true"
                panel-class="p-4"
            >
                <div class="space-y-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <InputField label="Nombre(s)" field="firstName" v-model="employee.firstName" :readonly="isReadOnly"
                            :error="frontendErrors.firstName || employee.errors.firstName" @validate="validateField('firstName')" />

                        <InputField label="Apellido(s)" field="lastName" v-model="employee.lastName" :readonly="isReadOnly"
                            :error="frontendErrors.lastName || employee.errors.lastName" @validate="validateField('lastName')" />

                        <InputField type="date" label="Fecha de nacimiento" field="birthDate" v-model="employee.birthDate"
                            :readonly="isReadOnly" :error="frontendErrors.birthDate || employee.errors.birthDate"
                            @validate="validateField('birthDate')" />

                        <InputField label="Edad" field="age" :model-value="age" readonly :show-counter="false" />

                        <InputField label="Correo electronico" field="email" v-model="employee.email" :readonly="isReadOnly"
                            :error="frontendErrors.email || employee.errors.email" @validate="validateField('email')" />

                        <InputField label="Telefono" field="phone" v-model="employee.phone" :readonly="isReadOnly"
                            :error="frontendErrors.phone || employee.errors.phone" @validate="validateField('phone')" />
                    </div>

                    <div class="space-y-4 border-t border-secondary pt-4">
                        <h3 class="text-sm font-semibold text-text">Contactos de emergencia</h3>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <InputField label="Contacto principal" field="emergencyContactName" v-model="employee.emergencyContactName"
                                :readonly="isReadOnly" :error="frontendErrors.emergencyContactName || employee.errors.emergencyContactName"
                                @validate="validateField('emergencyContactName')" />

                            <SelectField label="Parentesco" field="emergencyContactRelationship" v-model="employee.emergencyContactRelationship"
                                :options="relationshipOptions" :disabled="isReadOnly"
                                :error="frontendErrors.emergencyContactRelationship || employee.errors.emergencyContactRelationship"
                                @validate="validateField('emergencyContactRelationship')" />

                            <InputField label="Telefono de emergencia" field="emergencyContactPhone" v-model="employee.emergencyContactPhone"
                                :readonly="isReadOnly" :error="frontendErrors.emergencyContactPhone || employee.errors.emergencyContactPhone"
                                @validate="validateField('emergencyContactPhone')" />

                            <InputField label="Segundo contacto" field="secondaryEmergencyContactName" v-model="employee.secondaryEmergencyContactName"
                                :readonly="isReadOnly" :error="frontendErrors.secondaryEmergencyContactName || employee.errors.secondaryEmergencyContactName"
                                @validate="validateField('secondaryEmergencyContactName')" />

                            <SelectField label="Parentesco" field="secondaryEmergencyContactRelationship" v-model="employee.secondaryEmergencyContactRelationship"
                                :options="relationshipOptions" :disabled="isReadOnly"
                                :error="frontendErrors.secondaryEmergencyContactRelationship || employee.errors.secondaryEmergencyContactRelationship"
                                @validate="validateField('secondaryEmergencyContactRelationship')" />

                            <InputField label="Telefono secundario" field="secondaryEmergencyContactPhone" v-model="employee.secondaryEmergencyContactPhone"
                                :readonly="isReadOnly" :error="frontendErrors.secondaryEmergencyContactPhone || employee.errors.secondaryEmergencyContactPhone"
                                @validate="validateField('secondaryEmergencyContactPhone')" />
                        </div>
                    </div>
                </div>
            </FormPanel>

            <FormPanel
                v-if="activeStep === 'address'"
                title="Domicilio"
                centered-heading
                :heading-border="true"
                panel-class="p-4"
            >
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

                    <div class="overflow-hidden rounded-2xl border border-secondary bg-background">
                        <div class="flex flex-col items-center gap-2 border-b border-secondary px-4 py-3 text-center">
                            <p class="text-2xl font-black tracking-tight text-text">
                                Ubicacion
                            </p>

                            <button type="button"
                                class="text-xs font-bold text-primary disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!canOpenMaps" @click="openGoogleMaps">
                                Abrir mapa
                            </button>
                        </div>

                        <iframe v-if="fullAddress" class="h-44 w-full" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            :src="`https://www.google.com/maps?q=${encodeURIComponent(fullAddress)}&output=embed`" />

                        <div v-else class="flex h-44 items-center justify-center p-4 text-center">
                            <p class="text-sm font-semibold text-text opacity-50">
                                Captura el domicilio para previsualizar el mapa.
                            </p>
                        </div>
                    </div>
                </div>
            </FormPanel>

            <FormPanel
                v-if="activeStep === 'labor'"
                title="Laboral y fiscal"
                centered-heading
                :heading-border="true"
                panel-class="p-4"
            >
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <SelectField label="Departamento" field="departmentId" v-model="employee.departmentId"
                        :options="departments" :disabled="isReadOnly"
                        :error="frontendErrors.departmentId || employee.errors.departmentId"
                        @validate="validateField('departmentId')" />

                    <SelectField label="Puesto" field="positionId" v-model="employee.positionId"
                        :options="positions" :disabled="isReadOnly || !employee.departmentId"
                        :placeholder="employee.departmentId ? 'Seleccione un puesto' : 'Selecciona primero un departamento'"
                        :error="frontendErrors.positionId || employee.errors.positionId"
                        @validate="validateField('positionId')" />

                    <SelectField label="Tipo de contrato" field="contractType" v-model="employee.contractType" :options="[
                        { value: 'Planta/Indefinido', label: 'Planta/Indefinido' },
                        { value: 'Temporal', label: 'Temporal' },
                        { value: 'Por honorarios', label: 'Por honorarios' },
                    ]" :disabled="isReadOnly" :error="frontendErrors.contractType || employee.errors.contractType"
                        @validate="validateField('contractType')" />

                    <InputField v-if="isReadOnly" label="Antiguedad" field="seniority" v-model="employee.seniority" readonly />

                    <SelectField label="Grado de estudios" field="educationLevel" v-model="employee.educationLevel"
                        :options="educationLevelOptions" :disabled="isReadOnly"
                        :error="frontendErrors.educationLevel || employee.errors.educationLevel"
                        @validate="validateField('educationLevel')" />

                    <InputField label="Especialidad" field="specialty" v-model="employee.specialty" :readonly="isReadOnly"
                        :error="frontendErrors.specialty || employee.errors.specialty" @validate="validateField('specialty')" />

                    <InputField label="Banco" field="bank" v-model="employee.bank" :readonly="isReadOnly"
                        :error="frontendErrors.bank || employee.errors.bank" @validate="validateField('bank')" />

                    <InputField label="Cuenta bancaria" field="accountNumber" v-model="employee.accountNumber"
                        :readonly="isReadOnly" :error="frontendErrors.accountNumber || employee.errors.accountNumber"
                        @validate="validateField('accountNumber')" />

                    <InputField label="CLABE" field="bankClabe" v-model="employee.bankClabe"
                        :readonly="isReadOnly" :error="frontendErrors.bankClabe || employee.errors.bankClabe"
                        @validate="validateField('bankClabe')" />

                    <InputField label="Numero de tarjeta" field="bankCardNumber" v-model="employee.bankCardNumber"
                        :readonly="isReadOnly" :error="frontendErrors.bankCardNumber || employee.errors.bankCardNumber"
                        @validate="validateField('bankCardNumber')" />

                    <div class="rounded-2xl border border-secondary bg-background px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-text">¿Está dado de alta en IMSS?</p>
                                <p class="text-xs text-text opacity-70">Activa el NSS solo cuando aplique</p>
                            </div>

                            <button type="button"
                                class="relative inline-flex h-7 w-14 items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-50"
                                :class="employee.hasImss ? 'bg-primary' : 'bg-secondary'" :disabled="isReadOnly"
                                @click="employee.hasImss = !employee.hasImss">
                                <span class="inline-block h-6 w-6 transform rounded-full bg-background shadow transition"
                                    :class="employee.hasImss ? 'translate-x-7' : 'translate-x-1'" />
                            </button>
                        </div>
                    </div>

                    <InputField label="NSS" field="nss" v-model="employee.nss" :readonly="isReadOnly || !employee.hasImss"
                        :error="employee.hasImss ? (frontendErrors.nss || employee.errors.nss) : ''"
                        @validate="validateField('nss')" />

                    <InputField label="RFC" field="rfc" v-model="employee.rfc" :readonly="isReadOnly"
                        placeholder="Homoclave opcional; el sistema completa nombre y fecha"
                        :error="frontendErrors.rfc || employee.errors.rfc" @validate="validateField('rfc')" />
                </div>
            </FormPanel>
        </div>

        <template #footer>
            <div class="flex items-center justify-between gap-3 border-t border-secondary bg-background px-4 py-4 md:px-8">
                <button
                    v-if="!isFirstStep"
                    type="button"
                    class="rounded-xl border border-secondary bg-background px-4 py-2.5 text-sm font-semibold text-text transition hover:border-primary hover:text-primary"
                    :disabled="employee.processing"
                    @click="goToPreviousStep"
                >
                    Atras
                </button>
                <span v-else />

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-secondary bg-background px-4 py-2.5 text-sm font-semibold text-text transition hover:border-primary hover:text-primary"
                        :disabled="employee.processing"
                        @click="$emit('close')"
                    >
                        {{ isReadOnly ? 'Cerrar' : 'Cancelar' }}
                    </button>

                    <button
                        v-if="!isLastStep"
                        type="button"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="employee.processing"
                        @click="goToNextStep"
                    >
                        Siguiente
                    </button>

                    <button
                        v-else-if="!isReadOnly"
                        type="button"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="employee.processing"
                        @click="saveEmployee"
                    >
                        {{ employee.processing ? 'Guardando...' : modalConfig.saveButtonText }}
                    </button>
                </div>
            </div>
        </template>
    </GlobalModal>
</template>
