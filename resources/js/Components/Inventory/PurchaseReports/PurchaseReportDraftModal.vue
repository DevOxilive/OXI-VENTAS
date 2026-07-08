<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getPurchaseReportDraftModalConfig } from '@/config/ModalConfigs/purchaseReportDraftModalConfig'

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close'])

const items = computed(() => props.report?.items ?? [])

const modalConfig = computed(() => getPurchaseReportDraftModalConfig({
    report: props.report,
}))

function closeModal() {
    emit('close')
}
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @close="closeModal"
    >
        <div>
            <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Productos
                    </p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">
                        {{ items.length }}
                    </p>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Estado
                    </p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">
                        {{ report.status }}
                    </p>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase text-slate-500">
                        Folio
                    </p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">
                        {{ report.folio || 'N/A' }}
                    </p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <table class="hidden min-w-full divide-y divide-slate-200 md:table">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                Producto
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">
                                Stock
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">
                                Cantidad
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                Nota
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in items" :key="item.id">
                            <td class="px-4 py-3">
                                <p class="text-sm font-bold text-slate-900">
                                    {{ item.branch_product?.product?.name || 'Producto sin nombre' }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ item.branch_product?.product?.code || 'Sin código' }}
                                    |
                                    {{ item.branch_product?.product?.barcodes?.[0]?.barcode || 'Sin barcode' }}
                                </p>
                            </td>

                            <td class="px-4 py-3 text-right text-sm text-slate-700">
                                {{ item.current_stock }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm font-bold text-slate-900">
                                {{ item.requested_quantity }}
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ item.notes || 'Sin nota' }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="divide-y divide-slate-100 md:hidden">
                    <div v-for="item in items" :key="item.id" class="p-4">
                        <p class="font-bold text-slate-900">
                            {{ item.branch_product?.product?.name || 'Producto sin nombre' }}
                        </p>

                        <p class="text-xs text-slate-500">
                            {{ item.branch_product?.product?.code || 'Sin código' }}
                            |
                            {{ item.branch_product?.product?.barcodes?.[0]?.barcode || 'Sin barcode' }}
                        </p>

                        <p class="mt-2 text-sm text-slate-600">
                            Stock: <b>{{ item.current_stock }}</b>
                            |
                            Cantidad: <b>{{ item.requested_quantity }}</b>
                        </p>

                        <p class="mt-2 text-xs text-slate-500">
                            {{ item.notes || 'Sin nota' }}
                        </p>
                    </div>
                </div>

                <div v-if="!items.length" class="p-8 text-center">
                    <p class="text-sm font-semibold text-slate-700">
                        Este borrador no tiene productos.
                    </p>
                </div>
            </div>
        </div>
    </GlobalModal>
</template>
