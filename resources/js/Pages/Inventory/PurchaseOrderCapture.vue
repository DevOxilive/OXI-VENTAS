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
    { label: 'Pieza', value: 'Pieza' },
    { label: 'Caja', value: 'Caja' },
    { label: 'Kilo', value: 'Kilo' },
    { label: 'Costal', value: 'Costal' },
    { label: 'Bolsa', value: 'Bolsa' },
    { label: 'Paquete', value: 'Paquete' },
]
const form = reactive({
    purchased_at: props.orderDB.purchased_at || new Date().toISOString().slice(0, 10),
    items: (props.orderDB.items || []).map((item) => ({
        ...item,
        purchase_presentation: item.purchase_presentation || 'Pieza',
        package_quantity: number(item.package_quantity ?? item.requested_quantity),
        units_per_package: item.purchase_presentation === 'Caja'
            ? number(item.units_per_package) || 1
            : 1,
        purchase_price: number(item.purchase_price ?? item.previous_cost),
        promotion_status: item.purchase_notes ? 'YES' : 'NO',
        purchase_notes: item.purchase_notes || '',
        unavailable: Boolean(item.unavailable),
        image_failed: false,
    })),
})
const totalPurchased = computed(() => form.items.reduce(
    (total, item) => total + purchasedQuantity(item),
    0,
))
const branchSummary = computed(() => {
    const branches = new Map()

    for (const branch of props.orderDB.branches || []) {
        const branchId = Number(branch.id)
        const current = branches.get(branchId) || {
            id: branchId,
            name: branch.name || 'Sucursal',
            folios: [],
            productIds: new Set(),
        }

        if (branch.folio && !current.folios.includes(branch.folio)) {
            current.folios.push(branch.folio)
        }

        branches.set(branchId, current)
    }

    for (const item of form.items) {
        for (const branch of item.branch_breakdown || []) {
            const branchId = Number(branch.branch_id)
            const current = branches.get(branchId) || {
                id: branchId,
                name: branch.branch_name || 'Sucursal',
                folios: [],
                productIds: new Set(),
            }

            current.productIds.add(Number(item.product_id || item.id))

            if (branch.order_folio && !current.folios.includes(branch.order_folio)) {
                current.folios.push(branch.order_folio)
            }

            branches.set(branchId, current)
        }
    }

    return [...branches.values()]
        .map((branch) => ({ ...branch, products_count: branch.productIds.size }))
        .sort((first, second) => first.name.localeCompare(second.name, 'es'))
})
const toolbarConfig = computed(() => ({
    title: `Capturando ${props.orderDB.folio}`,
    subtitle: `${form.items.length} productos de ${branchSummary.value.length} sucursales.`,
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

function requiresBoxContent(item) {
    return item.purchase_presentation === 'Caja'
}

function presentationName(item) {
    return item.purchase_presentation || 'Pieza'
}

function quantityLabel(item) {
    return {
        Caja: 'Cajas compradas',
        Kilo: 'Kilos comprados',
        Costal: 'Costales comprados',
        Bolsa: 'Bolsas compradas',
        Paquete: 'Paquetes comprados',
        Pieza: 'Piezas compradas',
    }[presentationName(item)] || 'Cantidad comprada'
}

function quantitySuffix(item) {
    return {
        Caja: 'cajas',
        Kilo: 'kg',
        Costal: 'costales',
        Bolsa: 'bolsas',
        Paquete: 'paquetes',
        Pieza: 'pzas.',
    }[presentationName(item)] || ''
}

function purchasedQuantity(item) {
    if (item.unavailable) return 0

    return number(item.package_quantity) * (requiresBoxContent(item) ? number(item.units_per_package) : 1)
}

function togglePromotion(item) {
    const enabled = item.promotion_status !== 'YES'

    item.promotion_status = enabled ? 'YES' : 'NO'

    if (!enabled) {
        item.purchase_notes = ''
    }
}

function payload() {
    return {
        purchased_at: form.purchased_at,
        items: form.items.map((item) => ({
            id: item.id,
            purchase_presentation: presentationName(item),
            package_quantity: item.unavailable ? 0 : item.package_quantity,
            units_per_package: item.unavailable
                ? 0
                : (requiresBoxContent(item) ? item.units_per_package : 1),
            purchase_price: item.unavailable ? 0 : item.purchase_price,
            purchase_notes: item.promotion_status === 'YES' ? item.purchase_notes : null,
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
    router.get(route('inventory.branches.reports.purchase-orders', {
        branch: props.currentBranch.id,
        status: 'PURCHASING',
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
            errorMessage: 'Revisa las cantidades y los precios de compra capturados.',
        }),
    )
}

async function completeOrder() {
    const result = await confirmModalAction({
        mode: 'update',
        entityName: 'orden general',
        title: 'Completar compra general',
        message: 'Se actualizará el último costo de cada producto encontrado y las Órdenes de compra pasarán a revisión.',
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
            errorMessage: 'Revisa que cada producto tenga cantidad y precio de compra.',
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

        <div class="grid min-w-0 gap-5 2xl:grid-cols-[minmax(0,1fr)_320px]">
            <FormPanel
                title="Productos de la orden general"
                description="Registra únicamente lo que se encontró, la cantidad comprada y el nuevo precio."
                panel-class="min-w-0 shadow-none"
            >
                <div class="space-y-4">
                    <article
                        v-for="item in form.items"
                        :key="item.id"
                        class="grid min-w-0 gap-5 rounded-2xl border border-secondary bg-background p-4 xl:grid-cols-[minmax(280px,0.9fr)_minmax(0,1.35fr)]"
                    >
                        <section class="min-w-0 xl:border-r xl:border-secondary xl:pr-5">
                            <div class="flex min-w-0 gap-4">
                                <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-secondary bg-secondary">
                                    <img
                                        v-if="item.image_url && !item.image_failed"
                                        :src="item.image_url"
                                        :alt="item.product_name"
                                        class="h-full w-full object-cover"
                                        @error="item.image_failed = true"
                                    />
                                    <span v-else class="material-symbols-outlined text-4xl text-text opacity-35">inventory_2</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="text-base font-black leading-tight text-text">{{ item.product_name }}</p>
                                    <p class="mt-1 break-all text-xs text-text opacity-55">
                                        {{ item.product_code || 'Sin código' }}
                                    </p>
                                    <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-text opacity-65">
                                        {{ item.product_description || 'Sin descripción registrada.' }}
                                    </p>

                                    <div class="mt-3 space-y-1 text-sm text-text">
                                        <p>
                                            <span class="opacity-60">Precio anterior registrado:</span>
                                            <strong class="ml-1">{{ currency(item.previous_cost) }}</strong>
                                        </p>
                                        <p>
                                            <span class="opacity-60">Cantidad solicitada:</span>
                                            <strong class="ml-1">{{ quantity(item.requested_quantity) }} {{ item.base_unit || 'pzas.' }}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-secondary pt-3">
                                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-text opacity-50">Sucursales solicitantes</p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="branch in item.branch_breakdown"
                                        :key="`${item.id}_${branch.order_id || branch.branch_id}`"
                                        class="rounded-full border border-secondary bg-secondary px-3 py-1.5 text-xs font-semibold text-text"
                                    >
                                        {{ branch.branch_name }} · {{ quantity(branch.requested_quantity) }}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section class="min-w-0">
                            <div
                                class="grid gap-3"
                                :class="requiresBoxContent(item) ? 'md:grid-cols-2 2xl:grid-cols-4' : 'md:grid-cols-3'"
                            >
                                <SelectField
                                    v-model="item.purchase_presentation"
                                    :field="`general_order_${item.id}_presentation`"
                                    label="Presentación"
                                    :options="presentationOptions"
                                    :disabled="item.unavailable"
                                />
                                <InputField
                                    v-model="item.package_quantity"
                                    :field="`general_order_${item.id}_quantity`"
                                    validation-field="purchase_order_quantity"
                                    :label="quantityLabel(item)"
                                    type="text"
                                    inputmode="decimal"
                                    :suffix="quantitySuffix(item)"
                                    :show-counter="false"
                                    :disabled="item.unavailable"
                                />
                                <InputField
                                    v-if="requiresBoxContent(item)"
                                    v-model="item.units_per_package"
                                    :field="`general_order_${item.id}_box_content`"
                                    validation-field="purchase_order_quantity"
                                    label="Contenido por caja"
                                    type="text"
                                    inputmode="decimal"
                                    :suffix="item.base_unit || 'pzas.'"
                                    :show-counter="false"
                                    :disabled="item.unavailable"
                                />
                                <InputField
                                    v-model="item.purchase_price"
                                    :field="`general_order_${item.id}_purchase_price`"
                                    validation-field="purchase_order_cost"
                                    label="Precio de compra"
                                    type="text"
                                    inputmode="decimal"
                                    prefix="$"
                                    :show-counter="false"
                                    :disabled="item.unavailable"
                                />
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <SelectionCheckboxCard
                                    compact
                                    variant="soft"
                                    :checked="item.unavailable"
                                    title="No encontrado"
                                    @toggle="item.unavailable = !item.unavailable"
                                />
                                <SelectionCheckboxCard
                                    compact
                                    variant="soft"
                                    :checked="item.promotion_status === 'YES'"
                                    :disabled="item.unavailable"
                                    title="Descuento o promoción"
                                    @toggle="togglePromotion(item)"
                                />
                            </div>

                            <TextareaField
                                v-if="item.promotion_status === 'YES' && !item.unavailable"
                                v-model="item.purchase_notes"
                                class="mt-3"
                                :field="`general_order_${item.id}_purchase_notes`"
                                label="Nota del producto"
                                placeholder="Ej. promoción 3x2, cinco piezas de regalo o descuento indicado en el ticket"
                                :rows="3"
                            />

                            <div class="mt-4 rounded-2xl bg-secondary p-3 text-sm text-text">
                                <span class="block text-xs opacity-55">Cantidad total comprada</span>
                                <strong class="text-base">
                                    {{ quantity(purchasedQuantity(item)) }} {{ item.base_unit || 'pzas.' }}
                                </strong>
                            </div>
                        </section>
                    </article>
                </div>
            </FormPanel>

            <aside class="min-w-0 space-y-4 2xl:sticky 2xl:top-5 2xl:self-start">
                <FormPanel title="Sucursales participantes" panel-class="shadow-none">
                    <div class="space-y-2">
                        <div
                            v-for="branch in branchSummary"
                            :key="branch.id"
                            class="rounded-xl border border-secondary bg-secondary p-3 text-sm text-text"
                        >
                            <p class="font-bold">{{ branch.name }}</p>
                            <p class="mt-0.5 text-xs opacity-55">{{ branch.folios.join(', ') || 'Orden de compra incluida' }}</p>
                            <p class="mt-2 font-semibold">
                                {{ branch.products_count }} {{ branch.products_count === 1 ? 'producto' : 'productos' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-secondary pt-4 text-sm text-text">
                        <span class="block text-xs opacity-60">Cantidad total comprada</span>
                        <strong class="mt-1 block text-xl">{{ quantity(totalPurchased) }}</strong>
                    </div>
                </FormPanel>

                <div class="grid gap-2">
                    <AppButton block variant="secondary" @click="saveOrder">Guardar avance</AppButton>
                    <AppButton block @click="completeOrder">Completar compra</AppButton>
                </div>
            </aside>
        </div>
    </PageLayout>
</template>
