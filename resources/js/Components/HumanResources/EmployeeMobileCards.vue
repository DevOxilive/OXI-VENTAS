<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'



const props = defineProps({
    filteredEmployees: {
        type: Array,
        default: () => []
    },
    canView: {
        type: Boolean,
        default: false
    },
    canEdit: {
        type: Boolean,
        default: false
    },
    canDelete: {
        type: Boolean,
        default: false
    }
})

const canViewActions = computed(() =>
    props.canView || props.canEdit || props.canDelete
)

defineEmits(['view', 'edit', 'delete'])
</script>

<template>
    <div class="md:hidden space-y-3 mt-4">
        <div v-for="(employee, index) in filteredEmployees" :key="employee.id || index"
            class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="flex justify-between items-start gap-3">
                <div>
                    <p class="font-semibold text-slate-800">
                        {{ employee.firstName }} {{ employee.lastName }}
                    </p>

                    <p class="text-sm text-slate-500">
                        {{ employee.position }}
                    </p>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" :class="employee.employmentStatus === 'Activo'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700'">
                    {{ employee.employmentStatus }}
                </span>
            </div>

            <div class="mt-4 text-sm text-slate-500 space-y-1">
                <p>
                    <span class="font-medium text-slate-600">
                        Departamento:
                    </span>

                    {{ employee.department }}
                </p>

                <p>
                    <span class="font-medium text-slate-600">
                        Inicio:
                    </span>

                    {{ employee.startDate }}
                </p>
            </div>

            <div v-if="canViewActions" class="mt-4 flex justify-end gap-2">

                <button v-if="canView" type="button" title="Visualizar empleado"
                    @click="$emit('view', employee)"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-200 transition-all duration-200">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-blue-600 transition">
                        visibility
                    </span>
                </button>

                <button v-if="canEdit" type="button" title="Editar empleado"
                    @click="$emit('edit', employee)"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-amber-50 hover:border-amber-200 transition-all duration-200">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-amber-600 transition">
                        edit
                    </span>
                </button>

                <button v-if="canDelete" type="button" title="Eliminar empleado"
                    @click="$emit('delete', employee)"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-red-50 hover:border-red-200 transition-all duration-200">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-red-600 transition">
                        delete
                    </span>
                </button>

            </div>
        </div>

        <div v-if="!filteredEmployees.length"
            class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
            No se encontraron empleados registrados.
        </div>
    </div>
</template>