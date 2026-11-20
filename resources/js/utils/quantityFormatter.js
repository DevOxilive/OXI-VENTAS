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
  const numeric = Number(String(value ?? 0).replace(',', '.'))
  const rounded = Math.round(numeric * 1000) / 1000
  if (normalizedUnit === 'kg' && Number.isFinite(rounded) && Math.abs(rounded) < 1) {
    return `${formatInventoryQuantity(Math.round(rounded * 1000), 'pza', options)} g`
  }
  const suffix = normalizedUnit === 'kg'
    ? (options.kgLabel ?? 'kg')
    : (options.pieceLabel ?? 'pzas')

  return `${formatInventoryQuantity(value, normalizedUnit, options)} ${suffix}`
}

/**
 * Expresa un inventario por pieza en sus presentaciones completas y el sobrante
 * suelto. Solo es una etiqueta visual: el inventario continúa almacenándose en
 * piezas para que las ventas y los movimientos no cambien su comportamiento.
 */
export function formatInventoryPresentationStock(value, {
  unit = 'pza',
  hasBoxPresentation = false,
  hasPackagePresentation = false,
  piecesPerBox = 0,
  piecesPerPackage = 0,
} = {}) {
  const normalizedUnit = normalizeInventoryUnit(unit)
  const stock = Number(String(value ?? 0).replace(',', '.'))

  if (normalizedUnit !== 'pza' || !Number.isFinite(stock)) {
    return formatInventoryQuantityWithUnit(value, unit)
  }

  let remainingPieces = Math.max(0, Math.round(stock))
  const parts = []
  const boxSize = Math.round(Number(piecesPerBox))
  const packageSize = Math.round(Number(piecesPerPackage))

  if (hasBoxPresentation && boxSize >= 2) {
    const boxes = Math.floor(remainingPieces / boxSize)
    remainingPieces %= boxSize
    parts.push(`${formatInventoryQuantity(boxes)} ${boxes === 1 ? 'caja' : 'cajas'}`)
  }

  if (hasPackagePresentation && packageSize >= 2) {
    const packages = Math.floor(remainingPieces / packageSize)
    remainingPieces %= packageSize
    parts.push(`${formatInventoryQuantity(packages)} ${packages === 1 ? 'paquete' : 'paquetes'}`)
  }

  if (parts.length === 0) {
    return formatInventoryQuantityWithUnit(remainingPieces, 'pza')
  }

  parts.push(`${formatInventoryQuantity(remainingPieces)} ${remainingPieces === 1 ? 'pza suelta' : 'pzas sueltas'}`)

  return parts.join(' | ')
}
