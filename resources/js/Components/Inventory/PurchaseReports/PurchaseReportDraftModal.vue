<script setup>
import { computed, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { getPurchaseReportDraftModalConfig } from '@/config/ModalConfigs/purchaseReportDraftModalConfig'

const props = defineProps({
    report: { type: Object, required: true },
    branchName: { type: String, default: '' },
})

const emit = defineEmits(['close', 'edit'])
const form = reactive({ notes: '', items: [] })
const isDraft = computed(() => props.report?.status === 'DRAFT')
const isGenerated = computed(() => props.report?.status === 'GENERATED')
const canCapture = computed(() => isGenerated.value)
const estimatedTotal = computed(() => form.items.reduce(
    (total, item) => total + Number(item.estimated_total || 0),
    0,
))
const actualTotal = computed(() => form.items.reduce((total, item) => {
    if (item.unavailable) return total

    const gross = Number(item.purchased_quantity || 0) * Number(item.actual_price || 0)
    return total + Math.max(0, gross - Number(item.discount_amount || 0))
}, 0))
const modalConfig = computed(() => getPurchaseReportDraftModalConfig({
    report: props.report,
    branchName: props.branchName,
    itemCount: form.items.length,
    estimatedTotal: formatCurrency(estimatedTotal.value),
    actualTotal: formatCurrency(actualTotal.value),
}))

watch(() => props.report, syncForm, { immediate: true })

function syncForm() {
    form.notes = props.report?.notes ?? ''
    form.items = (props.report?.items ?? []).map((item) => {
        const product = item.branch_product?.product ?? {}
        const systemCost = Number(product.cost || item.estimated_price || 0)
        const savedCost = Number(item.actual_price || 0)
        const discountAmount = Number(item.discount_amount || 0)

        return {
            branch_product_id: item.branch_product_id,
            name: product.name || 'Producto sin nombre',
            code: product.barcodes?.[0]?.code || item.branch_product?.barcode || '',
            requested_quantity: Number(item.requested_quantity || 0),
            purchased_quantity: Number(item.purchased_quantity ?? item.requested_quantity ?? 0),
            estimated_price: systemCost,
            estimated_total: Number(item.estimated_total || (systemCost * Number(item.requested_quantity || 0))),
            actual_price: savedCost > 0 ? savedCost : systemCost,
            discount_amount: discountAmount,
            has_discount: discountAmount > 0,
            unavailable: item.status === 'UNAVAILABLE',
        }
    })
}

function formatCurrency(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0))
}

function lineTotal(item) {
    if (item.unavailable) return 0

    const gross = Number(item.purchased_quantity || 0) * Number(item.actual_price || 0)
    return Math.max(0, gross - Number(item.discount_amount || 0))
}

function grossTotal(item) {
    if (item.unavailable) return 0

    return Number(item.purchased_quantity || 0) * Number(item.actual_price || 0)
}

function toggleDiscount(item) {
    item.has_discount = !item.has_discount

    if (!item.has_discount) {
        item.discount_amount = 0
    }
}

function payload() {
    return {
        notes: form.notes,
        items: form.items.map((item) => ({
            branch_product_id: item.branch_product_id,
            requested_quantity: item.requested_quantity,
            purchased_quantity: item.unavailable ? 0 : item.purchased_quantity,
            actual_price: item.unavailable ? 0 : item.actual_price,
            discount_amount: item.unavailable || !item.has_discount ? 0 : item.discount_amount,
            unavailable: item.unavailable,
        })),
    }
}

function routeParams() {
    return {
        branch: props.report.branch_id,
        purchaseReport: props.report.id,
    }
}

function updateReport() {
    router.put(
        route('inventory.branches.purchase-reports.update', routeParams()),
        payload(),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Orden de compra',
            successTitle: 'Orden de compra actualizada correctamente',
            errorTitle: 'No se pudo actualizar la orden',
            errorMessage: 'Revisa las cantidades, costos y descuentos capturados.',
            onSuccess: () => emit('close'),
        }),
    )
}

async function generateReport() {
    const result = await confirmModalAction({
        mode: 'create',
        entityName: 'orden de compra',
        title: 'Generar orden de compra',
        message: 'El borrador pasará al seguimiento de órdenes de compra. ¿Deseas continuar?',
        confirmText: 'Generar orden',
    })

    if (!result.isConfirmed) return

    router.post(
        route('inventory.branches.purchase-reports.generate', routeParams()),
        payload(),
        getModalRequestOptions({
            mode: 'create',
            entityName: 'Orden de compra',
            successTitle: 'Orden de compra generada correctamente',
            errorTitle: 'No se pudo generar la orden',
            errorMessage: 'Revisa la información de la lista y vuelve a intentarlo.',
            onSuccess: () => emit('close'),
        }),
    )
}

async function completeReport() {
    const result = await confirmModalAction({
        mode: 'update',
        entityName: 'orden de compra',
        title: 'Completar compra',
        message: 'La orden quedará cerrada con las cantidades y costos capturados. ¿Deseas continuar?',
        confirmText: 'Completar compra',
    })

    if (!result.isConfirmed) return

    router.post(
        route('inventory.branches.purchase-reports.complete', routeParams()),
        payload(),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Orden de compra',
            successTitle: 'Compra completada correctamente',
            errorTitle: 'No se pudo completar la compra',
            errorMessage: 'Revisa las cantidades, costos y descuentos capturados.',
            onSuccess: () => emit('close'),
        }),
    )
}

