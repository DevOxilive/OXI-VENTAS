<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { getGeneralPurchaseOrderModalConfig } from '@/config/ModalConfigs/generalPurchaseOrderModalConfig'

const props = defineProps({
    order: { type: Object, required: true },
})

defineEmits(['close'])

const completed = computed(() => props.order.status === 'COMPLETED')
const modalConfig = computed(() => getGeneralPurchaseOrderModalConfig({
    order: props.order,
    branchCount: props.order.branches?.length || 0,
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
</script>

<template>
    <GlobalModal v-bind="modalConfig" @close="$emit('close')">
        <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_270px]">
            <FormPanel title="Productos" panel-class="min-w-0 shadow-none">
                <div class="divide-y divide-secondary rounded-2xl border border-secondary bg-background">
                    <article
                        v-for="item in order.items"
                        :key="item.id"
                        class="p-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-text">{{ item.product_name }}</p>
                                <p class="mt-0.5 truncate text-xs text-text opacity-55">
                                    {{ item.product_code || 'Sin codigo' }} · {{ item.base_unit || 'pieza' }}
                                </p>
                            </div>

                            <strong class="shrink-0 text-sm text-text">
                                {{ quantity(item.requested_quantity) }} solicitadas
                            </strong>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="branch in item.branch_breakdown"
                                :key="`${item.id}-${branch.branch_id}`"
                                class="rounded-full bg-secondary px-2.5 py-1 text-[11px] font-semibold text-text"
                            >
                                {{ branch.branch_name }}: {{ quantity(branch.requested_quantity) }}
                            </span>
                        </div>

                        <div
                            v-if="completed"
                            class="mt-3 grid gap-2 rounded-xl bg-secondary px-3 py-2 text-xs text-text sm:grid-cols-4"
                        >
                            <span>{{ item.unavailable ? 'No encontrado' : item.purchase_presentation }}</span>
                            <span v-if="!item.unavailable">{{ quantity(item.package_quantity) }} bultos</span>
                            <span v-if="!item.unavailable">{{ quantity(item.purchased_quantity) }} recibidas</span>
                            <strong v-if="!item.unavailable" class="sm:text-right">{{ currency(item.actual_total) }}</strong>
                        </div>
                    </article>

                    <p v-if="!order.items?.length" class="p-8 text-center text-sm text-text opacity-60">
                        Esta orden no tiene productos.
                    </p>
                </div>
            </FormPanel>

            <aside class="min-w-0 space-y-4">
                <FormPanel title="Sucursales" panel-class="shadow-none">
                    <div class="space-y-2">
                        <div
                            v-for="branch in order.branches"
                            :key="branch.id"
                            class="flex items-center justify-between gap-3 rounded-xl bg-secondary px-3 py-2 text-sm text-text"
                        >
                            <strong class="truncate">{{ branch.name }}</strong>
                            <span class="shrink-0 text-xs opacity-60">{{ quantity(branch.requested_quantity) }} pzas.</span>
                        </div>
                    </div>
                </FormPanel>

                <FormPanel v-if="completed" title="Compra" panel-class="shadow-none">
                    <div class="space-y-2 text-sm text-text">
                        <div class="flex justify-between gap-3"><span class="opacity-65">Subtotal</span><strong>{{ currency(order.gross_total) }}</strong></div>
                        <div class="flex justify-between gap-3"><span class="opacity-65">Descuento</span><strong>{{ currency(order.discount_total) }}</strong></div>
                        <div class="flex justify-between gap-3 border-t border-secondary pt-2 text-base"><span>Total</span><strong>{{ currency(order.actual_total) }}</strong></div>
                    </div>
                </FormPanel>

                <FormPanel v-if="order.notes" title="Notas" panel-class="shadow-none">
                    <p class="whitespace-pre-wrap text-sm text-text">{{ order.notes }}</p>
                </FormPanel>
            </aside>
        </div>

        <template #footer>
            <footer class="flex w-full justify-end border-t border-secondary bg-background p-3">
                <AppButton variant="secondary" @click="$emit('close')">Cerrar</AppButton>
            </footer>
        </template>
    </GlobalModal>
</template>
