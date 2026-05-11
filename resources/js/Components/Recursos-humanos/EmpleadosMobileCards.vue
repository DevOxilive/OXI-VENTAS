<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()

const authPermissions = computed(() => page.props.auth?.permissions || [])

const can = (permiso) => authPermissions.value.includes(permiso)

const puedeVerAcciones = computed(() =>
    can('empleados.ver') ||
    can('empleados.editar') ||
    can('empleados.eliminar')
)

defineProps({
    empleadosFiltrados: Array
})

defineEmits(['visualizar', 'editar', 'eliminar'])
</script>

<template>
    <div class="md:hidden space-y-3 mt-4">
        <div v-for="(empleado, index) in empleadosFiltrados" :key="empleado.id || index"
            class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="flex justify-between items-start gap-3">
                <div>
                    <p class="font-semibold text-slate-800">
                        {{ empleado.nombre }} {{ empleado.apellido }}
                    </p>

                    <p class="text-sm text-slate-500">
                        {{ empleado.puesto }}
                    </p>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="empleado.estado === 'Activo'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700'">
                    {{ empleado.estado }}
                </span>
            </div>

            <div class="mt-4 text-sm text-slate-500 space-y-1">
                <p>
                    <span class="font-medium text-slate-600">Departamento:</span>
                    {{ empleado.departamento }}
                </p>

                <p>
                    <span class="font-medium text-slate-600">Inicio:</span>
                    {{ empleado.fechaInicio }}
                </p>
            </div>

            <div v-if="puedeVerAcciones" class="mt-4 flex justify-end gap-2">
                <button v-if="can('empleados.ver')" type="button" title="Visualizar empleado"
                    @click="$emit('visualizar', empleado)"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-200 transition-all duration-200">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-blue-600 transition">
                        visibility
                    </span>
                </button>

                <button v-if="can('empleados.editar')" type="button" title="Editar empleado"
                    @click="$emit('editar', empleado)"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-amber-50 hover:border-amber-200 transition-all duration-200">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-amber-600 transition">
                        edit
                    </span>
                </button>

                <button v-if="can('empleados.eliminar')" type="button" title="Eliminar empleado"
                    @click="$emit('eliminar', empleado)"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-red-50 hover:border-red-200 transition-all duration-200">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-red-600 transition">
                        delete
                    </span>
                </button>
            </div>
        </div>

        <div v-if="!empleadosFiltrados.length"
            class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
            No se encontraron empleados registrados.
        </div>
    </div>
</template>