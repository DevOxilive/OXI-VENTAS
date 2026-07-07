

<script setup>
import { computed, ref, watch } from 'vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'

const props = defineProps({
  entries: {
    type: Array,
    default: () => []
  },

  status: {
    type: String,
    default: 'open'
  },

  searchTerm: {
    type: String,
    default: ''
  }
})

const emits = defineEmits(['view', 'edit', 'delete', 'search'])

const search = ref(props.searchTerm)
let searchTimeout = null

watch(
  () => props.searchTerm,
  (value) => {
    if ((value || '') !== search.value) {
      search.value = value || ''
    }
  }
)

watch(search, (value) => {
  if (value.trim() === (props.searchTerm || '').trim()) return

  window.clearTimeout(searchTimeout)

  searchTimeout = window.setTimeout(() => {
    emits('search', value.trim())
  }, 350)
})

const tableEntries = computed(() =>
  props.entries.map((entry) => ({
    ...entry,
    productName: entry.branch_product?.product?.name ?? 'Sin producto',
    scannedCode: entry.scanned_code && entry.scanned_code !== 'Seleccionado manualmente'
      ? entry.scanned_code
      : (entry.branch_product?.barcode ?? '-'),
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

const filteredEntries = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (!term || props.searchTerm) return tableEntries.value

  return tableEntries.value.filter((entry) => {
    return [
      entry.productName,
      entry.scannedCode,
      entry.lotNumber,
      entry.expirationDate,
      entry.userName,
      entry.createdDate,
      entry.createdTime
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term))
  })
})

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
  },
  {
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
    permission: [
      'audits.physical-counts.view',
      'audits.physical-counts.update',
      'audits.physical-counts.delete',
    ],
    handler: (row) => emits('view', row)
  },
  {
    id: 'edit',
    label: 'Editar',
    icon: 'edit',
    variant: 'amber',
    permission: 'audits.physical-counts.update',
    hidden: () => props.status !== 'open',
    handler: (row) => emits('edit', row)
  },
  {
    id: 'delete',
    label: 'Eliminar',
    icon: 'delete',
    variant: 'red',
    permission: 'audits.physical-counts.delete',
    hidden: () => props.status !== 'open',
    handler: (row) => emits('delete', row)
  }
]

function handleTableAction({ action, row }) {
  if (action === 'view') emits('view', row)
  else if (action === 'edit') emits('edit', row)
  else if (action === 'delete') emits('delete', row)
}
</script><template>
  <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <h2 class="mb-1 text-lg font-semibold text-gray-900">
      Últimos productos capturados
    </h2>

    <p class="mb-4 text-sm text-gray-500">
      Registros realizados dentro de esta auditoría, incluyendo el usuario que capturó cada conteo.
    </p>

    <div class="mb-4">
      <input
        v-model="search"
        type="search"
        placeholder="Buscar por producto, código, lote, usuario o fecha..."
        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
      />
      <p class="mt-2 text-xs text-gray-500">
        La búsqueda revisa todos los productos contados de esta auditoría.
      </p>
    </div>

    <GlobalTable
      :items="filteredEntries"
      :columns="columns"
      :actions="actions"
      mobile-card-header-field="productName"
      no-data-message="Sin registros encontrados."
      :show-pagination="false"
      :show-records-per-page="false"
      @action="handleTableAction"
    />
  </div>
</template>
