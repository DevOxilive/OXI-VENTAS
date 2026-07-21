<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'
import { formatCellValue, getNestedValue } from './tableFormatters.js'
import { getStatusClasses } from './tableStatusClasses.js'
import { useTableConfig } from './useTableConfig.js'

const props = defineProps({
  items: { type: Array, default: () => [] },
  columns: { type: Array, required: true },
  actions: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  noDataMessage: { type: String, default: 'No se encontraron registros' },
  loading: Boolean,
  hoverEffect: { type: Boolean, default: true },
  striped: Boolean,
  selectable: Boolean,
  selectedItems: Object,
})

const emit = defineEmits([
  'action',
  'row-click',
  'selection-change',
  'update:selectedItems',
])

const { can } = usePermissions()
const { visibleColumns, getCellValue, isActionVisible, totalColumns } = useTableConfig(props)

const canViewActions = computed(() =>
  props.actions?.some(action => isActionVisible(action, {}, { can }))
)

const tableRows = computed(() =>
  props.items.map((row, index) => ({
    row,
    index,
    key: row[props.rowKey] || index,
    cells: visibleColumns.value.map((column) => {
      const value = getCellValue(row, column)

      return {
        key: column.key,
        column,
        value,
        subValue: column.subKey ? getNestedValue(row, column.subKey) : null,
        content: renderCellContentFromValue(value, column),
      }
    }),
    visibleActions: canViewActions.value
      ? props.actions.filter((action) => isActionVisible(action, row, { can }))
      : [],
  }))
)

const allSelected = computed({
  get: () => props.items.length > 0 && props.items.every(item => props.selectedItems?.[item[props.rowKey]]),
  set: (value) => {
    const selected = {}

    if (value) {
      props.items.forEach(item => {
        selected[item[props.rowKey]] = true
      })
    }

    emit('update:selectedItems', selected)
    emit('selection-change', Object.keys(selected).filter(key => selected[key]))
  },
})

function toggleRowSelection(row) {
  const key = row[props.rowKey]
  const selected = { ...props.selectedItems }

  selected[key] = !selected[key]

  emit('update:selectedItems', selected)
  emit('selection-change', Object.keys(selected).filter(k => selected[k]))
}

function isRowSelected(row) {
  return props.selectedItems?.[row[props.rowKey]] || false
}

function handleAction(action, row) {
  emit('action', { action: action.id, row })

  if (action.handler) {
    action.handler(row)
  }
}

function handleRowClick(row) {
  emit('row-click', row)
}

function renderCellContentFromValue(value, column) {
  const formatted = formatCellValue(value, column)

  if (column.format === 'badge') {
    const statusMap = column.formatOptions?.statusMap || {}
    const color = column.formatOptions?.colorMap?.[value] || 'slate'
    const classes = getStatusClasses(value, statusMap, color)

    return {
      type: 'badge',
      label: formatted.label || formatted,
      classes,
    }
  }

  if (column.format === 'image') {
    return {
      type: 'image',
      ...formatted,
    }
  }

  if (column.format === 'swatch') {
    return {
      type: 'swatch',
      ...formatted,
    }
  }

  return {
    type: 'text',
    content: formatted,
  }
}
</script>

