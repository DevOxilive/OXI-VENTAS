export function buildGoogleMapsSearchUrl(query = '') {
    const normalizedQuery = String(query || '').trim()

    if (!normalizedQuery) return ''

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(normalizedQuery)}`
}

export function normalizeGoogleMapsUrl(value = '', fallbackQuery = '') {
    const candidate = String(value || '').trim()

    if (/^https?:\/\//i.test(candidate)) {
        return candidate
    }

    if (/^(www\.|maps\.app\.goo\.gl|goo\.gl|google\.)/i.test(candidate)) {
        return `https://${candidate}`
    }

    return buildGoogleMapsSearchUrl(candidate || fallbackQuery)
}
