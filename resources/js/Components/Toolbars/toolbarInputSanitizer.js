import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeField } from '@/Validation/sanitizers'

function sanitizeByRegistry(value, registryKey) {
    const config = fieldRegistry[registryKey] ?? {}

    return sanitizeField(value, config)
}

export function sanitizeToolbarSearch(value) {
    return sanitizeByRegistry(value, 'toolbar_search')
}

export function sanitizeToolbarFilter(value, filter = {}) {
    if (filter.type !== 'text') {
        return value
    }

    return sanitizeByRegistry(value, filter.field ?? 'toolbar_filter_text')
}
