/**
 * Regla única para instantes registrados por el sistema.
 *
 * La base conserva UTC. Esta utilidad sólo decide cómo se presentan esos
 * instantes: siempre con la hora operativa de Ciudad de México, sin depender
 * de la zona configurada en cada computadora.
 */
export const OPERATIONAL_TIME_ZONE = 'America/Mexico_City'

function fallback(options) {
    return options.fallback !== undefined ? options.fallback : '-'
}

function locale(options) {
    return options.locale || 'es-MX'
}

function parseInstant(value) {
    if (value instanceof Date) return value
    if (!value) return null

    const stringValue = String(value)
    const utcValue = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(stringValue)
        ? `${stringValue.replace(' ', 'T')}Z`
        : stringValue
    const parsed = new Date(utcValue)

    return Number.isNaN(parsed.getTime()) ? null : parsed
}

function parseDateOnly(value) {
    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/)
    if (!match) return null

    const [, year, month, day] = match
    // Mediodía UTC garantiza el mismo día en Ciudad de México.
    return new Date(Date.UTC(Number(year), Number(month) - 1, Number(day), 12))
}

export function formatOperationalDate(value, options = {}) {
    const date = parseDateOnly(value) || parseInstant(value)
    if (!date) return fallback(options)

    return new Intl.DateTimeFormat(locale(options), {
        timeZone: OPERATIONAL_TIME_ZONE,
        ...(options.long
            ? { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
            : { day: '2-digit', month: '2-digit', year: 'numeric' }),
    }).format(date)
}

export function formatOperationalTime(value, options = {}) {
    const date = parseInstant(value)
    if (!date) return fallback(options)

    const parts = new Intl.DateTimeFormat(locale(options), {
        timeZone: OPERATIONAL_TIME_ZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).formatToParts(date)
    const hour = parts.find((part) => part.type === 'hour')?.value || ''
    const minute = parts.find((part) => part.type === 'minute')?.value || ''
    const dayPeriod = parts.find((part) => part.type === 'dayPeriod')?.value || ''

    return `${hour}:${minute} ${dayPeriod.toLowerCase().includes('p') ? 'p. m.' : 'a. m.'}`
}

export function formatOperationalDateTime(value, options = {}) {
    const date = parseInstant(value)
    if (!date) return fallback(options)

    const dateParts = new Intl.DateTimeFormat(locale(options), {
        timeZone: OPERATIONAL_TIME_ZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).formatToParts(date)
    const day = dateParts.find((part) => part.type === 'day')?.value || ''
    const month = dateParts.find((part) => part.type === 'month')?.value || ''
    const year = dateParts.find((part) => part.type === 'year')?.value || ''

    return `${day} ${month} ${year}, ${formatOperationalTime(date, options)}`
}
