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

function renderCellContent(row, column) {
  const value = getCellValue(row, column)
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
        <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
          <tr>
            <th v-if="selectable" class="px-4 py-3 w-10">
              <input v-model="allSelected" type="checkbox" class="w-4 h-4 border-slate-300 rounded cursor-pointer" />
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

        <tbody class="divide-y divide-slate-100 bg-white">
          <tr v-for="(row, index) in items" :key="row[rowKey] || index" :class="[
            'transition-colors',
            hoverEffect ? 'hover:bg-slate-50' : '',
            striped && index % 2 === 1 ? 'bg-slate-50/60' : '',
          ]" @click="handleRowClick(row)">
            <td v-if="selectable" class="px-4 py-3 w-10">
              <input type="checkbox" :checked="isRowSelected(row)"
                class="w-4 h-4 border-slate-300 rounded cursor-pointer" @click.stop @change="toggleRowSelection(row)" />
            </td>

            <td v-for="column in visibleColumns" :key="column.key" class="px-4 py-3 align-middle"
              :class="column.cellClass || ''">
              <template v-if="column.format === 'badge'">
                <span :class="[
                  'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap',
                  renderCellContent(row, column).classes,
                ]">
                  {{ renderCellContent(row, column).label }}
                </span>
              </template>

              <template v-else-if="column.format === 'image'">
                <img v-if="getCellValue(row, column)" :src="getCellValue(row, column)"
                  :alt="column.formatOptions?.alt || 'Imagen'" class="h-8 w-8 rounded object-cover" />

                <span v-else class="text-slate-400 text-xs">
                  {{ column.formatOptions?.fallback || 'Sin imagen' }}
                </span>
              </template>

              <template v-else>
                <div v-if="column.formatOptions?.multiline || column.subKey" class="space-y-0.5">
                  <div class="font-medium text-slate-800 truncate">
                    {{ getCellValue(row, column) }}
                  </div>

                  <div v-if="column.subKey" class="text-xs text-slate-400 truncate">
                    {{ getNestedValue(row, column.subKey) }}
                  </div>
                </div>

                <span v-else class="block truncate" :class="column.textClass || 'text-slate-700'">
                  {{ renderCellContent(row, column).content }}
                </span>
              </template>
            </td>

            <td v-if="canViewActions" class="px-4 py-3">
              <div class="flex items-center justify-center gap-2">
                <template v-for="action in actions" :key="action.id">
                  <ActionIconButton v-if="isActionVisible(action, row, { can })" :icon="action.icon"
                    :title="action.label" :variant="action.variant || 'blue'" @click.stop="handleAction(action, row)" />
                </template>
              </div>
            </td>
          </tr>

          <tr v-if="items.length === 0 && !loading">
            <td :colspan="totalColumns" class="px-4 py-10 text-center text-slate-500">
              {{ noDataMessage }}
            </td>
          </tr>

          <tr v-if="loading">
            <td :colspan="totalColumns" class="px-4 py-10 text-center text-slate-500">
              Cargando...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>