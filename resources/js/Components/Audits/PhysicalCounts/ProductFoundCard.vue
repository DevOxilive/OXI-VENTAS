<script setup>
defineProps({
    product: {
        type: Object,
        default: null
    }
})
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">
            Producto encontrado
        </h2>

        <div
            v-if="product"
            class="mt-4 space-y-2"
        >
            <p>
                <strong>Producto:</strong>
                {{ product.name }}
            </p>

            <p>
                <strong>Código:</strong>
                {{ product.barcode }}
            </p>

            <p>
                <strong>Stock actual:</strong>
                {{ product.stock }}
            </p>

            <p>
                <strong>Código escaneado:</strong>
                {{ product.scanned_code }}
            </p>
        </div>

        <p
            v-else
            class="mt-2 text-sm text-gray-500"
        >
            Los datos del producto aparecerán aquí después de escanear.
        </p>
    </div>
</template>