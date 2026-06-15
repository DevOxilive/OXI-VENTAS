<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { productTableConfig } from '@/config/TableConfigs/productTableConfig'

defineProps({
  products: {
    type: Array,
    default: () => []
  },
  pagination: {
    type: [Object, Array],
    default: null
  },
  recordsPerPage: {
    type: Number,
    default: 50
  }
})

const emits = defineEmits([
  'view',
  'edit',
  'delete',
  'update:recordsPerPage',
  'page-change'
])

function handleTableAction({ action, row }) {
  if (action === 'view') emits('view', row)
  else if (action === 'edit') emits('edit', row)
  else if (action === 'delete') emits('delete', row)
}
</script>

<template>
  <GlobalTable :items="products" v-bind="productTableConfig" :pagination="pagination" :records-per-page="recordsPerPage"
    @action="handleTableAction" @update:records-per-page="$emit('update:recordsPerPage', $event)"
    @page-change="$emit('page-change', $event)" />
</template>