async function deleteDraft() {
    const result = await confirmModalAction({
        mode: 'delete',
        entityName: 'borrador',
        title: 'Eliminar borrador',
        message: 'Esta acción eliminará el borrador y sus productos. No se puede deshacer.',
        confirmText: 'Eliminar borrador',
    })

    if (!result.isConfirmed) return

    router.delete(
        route('inventory.branches.purchase-reports.destroy', routeParams()),
        getModalRequestOptions({
            mode: 'delete',
            entityName: 'Borrador',
            successTitle: 'Borrador eliminado correctamente',
            errorTitle: 'No se pudo eliminar el borrador',
            errorMessage: 'Actualiza la página y vuelve a intentarlo.',
            onSuccess: () => emit('close'),
        }),
    )
}
</script>

<template>
    <GlobalModal v-bind="modalConfig" @close="$emit('close')">
        <FormPanel
            title="Productos"
            :description="isGenerated ? 'Captura solamente lo que realmente se compro.' : 'Detalle de productos incluidos en la orden.'"
            panel-class="shadow-none"
        >
            <div class="space-y-2">
                <article
                    v-for="item in form.items"
                    :key="item.branch_product_id"
                    class="rounded-2xl border border-secondary bg-background p-4"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-base font-bold text-text">{{ item.name }}</p>
                            <p class="mt-0.5 truncate text-xs text-text opacity-60">{{ item.code || 'Sin codigo' }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-text">
                            <span class="rounded-full border border-secondary bg-secondary px-3 py-1.5 font-semibold">
                                Solicitadas: {{ item.requested_quantity }} piezas
                            </span>
                            <span class="rounded-full border border-secondary bg-secondary px-3 py-1.5 font-semibold">
                                Costo estimado: {{ formatCurrency(item.estimated_price) }} c/u
                            </span>
                        </div>
                    </div>

                    <div v-if="isDraft" class="mt-4 flex items-center justify-between border-t border-secondary pt-3 text-sm text-text">
                        <span class="opacity-70">Importe estimado</span>
                        <strong>{{ formatCurrency(item.estimated_total) }}</strong>
                    </div>

                    <div
                        v-else
                        class="mt-4 grid gap-4 border-t border-secondary pt-4 xl:grid-cols-[minmax(0,1fr)_300px]"
                    >
                        <div class="grid gap-3 md:grid-cols-3">
                            <InputField
                                v-model="item.purchased_quantity"
                                :field="`purchase_order_${item.branch_product_id}_purchased_quantity`"
                                validation-field="purchase_order_quantity"
                                label="Pzas. compradas"
                                type="text"
                                inputmode="numeric"
                                :disabled="!canCapture || item.unavailable"
                            />

                            <InputField
                                v-model="item.actual_price"
                                :field="`purchase_order_${item.branch_product_id}_actual_price`"
                                validation-field="purchase_order_cost"
                                label="Costo"
                                type="text"
                                inputmode="decimal"
                                prefix="$"
                                :disabled="!canCapture || item.unavailable"
                            />

                            <InputField
                                v-model="item.discount_amount"
                                :field="`purchase_order_${item.branch_product_id}_discount_amount`"
                                validation-field="purchase_order_discount"
                                label="Descuento"
                                type="text"
                                inputmode="decimal"
                                prefix="$"
                                :disabled="!canCapture || item.unavailable || !item.has_discount"
                            />
                        </div>

                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <SelectionCheckboxCard
                                    compact
                                    variant="soft"
                                    :checked="item.unavailable"
                                    :disabled="!canCapture"
                                    title="No encontrado"
                                    @toggle="item.unavailable = !item.unavailable"
                                />

                                <SelectionCheckboxCard
                                    compact
                                    variant="soft"
                                    :checked="item.has_discount"
                                    :disabled="!canCapture || item.unavailable"
                                    title="Con descuento"
                                    @toggle="toggleDiscount(item)"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3 rounded-xl border border-secondary bg-secondary p-3 text-sm text-text">
                                <div>
                                    <span class="block text-xs opacity-60">Subtotal</span>
                                    <strong>{{ formatCurrency(grossTotal(item)) }}</strong>
                                </div>
                                <div class="text-right">
                                    <span class="block text-xs opacity-60">Importe final</span>
                                    <strong class="text-base">{{ formatCurrency(lineTotal(item)) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <p v-if="!form.items.length" class="p-6 text-center text-sm text-text opacity-70">
                    Esta orden no tiene productos.
                </p>
            </div>
        </FormPanel>

        <FormPanel title="Notas generales" panel-class="shadow-none">
            <p class="whitespace-pre-wrap text-sm text-text" :class="form.notes ? '' : 'opacity-60'">
                {{ form.notes || 'Sin notas generales.' }}
            </p>
        </FormPanel>

        <template #footer>
            <footer class="flex w-full flex-wrap justify-end gap-2 border-t border-secondary bg-background p-3">
                <AppButton v-if="isDraft" variant="danger" @click="deleteDraft">Eliminar</AppButton>
                <AppButton v-if="isDraft" variant="secondary" @click="$emit('edit', report)">Editar</AppButton>
                <AppButton v-if="isDraft" @click="generateReport">Generar orden</AppButton>
                <AppButton v-if="isGenerated" variant="secondary" @click="updateReport">Guardar</AppButton>
                <AppButton v-if="isGenerated" @click="completeReport">Completar compra</AppButton>
                <AppButton variant="secondary" @click="$emit('close')">Cerrar</AppButton>
            </footer>
        </template>
    </GlobalModal>
</template>
