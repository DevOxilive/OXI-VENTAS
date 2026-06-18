<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'

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
    }
}

function reloadPhysicalCounts() {
    router.reload({
        only: ['physicalCounts'],
        preserveScroll: true,
        preserveState: true,
    })
}

function handleTableAction({ action, row }) {
    if (action === 'open') {
        router.visit(route('audits.physical-counts.show', row.id))
        return
    }

    if (action === 'close') {
        if (!confirm('¿Seguro que deseas cerrar esta auditoría? Ya no se podrá capturar hasta reabrirla.')) return

        router.patch(route('audits.physical-counts.close', row.id), {}, {
            preserveScroll: true,
        })

        return
    }

    if (action === 'reopen') {
        if (!confirm('¿Seguro que deseas reabrir esta auditoría? Se permitirá capturar nuevamente.')) return

        router.patch(route('audits.physical-counts.reopen', row.id), {}, {
            preserveScroll: true,
        })

        return
    }

    if (action === 'apply') {
        const confirmed = confirm(
            `¿Seguro que deseas aplicar los ajustes de esta auditoría?

Esta acción actualizará el stock del sistema con base en el conteo físico.

Importante:
- Los productos dañados no se sumarán al stock vendible.
- Los productos caducados no se sumarán al stock vendible.
- La auditoría quedará como aplicada.

Esta acción no debe revertirse manualmente.`
        )

        if (!confirmed) return

        router.patch(route('audits.physical-counts.apply-adjustments', row.id), {}, {
            preserveScroll: true,
        })

        return
    }

    if (action === 'delete') {
        if (!confirm('¿Seguro que deseas eliminar esta auditoría? Esta acción no se puede deshacer.')) return

        router.delete(route('audits.physical-counts.destroy', row.id), {
            preserveScroll: true,
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
            :branch="props.branch" @close="showCreateModal = false" />
    </PageLayout>
</template>