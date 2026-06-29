<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'
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
  mobileCardHeaderField: { type: String, required: true },
})

const emit = defineEmits([
  'action',
  'row-click',
])

const { can } = usePermissions()
const { getCellValue, isActionVisible } = useTableConfig(props)

const canViewActions = computed(() =>
  props.actions?.some(action => isActionVisible(action, {}, { can }))
)

const mobileBodyColumns = computed(() =>
  props.columns.filter(column =>
    column.mobileDisplay !== false &&
    !column.mobileBadge &&
    !column.mobileSecondary &&
    column.key !== props.mobileCardHeaderField
  )
)

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

function getActionButtonClasses(action) {
  const baseClasses = 'h-9 px-3 rounded-xl text-xs font-bold transition-colors inline-flex items-center justify-center gap-1'

  const variantMap = {
    blue: 'bg-blue-100 text-blue-700 hover:bg-blue-200',
    green: 'bg-green-100 text-green-700 hover:bg-green-200',
    red: 'bg-red-100 text-red-700 hover:bg-red-200',
    amber: 'bg-amber-100 text-amber-700 hover:bg-amber-200',
    slate: 'bg-slate-100 text-slate-600 hover:bg-slate-200',
    purple: 'bg-purple-100 text-purple-700 hover:bg-purple-200',
    orange: 'bg-orange-100 text-orange-700 hover:bg-orange-200',
    indigo: 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200',
    pink: 'bg-pink-100 text-pink-700 hover:bg-pink-200',
    cyan: 'bg-cyan-100 text-cyan-700 hover:bg-cyan-200',
  }

  return `${baseClasses} ${variantMap[action.variant] || variantMap.blue}`
}
</script>

<template>
  <div class="md:hidden space-y-2 p-3 bg-slate-50">
    <article v-for="(row, index) in items" :key="row[rowKey] || index"
      class="bg-white border border-slate-200 rounded-2xl p-3 shadow-sm" @click="handleRowClick(row)">
      <div class="flex justify-between items-start gap-3">
        <div class="min-w-0">
          <p v-if="mobileCardHeaderField" class="font-semibold text-slate-800 truncate">
            {{ getNestedValue(row, mobileCardHeaderField) }}
          </p>

          <template v-for="column in columns" :key="column.key">
            <p v-if="column.mobileSecondary" class="text-xs text-slate-500 truncate mt-0.5">
              {{ getCellValue(row, column) }}
            </p>
          </template>
        </div>

        <template v-for="column in columns" :key="column.key">
          <span v-if="column.mobileBadge" :class="[
            'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold shrink-0 whitespace-nowrap',
            column.format === 'badge'
              ? renderCellContent(row, column).classes
              : 'bg-slate-100 text-slate-600',
          ]">
            {{
              column.format === 'badge'
                ? renderCellContent(row, column).label
                : getCellValue(row, column)
            }}
          </span>
        </template>
      </div>

      <div v-if="mobileBodyColumns.length" class="mt-3 grid grid-cols-2 gap-3">
        <template v-for="column in mobileBodyColumns" :key="column.key">
          <div class="min-w-0">
            <p class="text-slate-400 text-xs font-medium">
              {{ column.mobileLabel || column.label }}
            </p>

            <span v-if="column.format === 'badge'" :class="[
              'inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold mt-1',
              renderCellContent(row, column).classes,
            ]">
              {{ renderCellContent(row, column).label }}
            </span>

            <img v-else-if="column.format === 'image' && getCellValue(row, column)" :src="getCellValue(row, column)"
              :alt="column.formatOptions?.alt || 'Imagen'" class="h-10 w-10 rounded object-cover mt-1" />

            <p v-else class="font-medium text-slate-700 truncate mt-0.5">
              {{ renderCellContent(row, column).content }}
            </p>
          </div>
        </template>
      </div>

      <div v-if="canViewActions" class="mt-3 flex justify-end gap-2 flex-wrap">
        <button v-for="action in actions" v-show="isActionVisible(action, row, { can })" :key="action.id" type="button"
          :title="action.label" :class="getActionButtonClasses(action)" @click.stop="handleAction(action, row)">
          <span v-if="action.icon" class="material-symbols-outlined text-[16px]">
            {{ action.icon }}
          </span>

          {{ action.label }}
        </button>
      </div>
    </article>

    <div v-if="items.length === 0 && !loading"
      class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
      {{ noDataMessage }}
    </div>

    <div v-if="loading" class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
      Cargando...
    </div>
  </div>
</template>