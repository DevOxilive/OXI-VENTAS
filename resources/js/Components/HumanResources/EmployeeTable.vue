<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'

const { can } = usePermissions()

const canViewActions = computed(() =>
    can('empleados.ver') ||
    can('empleados.editar') ||
    can('empleados.eliminar')
)

defineProps({
    filteredEmployees: {
        type: Array,
        default: () => []
    },
    search: String,
    positionFilter: String,
    departmentFilter: String,
    statusFilter: String
})

defineEmits([
    'update:search',
    'update:positionFilter',
    'update:departmentFilter',
    'update:statusFilter',
    'view',
    'edit',
    'delete'
])
</script>

<template>
    <div class="hidden md:block bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[900px]">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">
                            <input :value="search" @input="$emit('update:search', $event.target.value)" type="text"
                                placeholder="Buscar empleado"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400" />
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="positionFilter"
                                @change="$emit('update:positionFilter', $event.target.value)"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
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

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="departmentFilter"
                                @change="$emit('update:departmentFilter', $event.target.value)"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                <option value="">Departamento...</option>
                                <option value="Inventario">Inventario</option>
                                <option value="Sistemas">Sistemas</option>
                                <option value="Recursos Humanos">Recursos Humanos</option>
                                <option value="Ventas">Ventas</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="statusFilter" @change="$emit('update:statusFilter', $event.target.value)"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                <option value="">Estado...</option>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <input disabled placeholder="Periodo de inicio"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm bg-slate-100 text-slate-500 text-center" />
                        </th>

                        <th v-if="canViewActions" class="text-center px-4 py-3 font-semibold">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(employee, index) in filteredEmployees" :key="employee.id || index"
                        class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-4 font-medium text-slate-800">
                            {{ employee.firstName }} {{ employee.lastName }}
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ employee.position }}
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ employee.department }}
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="employee.employmentStatus === 'Activo'
                                ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-700'">
                                {{ employee.employmentStatus }}
                            </span>
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ employee.startDate }}
                        </td>

                        <td v-if="canViewActions" class="px-4 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <ActionIconButton v-if="can('empleados.ver')" icon="visibility"
                                    title="Visualizar empleado" variant="blue" @click="$emit('view', employee)" />

                                <ActionIconButton v-if="can('empleados.editar')" icon="edit" title="Editar empleado"
                                    variant="amber" @click="$emit('edit', employee)" />

                                <ActionIconButton v-if="can('empleados.eliminar')" icon="delete"
                                    title="Eliminar empleado" variant="red" @click="$emit('delete', employee)" />
                            </div>
                        </td>
                    </tr>

                    <tr v-if="!filteredEmployees.length">
                        <td :colspan="canViewActions ? 6 : 5" class="px-4 py-10 text-center text-slate-500">
                            No se encontraron empleados registrados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>