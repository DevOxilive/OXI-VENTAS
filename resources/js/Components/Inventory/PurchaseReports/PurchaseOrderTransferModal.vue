<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'

const props = defineProps({
    order: { type: Object, required: true },
    inventoryUsers: { type: Array, default: () => [] },
    contextBranchId: { type: [Number, String], required: true },
})

const emit = defineEmits(['close', 'transferred'])
const form = useForm({ assigned_to_user_id: '' })
const eligibleUsers = computed(() => props.inventoryUsers
    .filter((user) => Number(user.id) !== Number(props.order.assigned_to_user_id))
    .filter((user) => (user.branch_ids ?? []).some((branchId) => {
        return Number(branchId) === Number(props.order.branch_id)
    }))
    .map((user) => ({ value: user.id, label: user.name })))
const modalConfig = computed(() => ({
    title: `Transferir ${props.order.folio}`,
    subtitle: `Asigna la orden de ${props.order.branch_name} a otra persona de Inventario.`,
    mode: 'update',
    size: 'md',
    height: 'auto',
    columns: 1,
    showFooter: true,
    showSave: false,
    processing: form.processing,
    closeOnBackdrop: !form.processing,
    closeOnEsc: !form.processing,
}))

function submitTransfer() {
    if (!form.assigned_to_user_id || form.processing) return

    form.post(
        route('inventory.branches.reports.purchase-orders.source-orders.transfer', {
            branch: props.contextBranchId,
            purchaseOrder: props.order.id,
        }),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Orden de compra',
            successTitle: 'Orden transferida correctamente',
            errorTitle: 'No se pudo transferir la orden',
            errorMessage: 'Verifica que la persona seleccionada tenga acceso a la sucursal.',
            onSuccess: () => emit('transferred'),
        }),
    )
}
</script>

<template>
    <GlobalModal v-bind="modalConfig" @close="$emit('close')">
        <section class="space-y-4">
            <div class="rounded-2xl border border-secondary bg-secondary p-4 text-sm text-text">
                <p class="font-black">Responsable actual</p>
                <p class="mt-1 opacity-70">{{ order.assigned_to_name }}</p>
            </div>

            <SelectField
                v-model="form.assigned_to_user_id"
                label="Nueva responsable de Inventario"
                field="assigned_to_user_id"
                :options="eligibleUsers"
                :error="form.errors.assigned_to_user_id"
                placeholder="Selecciona una responsable"
                :disabled="form.processing || !eligibleUsers.length"
            />

            <p v-if="!eligibleUsers.length" class="text-sm text-primary">
                No hay otra persona de Inventario con acceso a esta sucursal.
            </p>
        </section>

        <template #footer>
            <footer class="flex w-full justify-end gap-2 border-t border-secondary bg-background p-3">
                <AppButton variant="secondary" :disabled="form.processing" @click="$emit('close')">
                    Cancelar
                </AppButton>
                <AppButton
                    variant="primary"
                    :disabled="form.processing || !form.assigned_to_user_id"
                    @click="submitTransfer"
                >
                    Transferir
                </AppButton>
            </footer>
        </template>
    </GlobalModal>
</template>
