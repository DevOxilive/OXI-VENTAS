<script setup>
import { computed } from 'vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getInventoryAlertsModalConfig } from '@/config/ModalConfigs/inventoryAlertsModalConfig'

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: '',
    },
    batches: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close'])

const numberFormatter = new Intl.NumberFormat('es-MX', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})

const longDateFormatter = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
})

const isLowStock = computed(() => props.type === 'lowStock')
const isInactiveCandidates = computed(() => props.type === 'inactiveCandidates')
const isProductAlert = computed(() => isLowStock.value || isInactiveCandidates.value)
const hasItems = computed(() => props.batches.length > 0)

const subtitle = computed(() => {
    if (isLowStock.value) {
        return 'Productos que requieren reposicion para mantener inventario sano.'
    }

    if (isInactiveCandidates.value) {
        return 'Productos con baja rotacion para revisar demanda, resurtido o permanencia.'
    }

    return 'Lotes que requieren revision prioritaria.'
})

const modalConfig = computed(() => getInventoryAlertsModalConfig({
    title: props.title,
    subtitle: subtitle.value,
}))

const emptyText = computed(() => {
    if (isLowStock.value) {
        return 'No hay productos con stock bajo para mostrar.'
    }

    if (isInactiveCandidates.value) {
        return 'No hay productos sin rotacion para mostrar.'
    }

    return 'No hay lotes para mostrar.'
})

const productCardClass = computed(() => {
    if (isInactiveCandidates.value) {
        return 'border-purple-200 bg-purple-50'
    }

    if (isLowStock.value) {
        return 'border-amber-200 bg-amber-50'
    }

    if (props.type === 'nearExpiration') {
        return 'border-orange-200 bg-orange-50'
    }

    if (props.type === 'expired') {
        return 'border-red-200 bg-red-50'
    }

    return 'border-slate-200 bg-white'
})

const detailCellClass = computed(() => {
    if (isInactiveCandidates.value) {
        return 'border-purple-100 bg-white'
    }

    if (isLowStock.value) {
        return 'border-amber-100 bg-white'
    }

    if (props.type === 'nearExpiration') {
        return 'border-orange-100 bg-orange-100/60'
    }

    if (props.type === 'expired') {
        return 'border-red-100 bg-red-100/60'
    }

    return 'border-slate-100 bg-slate-50/80'
})

const detailLabelClass = computed(() => {
    if (isInactiveCandidates.value) {
        return 'text-purple-500'
    }

    if (isLowStock.value) {
        return 'text-amber-500'
    }

    if (props.type === 'nearExpiration') {
        return 'text-orange-500'
    }

    if (props.type === 'expired') {
        return 'text-red-500'
    }

    return 'text-slate-400'
})

function cardToneStyle() {
    if (isInactiveCandidates.value) {
        return {
            backgroundColor: '#faf5ff',
            borderColor: '#d8b4fe',
        }
    }

    if (isLowStock.value) {
        return {
            backgroundColor: '#fffbeb',
            borderColor: '#fcd34d',
        }
    }

    if (props.type === 'nearExpiration') {
        return {
            backgroundColor: '#fff7ed',
            borderColor: '#fdba74',
        }
    }

    if (props.type === 'expired') {
        return {
            backgroundColor: '#fef2f2',
            borderColor: '#fca5a5',
        }
    }

    return {}
}

function detailToneStyle() {
    if (isInactiveCandidates.value) {
        return {
            backgroundColor: '#ffffff',
            borderColor: '#e9d5ff',
        }
    }

    if (isLowStock.value) {
        return {
            backgroundColor: '#ffffff',
            borderColor: '#fde68a',
        }
    }

    if (props.type === 'nearExpiration') {
        return {
            backgroundColor: '#fffaf5',
            borderColor: '#fed7aa',
        }
    }

    if (props.type === 'expired') {
        return {
            backgroundColor: '#fff5f5',
            borderColor: '#fecaca',
        }
    }

    return {}
}

function closeModal() {
    emit('close')
}

