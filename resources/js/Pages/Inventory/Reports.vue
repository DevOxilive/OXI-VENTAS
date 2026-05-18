<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const reportType = ref('')
const branch = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const reports = [
    { title: 'Inventario general', description: 'Productos, stock, precios y sucursales.', icon: 'inventory_2' },
    { title: 'Stock bajo', description: 'Productos por debajo del mínimo permitido.', icon: 'warning' },
    { title: 'Caducidades', description: 'Lotes próximos a vencer o vencidos.', icon: 'event_busy' },
    { title: 'Movimientos', description: 'Entradas, salidas y ajustes por periodo.', icon: 'sync_alt' },
    { title: 'Transferencias', description: 'Traspasos entre sucursales.', icon: 'compare_arrows' },
    { title: 'Ajustes', description: 'Diferencias entre sistema y físico.', icon: 'tune' }
]

const recentExports = [
    { id: 1, name: 'Inventario_general_2026-05-18.xlsx', type: 'Inventario general', user: 'Carlos Ramirez', date: 'Hoy 11:30' },
    { id: 2, name: 'Caducidades_mayo.xlsx', type: 'Caducidades', user: 'Andrea Mendoza', date: 'Ayer 16:20' },
    { id: 3, name: 'Movimientos_sucursal_centro.xlsx', type: 'Movimientos', user: 'Luis Santos', date: '2026-05-15' }
]

function generateReport() {
    console.log('Generar reporte', {
        reportType: reportType.value,
        branch: branch.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value
    })
}

function exportExcel(report) {
    console.log('Exportar excel', report)
}

function exportPdf(report) {
    console.log('Exportar PDF', report)
}
</script>

<template>
    <section class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Reportes</h1>
            <p class="text-sm text-slate-500 mt-1">
                Generación de reportes operativos del módulo de inventario.
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <h2 class="text-base font-bold text-slate-800 mb-4">
                Generador de reportes
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <select v-model="reportType" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Tipo de reporte</option>
                    <option>Inventario general</option>
                    <option>Stock bajo</option>
                    <option>Caducidades</option>
                    <option>Movimientos</option>
                    <option>Transferencias</option>
                    <option>Ajustes</option>
                </select>

                <select v-model="branch" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Sucursal</option>
                    <option>Sucursal Centro</option>
                    <option>Sucursal Norte</option>
                    <option>Sucursal Sur</option>
                </select>

                <input v-model="dateFrom" type="date" class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />

                <input v-model="dateTo" type="date" class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />

                <button @click="generateReport"
                    class="bg-[#1f1d2b] text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">description</span>
                    Generar
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="report in reports" :key="report.title"
                class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">
                            {{ report.icon }}
                        </span>
                    </div>

                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800">
                            {{ report.title }}
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            {{ report.description }}
                        </p>

                        <div class="flex gap-2 mt-4">
                            <button @click="exportExcel(report)"
                                class="bg-green-50 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">table_view</span>
                                Excel
                            </button>

                            <button @click="exportPdf(report)"
                                class="bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                                PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-800">
                    Exportaciones recientes
                </h2>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Archivo</th>
                            <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                            <th class="px-4 py-3 text-left font-semibold">Usuario</th>
                            <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                            <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in recentExports" :key="item.id"
                            class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-4 font-semibold text-slate-800">{{ item.name }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.type }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.user }}</td>
                            <td class="px-4 py-4 text-slate-500">{{ item.date }}</td>
                            <td class="px-4 py-4 text-center">
                                <button class="bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">
                                    Descargar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in recentExports" :key="item.id" class="border border-slate-200 rounded-2xl p-4">
                    <p class="font-bold text-slate-800">{{ item.name }}</p>
                    <p class="text-sm text-slate-500 mt-1">{{ item.type }} · {{ item.user }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ item.date }}</p>

                    <button class="mt-4 w-full bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">
                        Descargar
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>