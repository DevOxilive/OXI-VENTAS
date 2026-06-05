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
        <h3 class="font-bold text-base border-b pb-3 mb-4">
            Producto
        </h3>

        <div class="space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    Producto seleccionado
                </p>

                <p class="mt-1 text-sm font-black text-slate-900 truncate">
                    {{ product.name }}
                </p>

                <p class="text-xs text-slate-500 truncate">
                    {{ product.code || 'Sin código registrado' }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Stock actual
                    </span>

                    <span class="text-lg font-black text-slate-900">
                        {{ currentStock }} {{ product.unit ?? 'piezas' }}
                    </span>
                </div>

                <div class="mt-2 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Stock final
                    </span>

                    <span class="text-lg font-black" :class="projectedStock < 0 ? 'text-red-600' : 'text-emerald-600'">
                        {{ projectedStock }} {{ product.unit ?? 'piezas' }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