function parseDateValue(value) {
    if (!value) return null

    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return value
    }

    if (typeof value === 'string') {
        const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/)

        if (match) {
            const [, year, month, day] = match
            return new Date(Number(year), Number(month) - 1, Number(day))
        }
    }

    const parsed = new Date(value)
    return Number.isNaN(parsed.getTime()) ? null : parsed
}

function formatLongDate(value, fallback = 'Sin fecha') {
    const parsedDate = parseDateValue(value)

    if (!parsedDate) {
        if (typeof value === 'string' && value.trim() !== '') {
            return value
        }

        return fallback
    }

    return longDateFormatter.format(parsedDate)
}

function formatQuantity(value) {
    return numberFormatter.format(Number(value ?? 0))
}

function productName(item) {
    if (isProductAlert.value) {
        return item.name || item.code || 'Producto sin nombre'
    }

    return item.product_name || item.product_code || 'Producto sin nombre'
}

function productCode(item) {
    if (isProductAlert.value) {
        return item.code || `BP-${item.id}`
    }

    return item.product_code || `BP-${item.branch_product_id || item.id}`
}

function productStock(item) {
    return formatQuantity(item.stock)
}

function productMinStock(item) {
    return formatQuantity(item.minStock ?? item.min_stock)
}

function productStatus(item) {
    if (isInactiveCandidates.value) {
        return item.status || 'Producto sin rotacion'
    }

    return item.status || 'Stock bajo'
}

function productLastRestockedAt(item) {
    return formatLongDate(item.last_restocked_at, 'Sin registro')
}

function productInactiveDays(item) {
    return `${item.inactive_candidate_after_days ?? 90} dias`
}

function batchLotNumber(batch) {
    if (
        batch.lot_number === null ||
        batch.lot_number === undefined ||
        batch.lot_number === ''
    ) {
        return 'Sin numero de lote'
    }

    return batch.lot_number
}

function batchExpirationDate(batch) {
    return formatLongDate(
        batch.expiration_date ?? batch.formatted_expiration_date,
        'Sin fecha',
    )
}

function batchQuantity(batch) {
    return formatQuantity(batch.quantity)
}

function batchStatus(batch) {
    return batch.expiration_human_text
        || batch.expiration_status
        || batch.status
        || 'Sin informacion'
}
</script>

<template>
    <GlobalModal v-bind="modalConfig" @close="closeModal">
        <section class="p-1 sm:p-2">
            <div v-if="hasItems" class="space-y-3">
                <div class="space-y-3">
                    <template v-if="isProductAlert">
                        <article v-for="product in batches" :key="product.id"
                            class="rounded-2xl border p-3 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                            :class="productCardClass" :style="cardToneStyle()">
                            <div class="inventory-alert-row">
                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Producto
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ productName(product) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Codigo
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ productCode(product) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        {{ isInactiveCandidates ? 'Rotacion' : 'Minimo' }}
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ isInactiveCandidates ? productInactiveDays(product) : productMinStock(product) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Stock
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ productStock(product) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        {{ isInactiveCandidates ? 'Ultimo surtido' : 'Fecha' }}
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ isInactiveCandidates ? productLastRestockedAt(product) : 'Revisar resurtido' }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Estado
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ productStatus(product) }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </template>

                    <template v-else>
                        <article v-for="batch in batches" :key="batch.id"
                            class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                            :style="cardToneStyle()">
                            <div class="inventory-alert-row">
                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Producto
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ productName(batch) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Codigo
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ productCode(batch) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Lote
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ batchLotNumber(batch) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Stock
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ batchQuantity(batch) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Fecha
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ batchExpirationDate(batch) }}
                                    </p>
                                </div>

                                <div class="min-w-0 px-1 py-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.14em]" :class="detailLabelClass">
                                        Estado
                                    </p>

                                    <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-900">
                                        {{ batchStatus(batch) }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 p-8 text-center">
                <p class="text-sm font-bold text-slate-600">
                    {{ emptyText }}
                </p>
            </div>
        </section>
    </GlobalModal>
</template>

<style scoped>
.inventory-alert-row {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (min-width: 768px) {
    .inventory-alert-row {
        align-items: start;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }
}
</style>
