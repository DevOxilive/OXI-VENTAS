<script setup>
import { computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: { type: Object, required: true },
    orderDB: { type: Object, required: true },
})

const presentationOptions = [
    { label: 'Paquete', value: 'Paquete' },
    { label: 'Caja', value: 'Caja' },
    { label: 'Bolsa', value: 'Bolsa' },
    { label: 'Kilo', value: 'Kilo' },
    { label: 'Costal', value: 'Costal' },
    { label: 'Pieza', value: 'Pieza' },
]
const form = reactive({
    purchased_at: props.orderDB.purchased_at || new Date().toISOString().slice(0, 10),
    notes: props.orderDB.notes || '',
    items: (props.orderDB.items || []).map((item) => ({
        ...item,
        package_quantity: number(item.package_quantity),
        units_per_package: number(item.units_per_package) || 1,
        package_price: number(item.package_price),
        actual_total: number(item.actual_total),
        unavailable: Boolean(item.unavailable),
        promotion_notes: item.promotion_notes || '',
    })),
})
const actualTotal = computed(() => form.items.reduce(
    (total, item) => total + (item.unavailable ? 0 : number(item.actual_total)),
    0,
))
const grossTotal = computed(() => form.items.reduce((total, item) => total + lineGross(item), 0))
const discountTotal = computed(() => Math.max(0, grossTotal.value - actualTotal.value))
const toolbarConfig = computed(() => ({
    title: `Capturando ${props.orderDB.folio}`,
    subtitle: `${form.items.length} productos de ${props.orderDB.branches?.length || 0} sucursales.`,
    backButton: true,
    backLabel: 'Seguimiento',
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    actions: [],
    tabs: [],
}))

function number(value) {
    return Number(value || 0)
}

function quantity(value) {
    return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(number(value))
}

function currency(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(number(value))
}

function lineGross(item) {
    return item.unavailable ? 0 : number(item.package_quantity) * number(item.package_price)
}

function receivedUnits(item) {
    return item.unavailable ? 0 : number(item.package_quantity) * number(item.units_per_package)
}

function lineDiscount(item) {
    return Math.max(0, lineGross(item) - number(item.actual_total))
}

function netUnitCost(item) {
    const units = receivedUnits(item)
    return units > 0 ? number(item.actual_total) / units : 0
}

function payload() {
    return {
        purchased_at: form.purchased_at,
        notes: form.notes,
        items: form.items.map((item) => ({
            id: item.id,
            purchase_presentation: item.purchase_presentation,
            package_quantity: item.unavailable ? 0 : item.package_quantity,
            units_per_package: item.unavailable ? 0 : item.units_per_package,
            package_price: item.unavailable ? 0 : item.package_price,
            actual_total: item.unavailable ? 0 : item.actual_total,
            promotion_notes: item.unavailable ? null : item.promotion_notes,
            unavailable: item.unavailable,
        })),
    }
}

function routeParams() {
    return {
        branch: props.currentBranch.id,
        generalPurchaseOrder: props.orderDB.id,
    }
}

function backToTracking() {
    router.get(route('inventory.branches.reports.purchase-orders.tracking', {
        branch: props.currentBranch.id,
    }))
}

function saveOrder() {
    router.put(
        route('inventory.branches.reports.purchase-orders.update', routeParams()),
        payload(),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Orden general',
            successTitle: 'Orden general actualizada correctamente',
            errorTitle: 'No se pudo actualizar la orden general',
            errorMessage: 'Revisa presentaciones, cantidades, costos y totales pagados.',
        }),
    )
}

async function completeOrder() {
    const result = await confirmModalAction({
        mode: 'update',
        entityName: 'orden general',
        title: 'Completar compra general',
        message: 'La compra quedara cerrada y pasara al historial. Esta accion no modifica el stock.',
        confirmText: 'Completar compra',
    })

    if (!result.isConfirmed) return

    router.post(
        route('inventory.branches.reports.purchase-orders.complete', routeParams()),
        payload(),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Compra general',
            successTitle: 'Compra general completada correctamente',
            errorTitle: 'No se pudo completar la compra general',
            errorMessage: 'Revisa que todos los productos tengan cantidades y costos validos.',
            onSuccess: backToTracking,
        }),
    )
}
</script>

