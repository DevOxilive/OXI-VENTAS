<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'
import { formatCellValue, getNestedValue } from './tableFormatters.js'
import { getStatusClasses } from './tableStatusClasses.js'
import { useTableConfig } from './useTableConfig.js'

const props = defineProps({
  items: {
    type: Array,
    default: () => []
  },
  columns: {
    type: Array,
    required: true
  },
  actions: {
    type: Array,
    default: () => []
  },
  filters: Array,
  rowKey: {
    type: String,
    default: 'id'
  },
  noDataMessage: {
    type: String,
    default: 'No se encontraron registros'
  },
  loading: Boolean,
  mobileCardHeaderField: {
    type: String,
    required: true
  }
})

const emit = defineEmits([
  'action',
  'row-click',
])

const { can } = usePermissions()
const { getCellValue, isActionVisible } = useTableConfig(props)

const canViewActions = computed(() => {
  return props.actions && props.actions.some(action => isActionVisible(action, {}, { can }))
})

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
      ...formatted
    }
  }

  return {
    type: 'text',
    content: formatted
  }
}

function getActionButton(action, row) {
  const baseClasses = 'px-3 py-2 rounded-xl text-sm font-medium transition-colors'
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
  <div class="md:hidden space-y-3">
    <!-- Data Cards -->
    <div v-for="(row, index) in items" :key="row[rowKey] || index"
      class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm" @click="handleRowClick(row)">
      <!-- Card Header Section -->
      <div class="flex justify-between items-start gap-3">
        <div>
          <!-- Primary Field (required) -->
          <p v-if="mobileCardHeaderField" class="font-semibold text-slate-800">
            {{ getNestedValue(row, mobileCardHeaderField) }}
          </p>

          <!-- Secondary fields from columns marked for mobile header -->
          <template v-for="column in columns" :key="column.key">
            <p v-if="column.mobileSecondary" class="text-sm text-slate-500">
              {{ getCellValue(row, column) }}
            </p>
          </template>
        </div>

        <!-- Header Badge (if specified) -->
        <template v-if="columns.find(c => c.mobileBadge)">
          <template v-for="column in columns" :key="column.key">
            <span v-if="column.mobileBadge" :class="[
              'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold',
              column.format === 'badge'
                ? renderCellContent(row, column).classes
                : 'bg-slate-100 text-slate-600'
            ]">
              {{
                column.format === 'badge'
                  ? renderCellContent(row, column).label
                  : getCellValue(row, column)
              }}
            </span>
          </template>
        </template>
      </div>

      <!-- Card Body - Grid Layout -->
      <div v-if="columns.filter(c => c.mobileDisplay !== false && !c.mobileBadge && !c.mobileSecondary).length > 0"
        class="mt-4">
        <div class="grid gap-3">
          <template v-for="column in columns" :key="column.key">
            <div v-if="column.mobileDisplay !== false && !column.mobileBadge && !column.mobileSecondary"
              class="space-y-1">
              <p class="text-slate-400 text-xs font-medium">
                {{ column.mobileLabel || column.label }}
              </p>

              <!-- Badge in Body -->
              <span v-if="column.format === 'badge'" :class="[
                'inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold',
                renderCellContent(row, column).classes
              ]">
                {{ renderCellContent(row, column).label }}
              </span>

              <!-- Image in Body -->
              <img v-else-if="column.format === 'image' && getCellValue(row, column)" :src="getCellValue(row, column)"
                :alt="column.formatOptions?.alt || 'Image'" class="h-12 w-12 rounded object-cover" />

              <!-- Text in Body -->
              <p v-else class="font-medium text-slate-700">
                {{ renderCellContent(row, column).content }}
              </p>
            </div>
          </template>
        </div>
      </div>

      <!-- Card Footer - Actions -->
      <div v-if="canViewActions" class="mt-4 flex justify-end gap-2 flex-wrap">
        <button v-for="action in actions" v-show="isActionVisible(action, row, { can })" :key="action.id" type="button"
          :title="action.label" :class="getActionButton(action, row)" @click.stop="handleAction(action, row)">
          {{ action.label }}
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="items.length === 0 && !loading"
      class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
      {{ noDataMessage }}
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
      Cargando...
    </div>
  </div>
</template>
