<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
})

defineEmits(['close'])

const modalConfig = computed(() => ({
    isOpen: true,
    title: props.order.folio || 'Orden de sucursal',
    description: props.order.status_label || 'Detalle de la orden',
    icon: 'receipt_long',
    size: '5xl',
    showFooter: true,
}))

const completed = computed(() => props.order.status === 'COMPLETED')

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
        <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
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
                                    {{ item.product_code || 'Sin codigo' }} - {{ item.category_name || 'Sin categoria' }}
                                </p>
                            </div>

                            <span class="shrink-0 rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-text">
                                {{ item.status_label }}
                            </span>
                        </div>

                        <div class="mt-3 grid gap-2 rounded-xl bg-secondary px-3 py-2 text-xs text-text sm:grid-cols-4">
                            <span>Solicitado: <strong>{{ quantity(item.requested_quantity) }}</strong></span>
                            <span>Stock: <strong>{{ quantity(item.current_stock) }}</strong></span>
                            <span>Minimo: <strong>{{ quantity(item.min_stock) }}</strong></span>
                            <strong class="sm:text-right">{{ currency(item.estimated_total) }}</strong>
                        </div>

                        <div
                            v-if="completed"
                            class="mt-2 grid gap-2 rounded-xl bg-background px-3 py-2 text-xs text-text sm:grid-cols-3"
                        >
                            <span>Comprado: <strong>{{ quantity(item.purchased_quantity) }}</strong></span>
                            <span>Precio real: <strong>{{ currency(item.actual_price) }}</strong></span>
                            <strong class="sm:text-right">{{ currency(item.actual_total) }}</strong>
                        </div>
                    </article>

                    <p v-if="!order.items?.length" class="p-8 text-center text-sm text-text opacity-60">
                        Esta orden no tiene productos.
                    </p>
                </div>
            </FormPanel>

            <aside class="min-w-0 space-y-4">
                <FormPanel title="Resumen" panel-class="shadow-none">
                    <div class="space-y-2 text-sm text-text">
                        <div class="flex justify-between gap-3">
                            <span class="opacity-65">Estado</span>
                            <strong>{{ order.status_label }}</strong>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="opacity-65">Estimado</span>
                            <strong>{{ currency(order.estimated_total) }}</strong>
                        </div>
                        <div v-if="completed" class="flex justify-between gap-3 border-t border-secondary pt-2">
                            <span>Total pagado</span>
                            <strong>{{ currency(order.actual_total) }}</strong>
                        </div>
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
