<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import { useEmployeeActions } from '@/Composables/HumanResources/useEmployeeActions'
import { useEmployeeExport } from '@/Composables/HumanResources/useEmployeeExport'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePermissions } from '@/Composables/usePermissions'

import EmployeeToolbar from '@/Components/HumanResourses/EmployeeToolbar.vue'
import EmployeeTable from '@/Components/HumanResourses/EmployeeTable.vue'
import EmployeeRegisterModal from '@/Components/HumanResourses/EmployeeRegisterModal.vue'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

let unsubscribeEmployeeChanged = null
let unsubscribeOrganizationStructureChanged = null

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
    filterOptions: {
        type: Object,
        default: () => ({}),
    },
    organizationOptions: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters.search || '')
const recordsPerPage = ref(props.filters.perPage || 50)
const { handlePageChange } = useGlobalTablePagination()

const statusFilter = ref(props.filters.employmentStatus || '')
const departmentFilter = ref(props.filters.department || '')
const positionFilter = ref(props.filters.position || '')
const startDateFromFilter = ref(props.filters.startDateFrom || '')
const startDateToFilter = ref(props.filters.startDateTo || '')
const employees = computed(() => props.employeesDB?.data || [])
const employeePaginator = computed(() => props.employeesDB || {})
const positions = computed(() => props.filterOptions?.positions || [])
const departments = computed(() => props.filterOptions?.departments || [])
const statuses = computed(() => props.filterOptions?.statuses || [])

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
    search,
    startDateFromFilter,
    startDateToFilter,
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

const liveSelectedEmployee = computed(() => {
    if (!selectedEmployee.value?.id) return selectedEmployee.value

    return normalizedEmployees.value.find((employee) => {
        return employee.id === selectedEmployee.value.id
    }) ?? selectedEmployee.value
})

function reloadEmployees() {
    router.get(route('human-resources.employees.index'), {
        search: search.value,
        per_page: recordsPerPage.value,
        employmentStatus: statusFilter.value,
        department: departmentFilter.value,
        position: positionFilter.value,
        startDateFrom: startDateFromFilter.value,
        startDateTo: startDateToFilter.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function refreshEmployeesRealtime() {
    router.reload({
        only: ['employeesDB', 'filterOptions', 'organizationOptions'],
        preserveScroll: true,
        preserveState: true,
    })
}

watch(search, reloadEmployees)
watch(recordsPerPage, reloadEmployees)
watch(statusFilter, reloadEmployees)
watch(departmentFilter, reloadEmployees)
watch(positionFilter, reloadEmployees)
watch(startDateFromFilter, reloadEmployees)
watch(startDateToFilter, reloadEmployees)

function handleEmployeeToolbarFilter({ key, value }) {
    if (key === 'employmentStatus') statusFilter.value = value
    if (key === 'department') departmentFilter.value = value
    if (key === 'position') positionFilter.value = value
    if (key === 'startDateFrom') startDateFromFilter.value = value
    if (key === 'startDateTo') startDateToFilter.value = value
}

function handleEmployeeTableAction({ action, row }) {
    if (action === 'view' && can('employees.view')) openViewModal(row)
    if (action === 'edit' && can('employees.update')) openEditModal(row)
    if (action === 'delete' && can('employees.delete')) deleteEmployee(row)
}

function handleEmployeeToolbarAction(action) {
    if (action === 'create' && can('employees.create')) openCreateModal()
    if (action === 'export' && can('files.export')) exportExcel()
}

onMounted(() => {
    unsubscribeEmployeeChanged = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.employeeChanged,
        (event) => {
            if (
                event.action === 'deleted' &&
                selectedEmployee.value?.id === event.employeeId
            ) {
                closeModal()
            }

            refreshEmployeesRealtime()
        },
    )

    unsubscribeOrganizationStructureChanged = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.organizationStructureChanged,
        refreshEmployeesRealtime,
    )
})

onBeforeUnmount(() => {
    unsubscribeEmployeeChanged?.()
    unsubscribeOrganizationStructureChanged?.()
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <EmployeeToolbar :search="search" :records-per-page="recordsPerPage"
                :active-filters="{
                    employmentStatus: statusFilter,
                    department: departmentFilter,
                    position: positionFilter,
                    startDateFrom: startDateFromFilter,
                    startDateTo: startDateToFilter,
                }"
                :positions="positions"
                :departments="departments"
                :statuses="statuses"
                :filtered-records="normalizedEmployees.length" :total-records="employeePaginator?.total || 0"
                :can-create="can('employees.create')" :can-export="can('files.export')"
                @action="handleEmployeeToolbarAction" @update:search="search = $event"
                @update:filter="handleEmployeeToolbarFilter"
                @update:records-per-page="recordsPerPage = $event" />
        </template>

        <EmployeeTable :employees="normalizedEmployees" :pagination="employeePaginator" @page-change="handlePageChange"
            @action="handleEmployeeTableAction" />

        <EmployeeRegisterModal v-if="
            showModal &&
            (
                (modalMode === 'create' && can('employees.create')) ||
                (modalMode === 'edit' && can('employees.update')) ||
                (modalMode === 'view' && can('employees.view'))
            )
        " :mode="modalMode" :employeeToEdit="liveSelectedEmployee"
            :organization-options="organizationOptions" @close="closeModal" />
    </PageLayout>
</template>
