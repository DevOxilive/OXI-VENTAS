<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'

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

const emit = defineEmits([
    'close',
])

const isLowStock = computed(() => props.type === 'lowStock')

const hasItems = computed(() => props.batches.length > 0)

const subtitle = computed(() => {
    return isLowStock.value
        ? 'Listado de productos que requieren reposición de inventario'
        : 'Listado de lotes que requieren revisión'
})

const emptyText = computed(() => {
    return isLowStock.value
        ? 'No hay productos con stock bajo para mostrar.'
        : 'No hay lotes para mostrar.'
})

function closeModal() {
    emit('close')
}

function handleEscape(event) {
    if (event.key === 'Escape') {
        closeModal()
    }
}

function batchProductName(batch) {
    return batch.branch_product?.product?.name
        || batch.branch_product?.name
        || `Código: ${batch.branch_product?.barcode || `BP-${batch.branch_product_id}`}`
}

function batchExpirationDate(batch) {
    return batch.formatted_expiration_date
        || batch.expiration_date
        || 'Sin fecha'
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-5xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader :title="title" :subtitle="subtitle" :total-errors="0" mode="view" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <div v-if="hasItems" class="space-y-3">
                        <template v-if="isLowStock">
                            <article v-for="product in batches" :key="product.id"
                                class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">
                                            {{ product.name }}
                                        </p>

                                        <p class="text-xs text-slate-500 mt-1">
                                            Sucursal: {{ product.branch || 'No disponible' }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            Código: {{ product.code || `BP-${product.id}` }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Stock actual
                                            </p>

                                            <p class="font-black text-amber-700">
                                                {{ product.stock }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Stock mínimo
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ product.minStock }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Estado
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ product.status }}
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
                                            {{ batchProductName(batch) }}
                                        </p>

                                        <p class="text-xs text-slate-500 mt-1">
                                            Sucursal:
                                            {{ batch.branch_product?.branch?.name || 'No disponible' }}
                                        </p>

                                        <p class="text-xs text-slate-500">
                                            Lote:
                                            {{ batch.lot_number || 'Sin número de lote' }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500">
                                                Disponible
                                            </p>

                                            <p class="font-black text-slate-900">
                                                {{ batch.quantity }}
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
                                                {{ batch.expiration_human_text || 'Sin información' }}
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
            </GeneralModalContent>

            <GeneralModalFooter :processing="false" save-button-text="Cerrar" mode="view" @close="closeModal"
                @save="closeModal" />
        </div>
    </div>
</template>