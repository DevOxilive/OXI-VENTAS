<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'

import { usePermissions } from '@/Composables/usePermissions'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { physicalCountTableConfig } from '@/config/TableConfigs/physicalCountTableConfig'
import { getPhysicalCountToolbarConfig } from '@/config/ToolbarConfigs/physicalCountToolbarConfig'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    physicalCounts: {
        type: Object,
        default: () => ({ data: [] }),
    },
    branches: {
        type: Array,
        default: () => [],
    },
    branch: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
    canViewReports: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const { can } = usePermissions()
const { handlePageChange } = useGlobalTablePagination({
    only: ['physicalCounts', 'filters'],
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const recordsPerPage = ref(Number(props.filters.per_page || 25))
const showCreateModal = ref(false)
let filterTimeout = null

const physicalCounts = computed(() => props.physicalCounts?.data || [])

const physicalCountToolbarConfig = computed(() =>
    getPhysicalCountToolbarConfig({
        branch: props.branch,
        canCreate: can('audits.physical-counts.create'),
        status: statusFilter.value,
    })
)

function handleToolbarFilter({ key, value }) {
    if (key === 'statusFilter') {
        statusFilter.value = value
    }
}

function reloadPhysicalCounts() {
    router.get(route('audits.physical-counts.index'), {
        branch: props.branch?.slug,
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        per_page: recordsPerPage.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['physicalCounts', 'filters'],
    })
}

function handleToolbarAction(action) {
    if (action === 'create') {
        showCreateModal.value = true
        return
    }
}

async function handleTableAction({ action, row }) {
    if (action === 'open') {
        router.visit(route('audits.physical-counts.show', row.id))
        return
    }

    if (action === 'close') {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Cerrar auditoria',
            message: 'Deseas cerrar esta auditoria? Ya no se podra capturar hasta reabrirla.',
            confirmText: 'Si, cerrar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.close', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoria',
            successTitle: 'Auditoria cerrada correctamente',
            errorTitle: 'Error al cerrar auditoria',
            errorMessage: 'No fue posible cerrar la auditoria.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))

        return
    }

    if (action === 'reopen') {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Reabrir auditoria',
            message: 'Deseas reabrir esta auditoria? Se permitira capturar nuevamente.',
            confirmText: 'Si, reabrir',
            cancelText: 'Cancelar',
            confirmButtonColor: '#2563eb',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.reopen', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoria',
            successTitle: 'Auditoria reabierta correctamente',
            errorTitle: 'Error al reabrir auditoria',
            errorMessage: 'No fue posible reabrir la auditoria.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))

        return
    }

    if (action === 'apply') {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Aplicar ajustes',
            message: 'Deseas aplicar los ajustes de esta auditoria? El stock se actualizara con base en el conteo fisico.',
            confirmText: 'Si, aplicar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#16a34a',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.apply-adjustments', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoria',
            successTitle: 'Ajustes aplicados correctamente',
            errorTitle: 'Error al aplicar ajustes',
            errorMessage: 'No fue posible aplicar los ajustes.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))

        return
    }

    if (action === 'delete') {
        const result = await confirmModalAction({
            mode: 'delete',
            title: 'Eliminar auditoria',
            message: 'Deseas eliminar esta auditoria? Esta accion no se puede deshacer.',
            confirmText: 'Si, eliminar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        })

        if (!result.isConfirmed) return

        router.delete(route('audits.physical-counts.destroy', row.id), getModalRequestOptions({
            mode: 'delete',
            entityName: 'Auditoria',
            successTitle: 'Auditoria eliminada correctamente',
            errorTitle: 'Error al eliminar auditoria',
            errorMessage: 'No fue posible eliminar la auditoria.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))
    }
}

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('audits')
        .listen('.PhysicalCountChanged', () => {
            reloadPhysicalCounts()
        })
})

onBeforeUnmount(() => {
    clearTimeout(filterTimeout)

    if (!window.Echo) return

    window.Echo.leave('audits')
})

watch([statusFilter, recordsPerPage], () => {
    reloadPhysicalCounts()
})

watch(search, () => {
    clearTimeout(filterTimeout)
    filterTimeout = setTimeout(() => {
        reloadPhysicalCounts()
    }, 350)
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="physicalCountToolbarConfig"
                :search="search"
                :records-per-page="recordsPerPage"
                :filtered-records="physicalCounts.length"
                :total-records="props.physicalCounts?.total || physicalCounts.length"
                @update:search="search = $event"
                @update:filter="handleToolbarFilter"
                @update:records-per-page="recordsPerPage = $event"
                @action="handleToolbarAction"
            />
        </template>

        <GlobalTable
            :items="physicalCounts"
            v-bind="physicalCountTableConfig"
            :pagination="props.physicalCounts"
            row-key="id"
            @page-change="handlePageChange"
            @action="handleTableAction"
        />

        <CreatePhysicalCountModal
            v-if="can('audits.physical-counts.create')"
            :show="showCreateModal"
            :branch="props.branch"
            :users="props.users"
            @close="showCreateModal = false"
        />
    </PageLayout>
</template>
