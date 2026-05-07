<script setup>
defineProps({
    empleadosFiltrados: Array
})

defineEmits(['editar', 'eliminar'])
</script>

<template>
    <div class="md:hidden space-y-3 mt-4">
        <div v-for="(empleado, index) in empleadosFiltrados" :key="index"
            class="bg-white border rounded-xl p-4 shadow-sm">

            <div class="flex justify-between items-start">
                <div>
                    <p class="font-semibold text-slate-700">
                        {{ empleado.nombre }} {{ empleado.apellido }}
                    </p>
                    <p class="text-sm text-slate-500">
                        {{ empleado.puesto }}
                    </p>
                </div>

                <div class="text-sm text-slate-400">
                    {{ empleado.estado }}
                </div>
            </div>

            <div class="mt-3 text-xs text-slate-500 space-y-1">
                <p>Departamento: {{ empleado.departamento }}</p>
                <p>Inicio: {{ empleado.fechaInicio }}</p>
            </div>

            <div class="mt-3 flex justify-end gap-3 text-slate-600">
                <button @click="$emit('editar', empleado)" class="hover:text-blue-600 transition">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                </button>

                <button @click="$emit('eliminar', empleado)" class="hover:text-red-600 transition">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                </button>
            </div>
        </div>
    </div>
</template>