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

function getActionButtonClasses(action) {
  const baseClasses = 'h-9 px-3 rounded-xl text-xs font-bold transition-colors inline-flex items-center justify-center gap-1'

  const variantMap = {
    blue: 'bg-secondary text-primary hover:brightness-95',
    green: 'bg-secondary text-accent hover:brightness-95',
    red: 'bg-secondary text-primary hover:brightness-95',
    amber: 'bg-secondary text-accent hover:brightness-95',
    slate: 'bg-secondary text-text hover:brightness-95',
    purple: 'bg-secondary text-primary hover:brightness-95',
    orange: 'bg-secondary text-accent hover:brightness-95',
    indigo: 'bg-secondary text-primary hover:brightness-95',
    pink: 'bg-secondary text-primary hover:brightness-95',
    cyan: 'bg-secondary text-primary hover:brightness-95',
  }

  return `${baseClasses} ${variantMap[action.variant] || variantMap.blue}`
}
</script>

<template>
  <div class="space-y-2 bg-secondary p-3 md:hidden">
    <article v-for="(row, index) in items" :key="row[rowKey] || index"
      class="rounded-2xl border border-secondary bg-background p-3 shadow-sm" @click="handleRowClick(row)">
      <div class="flex justify-between items-start gap-3">
        <div class="min-w-0">
          <p v-if="mobileCardHeaderField" class="truncate font-semibold text-text">
            {{ getNestedValue(row, mobileCardHeaderField) }}
          </p>

          <template v-for="column in columns" :key="column.key">
            <p v-if="column.mobileSecondary" class="mt-0.5 truncate text-xs text-text opacity-70">
              {{ getCellValue(row, column) }}
            </p>
          </template>
        </div>

        <template v-for="column in columns" :key="column.key">
          <span v-if="column.mobileBadge" :class="[
            'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold shrink-0 whitespace-nowrap',
            column.format === 'badge'
              ? renderCellContent(row, column).classes
              : 'bg-secondary text-text',
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
            <p class="text-xs font-medium text-text opacity-50">
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

            <div v-else-if="column.format === 'swatch'" class="mt-1 flex items-center gap-2">
              <span
                class="h-4 w-4 shrink-0 rounded-full border border-secondary"
                :style="{ backgroundColor: renderCellContent(row, column).color }"
              />

              <p class="truncate font-medium text-text">
                {{ renderCellContent(row, column).label }}
              </p>
            </div>

            <p v-else class="mt-0.5 truncate font-medium text-text">
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
      class="rounded-2xl border border-secondary bg-background p-6 text-center text-text opacity-70 shadow-sm">
      {{ noDataMessage }}
    </div>

    <div v-if="loading" class="rounded-2xl border border-secondary bg-background p-6 text-center text-text opacity-70 shadow-sm">
      Cargando...
    </div>
  </div>
</template>
