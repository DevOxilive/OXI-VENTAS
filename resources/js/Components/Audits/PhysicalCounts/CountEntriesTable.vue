<script setup>
import { computed } from 'vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'

const props = defineProps({
  entries: {
    type: Array,
    default: () => []
  },

  status: {
    type: String,
    default: 'open'
  }
})

const emits = defineEmits(['view', 'edit', 'delete'])

const tableEntries = computed(() =>
  props.entries.map((entry) => ({
    ...entry,
    productName: entry.branch_product?.product?.name ?? 'Sin producto',
    scannedCode: entry.scanned_code ?? '-',
    countedQuantity: entry.counted_quantity ?? 0,
    damagedQuantity: entry.damaged_quantity ?? 0,
    expiredQuantity: entry.expired_quantity ?? 0,
    lotNumber: entry.product_batch?.lot_number ?? 'N/A',
    expirationDate:
      entry.expiration_date ??
      entry.product_batch?.expiration_date ??
      null,
    userName: entry.user?.name ?? 'Sin usuario',
    createdDate: entry.created_at,
    createdTime: entry.created_at
  }))
)

const columns = [
  {
    key: 'productName',
    label: 'Producto',
    format: 'text',
    mobileLabel: 'Producto',
    mobileDisplay: true
  },
  {
    key: 'scannedCode',
    label: 'Código',
    format: 'text',
    mobileLabel: 'Código',
    mobileDisplay: true
  },
  {
    key: 'countedQuantity',
    label: 'Contado',
    format: 'number',
    mobileLabel: 'Contado',
    mobileDisplay: true
  },
  {
    key: 'damagedQuantity',
    label: 'Dañado',
    format: 'number',
    mobileLabel: 'Dañado',
    mobileDisplay: true
  },
  {
    key: 'expiredQuantity',
    label: 'Caducado',
    format: 'number',
    mobileLabel: 'Caducado',
    mobileDisplay: true
  },
  {
    key: 'lotNumber',
    label: 'Lote',
    format: 'text',
    mobileLabel: 'Lote',
    mobileDisplay: false
  },
  {
    key: 'expirationDate',
    label: 'Caducidad',
    format: 'date',
    mobileLabel: 'Caducidad',
    mobileDisplay: false
  },{
  key: 'userName',
  label: 'Registró',
  format: 'text',
  mobileLabel: 'Registró',
  mobileDisplay: true
},
  {
    key: 'createdDate',
    label: 'Fecha',
    format: 'date',
    mobileLabel: 'Fecha',
    mobileDisplay: false
  },
  {
    key: 'createdTime',
    label: 'Hora',
    format: 'time',
    mobileLabel: 'Hora',
    mobileDisplay: false
  }
  
]

const actions = [
  {
    id: 'view',
    label: 'Ver',
    icon: 'visibility',
    variant: 'blue',
    handler: (row) => emits('view', row)
  },
{
  id: 'edit',
  label: 'Editar',
  icon: 'edit',
  variant: 'amber',
  show: () => props.status === 'open',
  handler: (row) => emits('edit', row)
},
{
  id: 'delete',
  label: 'Eliminar',
  icon: 'delete',
  variant: 'red',
  show: () => props.status === 'open',
  handler: (row) => emits('delete', row)
}
]
function handleTableAction({ action, row }) {
  console.log('Acción recibida:', action, row)

  if (action === 'view') emits('view', row)
  else if (action === 'edit') emits('edit', row)
  else if (action === 'delete') emits('delete', row)
}
</script>

<template>
  <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
   <h2 class="mb-1 text-lg font-semibold text-gray-900">
  Últimos productos capturados
</h2>

<p class="mb-4 text-sm text-gray-500">
  Registros realizados dentro de esta auditoría, incluyendo el usuario que capturó cada conteo.
</p>

    <GlobalTable
      :items="tableEntries"
      :columns="columns"
      :actions="actions"
      mobile-card-header-field="productName"
      no-data-message="Sin registros todavía."
      :show-pagination="false"
      :show-records-per-page="false"
      @action="handleTableAction"
    />
  </div>
</template>