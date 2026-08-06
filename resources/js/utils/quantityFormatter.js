const DEFAULT_LOCALE = 'es-MX'

export function normalizeInventoryUnit(unit) {
  const normalized = String(unit ?? '').trim().toLowerCase()

  return normalized === 'kg' || normalized === 'kilo' || normalized === 'kilogramo'
    ? 'kg'
    : 'pza'
}

export function isDecimalInventoryUnit(unit) {
  return normalizeInventoryUnit(unit) === 'kg'
}

export function formatInventoryQuantity(value, unit = 'pza', options = {}) {
  const numericValue = Number(String(value ?? 0).replace(',', '.'))

  if (!Number.isFinite(numericValue)) {
    return options.fallback ?? '0'
  }

  const normalizedUnit = normalizeInventoryUnit(unit)
  const decimals = options.decimals ?? 3

  if (normalizedUnit !== 'kg') {
    return new Intl.NumberFormat(options.locale ?? DEFAULT_LOCALE, {
      maximumFractionDigits: 0,
    }).format(Math.round(numericValue))
  }

  return new Intl.NumberFormat(options.locale ?? DEFAULT_LOCALE, {
    minimumFractionDigits: options.minimumFractionDigits ?? 0,
    maximumFractionDigits: decimals,
  }).format(numericValue)
}

export function formatInventoryQuantityWithUnit(value, unit = 'pza', options = {}) {
  const normalizedUnit = normalizeInventoryUnit(unit)
  const suffix = normalizedUnit === 'kg'
    ? (options.kgLabel ?? 'kg')
    : (options.pieceLabel ?? 'pzas')

  return `${formatInventoryQuantity(value, normalizedUnit, options)} ${suffix}`
}
