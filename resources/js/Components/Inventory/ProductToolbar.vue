<script setup>
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

defineProps({
    total: {
        type: Number,
        default: 0
    },

    recordsToShow: {
        type: Number,
        default: 10
    }
})

defineEmits([
    'create',
    'update:recordsToShow'
])
</script>

<template>
    <div
        class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white rounded-3xl border border-slate-200 shadow-sm p-6"
    >

        <div>
            <h1 class="text-2xl font-bold text-slate-800">
                Gestión de ProductosS
            </h1>

            <p class="text-sm text-slate-500 mt-1">
                {{ total }} productos encontrados
            </p>
        </div>

        <div class="flex items-center gap-3">

            <select
                :value="recordsToShow"
                @change="$emit('update:recordsToShow', Number($event.target.value))"
                class="border border-slate-300 rounded-xl px-4 py-2 text-sm bg-white shadow-sm"
            >
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
             
            </select>

            <button
                v-if="can('productos.crear')"
                @click="$emit('create')"
                class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold transition"
            >
                + Nuevo producto
            </button>

        </div>

    </div>
</template>