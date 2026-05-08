<script setup>
defineProps({
    empleadosFiltrados: Array,
    busqueda: String,
    filtroPuesto: String,
    filtroDepartamento: String,
    filtroEstado: String
})

defineEmits([
    'update:busqueda',
    'update:filtroPuesto',
    'update:filtroDepartamento',
    'update:filtroEstado',
    'visualizar',
    'editar',
    'eliminar'
])
</script>

<template>
    <div class="hidden md:block bg-white border rounded-xl overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
            <thead class="bg-gray-50 text-slate-600">
                <tr>

                    <th class="text-left px-4 py-3">
                        <input id="busquedaEmpleado" name="busquedaEmpleado" :value="busqueda"
                            @input="$emit('update:busqueda', $event.target.value)" type="text"
                            placeholder="Buscar empleado"
                            class="w-full border rounded-md px-3 py-1.5 text-sm outline-none" />
                    </th>

                    <th class="text-left px-4 py-3">
                        <select id="filtroPuesto" name="filtroPuesto" :value="filtroPuesto"
                            @change="$emit('update:filtroPuesto', $event.target.value)"
                            class="w-full border rounded-md px-3 py-1.5 text-sm outline-none bg-white">
                            <option value="">Puesto...</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Auxiliar">Auxiliar</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Vendedor">Vendedor</option>
                            <option value="Administrador">Administrador</option>
                            <option value="Jefe de almacén">Jefe de almacén</option>
                            <option value="Analista">Analista</option>
                            <option value="Capturista">Capturista</option>
                            <option value="Ejecutiva comercial">Ejecutiva comercial</option>
                        </select>
                    </th>

                    <th class="text-left px-4 py-3">
                        <select id="filtroDepartamento" name="filtroDepartamento" :value="filtroDepartamento"
                            @change="$emit('update:filtroDepartamento', $event.target.value)"
                            class="w-full border rounded-md px-3 py-1.5 text-sm outline-none bg-white">
                            <option value="">Departamento...</option>
                            <option value="Inventario">Inventario</option>
                            <option value="Sistemas">Sistemas</option>
                            <option value="Recursos Humanos">Recursos Humanos</option>
                            <option value="Ventas">Ventas</option>
                        </select>
                    </th>

                    <th class="text-left px-4 py-3">
                        <select id="filtroEstado" name="filtroEstado" :value="filtroEstado"
                            @change="$emit('update:filtroEstado', $event.target.value)"
                            class="w-full border rounded-md px-3 py-1.5 text-sm outline-none bg-white">
                            <option value="">Estado...</option>
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </th>

                    <th class="text-left px-4 py-3">
                        <input id="periodoInicio" name="periodoInicio" disabled placeholder="Periodo de inicio"
                            class="w-full border rounded-md px-3 py-1.5 text-sm bg-gray-100 text-center" />
                    </th>

                    <th class="text-left px-4 py-3">
                        Acciones
                    </th>

                </tr>
            </thead>

            <tbody>
                <tr v-for="(empleado, index) in empleadosFiltrados" :key="index" class="border-t hover:bg-gray-50">
                    <td class="px-4 py-3">
                        {{ empleado.nombre }} {{ empleado.apellido }}
                    </td>

                    <td class="px-4 py-3">
                        {{ empleado.puesto }}
                    </td>

                    <td class="px-4 py-3">
                        {{ empleado.departamento }}
                    </td>

                    <td class="px-4 py-3">
                        {{ empleado.estado }}
                    </td>

                    <td class="px-4 py-3">
                        {{ empleado.fechaInicio }}
                    </td>

                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3 text-slate-600">
                            <button @click="$emit('visualizar', empleado)" class="hover:text-emerald-600 transition"
                                title="Visualizar">
                                <span class="material-symbols-outlined text-[20px]">
                                    visibility
                                </span>
                            </button>

                            <button @click="$emit('editar', empleado)" class="hover:text-blue-600 transition">
                                <span class="material-symbols-outlined text-[20px]">
                                    edit
                                </span>
                            </button>

                            <button @click="$emit('eliminar', empleado)" class="hover:text-red-600 transition">
                                <span class="material-symbols-outlined text-[20px]">
                                    delete
                                </span>
                            </button>

                        </div>
                    </td>

                </tr>
            </tbody>
        </table>
    </div>
</template>