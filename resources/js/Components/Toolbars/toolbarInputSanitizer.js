import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeField, sanitizeFieldWithCursor } from '@/Validation/sanitizers'

function sanitizeByRegistry(value, registryKey) {
    const config = fieldRegistry[registryKey] ?? {}

    return sanitizeField(value, config)
}

function sanitizeByRegistryWithCursor(value, registryKey, selectionStart, selectionEnd) {
    const config = fieldRegistry[registryKey] ?? {}

    return sanitizeFieldWithCursor(value, config, selectionStart, selectionEnd)
}

export function sanitizeToolbarSearch(value) {
    return sanitizeByRegistry(value, 'toolbar_search')
}

export function sanitizeToolbarSearchWithCursor(value, selectionStart, selectionEnd) {
    return sanitizeByRegistryWithCursor(value, 'toolbar_search', selectionStart, selectionEnd)
}

export function sanitizeToolbarFilter(value, filter = {}) {
    if (filter.type !== 'text') {
        return value
    }

    return sanitizeByRegistry(value, filter.field ?? 'toolbar_filter_text')
}

export function sanitizeToolbarFilterWithCursor(value, filter = {}, selectionStart, selectionEnd) {
    if (filter.type !== 'text') {
        return { value, selectionStart, selectionEnd }
    }

    return sanitizeByRegistryWithCursor(
        value,
        filter.field ?? 'toolbar_filter_text',
        selectionStart,
        selectionEnd,
    )
}
