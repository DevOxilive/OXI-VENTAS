<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'

import { usePermissions } from '@/Composables/usePermissions'
import { physicalCountTableConfig } from '@/config/TableConfigs/physicalCountTableConfig'
import { getPhysicalCountToolbarConfig } from '@/config/ToolbarConfigs/physicalCountToolbarConfig'
defineOptions({ layout: AdminLayout })

const props = defineProps({
    physicalCounts: {
        type: Array,
        default: () => [],
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
})

const { can } = usePermissions()

const search = ref('')
const statusFilter = ref('')
const showCreateModal = ref(false)

const filteredPhysicalCounts = computed(() => {
    return props.physicalCounts.filter((physicalCount) => {
        const searchValue = search.value.toLowerCase().trim()

        const matchesSearch =
            !searchValue ||
            physicalCount.name?.toLowerCase().includes(searchValue) ||
            physicalCount.folio?.toLowerCase().includes(searchValue) ||
            physicalCount.status?.toLowerCase().includes(searchValue)

        const matchesStatus =
            !statusFilter.value || physicalCount.status === statusFilter.value

        return matchesSearch && matchesStatus
    })
})

const physicalCountToolbarConfig = computed(() =>
    getPhysicalCountToolbarConfig({
        branch: props.branch,
        canCreate: can('audits.physical-counts.create'),
    })
)

function handleToolbarFilter({ key, value }) {
    if (key === 'statusFilter') {
        statusFilter.value = value
    }
}

function handleToolbarAction(action) {
    if (action === 'create') {
        showCreateModal.value = true
        return
    }
}

function reloadPhysicalCounts() {
    router.reload({
        only: ['physicalCounts'],
        preserveScroll: true,
        preserveState: true,
    })
}

async function handleTableAction({ action, row }) {
    if (action === 'open') {
        router.visit(route('audits.physical-counts.show', row.id))
        return
    }

    if (action === 'close') {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Cerrar auditor?a',
            message: '?Deseas cerrar esta auditor?a? Ya no se podr? capturar hasta reabrirla.',
            confirmText: 'S?, cerrar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.close', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoría',
            successTitle: 'Auditor?a cerrada correctamente',
            errorTitle: 'Error al cerrar auditor?a',
            errorMessage: 'No fue posible cerrar la auditor?a.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))

        return
    }

    if (action === 'reopen') {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Reabrir auditor?a',
            message: '?Deseas reabrir esta auditor?a? Se permitir? capturar nuevamente.',
            confirmText: 'S?, reabrir',
            cancelText: 'Cancelar',
            confirmButtonColor: '#2563eb',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.reopen', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoría',
            successTitle: 'Auditor?a reabierta correctamente',
            errorTitle: 'Error al reabrir auditor?a',
            errorMessage: 'No fue posible reabrir la auditor?a.',
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
            message: '?Deseas aplicar los ajustes de esta auditor?a? El stock se actualizar? con base en el conteo f?sico.',
            confirmText: 'S?, aplicar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#16a34a',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.apply-adjustments', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoría',
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
            title: 'Eliminar auditor?a',
            message: '?Deseas eliminar esta auditor?a? Esta acci?n no se puede deshacer.',
            confirmText: 'S?, eliminar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        })

        if (!result.isConfirmed) return

        router.delete(route('audits.physical-counts.destroy', row.id), getModalRequestOptions({
            mode: 'delete',
            entityName: 'Auditoría',
            successTitle: 'Auditor?a eliminada correctamente',
            errorTitle: 'Error al eliminar auditor?a',
            errorMessage: 'No fue posible eliminar la auditor?a.',
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
    if (!window.Echo) return

    window.Echo.leave('audits')
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar v-bind="physicalCountToolbarConfig" :search="search"
                :filtered-records="filteredPhysicalCounts.length" :total-records="physicalCounts.length"
                @update:search="search = $event" @update:filter="handleToolbarFilter" @action="handleToolbarAction" />
        </template>

        <GlobalTable :items="filteredPhysicalCounts" v-bind="physicalCountTableConfig" row-key="id"
            :show-pagination="false" @action="handleTableAction" />

        <CreatePhysicalCountModal v-if="can('audits.physical-counts.create')" :show="showCreateModal"
            :branch="props.branch" :users="props.users" @close="showCreateModal = false" />
    </PageLayout>
</template>
