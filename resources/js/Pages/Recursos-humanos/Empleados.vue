<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

import { useEmpleadoActions } from '@/Composables/Recursos-humanos/useEmpleadoActions'
import { useEmpleadoFilters } from '@/Composables/Recursos-humanos/useEmpleadoFilters'
import { useEmpleadoExport } from '@/Composables/Recursos-humanos/useEmpleadoExport'
import { usePermissions } from '@/Composables/usePermissions'
import EmpleadoRegisterModal from '@/Components/Formularios/EmpleadoRegisterModal.vue'
import EmpleadosToolbar from '@/Components/Recursos-humanos/EmpleadosToolbar.vue'
import EmpleadosTable from '@/Components/Recursos-humanos/EmpleadosTable.vue'
import EmpleadosMobileCards from '@/Components/Recursos-humanos/EmpleadosMobileCards.vue'

defineOptions({ layout: AdminLayout })

const { can } = usePermissions()

const { empleadosDB } = defineProps({
    empleadosDB: Array
})

const {
    filtroEstado,
    filtroDepartamento,
    filtroPuesto,
    registrosAMostrar,
    busqueda,
    empleadosFiltrados
} = useEmpleadoFilters(empleadosDB)

const { exportarExcel } = useEmpleadoExport(
    filtroEstado,
    filtroDepartamento,
    filtroPuesto,
    busqueda
)

const {
    showModal,
    modoModal,
    empleadoSeleccionado,
    abrirModalGeneral,
    abrirModalEditar,
    abrirModalVisualizar,
    cerrarModal,
    eliminarEmpleado
} = useEmpleadoActions()
</script>

<template>
    <div class="bg-[#f6f3f7] min-h-screen rounded-2xl md:rounded-3xl p-4 md:p-6">
        <pre>{{ $page.props.auth }}</pre>

        <h1 class="text-lg md:text-xl font-semibold text-slate-700 mb-5">
            Empleados
        </h1>

        <EmpleadosToolbar v-if="can('empleados.crear') || can('empleados.exportar')"
            :empleadosFiltrados="empleadosFiltrados" :empleadosDB="empleadosDB" :registrosAMostrar="registrosAMostrar"
            @nuevo="abrirModalGeneral" @excel="exportarExcel" @update:registrosAMostrar="registrosAMostrar = $event" />

        <EmpleadosTable v-if="can('empleados.ver')" :empleadosFiltrados="empleadosFiltrados" :busqueda="busqueda"
            :filtroPuesto="filtroPuesto" :filtroDepartamento="filtroDepartamento" :filtroEstado="filtroEstado"
            @update:busqueda="busqueda = $event" @update:filtroPuesto="filtroPuesto = $event"
            @update:filtroDepartamento="filtroDepartamento = $event" @update:filtroEstado="filtroEstado = $event"
            @visualizar="abrirModalVisualizar" @editar="abrirModalEditar" @eliminar="eliminarEmpleado" />

        <EmpleadosMobileCards v-if="can('empleados.ver')" :empleadosFiltrados="empleadosFiltrados"
            @visualizar="abrirModalVisualizar" @editar="abrirModalEditar" @eliminar="eliminarEmpleado" />

        <EmpleadoRegisterModal v-if="
            showModal &&
            (
                (modoModal === 'create' && can('empleados.crear')) ||
                (modoModal === 'edit' && can('empleados.editar')) ||
                (modoModal === 'view' && can('empleados.ver'))
            )
        " :modo="modoModal" :empleadoEditar="empleadoSeleccionado" @close="cerrarModal" />

    </div>
</template>