<script setup>
import ExportButton from '@/Components/ExportButton.vue'

defineProps({
    filteredProducts: {
        type: Array,
        default: () => []
    },
    productsDb: {
        type: Array,
        default: () => []
    },
    recordsToShow: {
        type: Number,
        default: 10
    }
})

defineEmits([
    'create',
    'excel',
    'update:recordsToShow'
])
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="flex items-center gap-3">
            <button @click="$emit('create')"
                class="bg-[#1f1d2b] text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">
                    add_circle
                </span>

                Nuevo producto
            </button>

            <ExportButton @click="$emit('excel')" />
        </div>

        <div class="flex items-center gap-3">
            <div class="text-sm text-slate-500">
                Mostrando
                {{ filteredProducts.length }}
                de
                {{ productsDb.length }}
                registros
            </div>

            <select :value="recordsToShow" @change="$emit('update:recordsToShow', Number($event.target.value))"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>
        </div>
    </div>
</template>