<template>
  <div class="hidden md:block">
    <div class="overflow-x-auto">
      <table class="w-full text-sm border-collapse">
        <thead class="border-b border-secondary bg-secondary text-text">
          <tr>
            <th v-if="selectable" class="px-4 py-3 w-10">
              <button
                type="button"
                role="checkbox"
                :aria-checked="allSelected"
                aria-label="Seleccionar todos los registros"
                class="grid h-5 w-5 place-items-center rounded-md border-2 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary/30"
                :class="allSelected ? 'border-primary bg-primary text-background shadow-sm' : 'border-secondary bg-background text-transparent hover:border-primary'"
                @click="allSelected = !allSelected"
              >
                <svg viewBox="0 0 16 16" class="h-4 w-4" fill="none" aria-hidden="true">
                  <path d="M3.5 8.5 6.5 11.5 12.5 4.75" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.35" />
                </svg>
              </button>
            </th>

            <th v-for="column in visibleColumns" :key="column.key"
              class="text-left px-4 py-3 font-semibold whitespace-nowrap"
              :style="column.width ? { width: column.width } : {}">
              {{ column.label }}
            </th>

            <th v-if="canViewActions" class="text-center px-4 py-3 font-semibold whitespace-nowrap">
              Acciones
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-secondary bg-background">
          <tr v-for="tableRow in tableRows" :key="tableRow.key" :class="[
            'transition-colors',
            hoverEffect ? 'hover:bg-secondary' : '',
            striped && tableRow.index % 2 === 1 ? 'bg-secondary' : '',
            selectable && isRowSelected(tableRow.row) ? 'bg-secondary' : '',
          ]" @click="handleRowClick(tableRow.row)">
            <td v-if="selectable" class="px-4 py-3 w-10">
              <button
                type="button"
                role="checkbox"
                :aria-checked="isRowSelected(tableRow.row)"
                :aria-label="`Seleccionar ${tableRow.row[rowKey]}`"
                class="grid h-5 w-5 place-items-center rounded-md border-2 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary/30"
                :class="isRowSelected(tableRow.row) ? 'border-primary bg-primary text-background shadow-sm' : 'border-secondary bg-background text-transparent hover:border-primary'"
                @click.stop="toggleRowSelection(tableRow.row)"
              >
                <svg viewBox="0 0 16 16" class="h-4 w-4" fill="none" aria-hidden="true">
                  <path d="M3.5 8.5 6.5 11.5 12.5 4.75" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.35" />
                </svg>
              </button>
            </td>

            <td v-for="cell in tableRow.cells" :key="cell.key" class="px-4 py-3 align-middle"
              :class="cell.column.cellClass || ''">
              <template v-if="cell.column.format === 'badge'">
                <span :class="[
                  'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap',
                  cell.content.classes,
                ]">
                  {{ cell.content.label }}
                </span>
              </template>

              <template v-else-if="cell.column.format === 'image'">
                <img v-if="cell.value" :src="cell.value"
                  :alt="cell.column.formatOptions?.alt || 'Imagen'" class="h-8 w-8 rounded object-cover" />

                <span v-else class="text-xs text-text opacity-50">
                  {{ cell.column.formatOptions?.fallback || 'Sin imagen' }}
                </span>
              </template>

              <template v-else-if="cell.column.format === 'swatch'">
                <div class="flex items-center gap-3">
                  <span
                    class="h-4 w-4 shrink-0 rounded-full border border-secondary"
                    :style="{ backgroundColor: cell.content.color }"
                  />

                  <span class="font-medium text-text">
                    {{ cell.content.label }}
                  </span>
                </div>
              </template>

              <template v-else>
                <div v-if="cell.column.formatOptions?.multiline || cell.column.subKey" class="space-y-0.5">
                  <div class="truncate font-medium text-text">
                    {{ cell.value }}
                  </div>

                  <div v-if="cell.column.subKey" class="truncate text-xs text-text opacity-50">
                    {{ cell.subValue }}
                  </div>
                </div>

                <span v-else class="block truncate" :class="cell.column.textClass || 'text-text opacity-80'">
                  {{ cell.content.content }}
                </span>
              </template>
            </td>

            <td v-if="canViewActions" class="px-4 py-3">
              <div class="flex items-center justify-center gap-2">
                <template v-for="action in tableRow.visibleActions" :key="action.id">
                  <ActionIconButton :icon="action.icon"
                    :title="action.label" :variant="action.variant || 'blue'" @click.stop="handleAction(action, tableRow.row)" />
                </template>
              </div>
            </td>
          </tr>

          <tr v-if="items.length === 0 && !loading">
            <td :colspan="totalColumns" class="px-4 py-10 text-center text-text opacity-70">
              {{ noDataMessage }}
            </td>
          </tr>

          <tr v-if="loading">
            <td :colspan="totalColumns" class="px-4 py-10 text-center text-text opacity-70">
              Cargando...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
