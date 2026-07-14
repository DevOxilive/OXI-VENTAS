<script setup>
defineProps({
    product: {
        type: Object,
        required: true,
    },
    currentStock: {
        type: Number,
        default: 0,
    },
    projectedStock: {
        type: Number,
        default: 0,
    },
})
</script>

<template>
    <section class="xl:col-span-2 flex flex-col min-h-0">
        <h3 class="mb-4 border-b border-secondary pb-3 text-base font-bold text-text">
            Producto
        </h3>

        <div class="space-y-3">
            <div class="rounded-2xl border border-secondary bg-secondary px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-text opacity-70">
                    Producto seleccionado
                </p>

                <p class="mt-1 truncate text-sm font-black text-text">
                    {{ product.name }}
                </p>

                <p class="truncate text-xs text-text opacity-70">
                    {{ product.code || 'Sin código registrado' }}
                </p>
            </div>

            <div class="rounded-2xl border border-secondary bg-background p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text opacity-70">
                        Stock actual
                    </span>

                    <span class="text-lg font-black text-text">
                        {{ currentStock }} {{ product.unit ?? 'piezas' }}
                    </span>
                </div>

                <div class="mt-2 flex items-center justify-between">
                    <span class="text-xs text-text opacity-70">
                        Stock final
                    </span>

                    <span class="text-lg font-black" :class="projectedStock < 0 ? 'text-primary' : 'text-accent'">
                        {{ projectedStock }} {{ product.unit ?? 'piezas' }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
