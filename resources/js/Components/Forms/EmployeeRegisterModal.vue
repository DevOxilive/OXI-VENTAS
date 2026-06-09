<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue'
import { useEmployeeForm } from '@/Composables/HumanResources/useEmployeeForm'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import EmployeeData from '@/Components/Forms/EmployeeData.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    mode: String,
    employeeToEdit: Object
})

const {
    employee,
    frontendErrors,
    departments,
    errorSummary,
    validateField,
    saveEmployee,
    loadEditData
} = useEmployeeForm(props, emit)

const isReadOnly = computed(() => props.mode === 'view')

const saveButtonText = computed(() => {
    if (employee.processing) return 'Procesando...'

    return props.mode === 'create'
        ? 'Guardar empleado'
        : 'Actualizar empleado'
})

const totalErrors = computed(() => errorSummary.value.length)

function closeModal() {
    emit('close')
}

function handleEsc(e) {
    if (e.key === 'Escape') closeModal()
}

onMounted(() => {
    loadEditData()
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 md:items-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <section
            class="relative flex h-[100dvh] w-full flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl md:h-[94vh] md:w-[98%] md:max-w-[1600px] md:rounded-3xl">
            <GeneralModalHeader :title="props.mode === 'create'
                ? 'Registrar empleado'
                : props.mode === 'edit'
                    ? 'Actualizar empleado'
                    : 'Detalle del empleado'" subtitle="Información general del empleado"
                :total-errors="totalErrors" :mode="props.mode" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <div class="min-w-0">
                    <EmployeeData :employee="employee" :frontend-errors="frontendErrors" :departments="departments"
                        :readonly="isReadOnly" @validate="validateField" />
                </div>
            </GeneralModalContent>

            <GeneralModalFooter :processing="employee.processing" :save-button-text="saveButtonText" :mode="props.mode"
                @save="saveEmployee" @close="closeModal" />
        </section>
    </div>
</template>