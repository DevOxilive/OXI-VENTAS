<script setup>
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

defineProps({
    total: {
        type: Number,
        default: 0
    }
})

defineEmits(['create'])
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

        <div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-800">
                Gestión de Productos
            </h1>

            <p class="text-sm text-slate-500 mt-1">
                Control de productos, stock, costos y precios
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="px-4 py-2 rounded-full bg-blue-600 text-white text-sm font-semibold">
                {{ total }} productos
            </span>

            <button
                v-if="can('productos.crear')"
                @click="$emit('create')"
                class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold transition"
            >
                + Nuevo producto
            </button>
        </div>

    </div>
</template>