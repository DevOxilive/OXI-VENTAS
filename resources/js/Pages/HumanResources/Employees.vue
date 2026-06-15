<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import EmployeeMobileCards from '@/Components/HumanResources/EmployeeMobileCards.vue'
import { useEmployeeActions } from '@/Composables/HumanResources/useEmployeeActions'
import { useEmployeeFilters } from '@/Composables/HumanResources/useEmployeeFilters'
import { useEmployeeExport } from '@/Composables/HumanResources/useEmployeeExport'
import EmployeeRegisterModal from '@/Components/Forms/EmployeeRegisterModal.vue'
import EmployeeToolbar from '@/Components/HumanResources/EmployeeToolbar.vue'
import EmployeeTable from '@/Components/HumanResources/EmployeeTable.vue'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

const { can } = usePermissions()

const props = defineProps({
    employeesDB: Array
})

const employeesDB = computed(() => props.employeesDB ?? [])

const {
    statusFilter,
    departmentFilter,
    positionFilter,
    recordsToShow,
    search,
    filteredEmployees
} = useEmployeeFilters(employeesDB)

const { exportExcel } = useEmployeeExport(
    statusFilter,
    departmentFilter,
    positionFilter,
    search
)

const {
    showModal,
    modalMode,
    selectedEmployee,
    openCreateModal,
    openEditModal,
    openViewModal,
    closeModal,
    deleteEmployee
} = useEmployeeActions()

function reloadEmployees() {
    router.reload({
        only: ['employeesDB'],
        preserveScroll: true,
        preserveState: true,
    })
}

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('systems')
      
})

onBeforeUnmount(() => {
    if (!window.Echo) return

    window.Echo.leave('systems')
})
</script>

<template>
    <div class="bg-[#f6f3f7] min-h-screen rounded-2xl md:rounded-3xl p-4 md:p-6">

        <h1 class="text-lg md:text-xl font-semibold text-slate-700 mb-5">
            Empleados
        </h1>
<EmployeeToolbar :filteredEmployees="filteredEmployees" :employeesDB="employeesDB" :recordsToShow="recordsToShow" :can-create="can('employees.create')" 
   :can-export="can('files.export')"
  @create="openCreateModal"
  @excel="exportExcel"
  @update:recordsToShow="recordsToShow = $event"
/>

<EmployeeTable :filteredEmployees="filteredEmployees" :search="search" :positionFilter="positionFilter" :departmentFilter="departmentFilter"
  :statusFilter="statusFilter" :can-view="can('employees.view')" :can-edit="can('employees.update')" :can-delete="can('employees.delete')"
  @update:search="search = $event"
  @update:positionFilter="positionFilter = $event"
  @update:departmentFilter="departmentFilter = $event"
  @update:statusFilter="statusFilter = $event"
  @view="openViewModal"
  @edit="openEditModal"
  @delete="deleteEmployee"
/>
    <EmployeeMobileCards :filteredEmployees="filteredEmployees" :can-view="can('employees.view')"  :can-edit="can('employees.update')" :can-delete="can('employees.delete')"
  @view="openViewModal"
  @edit="openEditModal"
  @delete="deleteEmployee"
/>   

        <EmployeeRegisterModal v-if="
            showModal &&
            (
                (modalMode === 'create' && can('employees.create')) ||
                (modalMode === 'edit' && can('employees.update')) ||
                (modalMode === 'view' && can('employees.view'))
            )
        " :mode="modalMode" :employeeToEdit="selectedEmployee" @close="closeModal" />

    </div>
</template>