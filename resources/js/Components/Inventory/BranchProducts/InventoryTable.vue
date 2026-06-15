<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { inventoryTableConfig } from '@/Config/TableConfigs/inventoryTableConfig'

defineProps({
    filteredProducts: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: [Object, Array],
        default: null,
    },
    recordsPerPage: {
        type: Number,
        default: 50,
    },
})

const emit = defineEmits([
    'view',
    'edit',
    'entry',
    'exit',
    'movements',
    'batches',
    'delete',
    'update:recordsPerPage',
    'page-change',
])

function handleTableAction({ action, row }) {
    if (action === 'entry') emit('entry', row)
    else if (action === 'exit') emit('exit', row)
    else if (action === 'movements') emit('movements', row)
    else if (action === 'batches') emit('batches', row)
    else if (action === 'view') emit('view', row)
    else if (action === 'edit') emit('edit', row)
    else if (action === 'delete') emit('delete', row)
}
</script>

<template>
    <GlobalTable :items="filteredProducts" v-bind="inventoryTableConfig" :pagination="pagination"
        :records-per-page="recordsPerPage" @action="handleTableAction"
        @update:records-per-page="$emit('update:recordsPerPage', $event)" @page-change="$emit('page-change', $event)" />
</template>