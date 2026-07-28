<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { getInventoryMovementModalConfig } from '@/config/ModalConfigs/inventoryMovementModalConfig'
import { inventoryMovementsTableConfig } from '@/config/TableConfigs/inventoryMovementsTableConfig'

defineOptions({
    layout: AdminLayout,
})

const props = defineProps({
    branchProductsDB: Array,
    movementsDB: Object,
})

const branchProducts = computed(() => props.branchProductsDB ?? [])
const movements = computed(() => props.movementsDB?.data ?? [])
const { handlePageChange } = useGlobalTablePagination()
const showModal = ref(false)

const typeOptions = [
    { label: 'Entrada', value: 'IN' },
    { label: 'Salida', value: 'OUT' },
    { label: 'Ajuste', value: 'ADJUSTMENT' },
]

const reasonOptions = [
    { label: 'Compra', value: 'PURCHASE' },
    { label: 'Venta', value: 'SALE' },
    { label: 'Producto danado', value: 'DAMAGED' },
    { label: 'Producto robado', value: 'STOLEN' },
    { label: 'Producto caducado', value: 'EXPIRED' },
    { label: 'Otros...', value: 'OTHER' },
    { label: 'Transferencia', value: 'TRANSFER' },
    { label: 'Ajuste manual', value: 'MANUAL' },
]

const form = useForm({
    branch_product_id: '',
    type: 'OUT',
    reason: '',
    quantity: '',
    notes: '',
})

const totalErrors = computed(() => Object.keys(form.errors || {}).length)

const modalConfig = computed(() => getInventoryMovementModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
}))

function openModal() {
    form.reset()
    form.type = 'OUT'
    showModal.value = true
}

function closeModal() {
    if (form.processing) return

    showModal.value = false
}

function submit() {
    form.post(route('inventario.stock-movements.store'), getModalRequestOptions({
        mode: 'create',
        entityName: modalConfig.value.alerts.entityName,
        close: closeModal,
        successTitle: modalConfig.value.alerts.create.successTitle,
        errorTitle: modalConfig.value.alerts.create.errorTitle,
        errorMessage: modalConfig.value.alerts.create.errorMessage,
    }))
}
</script>

<template>
    <div class="min-h-screen rounded-3xl bg-background p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-text">
                    Movimientos de inventario
                </h1>

                <p class="mt-1 text-sm text-text opacity-70">
                    Entradas, salidas, robos, danos y caducidades
                </p>
            </div>

            <button
                class="rounded-xl border border-primary bg-primary px-5 py-3 text-sm font-medium text-white transition hover:brightness-110"
                @click="openModal"
            >
                Nuevo movimiento
            </button>
        </div>

        <GlobalTable
            :items="movements"
            v-bind="inventoryMovementsTableConfig"
            :pagination="movementsDB"
            @page-change="handlePageChange"
        />

        <GlobalModal
            v-if="showModal"
            v-bind="modalConfig"
            @save="submit"
            @close="closeModal"
        >
            <form class="grid grid-cols-1 md:grid-cols-2 gap-5" @submit.prevent="submit">
                <div class="md:col-span-2">
                    <SelectField
                        v-model="form.branch_product_id"
                        label="Producto / Sucursal"
                        field="branch_product_id"
                        placeholder="Selecciona un producto"
                        :options="branchProducts.map(item => ({
                            label: `${item.product?.name} - ${item.branch?.name}`,
                            value: item.id,
                        }))"
                        :error="form.errors.branch_product_id"
                    />
                </div>

                <SelectField
                    v-model="form.type"
                    label="Tipo"
                    field="type"
                    :options="typeOptions"
                    :error="form.errors.type"
                />

                <SelectField
                    v-model="form.reason"
                    label="Motivo"
                    field="reason"
                    placeholder="Selecciona un motivo"
                    :options="reasonOptions"
                    :error="form.errors.reason"
                />

                <div class="md:col-span-2">
                    <InputField
                        v-model="form.quantity"
                        label="Cantidad"
                        field="quantity"
                        type="number"
                        :error="form.errors.quantity"
                    />
                </div>

                <div class="md:col-span-2">
                    <TextareaField
                        v-model="form.notes"
                        label="Observaciones"
                        field="notes"
                        :rows="4"
                        :error="form.errors.notes"
                    />
                </div>
            </form>
        </GlobalModal>
    </div>
</template>
