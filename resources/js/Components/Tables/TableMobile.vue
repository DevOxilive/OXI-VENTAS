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
  selectable: Boolean,
  selectedItems: Object,
  mobileCardHeaderField: { type: String, required: true },
})

const emit = defineEmits([
  'action',
  'row-click',
  'selection-change',
  'update:selectedItems',
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

const tableRows = computed(() =>
  props.items.map((row, index) => {
    const cellByKey = new Map()

    props.columns.forEach((column) => {
      const value = getCellValue(row, column)

      cellByKey.set(column.key, {
        key: column.key,
        column,
        value,
        subValue: column.subKey ? getNestedValue(row, column.subKey) : null,
        content: renderCellContentFromValue(value, column),
      })
    })

    return {
      row,
      index,
      key: row[props.rowKey] || index,
      cellByKey,
      headerValue: getNestedValue(row, props.mobileCardHeaderField),
      secondaryCells: props.columns
        .filter((column) => column.mobileSecondary)
        .map((column) => cellByKey.get(column.key))
        .filter(Boolean),
      badgeCells: props.columns
        .filter((column) => column.mobileBadge)
        .map((column) => cellByKey.get(column.key))
        .filter(Boolean),
      bodyCells: mobileBodyColumns.value
        .map((column) => cellByKey.get(column.key))
        .filter(Boolean),
      visibleActions: canViewActions.value
        ? props.actions.filter((action) => isActionVisible(action, row, { can }))
        : [],
    }
  })
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

function toggleRowSelection(row) {
  const key = row[props.rowKey]
  const selected = { ...(props.selectedItems || {}) }
  selected[key] = !selected[key]

  emit('update:selectedItems', selected)
  emit('selection-change', Object.keys(selected).filter(key => selected[key]))
}

function isRowSelected(row) {
  return props.selectedItems?.[row[props.rowKey]] || false
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
    <article v-for="tableRow in tableRows" :key="tableRow.key"
      class="rounded-2xl border border-secondary bg-background p-3 shadow-sm" @click="handleRowClick(tableRow.row)">
      <div class="flex justify-between items-start gap-3">
        <button
          v-if="selectable"
          type="button"
          role="checkbox"
          :aria-checked="isRowSelected(tableRow.row)"
          class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-md border-2 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary/30"
          :class="isRowSelected(tableRow.row) ? 'border-primary bg-primary text-background shadow-sm' : 'border-secondary bg-background text-transparent hover:border-primary'"
          :aria-label="`Seleccionar ${tableRow.headerValue}`"
          @click.stop
          @click="toggleRowSelection(tableRow.row)"
        >
          <svg viewBox="0 0 16 16" class="h-4 w-4" fill="none" aria-hidden="true">
            <path d="M3.5 8.5 6.5 11.5 12.5 4.75" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.35" />
          </svg>
        </button>
        <div class="min-w-0">
          <p v-if="mobileCardHeaderField" class="truncate font-semibold text-text">
            {{ tableRow.headerValue }}
          </p>

          <template v-for="cell in tableRow.secondaryCells" :key="cell.key">
            <p class="mt-0.5 truncate text-xs text-text opacity-70">
              {{ cell.value }}
            </p>
            <span
              v-if="cell.column.subKeyBadge && cell.subValue"
              class="mt-1 inline-flex items-center rounded-full bg-secondary px-2 py-0.5 text-[10px] font-bold text-primary"
            >
              {{ cell.subValue }}
            </span>
          </template>
        </div>

        <template v-for="cell in tableRow.badgeCells" :key="cell.key">
          <span :class="[
            'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold shrink-0 whitespace-nowrap',
            cell.column.format === 'badge'
              ? cell.content.classes
              : 'bg-secondary text-text',
          ]">
            {{
              cell.column.format === 'badge'
                ? cell.content.label
                : cell.value
            }}
          </span>
        </template>
      </div>

      <div v-if="tableRow.bodyCells.length" class="mt-3 grid grid-cols-2 gap-3">
        <template v-for="cell in tableRow.bodyCells" :key="cell.key">
          <div class="min-w-0">
            <p class="text-xs font-medium text-text opacity-50">
              {{ cell.column.mobileLabel || cell.column.label }}
            </p>

            <span v-if="cell.column.format === 'badge'" :class="[
              'inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold mt-1',
              cell.content.classes,
            ]">
              {{ cell.content.label }}
            </span>

            <img v-else-if="cell.column.format === 'image' && cell.value" :src="cell.value"
              :alt="cell.column.formatOptions?.alt || 'Imagen'" class="h-10 w-10 rounded object-cover mt-1" />

            <div v-else-if="cell.column.format === 'swatch'" class="mt-1 flex items-center gap-2">
              <span
                class="h-4 w-4 shrink-0 rounded-full border border-secondary"
                :style="{ backgroundColor: cell.content.color }"
              />

              <p class="truncate font-medium text-text">
                {{ cell.content.label }}
              </p>
            </div>

            <p v-else class="mt-0.5 truncate font-medium text-text">
              {{ cell.content.content }}
            </p>
            <span
              v-if="cell.column.subKeyBadge && cell.subValue"
              class="mt-1 inline-flex items-center rounded-full bg-secondary px-2 py-0.5 text-[10px] font-bold text-primary"
            >
              {{ cell.subValue }}
            </span>
          </div>
        </template>
      </div>

      <div v-if="canViewActions" class="mt-3 flex justify-end gap-2 flex-wrap">
        <button v-for="action in tableRow.visibleActions" :key="action.id" type="button"
          :title="action.label" :class="getActionButtonClasses(action)" @click.stop="handleAction(action, tableRow.row)">
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
