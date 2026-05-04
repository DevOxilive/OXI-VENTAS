<script setup>
import { ref } from 'vue'
import SistemasLayout from '@/Layouts/SistemasLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: SistemasLayout })

// 🔥 TRAER USUARIOS DESDE LARAVEL
const empleados = usePage().props.empleados

const showGeneralModal = ref(false)
const showLaboralModal = ref(false)

const generalData = ref({
    nombre: '',
    puesto: '',
    departamento: '',
    estado: 'Activo',
    correo: '',
    telefono: '',
    domicilio: '',
    dia: '',
    mes: '',
    anio: ''
})

const laboralData = ref({
    departamento: '',
    puesto: '',
    banco: '',
    estado: 'Activo',
    cuenta: '',
    tipoContrato: '',
    antiguedad: '',
    grado: '',
    nss: '',
    rfc: '',
    especialidad: ''
})

function abrirModalGeneral() {
    showGeneralModal.value = true
}

function continuarLaboral() {
    showGeneralModal.value = false
    showLaboralModal.value = true
}

function guardarEmpleado() {
    showLaboralModal.value = false
    console.log('Datos Generales:', generalData.value)
    console.log('Datos Laborales:', laboralData.value)
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
                  <Link href="/register"
    class="bg-[#1f1d2b] text-white px-5 py-2 rounded-full text-sm flex items-center gap-2">

    <span class="material-symbols-outlined text-[18px]">add_circle</span>
    Nuevo empleado

</Link>
                </button>
            </div>
        </div>

      
        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3">Empleado</th>
                        <th class="text-left px-4 py-3">Rol</th>
                        <th class="text-left px-4 py-3">Departamento</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-left px-4 py-3">Fecha de registro</th>
                        <th class="text-left px-4 py-3">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="(empleado, index) in empleados" :key="index"
                        class="border-t hover:bg-gray-50">

                        <!-- NOMBRE + EMAIL -->
                        <td class="px-4 py-3">
                            <div class="flex flex-col">
                                <span>{{ empleado.name }}</span>
                                <span class="text-xs text-gray-500">{{ empleado.email }}</span>
                            </div>
                        </td>

                        <!-- ROL REAL -->
                        <td class="px-4 py-3 capitalize">
                            {{ empleado.role }}
                        </td>

                        <!-- SIMULADOS -->
                        <td class="px-4 py-3">Sistemas</td>
                        <td class="px-4 py-3">Activo</td>

                        <!-- FECHA REAL -->
                        <td class="px-4 py-3">
                            {{ new Date(empleado.created_at).toLocaleDateString() }}
                        </td>
                         
                        <!-- ACCIONES -->
                        <td class="px-4 py-3">✏️ 🗑️</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            <button class="bg-[#1f1d2b] text-white px-5 py-2 rounded-full text-sm">
                Exportar Excel
            </button>
        </div>

        <!-- MODALES (los dejo igual porque ya funcionan) -->
        <!-- NO CAMBIÉ NADA AQUÍ -->
   

        <!-- MODAL DATOS GENERALES -->
        <div v-if="showGeneralModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div class="bg-[#e9e9e9] w-[850px] rounded-3xl p-6 shadow-xl">
                <h2 class="text-5xl font-bold text-center mb-6">Datos generales</h2>
                <div class="grid grid-cols-3 gap-5">
                    <div class="bg-white rounded-lg p-4 flex flex-col items-center justify-center">
                        <div class="w-32 h-36 bg-gray-200 mb-4"></div>
                        <button class="bg-[#1f1d2b] text-white px-4 py-2 rounded-full text-sm">Agregar foto</button>
                    </div>
                    <div class="col-span-2 grid grid-cols-2 gap-4 bg-white p-4 rounded-lg">
                        <input v-model="generalData.nombre" placeholder="Nombre Completo"
                            class="border rounded px-3 py-2">
                        <select v-model="generalData.puesto" class="border rounded px-3 py-2">
                            <option>Seleccionar...</option>
                        </select>
                        <input v-model="generalData.departamento" placeholder="Departamento"
                            class="border rounded px-3 py-2">
                        <select v-model="generalData.estado" class="border rounded px-3 py-2">
                            <option>Activo</option>
                        </select>
                        <input v-model="generalData.correo" placeholder="Correo" class="border rounded px-3 py-2">
                        <input v-model="generalData.telefono" placeholder="Telefono" class="border rounded px-3 py-2">
                        <input v-model="generalData.domicilio" placeholder="Domicilio"
                            class="border rounded px-3 py-2 col-span-2">
                        <div class="col-span-2 flex gap-2">
                            <input v-model="generalData.dia" placeholder="DD" class="border rounded px-3 py-2 w-full">
                            <input v-model="generalData.mes" placeholder="MM" class="border rounded px-3 py-2 w-full">
                            <input v-model="generalData.anio" placeholder="YYYY"
                                class="border rounded px-3 py-2 w-full">
                        </div>
                        <div class="col-span-2 flex justify-between mt-3">
                            <button @click="continuarLaboral"
                                class="bg-[#1f1d2b] text-white px-6 py-2 rounded-full">Continuar</button>
                            <button @click="showGeneralModal = false"
                                class="bg-gray-300 px-6 py-2 rounded-full">Descartar cambios</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DATOS LABORALES -->
        <div v-if="showLaboralModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div class="bg-[#e9e9e9] w-[850px] rounded-3xl p-6 shadow-xl">
                <h2 class="text-5xl font-bold text-center mb-6">Datos laborales</h2>
                <div class="grid grid-cols-2 gap-4 bg-white p-4 rounded-lg">
                    <input v-model="laboralData.departamento" placeholder="Departamento"
                        class="border rounded px-3 py-2">
                    <input v-model="laboralData.banco" placeholder="Banco" class="border rounded px-3 py-2">
                    <select v-model="laboralData.puesto" class="border rounded px-3 py-2">
                        <option>Seleccionar...</option>
                    </select>
                    <input v-model="laboralData.cuenta" placeholder="Num. Cuenta" class="border rounded px-3 py-2">
                    <select v-model="laboralData.estado" class="border rounded px-3 py-2">
                        <option>Activo</option>
                    </select>
                    <input v-model="laboralData.grado" placeholder="Último grado de estudios"
                        class="border rounded px-3 py-2">
                    <div class="flex gap-2">
                        <input placeholder="DD" class="border rounded px-3 py-2 w-full">
                        <input placeholder="MM" class="border rounded px-3 py-2 w-full">
                        <input placeholder="YYYY" class="border rounded px-3 py-2 w-full">
                    </div>
                    <input v-model="laboralData.especialidad" placeholder="Especialidad"
                        class="border rounded px-3 py-2">
                    <input v-model="laboralData.tipoContrato" placeholder="Tipo de contrato"
                        class="border rounded px-3 py-2">
                    <input v-model="laboralData.nss" placeholder="NSS" class="border rounded px-3 py-2">
                    <input v-model="laboralData.antiguedad" placeholder="Antigüedad" class="border rounded px-3 py-2">
                    <input v-model="laboralData.rfc" placeholder="RFC" class="border rounded px-3 py-2">
                    <div class="col-span-2 flex justify-between mt-3">
                        <button @click="guardarEmpleado"
                            class="bg-[#1f1d2b] text-white px-6 py-2 rounded-full">Guardar</button>
                        <button @click="showLaboralModal = false"
                            class="bg-gray-300 px-6 py-2 rounded-full">Descartar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