<template>
    <Head :title="`Capturar ${orderDB.folio}`" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar v-bind="toolbarConfig" @back="backToTracking" />
        </template>

        <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
            <FormPanel title="Productos comprados" panel-class="min-w-0 shadow-none">
                <div class="space-y-3">
                    <article
                        v-for="item in form.items"
                        :key="item.id"
                        class="rounded-2xl border border-secondary bg-background p-4"
                    >
                        <div class="flex flex-col gap-2 border-b border-secondary pb-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-text">{{ item.product_name }}</p>
                                <p class="mt-0.5 truncate text-xs text-text opacity-55">
                                    {{ item.product_code || 'Sin codigo' }} · {{ item.base_unit || 'pieza' }}
                                </p>
                            </div>
                            <strong class="shrink-0 text-sm text-text">{{ quantity(item.requested_quantity) }} solicitadas</strong>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2 2xl:grid-cols-[140px_repeat(4,minmax(120px,1fr))]">
                            <SelectField
                                v-model="item.purchase_presentation"
                                :field="`general_order_${item.id}_presentation`"
                                label="Presentacion"
                                :options="presentationOptions"
                                :disabled="item.unavailable"
                            />
                            <InputField
                                v-model="item.package_quantity"
                                :field="`general_order_${item.id}_packages`"
                                validation-field="purchase_order_quantity"
                                label="Cantidad"
                                type="text"
                                inputmode="decimal"
                                :show-counter="false"
                                :disabled="item.unavailable"
                            />
                            <InputField
                                v-model="item.units_per_package"
                                :field="`general_order_${item.id}_units`"
                                validation-field="purchase_order_quantity"
                                label="Contenido"
                                type="text"
                                inputmode="decimal"
                                :suffix="item.base_unit || 'pzas.'"
                                :show-counter="false"
                                :disabled="item.unavailable"
                            />
                            <InputField
                                v-model="item.package_price"
                                :field="`general_order_${item.id}_package_price`"
                                validation-field="purchase_order_cost"
                                label="Precio"
                                type="text"
                                inputmode="decimal"
                                prefix="$"
                                :show-counter="false"
                                :disabled="item.unavailable"
                            />
                            <InputField
                                v-model="item.actual_total"
                                :field="`general_order_${item.id}_actual_total`"
                                validation-field="purchase_order_cost"
                                label="Pagado"
                                type="text"
                                inputmode="decimal"
                                prefix="$"
                                :show-counter="false"
                                :disabled="item.unavailable"
                            />
                        </div>

                        <div class="mt-3 grid items-end gap-3 xl:grid-cols-[minmax(0,1fr)_170px_300px]">
                            <InputField
                                v-model="item.promotion_notes"
                                :field="`general_order_${item.id}_promotion`"
                                validation-field="purchase_promotion"
                                label="Promocion"
                                placeholder="Ej. 3x2"
                                :show-counter="false"
                                :disabled="item.unavailable"
                            />
                            <SelectionCheckboxCard
                                compact
                                variant="soft"
                                :checked="item.unavailable"
                                title="No encontrado"
                                @toggle="item.unavailable = !item.unavailable"
                            />
                            <div class="flex min-h-[46px] flex-wrap items-center justify-between gap-2 rounded-xl bg-secondary px-3 py-2 text-xs text-text">
                                <span><strong>{{ quantity(receivedUnits(item)) }}</strong> recibidas</span>
                                <span><strong>{{ currency(lineDiscount(item)) }}</strong> desc.</span>
                                <span><strong>{{ currency(netUnitCost(item)) }}</strong> c/u</span>
                            </div>
                        </div>
                    </article>
                </div>
            </FormPanel>

            <aside class="space-y-4">
                <FormPanel title="Resumen" panel-class="shadow-none">
                    <InputField v-model="form.purchased_at" field="general_purchase_date" label="Fecha" type="date" />
                    <div class="mt-4 space-y-2 border-t border-secondary pt-4 text-sm text-text">
                        <div class="flex justify-between gap-3"><span class="opacity-65">Importe</span><strong>{{ currency(grossTotal) }}</strong></div>
                        <div class="flex justify-between gap-3"><span class="opacity-65">Descuento</span><strong>{{ currency(discountTotal) }}</strong></div>
                        <div class="flex justify-between gap-3 text-base"><span>Pagado</span><strong>{{ currency(actualTotal) }}</strong></div>
                    </div>
                </FormPanel>

                <FormPanel title="Notas" panel-class="shadow-none">
                    <TextareaField v-model="form.notes" field="general_purchase_notes" placeholder="Observaciones" :rows="3" />
                </FormPanel>

                <div class="grid gap-2">
                    <AppButton block variant="secondary" @click="saveOrder">Guardar avance</AppButton>
                    <AppButton block @click="completeOrder">Completar compra</AppButton>
                </div>
            </aside>
        </div>
    </PageLayout>
</template>
