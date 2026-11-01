<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import SelectField from '@/Components/Forms/SelectField.vue'

const props = defineProps({
    employee: {
        type: Object,
        required: true,
    },
    users: {
        type: Array,
        default: () => [],
    },
    form: {
        type: Object,
        required: true,
    },
})

defineEmits(['close', 'save'])

const employeeName = computed(() => `${props.employee?.first_name || props.employee?.firstName || ''} ${props.employee?.last_name || props.employee?.lastName || ''}`.trim())
const totalErrors = computed(() => Object.keys(props.form?.errors || {}).length)
</script>

<template>
    <GlobalModal
        title="Vincular usuario existente"
        :subtitle="`Selecciona la cuenta que corresponde a ${employeeName || 'este empleado'}. La cuenta conservará su rol, permisos y sucursales.`"
        mode="edit"
        size="md"
        height="auto"
        :total-errors="totalErrors"
        :processing="form.processing"
        save-button-text="Vincular usuario"
        @save="$emit('save')"
        @close="$emit('close')"
    >
        <template #content>
            <form class="p-5" @submit.prevent="$emit('save')">
                <SelectField
                    v-model="form.user_id"
                    label="Usuario sin empleado"
                    field="link-employee-user"
                    placeholder="Selecciona un usuario"
                    :options="users"
                    :error="form.errors.user_id"
                    data-modal-autofocus
                />

                <p v-if="form.errors.employee_id" class="mt-2 text-xs text-red-500">
                    {{ form.errors.employee_id }}
                </p>

                <p class="mt-3 text-xs text-text opacity-70">
                    Después de guardarlo, esta cuenta podrá registrar ventas con {{ employeeName || 'el empleado seleccionado' }} como responsable.
                </p>
            </form>
        </template>
    </GlobalModal>
</template>
