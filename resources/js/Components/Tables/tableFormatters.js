export const formatters = {
  text: (value, options = {}) => {
    if (value === null || value === undefined) return options.fallback || '-'
    if (options.truncate) return truncateText(String(value), options.truncate)
    return String(value)
  },

  currency: (value, options = {}) => {
    if (value === null || value === undefined) return options.fallback || '$0.00'
    const decimals = options.decimals ?? 2
    const locale = options.locale || 'es-MX'
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: 'MXN',
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(value)
  },

  date: (value, options = {}) => {
    if (!value) return options.fallback || '-'
    const date = typeof value === 'string' ? new Date(value) : value
    const locale = options.locale || 'es-MX'
    const format = options.format || 'short'

    if (format === 'short') {
      return date.toLocaleDateString(locale)
    }
    if (format === 'long') {
      return date.toLocaleDateString(locale, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
    }
    if (format === 'time') {
      return date.toLocaleTimeString(locale)
    }
    if (format === 'datetime') {
      return `${date.toLocaleDateString(locale)} ${date.toLocaleTimeString(locale)}`
    }
    return date.toLocaleDateString(locale)
  },

  time: (value, options = {}) => {
    if (!value) return options.fallback || '-'

    const [hours = '00', minutes = '00'] = String(value).split(':')
    const date = new Date(2000, 0, 1, Number(hours), Number(minutes))

    if (Number.isNaN(date.getTime())) return options.fallback || '-'

    return new Intl.DateTimeFormat(options.locale || 'es-MX', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    }).format(date)
  },

  badge: (value, options = {}) => {
    const label = options.labelMap?.[value] || String(value)
    return { label, value }
  },

  boolean: (value, options = {}) => {
    if (value === null || value === undefined) return options.fallback || '-'
    const trueLabel = options.trueLabel || 'Sí'
    const falseLabel = options.falseLabel || 'No'
    return value ? trueLabel : falseLabel
  },

  image: (value, options = {}) => {
    if (!value) return null
    return {
      src: value,
      alt: options.alt || 'Imagen',
      size: options.size || 'sm',
    }
  },

  swatch: (value, options = {}) => {
    const fallback = options.fallback || 'Sin color'

    if (!value) {
      return {
        label: fallback,
        color: options.emptyColor || '#e2e8f0',
      }
    }

    return {
      label: String(value),
      color: String(value),
    }
  },

  number: (value, options = {}) => {
    if (value === null || value === undefined) return options.fallback || '0'
    const decimals = options.decimals ?? 0
    const num = Number(value)
    if (isNaN(num)) return options.fallback || '0'
    return num.toFixed(decimals)
  },
}

export function formatCellValue(value, column = {}) {
  const format = column.format || 'text'
  const formatter = formatters[format] || formatters.text

  try {
    return formatter(value, column.formatOptions || {})
  } catch (e) {
    console.error(`Error formatting value with format "${format}":`, e)
    return column.formatOptions?.fallback || '-'
  }
}

export function getNestedValue(obj, path) {
  if (!path) return obj
  return path.split('.').reduce((current, prop) => {
    if (current === null || current === undefined) return null
    return current[prop]
  }, obj)
}

export function truncateText(text, length) {
  if (!text) return ''
  if (text.length <= length) return text
  return text.substring(0, length) + '...'
}

export default formatters
