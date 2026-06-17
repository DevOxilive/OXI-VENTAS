<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { router } from '@inertiajs/vue3'

defineProps({
    physicalCounts: {
        type: Array,
        default: () => []
    }
})

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'folio', label: 'Folio' },
    
       {
    key: 'status',
    label: 'Estado',
    format: 'badge',
    formatOptions: {
        labelMap: {
            open: 'Abierto',
            closed: 'Cerrado',
            applied: 'Aplicado'
        },
        colorMap: {
            open: 'green',
            closed: 'slate',
            applied: 'blue'
        }
    }
}
]
const actions = [
    {
  
    id: 'open',
label: 'Ingresar a auditoría',
    icon: 'login',
    variant: 'blue'
},
    {
        id: 'close',
        label: 'Cerrar auditoría',
        icon: 'lock',
        variant: 'amber',
        show: (item) => item.status === 'open'
    },
    {
        id: 'reopen',
        label: 'Reabrir auditoría',
        icon: 'restart_alt',
        variant: 'green',
        show: (item) => item.status === 'closed'
    },
    {
        id: 'apply',
        label: 'Aplicar ajustes',
        icon: 'check_circle',
        variant: 'green',
        show: (item) => item.status === 'closed'
    },
    {
        id: 'delete',
        label: 'Eliminar auditoría',
        icon: 'delete',
        variant: 'red',
        show: (item) => item.status === 'open'
    }
]
function handleAction({ action, row }) {
    if (action === 'open') {
        router.visit(route('audits.physical-counts.show', row.id))
    }

    if (action === 'close') {
        if (!confirm('¿Seguro que deseas cerrar esta auditoría? Ya no se podrá capturar hasta reabrirla.')) return

        router.patch(route('audits.physical-counts.close', row.id), {}, {
            preserveScroll: true,
        })
    }

    if (action === 'reopen') {
        if (!confirm('¿Seguro que deseas reabrir esta auditoría? Se permitirá capturar nuevamente.')) return

        router.patch(route('audits.physical-counts.reopen', row.id), {}, {
            preserveScroll: true,
        })
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
}

    if (action === 'delete') {
        if (!confirm('¿Seguro que deseas eliminar esta auditoría? Esta acción no se puede deshacer.')) return

        router.delete(route('audits.physical-counts.destroy', row.id), {
            preserveScroll: true,
        })
    }
}
</script>

<template>
    <GlobalTable
        :items="physicalCounts"
        :columns="columns"
        :actions="actions"
        row-key="id"
        mobile-card-header-field="name"
        no-data-message="Todavía no hay conteos físicos creados."
        :show-records-per-page="false"
        :show-pagination="false"
        @action="handleAction"
    />
</template>