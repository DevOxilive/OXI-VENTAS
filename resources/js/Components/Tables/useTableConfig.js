import { computed } from 'vue'
import { getNestedValue } from './tableFormatters.js'

/**
 * Composable for table configuration and utilities
 */
export function useTableConfig(props) {
  /**
   * Get visible columns based on column config
   */
  const visibleColumns = computed(() => {
    if (!props.columns) return []

    return props.columns.filter(column => {
      // Check if visible property is a function
      if (typeof column.visible === 'function') {
        return column.visible()
      }
      // Default to true if not specified or is explicitly true
      return column.visible !== false
    })
  })

  /**
   * Get a column by key
   */
  const getColumnByKey = (key) => {
    return props.columns?.find(col => col.key === key)
  }

  /**
   * Get cell value from row data
   */
  const getCellValue = (row, column) => {
    const value = getNestedValue(row, column.key)

    // Handle fallback values
    if ((value === null || value === undefined || value === '') && column.fallback !== undefined) {
      return column.fallback
    }

    return value
  }

  /**
   * Get action visibility for a specific row
   */
  const isActionVisible = (action, row, userPermissions) => {
    // Check if action has a hidden function
    if (typeof action.hidden === 'function' && action.hidden(row)) {
      return false
    }

    // Check permissions if specified
    if (action.permission && userPermissions) {
      if (Array.isArray(action.permission)) {
        return action.permission.some((permission) => userPermissions.can(permission))
      }

      return userPermissions.can(action.permission)
    }

    return true
  }

  /**
   * Get filter definition
   */
  const getFilter = (key) => {
    return props.filters?.find(f => f.key === key)
  }

  /**
   * Calculate total columns including actions
   */
  const totalColumns = computed(() => {
    let count = visibleColumns.value.length

    // Add actions column if there are actions
    if (props.actions && props.actions.length > 0) {
      count += 1
    }

    // Add selection column if selectable
    if (props.selectable) {
      count += 1
    }

    return count
  })

  /**
   * Check if table has any columns with filters
   */
  const hasFilters = computed(() => {
    return props.filters && props.filters.length > 0
  })

  /**
   * Check if table is empty
   */
  const isEmpty = computed(() => {
    return !props.items || props.items.length === 0
  })

  return {
    visibleColumns,
    getColumnByKey,
    getCellValue,
    isActionVisible,
    getFilter,
    totalColumns,
    hasFilters,
    isEmpty,
  }
}

export default useTableConfig
