<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { getInventoryMovementModalConfig } from '@/config/ModalConfigs/inventoryMovementModalConfig'

defineOptions({
    layout: AdminLayout,
})

const props = defineProps({
    branchProductsDB: Array,
    movementsDB: Array,
})

const branchProducts = computed(() => props.branchProductsDB ?? [])
const movements = computed(() => props.movementsDB ?? [])
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

const movementLabels = {
    PURCHASE: 'Compra',
    SALE: 'Venta',
    DAMAGED: 'Producto danado',
    STOLEN: 'Producto robado',
    EXPIRED: 'Producto caducado',
    OTHER: 'Otros',
    TRANSFER: 'Transferencia',
    MANUAL: 'Ajuste manual',
    IN: 'Entrada',
    OUT: 'Salida',
    ADJUSTMENT: 'Ajuste manual',
}

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

function translateMovement(value) {
    return movementLabels[value] || value
}

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
    <div class="bg-[#f6f3f7] min-h-screen rounded-3xl p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[#1f1d2b]">
                    Movimientos de inventario
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Entradas, salidas, robos, danos y caducidades
                </p>
            </div>

            <button
                class="bg-[#1f1d2b] hover:bg-[#2a2738] text-white px-5 py-3 rounded-xl text-sm font-medium transition"
                @click="openModal"
            >
                Nuevo movimiento
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-100">
                        <tr class="text-left text-sm text-slate-600">
                            <th class="px-5 py-4 font-semibold">
                                Producto
                            </th>
                            <th class="px-5 py-4 font-semibold">
                                Sucursal
                            </th>
                            <th class="px-5 py-4 font-semibold">
                                Tipo
                            </th>
                            <th class="px-5 py-4 font-semibold">
                                Motivo
                            </th>
                            <th class="px-5 py-4 font-semibold">
                                Cantidad
                            </th>
                            <th class="px-5 py-4 font-semibold">
                                Stock anterior
                            </th>
                            <th class="px-5 py-4 font-semibold">
                                Nuevo stock
                            </th>
                            <th class="px-5 py-4 font-semibold">
                                Fecha
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="movement in movements"
                            :key="movement.id"
                            class="border-t border-slate-100 hover:bg-slate-50 transition"
                        >
                            <td class="px-5 py-4 font-medium text-slate-800">
                                {{ movement.branch_product?.product?.name }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.branch_product?.branch?.name }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-medium"
                                    :class="movement.type === 'IN'
                                        ? 'bg-green-100 text-green-700'
                                        : movement.type === 'OUT'
                                            ? 'bg-red-100 text-red-700'
                                            : 'bg-yellow-100 text-yellow-700'"
                                >
                                    {{ translateMovement(movement.type) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ translateMovement(movement.reason) }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.quantity }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.previous_stock }}
                            </td>

                            <td class="px-5 py-4 text-slate-600">
                                {{ movement.new_stock }}
                            </td>

                            <td class="px-5 py-4 text-slate-500 text-sm">
                                {{ movement.created_at }}
                            </td>
                        </tr>

                        <tr v-if="movements.length === 0">
                            <td colspan="8" class="text-center py-10 text-slate-400">
                                No hay movimientos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

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
