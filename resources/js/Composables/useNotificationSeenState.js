import { computed, ref } from 'vue'

export function useNotificationSeenState({
    items,
    storageKey,
    dismissedStorageKey = null,
    itemKey = defaultNotificationKey,
}) {
    const seenKeys = ref(new Set())
    const dismissedKeys = ref(new Set())

    const visibleItems = computed(() => {
        return (items.value || []).filter((item) => !dismissedKeys.value.has(itemKey(item)))
    })

    const unreadCount = computed(() => {
        return visibleItems.value.filter((item) => !seenKeys.value.has(itemKey(item))).length
    })

    function resolvedStorageKey(keySource) {
        return typeof keySource === 'function'
            ? keySource()
            : keySource?.value ?? keySource
    }

    function loadSeenNotifications() {
        const key = resolvedStorageKey(storageKey)
        if (!key || typeof localStorage === 'undefined') return

        try {
            seenKeys.value = new Set(JSON.parse(localStorage.getItem(key) || '[]'))
        } catch {
            seenKeys.value = new Set()
        }
    }

    function loadDismissedNotifications() {
        const key = resolvedStorageKey(dismissedStorageKey)
        if (!key || typeof localStorage === 'undefined') return

        try {
            dismissedKeys.value = new Set(JSON.parse(localStorage.getItem(key) || '[]'))
        } catch {
            dismissedKeys.value = new Set()
        }
    }

    function markNotificationsSeen() {
        const key = resolvedStorageKey(storageKey)
        if (!key || typeof localStorage === 'undefined') return

        const next = new Set(seenKeys.value)

        for (const item of visibleItems.value) {
            next.add(itemKey(item))
        }
        seenKeys.value = next
        localStorage.setItem(key, JSON.stringify([...next].slice(-120)))
    }

    function dismissNotification(item) {
        const key = resolvedStorageKey(dismissedStorageKey)
        if (!key || typeof localStorage === 'undefined') return

        const notificationKey = itemKey(item)
        const nextDismissed = new Set(dismissedKeys.value)
        nextDismissed.add(notificationKey)
        dismissedKeys.value = nextDismissed
        localStorage.setItem(key, JSON.stringify([...nextDismissed].slice(-120)))

        const nextSeen = new Set(seenKeys.value)
        nextSeen.add(notificationKey)
        seenKeys.value = nextSeen

        const seenKey = resolvedStorageKey(storageKey)
        if (seenKey) {
            localStorage.setItem(seenKey, JSON.stringify([...nextSeen].slice(-120)))
        }
    }

    return {
        visibleItems,
        unreadCount,
        loadSeenNotifications,
        loadDismissedNotifications,
        markNotificationsSeen,
        dismissNotification,
    }
}

function defaultNotificationKey(item) {
    return `${item?.id ?? 'notification'}:${item?.status ?? ''}:${item?.updated_at ?? ''}`
}
