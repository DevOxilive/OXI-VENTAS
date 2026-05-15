<script setup>
import { computed, watch } from 'vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'

const props = defineProps({
    employee: Object,
    frontendErrors: Object,
    departments: Array,
    readonly: {
        type: Boolean,
        default: false
    }
})

defineEmits(['validate'])

watch(
    () => props.employee?.hasImss,
    (hasImss) => {
        if (!hasImss && props.employee) {
            props.employee.nss = ''
        }
    }
)

const fullAddress = computed(() => {
    const employee = props.employee || {}

    return [
        employee.street,
        employee.externalNumber,
        employee.internalNumber,
        employee.neighborhood,
        employee.municipality,
        employee.addressState,
        employee.postalCode
    ]
        .filter(Boolean)
        .join(', ')
})

const mapsUrl = computed(() => {
    const registeredUrl = props.employee?.mapsUrl?.trim()

    if (registeredUrl) return registeredUrl
    if (!fullAddress.value) return ''

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(fullAddress.value)}`
})

const canOpenMaps = computed(() => Boolean(mapsUrl.value))

function openGoogleMaps() {
    if (!mapsUrl.value) return

    window.open(mapsUrl.value, '_blank', 'noopener,noreferrer')
}
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">Datos personales</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Nombre" field="firstName" v-model="employee.firstName" :readonly="readonly"
                        :error="frontendErrors.firstName || employee.errors.firstName"
                        @validate="$emit('validate', 'firstName')" />

                    <InputField label="Apellido" field="lastName" v-model="employee.lastName" :readonly="readonly"
                        :error="frontendErrors.lastName || employee.errors.lastName"
                        @validate="$emit('validate', 'lastName')" />

                    <InputField label="Correo electrónico" field="email" v-model="employee.email" :readonly="readonly"
                        :error="frontendErrors.email || employee.errors.email" @validate="$emit('validate', 'email')" />

                    <InputField label="Teléfono" field="phone" v-model="employee.phone" :readonly="readonly"
                        :error="frontendErrors.phone || employee.errors.phone" @validate="$emit('validate', 'phone')" />

                    <InputField type="date" label="Fecha de ingreso" field="startDate" v-model="employee.startDate"
                        :readonly="readonly" :error="frontendErrors.startDate || employee.errors.startDate"
                        @validate="$emit('validate', 'startDate')" />

                    <SelectField label="Estatus" field="employmentStatus" v-model="employee.employmentStatus"
                        :options="['Activo', 'Inactivo']" :disabled="readonly"
                        :error="frontendErrors.employmentStatus || employee.errors.employmentStatus"
                        @validate="$emit('validate', 'employmentStatus')" />
                </div>
            </section>

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">Domicilio</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Calle" field="street" v-model="employee.street" :readonly="readonly"
                        :error="frontendErrors.street || employee.errors.street"
                        @validate="$emit('validate', 'street')" />

                    <InputField label="Número exterior" field="externalNumber" v-model="employee.externalNumber"
                        :readonly="readonly" :error="frontendErrors.externalNumber || employee.errors.externalNumber"
                        @validate="$emit('validate', 'externalNumber')" />

                    <InputField label="Número interior" field="internalNumber" v-model="employee.internalNumber"
                        :readonly="readonly" :error="frontendErrors.internalNumber || employee.errors.internalNumber"
                        @validate="$emit('validate', 'internalNumber')" />

                    <InputField label="Código postal" field="postalCode" v-model="employee.postalCode"
                        :readonly="readonly" :error="frontendErrors.postalCode || employee.errors.postalCode"
                        @validate="$emit('validate', 'postalCode')" />

                    <InputField label="Colonia" field="neighborhood" v-model="employee.neighborhood"
                        :readonly="readonly" :error="frontendErrors.neighborhood || employee.errors.neighborhood"
                        @validate="$emit('validate', 'neighborhood')" />

                    <InputField label="Municipio" field="municipality" v-model="employee.municipality"
                        :readonly="readonly" :error="frontendErrors.municipality || employee.errors.municipality"
                        @validate="$emit('validate', 'municipality')" />

                    <InputField label="Estado" field="addressState" v-model="employee.addressState" :readonly="readonly"
                        :error="frontendErrors.addressState || employee.errors.addressState"
                        @validate="$emit('validate', 'addressState')" />

                    <div class="space-y-2">
                        <InputField label="URL Google Maps" field="mapsUrl" v-model="employee.mapsUrl"
                            :readonly="readonly" :error="frontendErrors.mapsUrl || employee.errors.mapsUrl"
                            @validate="$emit('validate', 'mapsUrl')" />

                        <button type="button"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canOpenMaps" @click="openGoogleMaps">
                            Ver ubicación en Google Maps
                        </button>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="font-bold text-base border-b pb-3 mb-4">Laboral y fiscal</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <InputField label="Puesto" field="position" v-model="employee.position" :readonly="readonly"
                        :error="frontendErrors.position || employee.errors.position"
                        @validate="$emit('validate', 'position')" />

                    <SelectField label="Departamento" field="department" v-model="employee.department"
                        :options="departments" :disabled="readonly"
                        :error="frontendErrors.department || employee.errors.department"
                        @validate="$emit('validate', 'department')" />

                    <InputField label="Banco" field="bank" v-model="employee.bank" :readonly="readonly"
                        :error="frontendErrors.bank || employee.errors.bank" @validate="$emit('validate', 'bank')" />

                    <InputField label="Cuenta bancaria" field="accountNumber" v-model="employee.accountNumber"
                        :readonly="readonly" :error="frontendErrors.accountNumber || employee.errors.accountNumber"
                        @validate="$emit('validate', 'accountNumber')" />

                    <InputField label="Grado de estudios" field="educationLevel" v-model="employee.educationLevel"
                        :readonly="readonly" :error="frontendErrors.educationLevel || employee.errors.educationLevel"
                        @validate="$emit('validate', 'educationLevel')" />

                    <InputField label="Especialidad" field="specialty" v-model="employee.specialty" :readonly="readonly"
                        :error="frontendErrors.specialty || employee.errors.specialty"
                        @validate="$emit('validate', 'specialty')" />

                    <SelectField label="Tipo de contrato" field="contractType" v-model="employee.contractType" :options="[
                        { value: 'Planta/Indefinido', label: 'Planta/Indefinido' },
                        { value: 'Temporal', label: 'Temporal' },
                        { value: 'Por honorarios', label: 'Por honorarios' }
                    ]" :disabled="readonly" :error="frontendErrors.contractType || employee.errors.contractType"
                        @validate="$emit('validate', 'contractType')" />

                    <InputField label="Antigüedad" field="seniority" v-model="employee.seniority" readonly />

                    <div class="space-y-2">
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">¿Está dado de alta en IMSS?</p>
                                <p class="text-xs text-slate-500">Activa el NSS solo cuando aplique</p>
                            </div>

                            <button type="button"
                                class="relative inline-flex h-7 w-14 items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-50"
                                :class="employee.hasImss ? 'bg-[#1f1d2b]' : 'bg-slate-300'" :disabled="readonly"
                                @click="employee.hasImss = !employee.hasImss">
                                <span class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                                    :class="employee.hasImss ? 'translate-x-7' : 'translate-x-1'" />
                            </button>
                        </div>

                        <InputField label="NSS" field="nss" v-model="employee.nss"
                            :readonly="readonly || !employee.hasImss"
                            :error="employee.hasImss ? (frontendErrors.nss || employee.errors.nss) : ''"
                            @validate="$emit('validate', 'nss')" />
                    </div>

                    <InputField label="RFC" field="rfc" v-model="employee.rfc" :readonly="readonly"
                        :error="frontendErrors.rfc || employee.errors.rfc" @validate="$emit('validate', 'rfc')" />
                </div>
            </section>

        </div>
    </div>
</template>