<script setup>
import { computed } from 'vue'

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
    <div class="h-fit rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">
                    Producto actual escaneado
                </h2>

                <p class="mt-0.5 text-[11px] text-gray-500">
                    Informacion del producto encontrado para capturar conteo.
                </p>
            </div>

            <span
                v-if="product"
                class="shrink-0 rounded-full bg-green-50 px-2.5 py-1 text-[11px] font-semibold text-green-700"
            >
                Listo para capturar
            </span>
        </div>

        <div
            v-if="product"
            class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-[1.4fr_0.75fr_0.75fr_0.55fr]"
        >
            <div class="rounded-md bg-slate-50 px-2.5 py-2">
                <p class="text-[11px] font-medium text-slate-500">Producto</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">
                    {{ product.name }}
                </p>
            </div>

            <div class="rounded-md bg-slate-50 px-2.5 py-2">
                <p class="text-xs font-medium text-slate-500">Código escaneado</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">
                    {{ scannedCodeLabel }}
                </p>
            </div>

            <div class="rounded-md bg-slate-50 px-2.5 py-2">
                <p class="text-xs font-medium text-slate-500">Código principal</p>
                <p class="mt-0.5 text-sm font-semibold text-slate-900">
                    {{ product.barcode || 'Sin código' }}
                </p>
            </div>

            <div v-if="canViewStock" class="rounded-md bg-slate-50 px-2.5 py-2">
                <p class="text-[11px] font-medium text-slate-500">Stock</p>
                <p class="mt-0.5 text-base font-bold text-slate-900">
                    {{ product.stock ?? 0 }}
                </p>
            </div>
        </div>

        <div
            v-if="product"
            class="mt-2 flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5"
        >
            <p class="text-[11px] font-medium text-slate-500">
                Lotes disponibles
            </p>

            <p class="text-sm font-bold text-slate-900">
                {{ availableBatchesCount }}
            </p>
        </div>

        <p
            v-else-if="!product"
            class="mt-3 rounded-md border border-dashed border-gray-300 bg-gray-50 p-3 text-xs text-gray-500"
        >
            Escanea un código para visualizar aquí el producto que se va a capturar.
        </p>
    </div>
</template>
