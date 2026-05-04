<script setup>
import { ref } from 'vue'
import RecursosHumanosLayout from '@/Layouts/RecursosHumanosLayout.vue'
import EmpleadoRegisterModal from '@/Components/Forms/EmpleadoRegisterModal.vue'

defineOptions({ layout: RecursosHumanosLayout })

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
    <div class="bg-[#f6f3f7] min-h-screen rounded-3xl p-6">
        <h1 class="text-xl font-semibold text-slate-700 mb-6">Dashboard Empleados</h1>

        <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">
            <div class="flex gap-3">
                <button class="px-5 py-2 rounded-xl bg-white border shadow-sm">Empleados</button>
                <button class="px-5 py-2 rounded-xl bg-white border shadow-sm">Candidatos</button>
            </div>

            <div class="flex items-center gap-3">
                <div class="bg-white rounded-full px-5 py-2 border flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">search</span>
                    <input type="text" placeholder="Buscar empleado" class="outline-none bg-transparent text-sm" />
                </div>

                <button @click="abrirModalGeneral"
                    class="bg-[#1f1d2b] text-white px-5 py-2 rounded-full text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    Nuevo empleado
                </button>
            </div>
        </div>

        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
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

        <div class="mt-5">
            <button class="bg-[#1f1d2b] text-white px-5 py-2 rounded-full text-sm flex items-center gap-2">
                Exportar Excel
            </button>
        </div>

        <EmpleadoRegisterModal v-if="showModal" @close="cerrarModal" />
    </div>
</template>
