<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue'
import { useEmployeeForm } from '@/Composables/HumanResources/useEmployeeForm'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import EmployeeData from '@/Components/Forms/EmployeeData.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    modo: String,
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

function closeModal() {
    emit('close')
}

const saveButtonText = computed(() => {
    if (employee.processing) return 'Procesando...'

    return props.modo === 'create'
        ? 'Guardar empleado'
        : 'Actualizar empleado'
})

const totalErrors = computed(() => errorSummary.value.length)

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

const isReadOnly = computed(() => props.modo === 'view')
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader :modo="modo" :totalErrors="totalErrors" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <EmployeeData :employee="employee" :frontendErrors="frontendErrors" :departments="departments"
                    :readonly="isReadOnly" @validate="validateField" />
            </GeneralModalContent>

            <GeneralModalFooter :employee="employee" :modo="modo" :saveButtonText="saveButtonText" @save="saveEmployee"
                @close="closeModal" />
        </div>
    </div>
</template>