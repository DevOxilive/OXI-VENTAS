<script setup>
import { computed, ref } from 'vue'
import { formatInventoryQuantity } from '@/utils/quantityFormatter'

const props = defineProps({
  comparison: {
    type: Array,
    default: () => [],
  },
})

const activeFilter = ref('all')

const filters = [
  { key: 'all', label: 'Todos' },
  { key: 'missing', label: 'Faltantes' },
  { key: 'surplus', label: 'Sobrantes' },
  { key: 'matched', label: 'Correctos' },
]

const filteredComparison = computed(() => {
  if (activeFilter.value === 'all') return props.comparison

  return props.comparison.filter(item => item.status === activeFilter.value)
})

const statusLabel = (status) => {
  if (status === 'missing') return 'Faltante'
  if (status === 'surplus') return 'Sobrante'
  return 'Correcto'
}

const statusClass = (status) => {
  if (status === 'missing') return 'bg-secondary text-primary'
  if (status === 'surplus') return 'bg-secondary text-accent'
  return 'bg-secondary text-accent'
}

function unit(item) {
  return item.inventory_unit ?? item.base_unit ?? 'pza'
}

function quantity(item, key) {
  return formatInventoryQuantity(item[key] ?? 0, unit(item))
}

function difference(item) {
  if (item.difference === null || item.difference === undefined) return '-'

  const formatted = quantity(item, 'difference')
  return Number(item.difference) > 0 ? `+${formatted}` : formatted
}

</script>

<template>
  <div class="mt-4 flex flex-wrap gap-2">
  <button
    v-for="filter in filters"
    :key="filter.key"
    type="button"
    class="rounded-full border px-3 py-1.5 text-xs font-semibold"
    :class="activeFilter === filter.key
      ? 'border-primary bg-primary text-white'
      : 'border-secondary bg-background text-text hover:bg-secondary'"
    @click="activeFilter = filter.key"
  >
    {{ filter.label }}
  </button>
</div>
  <div class="min-w-0 rounded-xl border border-secondary bg-background p-4 shadow-sm">
    <h2 class="text-lg font-semibold text-text">
      Comparativo de inventario
    </h2>

    <div class="mt-4 min-w-0 overflow-hidden rounded-xl border border-secondary">
      <table class="w-full table-fixed border-collapse text-left text-sm">
        <thead class="border-b border-secondary bg-secondary text-text">
          <tr>
            <th class="py-2">Producto</th>
            <th class="py-2">Sistema</th>
            <th class="py-2">Contado</th>
            <th class="py-2">Dañado</th>
            <th class="py-2">Caducado</th>
            <th class="py-2">Vendible</th>
            <th class="py-2">Diferencia</th>
            <th class="py-2">Estado</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-secondary bg-background">
     <tr v-if="filteredComparison.length === 0">
            <td colspan="8" class="py-6 text-center text-text opacity-70">
              Todavía no hay productos comparados.
            </td>
          </tr>

         <tr
  v-for="item in filteredComparison"
  :key="item.branch_product_id"
  class="odd:bg-secondary transition-colors hover:bg-primary/10"
>
            <td class="truncate py-3 pr-3 text-text" :title="item.product_name">
              {{ item.product_name }}
            </td>

            <td class="py-3">
              {{ quantity(item, 'system_stock') }}
            </td>

            <td class="py-3">
              {{ quantity(item, 'counted_stock') }}
            </td>

            <td class="py-3">
              {{ quantity(item, 'damaged_stock') }}
            </td>

            <td class="py-3">
              {{ quantity(item, 'expired_stock') }}
            </td>
            <td class="py-3 font-semibold text-text">
{{ quantity(item, 'sellable_stock') }}
</td>

            <td
              class="py-3 font-semibold"
              :class="{
                'text-primary': item.difference < 0,
                'text-accent': item.difference === 0,
                'text-accent': item.difference > 0,
              }"
            >
              {{ difference(item) }}
            </td>

            <td class="py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="statusClass(item.status)"
              >
                {{ statusLabel(item.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
