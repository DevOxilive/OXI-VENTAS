<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useEmployeeActions } from '@/Composables/HumanResources/useEmployeeActions'
import { useEmployeeExport } from '@/Composables/HumanResources/useEmployeeExport'
import { getEmployeeToolbarConfig } from '@/config/ToolbarConfigs/employeeToolbarConfig'
import EmployeeRegisterModal from '@/Components/Forms/EmployeeRegisterModal.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { employeeTableConfig } from '@/config/TableConfigs/employeeTableConfig'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

const { can } = usePermissions()

const props = defineProps({
    employeesDB: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters.search || '')
const recordsPerPage = ref(props.filters.perPage || 50)

const statusFilter = ref(props.filters.employmentStatus || '')
const departmentFilter = ref(props.filters.department || '')
const positionFilter = ref(props.filters.position || '')

const employees = computed(() => props.employeesDB?.data || [])
const employeePaginator = computed(() => props.employeesDB || {})

const normalizedEmployees = computed(() =>
    employees.value.map((employee) => ({
        ...employee,
        fullName: `${employee.firstName || ''} ${employee.lastName || ''}`.trim(),
    }))
)

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
    deleteEmployee,
} = useEmployeeActions()

const employeeToolbarConfig = computed(() =>
    getEmployeeToolbarConfig({
        canCreate: can('employees.create'),
        canExport: can('files.export'),
    })
)

function reloadEmployees() {
    router.get(route('human-resources.employees.index'), {
        search: search.value,
        per_page: recordsPerPage.value,
        employmentStatus: statusFilter.value,
        department: departmentFilter.value,
        position: positionFilter.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function handlePageChange(url) {
    if (!url) return

    router.get(url, {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

watch(search, reloadEmployees)
watch(recordsPerPage, reloadEmployees)
watch(statusFilter, reloadEmployees)
watch(departmentFilter, reloadEmployees)
watch(positionFilter, reloadEmployees)

function handleEmployeeTableAction({ action, row }) {
    if (action === 'view' && can('employees.view')) openViewModal(row)
    if (action === 'edit' && can('employees.update')) openEditModal(row)
    if (action === 'delete' && can('employees.delete')) deleteEmployee(row)
}

function handleEmployeeToolbarAction(action) {
    if (action === 'create') openCreateModal()
    if (action === 'export') exportExcel()
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
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar title="Empleados" subtitle="Administración del personal registrado"
                v-bind="employeeToolbarConfig" :search="search" :records-per-page="recordsPerPage"
                :records-per-page-options="[10, 25, 50, 100]" :filtered-records="normalizedEmployees.length"
                :total-records="employeePaginator?.total || 0" @action="handleEmployeeToolbarAction"
                @update:search="search = $event" @update:records-per-page="recordsPerPage = $event" />
        </template>

        <GlobalTable :items="normalizedEmployees" v-bind="employeeTableConfig" :pagination="employeePaginator"
            @page-change="handlePageChange" @action="handleEmployeeTableAction" />

        <EmployeeRegisterModal v-if="
            showModal &&
            (
                (modalMode === 'create' && can('employees.create')) ||
                (modalMode === 'edit' && can('employees.update')) ||
                (modalMode === 'view' && can('employees.view'))
            )
        " :mode="modalMode" :employeeToEdit="selectedEmployee" @close="closeModal" />
    </PageLayout>
</template>