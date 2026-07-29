<script setup>
import { computed } from 'vue'

import FormPanel from '@/Components/Cards/FormPanel.vue'

const props = defineProps({
    product: {
        type: Object,
        default: null,
    },
    canViewStock: {
        type: Boolean,
        default: false,
    },
})

const scannedCodeLabel = computed(() => {
    if (!props.product) return ''

    if (props.product.scanned_code && props.product.scanned_code !== 'Seleccionado manualmente') {
        return props.product.scanned_code
    }

    return props.product.barcode || 'Sin código'
})

const availableBatchesCount = computed(() => props.product?.batches?.length ?? 0)
</script>

<template>
    <FormPanel
        title="Producto seleccionado"
        description="Información del producto disponible para registrar el conteo."
        panel-class="h-fit"
    >
        <template #header>
            <span
                v-if="product"
                class="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-accent"
            >
                Listo para capturar
            </span>
        </template>

        <div
            v-if="product"
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div class="rounded-xl border border-secondary bg-background p-3">
                <p class="text-xs font-medium text-text opacity-55">Producto</p>
                <p class="mt-1 text-sm font-semibold text-text">{{ product.name }}</p>
            </div>

            <div class="rounded-xl border border-secondary bg-background p-3">
                <p class="text-xs font-medium text-text opacity-55">Código escaneado</p>
                <p class="mt-1 break-all text-sm font-semibold text-text">{{ scannedCodeLabel }}</p>
            </div>

            <div class="rounded-xl border border-secondary bg-background p-3">
                <p class="text-xs font-medium text-text opacity-55">Código principal</p>
                <p class="mt-1 break-all text-sm font-semibold text-text">
                    {{ product.barcode || 'Sin código' }}
                </p>
            </div>

            <div class="rounded-xl border border-secondary bg-background p-3">
                <p class="text-xs font-medium text-text opacity-55">
                    {{ canViewStock ? 'Existencia actual' : 'Lotes disponibles' }}
                </p>
                <p class="mt-1 text-lg font-bold text-text">
                    {{ canViewStock ? (product.stock ?? 0) : availableBatchesCount }}
                </p>
            </div>

            <div
                v-if="canViewStock"
                class="rounded-xl border border-secondary bg-background p-3 sm:col-span-2 xl:col-span-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-text opacity-65">Lotes disponibles</span>
                    <strong class="text-text">{{ availableBatchesCount }}</strong>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-secondary bg-background p-5 text-sm text-text opacity-65"
        >
            Busca o escanea un producto para consultar su información.
        </div>
    </FormPanel>
</template>
