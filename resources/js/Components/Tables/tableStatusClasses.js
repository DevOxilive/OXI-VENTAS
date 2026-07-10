/**
 * Predefined status maps for common statuses
 */
export const predefinedStatusMaps = {
  activeInactive: {
    'Activo': 'green',
    'Inactivo': 'red',
    'Pendiente': 'amber',
  },
  inventory: {
    'Disponible': 'green',
    'Stock bajo': 'amber',
    'Agotado': 'red',
    'Producto sin rotación': 'purple',
  },
  physical: {
    'Faltante': 'red',
    'Sobrante': 'amber',
    'Correcto': 'green',
  },
  common: {
    'Sí': 'green',
    'No': 'red',
    'Pendiente': 'amber',
    'Completado': 'green',
    'Cancelado': 'red',
    'En proceso': 'blue',
  }
}

/**
 * Color variant to Tailwind class map
 */
const variantMap = {
  green: 'bg-secondary text-accent',
  red: 'bg-secondary text-primary',
  amber: 'bg-secondary text-accent',
  blue: 'bg-secondary text-primary',
  slate: 'bg-secondary text-text',
  purple: 'bg-secondary text-primary',
  orange: 'bg-secondary text-accent',
  indigo: 'bg-secondary text-primary',
  pink: 'bg-secondary text-primary',
  cyan: 'bg-secondary text-primary',
}

/**
 * Get Tailwind classes for a status badge
 * @param {string} status - The status value
 * @param {object} customMap - Custom status to color map
 * @param {string} defaultColor - Default color if status not found
 * @returns {string} Tailwind CSS classes
 */
export function getStatusClasses(status, customMap = {}, defaultColor = 'slate') {
  if (!status) return variantMap[defaultColor] || variantMap.slate

  // Merge custom map with predefined
  const fullMap = { ...customMap }

  // Try exact match
  const color = fullMap[status]
  if (color && variantMap[color]) {
    return variantMap[color]
  }

  // Fallback to default color
  return variantMap[defaultColor] || variantMap.slate
}

/**
 * Get all available color variants
 */
export function getAvailableVariants() {
  return Object.keys(variantMap)
}

export default {
  predefinedStatusMaps,
  getStatusClasses,
  getAvailableVariants,
}
