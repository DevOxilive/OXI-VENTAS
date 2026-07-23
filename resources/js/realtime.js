import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

function envNumber(value, fallback = null) {
    if (value === undefined || value === '') return fallback

    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : fallback
}

const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'
const port = envNumber(import.meta.env.VITE_REVERB_PORT, 8080)
const isSecure = scheme === 'https'

/** Unico catalogo de canales consumidos por la aplicacion. */
export const REALTIME_CHANNELS = Object.freeze({
    audits: 'audits',
    inventoryProducts: 'inventory.products',
    systems: 'systems',
    inventoryBranch: (branchId) => `inventory.branch.${branchId}`,
    user: (userId) => `users.${userId}`,
})

/** Unico catalogo de nombres publicados mediante broadcastAs(). */
export const REALTIME_EVENTS = Object.freeze({
    activityLogged: '.realtime.activity',
    branchChanged: '.branch.changed',
    employeeChanged: '.employee.changed',
    organizationStructureChanged: '.organization-structure.changed',
    physicalCountChanged: '.PhysicalCountChanged',
    productChanged: '.product.changed',
    stockUpdated: '.stock.updated',
    attendanceChanged: '.attendance.changed',
    systemAuditChanged: '.system.audit.changed',
    systemTrashChanged: '.system.trash.changed',
    userChanged: '.UserChanged',
})

/**
 * Toda opcion de transporte, reconexion y timeout se administra aqui.
 * Los timeouts nulos conservan los valores predeterminados de Pusher/Reverb.
 */
export const REALTIME_CONFIG = Object.freeze({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    host: import.meta.env.VITE_REVERB_HOST,
    port,
    scheme,
    secure: isSecure,
    transports: Object.freeze([isSecure ? 'wss' : 'ws']),
    reconnect: Object.freeze({
        enabled: true,
        activityTimeout: envNumber(import.meta.env.VITE_REALTIME_ACTIVITY_TIMEOUT),
        pongTimeout: envNumber(import.meta.env.VITE_REALTIME_PONG_TIMEOUT),
        unavailableTimeout: envNumber(import.meta.env.VITE_REALTIME_UNAVAILABLE_TIMEOUT),
    }),
})

let realtimeClient = null
const channelSubscriptionCounts = new Map()

function connectionOptions() {
    const options = {
        broadcaster: REALTIME_CONFIG.broadcaster,
        key: REALTIME_CONFIG.key,
        wsHost: REALTIME_CONFIG.host,
        wsPort: REALTIME_CONFIG.port,
        wssPort: REALTIME_CONFIG.port,
        forceTLS: REALTIME_CONFIG.secure,
        enabledTransports: [...REALTIME_CONFIG.transports],
    }

    const timeoutOptions = {
        activityTimeout: REALTIME_CONFIG.reconnect.activityTimeout,
        pongTimeout: REALTIME_CONFIG.reconnect.pongTimeout,
        unavailableTimeout: REALTIME_CONFIG.reconnect.unavailableTimeout,
    }

    Object.entries(timeoutOptions).forEach(([key, value]) => {
        if (value !== null) options[key] = value
    })

    return options
}

/** Inicializa una sola instancia para toda la vida de la aplicacion. */
export function initializeRealtime() {
    if (typeof window === 'undefined') return null
    if (realtimeClient) return realtimeClient

    window.Pusher = Pusher
    realtimeClient = new Echo(connectionOptions())

    // Se conserva por compatibilidad con Laravel/Axios y herramientas de depuracion.
    window.Echo = realtimeClient

    return realtimeClient
}

function getRealtimeClient() {
    return realtimeClient ?? initializeRealtime()
}

/**
 * Suscribe un listener y devuelve una unica funcion de limpieza.
 * El conteo evita que un componente abandone un canal usado por otro.
 */
export function subscribeRealtime(channelName, eventName, handler) {
    return subscribeToRealtimeChannel(channelName, eventName, handler)
}

/**
 * Subscribes to an authorized private channel without creating another Echo client.
 */
export function subscribePrivateRealtime(channelName, eventName, handler) {
    return subscribeToRealtimeChannel(channelName, eventName, handler, true)
}

function subscribeToRealtimeChannel(channelName, eventName, handler, isPrivate = false) {
    const client = getRealtimeClient()

    if (!client || !channelName || !eventName || typeof handler !== 'function') {
        return () => {}
    }

    const channel = isPrivate ? client.private(channelName) : client.channel(channelName)
    channel.listen(eventName, handler)
    const subscriptionKey = `${isPrivate ? 'private' : 'public'}:${channelName}`
    channelSubscriptionCounts.set(
        subscriptionKey,
        (channelSubscriptionCounts.get(subscriptionKey) ?? 0) + 1,
    )

    let active = true

    return () => {
        if (!active) return
        active = false

        channel.stopListening(eventName, handler)

        const remaining = Math.max(
            0,
            (channelSubscriptionCounts.get(subscriptionKey) ?? 1) - 1,
        )

        if (remaining === 0) {
            channelSubscriptionCounts.delete(subscriptionKey)
            client.leave(channelName)
            return
        }

        channelSubscriptionCounts.set(subscriptionKey, remaining)
    }
}
