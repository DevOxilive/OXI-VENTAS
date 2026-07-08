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
  green: 'bg-green-100 text-green-700',
  red: 'bg-red-100 text-red-700',
  amber: 'bg-amber-100 text-amber-700',
  blue: 'bg-blue-100 text-blue-700',
  slate: 'bg-slate-100 text-slate-600',
  purple: 'bg-purple-100 text-purple-700',
  orange: 'bg-orange-100 text-orange-700',
  indigo: 'bg-indigo-100 text-indigo-700',
  pink: 'bg-pink-100 text-pink-700',
  cyan: 'bg-cyan-100 text-cyan-700',
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
