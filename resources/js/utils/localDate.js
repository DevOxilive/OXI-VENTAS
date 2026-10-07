/**
 * Fecha para controles HTML date en el calendario de la sucursal.
 * toISOString usa UTC y, después de las 18:00 en México, adelanta un día.
 */
export function localDateInput(value = new Date()) {
  const date = value instanceof Date ? value : new Date(value)
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000)

  return local.toISOString().slice(0, 10)
}
