<script setup>
import ExportButton from '@/Components/ExportButton.vue'
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

defineProps({
    empleadosFiltrados: Array,
    empleadosDB: Array,
    registrosAMostrar: Number
})

defineEmits([
    'nuevo',
    'excel',
    'update:registrosAMostrar'
])
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">

        <div class="flex items-center gap-3">
            <button v-if="can('empleados.crear')" @click="$emit('nuevo')"
                class="bg-[#1f1d2b] text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                Nuevo empleado
            </button>

            <ExportButton v-if="can('empleados.exportar')" label="Exportar Excel" @click="$emit('excel')" />
        </div>

        <div class="flex items-center gap-2 text-sm text-slate-600">

            <span>Mostrar</span>

            <select id="registrosAMostrar" name="registrosAMostrar" :value="registrosAMostrar"
                @change="$emit('update:registrosAMostrar', Number($event.target.value))"
                class="border rounded-md px-3 py-1.5 outline-none bg-white">
                <option :value="5">5</option>
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>

            <span>registros</span>

        </div>
    </div>

    <div class="mb-3 text-sm text-slate-500">
        Mostrando {{ empleadosFiltrados.length }} de {{ empleadosDB.length }} registros
    </div>
</template>