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

const isLowStock = computed(() => props.type === 'lowStock')
const isInactiveCandidates = computed(() => props.type === 'inactiveCandidates')
const isProductAlert = computed(() => isLowStock.value || isInactiveCandidates.value)
const hasItems = computed(() => props.batches.length > 0)

const subtitle = computed(() => {
    if (isLowStock.value) {
        return 'Listado de productos que requieren reposición de inventario'
    }

    if (isInactiveCandidates.value) {
        return 'Listado de productos sin surtido reciente que podrían revisarse para inactivar'
    }

    return 'Listado de lotes que requieren revisión'
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
        return 'No hay productos candidatos a inactivar para mostrar.'
    }

    return 'No hay lotes para mostrar.'
})

const productCardClass = computed(() => {
    if (isInactiveCandidates.value) {
        return 'border-purple-200 bg-purple-50'
    }

    return 'border-amber-200 bg-amber-50'
})

const productValueClass = computed(() => {
    if (isInactiveCandidates.value) {
        return 'text-purple-700'
    }

    return 'text-amber-700'
})

function closeModal() {
    emit('close')
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
    return item.stock ?? 0
}

function productMinStock(item) {
    return item.minStock ?? item.min_stock ?? 0
}

function productStatus(item) {
    if (isInactiveCandidates.value) {
        return item.status || 'Sin surtir recientemente'
    }

    return item.status || 'Stock bajo'
}

function productLastRestockedAt(item) {
    return item.last_restocked_at || 'Sin registro'
}

function productInactiveDays(item) {
    return item.inactive_candidate_after_days ?? 90
}

function batchLotNumber(batch) {
    if (
        batch.lot_number === null ||
        batch.lot_number === undefined ||
        batch.lot_number === ''
    ) {
        return 'Sin número de lote'
    }

    return batch.lot_number
}

function batchExpirationDate(batch) {
    return batch.formatted_expiration_date
        || batch.expiration_date
        || 'Sin fecha'
}

function batchQuantity(batch) {
    return batch.quantity ?? 0
}

function batchStatus(batch) {
    return batch.expiration_human_text
        || batch.expiration_status
        || batch.status
        || 'Sin información'
}

</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @close="closeModal"
    >
                <section class="p-1 sm:p-2">
                    <div v-if="hasItems" class="space-y-3">
                        <template v-if="isProductAlert">
                            <article v-for="product in batches" :key="product.id" class="rounded-2xl border p-4"
                                :class="productCardClass">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">
                                            {{ productName(product) }}
                                        </p>

                                        <p class="text-xs text-slate-500 mt-1">
                                            Código: {{ productCode(product) }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Stock actual
                                            </p>

                                            <p class="font-black" :class="productValueClass">
                                                {{ productStock(product) }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                {{ isInactiveCandidates ? 'Último surtido' : 'Stock mínimo' }}
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ isInactiveCandidates ? productLastRestockedAt(product) :
                                                productMinStock(product) }}
                                            </p>
                                        </div>

                                        <div v-if="isInactiveCandidates">
                                            <p class="text-xs text-slate-500">
                                                Días límite
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ productInactiveDays(product) }} días
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Estado
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ productStatus(product) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </template>

                        <template v-else>
                            <article v-for="batch in batches" :key="batch.id"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">
                                            {{ productName(batch) }}
                                        </p>

                                        <p class="text-xs text-slate-500 mt-1">
                                            Código: {{ productCode(batch) }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            Lote: {{ batchLotNumber(batch) }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Disponible
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ batchQuantity(batch) }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Caducidad
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ batchExpirationDate(batch) }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Estado
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ batchStatus(batch) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-slate-300 p-8 text-center">
                        <p class="text-sm font-bold text-slate-600">
                            {{ emptyText }}
                        </p>
                    </div>
                </section>
    </GlobalModal>
</template>
