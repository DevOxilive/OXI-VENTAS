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
    console.log('SI ENTRE A GUARDAR')

    empleado.post(route('rh.empleados.store'), {
        onSuccess: () => {
            emit('close')
            empleado.reset()
        },
        onError: (errors) => {
            console.log('ERRORES VALIDACION:', errors)
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
    <div
        class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-3 sm:p-6 overflow-y-auto">

        <!-- Fondo clickeable -->
        <div @click="cerrar" class="absolute inset-0"></div>

        <!-- Modal -->
        <div class="relative bg-[#efefef] w-full max-w-[1200px] rounded-[24px] shadow-2xl p-4 sm:p-6 lg:p-8 my-6 z-10">
            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-center text-slate-800 mb-6">
                Registro de nuevo empleado
            </h2>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <!-- DATOS GENERALES -->
                <div class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border">
                    <h3 class="text-xl sm:text-2xl font-bold mb-5 text-slate-700 border-b pb-2">Datos generales</h3>

                    <div class="flex flex-col sm:flex-row gap-5 mb-5 items-center sm:items-start">
                        <div class="w-28 h-32 sm:w-32 sm:h-36 bg-gray-200 rounded-lg flex-shrink-0"></div>
                        <div class="flex items-end">
                            <button type="button" class="bg-[#1f1d2b] text-white px-4 py-2 rounded-full text-sm">
                                Agregar foto
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Nombre</label>
                            <input v-model="empleado.nombre" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Apellido</label>
                            <input v-model="empleado.apellido" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Correo</label>
                            <input v-model="empleado.correo" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Teléfono</label>
                            <input v-model="empleado.telefono" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-sm font-semibold text-slate-600">Domicilio</label>
                            <input v-model="empleado.domicilio" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Fecha de inicio</label>
                            <input type="date" v-model="empleado.fechaInicio"
                                class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Estado</label>
                            <select v-model="empleado.estado" class="w-full border rounded-lg px-3 py-2 mt-1">
                                <option>Activo</option>
                                <option>Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- DATOS LABORALES -->
                <div class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border">
                    <h3 class="text-xl sm:text-2xl font-bold mb-5 text-slate-700 border-b pb-2">Datos laborales</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Puesto</label>
                            <input v-model="empleado.puesto" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Departamento</label>
                            <input v-model="empleado.departamento" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Banco</label>
                            <input v-model="empleado.banco" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Número de cuenta</label>
                            <input v-model="empleado.cuenta" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Último grado de estudios</label>
                            <input v-model="empleado.grado" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Especialidad</label>
                            <input v-model="empleado.especialidad" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Tipo de contrato</label>
                            <input v-model="empleado.tipoContrato" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">Antigüedad</label>
                            <input v-model="empleado.antiguedad" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">NSS</label>
                            <input v-model="empleado.nss" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-slate-600">RFC</label>
                            <input v-model="empleado.rfc" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-3 mt-8">
                <button type="button" @click="guardar"
                    class="bg-[#1f1d2b] text-white px-8 py-3 rounded-full font-medium w-full sm:w-auto">
                    Guardar empleado
                </button>

                <button type="button" @click="cerrar"
                    class="bg-gray-300 px-8 py-3 rounded-full font-medium w-full sm:w-auto">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</template>