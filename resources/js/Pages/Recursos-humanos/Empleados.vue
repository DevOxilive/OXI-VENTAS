<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import EmpleadoRegisterModal from '@/Components/Forms/EmpleadoRegisterModal.vue'

defineOptions({ layout: AdminLayout })

const showModal = ref(false)

const { empleadosDB } = defineProps({
    empleadosDB: Array
})

function abrirModalGeneral() {
    showModal.value = true
}

function cerrarModal() {
    showModal.value = false
}
</script>

<template>
    <div class="bg-[#f6f3f7] min-h-screen rounded-2xl md:rounded-3xl p-4 md:p-6">

        <!-- HEADER -->
        <h1 class="text-lg md:text-xl font-semibold text-slate-700 mb-5 md:mb-6">
            Empleados
        </h1>

        <!-- TOOLBAR -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">

            <!-- TABS -->
            <div class="flex gap-2 overflow-x-auto">
                <button class="px-4 py-2 rounded-xl bg-white border shadow-sm text-sm whitespace-nowrap">
                    Empleados
                </button>
                <button class="px-4 py-2 rounded-xl bg-white border shadow-sm text-sm whitespace-nowrap">
                    Candidatos
                </button>
            </div>

            <!-- ACTIONS -->
            <div class="flex flex-col sm:flex-row gap-3 sm:items-center">

                <!-- SEARCH -->
                <div class="bg-white rounded-full px-4 py-2 border flex items-center gap-2 w-full sm:w-auto">
                    <span class="material-symbols-outlined text-[20px]">search</span>
                    <input type="text" placeholder="Buscar empleado"
                        class="outline-none bg-transparent text-sm w-full" />
                </div>

                <!-- ADD -->
                <button @click="abrirModalGeneral"
                    class="bg-[#1f1d2b] text-white px-4 py-2 rounded-full text-sm flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    Nuevo
                </button>

            </div>
        </div>

        <!-- DESKTOP / TABLET TABLE -->
        <div class="hidden md:block bg-white border rounded-xl overflow-x-auto">

            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-gray-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3">Empleado</th>
                        <th class="text-left px-4 py-3">Puesto</th>
                        <th class="text-left px-4 py-3">Departamento</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-left px-4 py-3">Fecha de inicio</th>
                        <th class="text-left px-4 py-3">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="(empleado, index) in empleadosDB" :key="index" class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3">{{ empleado.nombre }}</td>
                        <td class="px-4 py-3">{{ empleado.puesto }}</td>
                        <td class="px-4 py-3">{{ empleado.departamento }}</td>
                        <td class="px-4 py-3">{{ empleado.estado }}</td>
                        <td class="px-4 py-3">{{ empleado.fecha }}</td>
                        <td class="px-4 py-3">✏️ 🗑️</td>
                    </tr>
                </tbody>
            </table>

        </div>

        <!-- MOBILE CARDS -->
        <div class="md:hidden space-y-3">

            <div v-for="(empleado, index) in empleadosDB" :key="index" class="bg-white border rounded-xl p-4 shadow-sm">

                <div class="flex justify-between items-start">

                    <div>
                        <p class="font-semibold text-slate-700">
                            {{ empleado.nombre }}
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
                    <p>Inicio: {{ empleado.fecha }}</p>
                </div>

                <div class="mt-3 flex justify-end gap-3 text-lg">
                    ✏️ 🗑️
                </div>

            </div>

        </div>

        <!-- EXPORT -->
        <div class="mt-5">
            <button class="bg-[#1f1d2b] text-white px-4 py-2 rounded-full text-sm">
                Exportar Excel
            </button>
        </div>

        <EmpleadoRegisterModal v-if="showModal" @close="cerrarModal" />

    </div>
</template>