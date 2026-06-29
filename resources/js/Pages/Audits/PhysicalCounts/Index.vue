<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'
import {
    ErrorAlert,
    ToastAlert,
    UniversalActionModal,
} from '@/Components/Modales/UniversalActionModal'

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

function notifyError(title, message) {
    ErrorAlert({ title, message })
}

async function handleTableAction({ action, row }) {
    if (action === 'open') {
        router.visit(route('audits.physical-counts.show', row.id))
        return
    }

    if (action === 'close') {
        const result = await UniversalActionModal({
            title: 'Cerrar auditoría',
            message: '¿Deseas cerrar esta auditoría? Ya no se podrá capturar hasta reabrirla.',
            confirmText: 'Sí, cerrar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.close', row.id), {}, {
            preserveScroll: true,
            onSuccess: () => ToastAlert({ title: 'Auditoría cerrada correctamente' }),
            onError: () => notifyError('Error al cerrar auditoría', 'No fue posible cerrar la auditoría.'),
        })

        return
    }

    if (action === 'reopen') {
        const result = await UniversalActionModal({
            title: 'Reabrir auditoría',
            message: '¿Deseas reabrir esta auditoría? Se permitirá capturar nuevamente.',
            confirmText: 'Sí, reabrir',
            cancelText: 'Cancelar',
            confirmButtonColor: '#2563eb',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.reopen', row.id), {}, {
            preserveScroll: true,
            onSuccess: () => ToastAlert({ title: 'Auditoría reabierta correctamente' }),
            onError: () => notifyError('Error al reabrir auditoría', 'No fue posible reabrir la auditoría.'),
        })

        return
    }

    if (action === 'apply') {
        const result = await UniversalActionModal({
            title: 'Aplicar ajustes',
            message: '¿Deseas aplicar los ajustes de esta auditoría? El stock se actualizará con base en el conteo físico.',
            confirmText: 'Sí, aplicar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#16a34a',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.apply-adjustments', row.id), {}, {
            preserveScroll: true,
            onSuccess: () => ToastAlert({ title: 'Ajustes aplicados correctamente' }),
            onError: () => notifyError('Error al aplicar ajustes', 'No fue posible aplicar los ajustes.'),
        })

        return
    }

    if (action === 'delete') {
        const result = await UniversalActionModal({
            title: 'Eliminar auditoría',
            message: '¿Deseas eliminar esta auditoría? Esta acción no se puede deshacer.',
            confirmText: 'Sí, eliminar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        })

        if (!result.isConfirmed) return

        router.delete(route('audits.physical-counts.destroy', row.id), {
            preserveScroll: true,
            onSuccess: () => ToastAlert({ title: 'Auditoría eliminada correctamente' }),
            onError: () => notifyError('Error al eliminar auditoría', 'No fue posible eliminar la auditoría.'),
        })
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
