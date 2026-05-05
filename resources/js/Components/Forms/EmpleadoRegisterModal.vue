<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import { useForm } from '@inertiajs/vue3'

const emit = defineEmits(['close'])

const empleado = useForm({
    nombre: '',
    apellido: '',
    puesto: '',
    departamento: '',
    estado: 'Activo',
    correo: '',
    telefono: '',
    domicilio: '',
    fechaInicio: '',
    banco: '',
    cuenta: '',
    grado: '',
    especialidad: '',
    tipoContrato: '',
    antiguedad: '',
    nss: '',
    rfc: ''
})

function cerrar() {
    emit('close')
}

function guardar() {
    empleado.post(route('rh.empleados.store'), {
        onSuccess: () => {
            emit('close')
            empleado.reset()
        },
        onError: (errors) => {
            console.log(errors)
        }
    })
}

function handleEsc(e) {
    if (e.key === 'Escape') cerrar()
}

onMounted(() => window.addEventListener('keydown', handleEsc))
onBeforeUnmount(() => window.removeEventListener('keydown', handleEsc))
</script>

<template>
    <div class="fixed inset-0 bg-black/50 z-50 flex">

        <!-- BACKDROP -->
        <div class="absolute inset-0" @click="cerrar"></div>

        <!-- MODAL / SHEET -->
        <div class="relative bg-white w-full md:max-w-[1200px] md:rounded-2xl
                   md:m-auto h-full md:h-auto overflow-y-auto shadow-2xl">

            <!-- HEADER MOBILE STYLE -->
            <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center z-10">

                <h2 class="text-lg md:text-2xl font-bold text-slate-800">
                    Nuevo empleado
                </h2>

                <button @click="cerrar" class="text-slate-500 text-xl px-3 py-1">
                    ✕
                </button>

            </div>

            <div class="p-4 md:p-8 space-y-6">

                <!-- GRID -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- GENERAL -->
                    <div class="border rounded-2xl p-4 md:p-6">

                        <h3 class="font-bold text-lg mb-4">
                            Datos generales
                        </h3>

                        <div class="space-y-4">

                            <div class="flex gap-4 items-center">
                                <div class="w-20 h-24 bg-gray-200 rounded-lg"></div>

                                <button class="bg-[#1f1d2b] text-white px-4 py-2 rounded-full text-sm">
                                    Foto
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                                <input v-model="empleado.nombre" placeholder="Nombre"
                                    class="border rounded-lg px-3 py-2">

                                <input v-model="empleado.apellido" placeholder="Apellido"
                                    class="border rounded-lg px-3 py-2">

                                <input v-model="empleado.correo" placeholder="Correo"
                                    class="border rounded-lg px-3 py-2 sm:col-span-2">

                                <input v-model="empleado.telefono" placeholder="Teléfono"
                                    class="border rounded-lg px-3 py-2 sm:col-span-2">

                                <input v-model="empleado.domicilio" placeholder="Domicilio"
                                    class="border rounded-lg px-3 py-2 sm:col-span-2">

                                <input type="date" v-model="empleado.fechaInicio" class="border rounded-lg px-3 py-2">

                                <select v-model="empleado.estado" class="border rounded-lg px-3 py-2">
                                    <option>Activo</option>
                                    <option>Inactivo</option>
                                </select>

                            </div>

                        </div>

                    </div>

                    <!-- LABORAL -->
                    <div class="border rounded-2xl p-4 md:p-6">

                        <h3 class="font-bold text-lg mb-4">
                            Datos laborales
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                            <input v-model="empleado.puesto" placeholder="Puesto" class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.departamento" placeholder="Departamento"
                                class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.banco" placeholder="Banco" class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.cuenta" placeholder="Cuenta" class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.grado" placeholder="Grado estudios"
                                class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.especialidad" placeholder="Especialidad"
                                class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.tipoContrato" placeholder="Contrato"
                                class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.antiguedad" placeholder="Antigüedad"
                                class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.nss" placeholder="NSS" class="border rounded-lg px-3 py-2">

                            <input v-model="empleado.rfc" placeholder="RFC" class="border rounded-lg px-3 py-2">

                        </div>

                    </div>

                </div>

                <!-- ACTIONS -->
                <div class="flex flex-col md:flex-row justify-end gap-3 pt-4">

                    <button @click="guardar" class="bg-[#1f1d2b] text-white px-6 py-3 rounded-full w-full md:w-auto">
                        Guardar
                    </button>

                    <button @click="cerrar" class="bg-gray-200 px-6 py-3 rounded-full w-full md:w-auto">
                        Cancelar
                    </button>

                </div>

            </div>

        </div>

    </div>
</template>