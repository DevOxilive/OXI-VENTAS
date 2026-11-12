<script setup>
import SidebarItem from '@/Components/Layout/SidebarItem.vue'
import SessionExpiryWarningModal from '@/Components/Modales/SessionExpiryWarningModal.vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { generateMenu } from '@/config/menuConfig'
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import {
    usePermissions,
    updateLivePermissions,
} from '@/Composables/usePermissions'
import { ToastAlert, WarningAlert } from '@/Components/Modales/UniversalActionModal'
import {
    REALTIME_BROWSER_EVENTS,
    REALTIME_CHANNELS,
    REALTIME_EVENTS,
    subscribePrivateRealtime,
    subscribeRealtime,
} from '@/realtime'
import { t } from '@/i18n/es'

const page = usePage()
const desktopMediaQuery = '(min-width: 768px)'
const ROLE_SESSION_ACTIVITY_INTERVAL_MS = 30 * 1000
const ROLE_SESSION_ACTIVITY_EVENTS = [
    'click',
    'keydown',
    'input',
    'change',
    'scroll',
    'touchstart',
]

const { permissions, role } = usePermissions()

const menuItems = computed(() => generateMenu(
    role.value,
    permissions.value,
    page.props.sidebarBranches ?? page.props.branches ?? [],
))

const branchKeys = computed(() =>
    menuItems.value
        .find((item) => item.key === 'branches')
        ?.children?.map((branch) => branch.key) ?? [],
)

const sidebarOpen = ref(false)
const desktopSidebarCollapsed = ref(false)
const desktopSidebarStorageKey = 'desktopSidebarCollapsed'
const colorThemeStorageKey = 'color-theme'
const themeMetaColors = {
    dark: '#070304',
    light: '#e0000f',
}
const collapseEventName = 'sidebar:collapse-all'
const isDesktopViewport = ref(false)
const currentTheme = ref('light')
const profileMenu = ref(null)
let unsubscribeUserChanged = null
let unsubscribeRealtimeActivity = null
let unsubscribeGlobalDataChanged = null
let desktopMediaQueryList = null
let handleDesktopViewportChange = null
let sessionExpiryTimer = null
let sessionWarningTimer = null
let sessionCountdownTimer = null
let sessionLogoutStarted = false
let roleSessionActivityRequest = null
let lastRoleSessionActivityAt = 0

const sessionPolicy = computed(() => page.props.auth?.sessionPolicy ?? null)
const activeSessionExpiresAt = ref(null)
const showSessionExpiryWarning = ref(false)
const sessionWarningRemainingSeconds = ref(0)
const continuingRoleSession = ref(false)

function clearRoleSessionTimers() {
    window.clearTimeout(sessionExpiryTimer)
    window.clearTimeout(sessionWarningTimer)
    window.clearInterval(sessionCountdownTimer)
    sessionExpiryTimer = null
    sessionWarningTimer = null
    sessionCountdownTimer = null
}

function updateSessionWarningCountdown() {
    const remainingMilliseconds = (Number(activeSessionExpiresAt.value || 0) * 1000) - Date.now()
    sessionWarningRemainingSeconds.value = Math.max(0, Math.ceil(remainingMilliseconds / 1000))

    if (remainingMilliseconds <= 0) {
        closeExpiredSession()
    }
}

function closeExpiredSession() {
    if (sessionLogoutStarted) return

    sessionLogoutStarted = true
    showSessionExpiryWarning.value = false
    clearRoleSessionTimers()
    router.post(route('logout'))
}

async function continueRoleSession() {
    continuingRoleSession.value = true

    try {
        const { data } = await window.axios.post(route('session.continue'))
        showSessionExpiryWarning.value = false
        scheduleRoleSessionTimeout(data?.expires_at)
    } catch {
        closeExpiredSession()
    } finally {
        continuingRoleSession.value = false
    }
}

function renewRoleSessionFromActivity() {
    if (
        sessionPolicy.value?.keep_alive
        || !page.props.auth?.user?.id
        || sessionLogoutStarted
        || roleSessionActivityRequest
    ) return

    const now = Date.now()
    if (now - lastRoleSessionActivityAt < ROLE_SESSION_ACTIVITY_INTERVAL_MS) return

    // Se actualiza antes de enviar la petición para no duplicarla cuando el
    // usuario escribe, se desplaza o navega varias veces seguidas.
    lastRoleSessionActivityAt = now
    roleSessionActivityRequest = window.axios
        .post(route('session.continue'))
        .then(({ data }) => {
            showSessionExpiryWarning.value = false
            scheduleRoleSessionTimeout(data?.expires_at)
        })
        .catch(() => {
            // La siguiente interacción podrá reintentar la renovación. No se
            // amplía el contador local si el servidor no confirmó la sesión.
            lastRoleSessionActivityAt = 0
        })
        .finally(() => {
            roleSessionActivityRequest = null
        })
}

function showRoleSessionWarning() {
    if (showSessionExpiryWarning.value) return

    updateSessionWarningCountdown()
    if (sessionWarningRemainingSeconds.value <= 0) return

    showSessionExpiryWarning.value = true
    sessionCountdownTimer = window.setInterval(updateSessionWarningCountdown, 1000)
}

function scheduleRoleSessionTimeout(expiresAt = sessionPolicy.value?.expires_at) {
    clearRoleSessionTimers()
    sessionLogoutStarted = false

    if (sessionPolicy.value?.keep_alive || !expiresAt) {
        activeSessionExpiresAt.value = null
        showSessionExpiryWarning.value = false
        return
    }

    activeSessionExpiresAt.value = Number(expiresAt)
    const expiresAtMilliseconds = Number(expiresAt) * 1000
    const warningMilliseconds = Number(sessionPolicy.value?.warning_minutes || 5) * 60 * 1000
    const remainingMilliseconds = expiresAtMilliseconds - Date.now()

    if (remainingMilliseconds <= 0) {
        closeExpiredSession()
        return
    }

    const warningDelay = Math.max(0, remainingMilliseconds - warningMilliseconds)
    sessionWarningTimer = window.setTimeout(showRoleSessionWarning, warningDelay)
    sessionExpiryTimer = window.setTimeout(closeExpiredSession, remainingMilliseconds)
}

function distributeGlobalDataChange(event) {
    if (typeof window === 'undefined') return

    window.dispatchEvent(new CustomEvent(REALTIME_BROWSER_EVENTS.dataChanged, {
        detail: event,
    }))

}

function emitCollapseAllItems() {
    if (typeof window === 'undefined') return

    window.dispatchEvent(new CustomEvent(collapseEventName))
}

function toggleSidebar() {
    const nextValue = !sidebarOpen.value
    sidebarOpen.value = nextValue

    if (!nextValue) {
        emitCollapseAllItems()
    }
}

function toggleDesktopSidebar() {
    const nextValue = !desktopSidebarCollapsed.value
    desktopSidebarCollapsed.value = nextValue

    if (nextValue) {
        emitCollapseAllItems()
    }
}

function expandDesktopSidebar() {
    desktopSidebarCollapsed.value = false
}

function closeSidebarAfterNavigation() {
    sidebarOpen.value = false
    emitCollapseAllItems()
}

function closeSidebarFromOutside() {
    if (!isDesktopViewport.value && sidebarOpen.value) {
        closeSidebarAfterNavigation()
        return
    }

    if (isDesktopViewport.value && !desktopSidebarCollapsed.value) {
        desktopSidebarCollapsed.value = true
        emitCollapseAllItems()
    }
}

function logout() {
    router.post(route('logout'))
}

function normalizeFlashMessage(value, fallbackTitle) {
    if (!value) return null

    if (typeof value === 'string') {
        return {
            title: fallbackTitle,
            message: value,
        }
    }

    return {
        title: value.title || fallbackTitle,
        message: value.message || '',
    }
}

function syncTheme(theme) {
    if (typeof window === 'undefined') return

    const nextTheme = theme === 'dark' ? 'dark' : 'light'
    const root = document.documentElement
    const shouldUseDarkMode = nextTheme === 'dark'
    const themeColorMeta = document.querySelector('meta[name="theme-color"]')
    const nextMetaColor = themeMetaColors[nextTheme]
    const previousTheme = currentTheme.value
    const alreadyStored = window.localStorage.getItem(colorThemeStorageKey) === nextTheme
    const alreadyApplied = root.classList.contains('dark') === shouldUseDarkMode
    const metaAlreadyApplied = themeColorMeta?.getAttribute('content') === nextMetaColor

    currentTheme.value = nextTheme

    if (alreadyStored && alreadyApplied && metaAlreadyApplied && previousTheme === nextTheme) {
        return
    }

    if (!alreadyStored) {
        window.localStorage.setItem(colorThemeStorageKey, nextTheme)
    }

    if (!alreadyApplied) {
        root.classList.toggle('dark', shouldUseDarkMode)
    }

    if (themeColorMeta && !metaAlreadyApplied) {
        themeColorMeta.setAttribute('content', nextMetaColor)
    }

    window.dispatchEvent(new CustomEvent('oxi-theme-change', {
        detail: { theme: nextTheme },
    }))
}

function toggleTheme() {
    syncTheme(currentTheme.value === 'dark' ? 'light' : 'dark')
}

function closeProfileMenu() {
    profileMenu.value?.removeAttribute('open')
}

function handleMainClick(event) {
    closeSidebarFromOutside()

    if (!event.target.closest('.profile-menu')) {
        closeProfileMenu()
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        desktopSidebarCollapsed.value =
            window.localStorage.getItem(desktopSidebarStorageKey) === 'true'
        currentTheme.value =
            window.localStorage.getItem(colorThemeStorageKey)
            || (document.documentElement.classList.contains('dark') ? 'dark' : 'light')

        desktopMediaQueryList = window.matchMedia(desktopMediaQuery)
        handleDesktopViewportChange = (event) => {
            isDesktopViewport.value = event.matches

            if (event.matches) {
                sidebarOpen.value = false
            }
        }

        isDesktopViewport.value = desktopMediaQueryList.matches
        desktopMediaQueryList.addEventListener('change', handleDesktopViewportChange)

        ROLE_SESSION_ACTIVITY_EVENTS.forEach((eventName) => {
            document.addEventListener(eventName, renewRoleSessionFromActivity, { passive: true })
        })
        scheduleRoleSessionTimeout()
    }

    if (!page.props.auth?.user?.id) return

    const handleUserChanged = (event) => {
        if (Number(page.props.auth.user.id) !== Number(event.userId)) return

        updateLivePermissions({
            permissions: event.permissions || [],
            role: event.role,
            userId: event.userId,
        })
    }

    const handleRealtimeActivity = (event) => {
        ToastAlert({
            icon: 'info',
            title: event.message || 'Hay una actualizacion en tiempo real',
        })
    }

    unsubscribeUserChanged = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.userChanged,
        handleUserChanged,
    )
    unsubscribeRealtimeActivity = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.activityLogged,
        handleRealtimeActivity,
    )
    unsubscribeGlobalDataChanged = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.systemAuditChanged,
        distributeGlobalDataChange,
    )
})

onBeforeUnmount(() => {
    if (desktopMediaQueryList && handleDesktopViewportChange) {
        desktopMediaQueryList.removeEventListener('change', handleDesktopViewportChange)
    }

    clearRoleSessionTimers()
    ROLE_SESSION_ACTIVITY_EVENTS.forEach((eventName) => {
        document.removeEventListener(eventName, renewRoleSessionFromActivity)
    })

    unsubscribeUserChanged?.()
    unsubscribeRealtimeActivity?.()
    unsubscribeGlobalDataChanged?.()

})

watch(
    () => sessionPolicy.value?.expires_at,
    (expiresAt) => {
        if (expiresAt) scheduleRoleSessionTimeout(expiresAt)
    },
)

watch(desktopSidebarCollapsed, (value) => {
    if (typeof window === 'undefined') return

    window.localStorage.setItem(desktopSidebarStorageKey, String(value))
})

watch(
    () => page.props.flash?.warning,
    (warning) => {
        const alert = normalizeFlashMessage(warning, 'Atencion requerida')
        if (!alert) return

        WarningAlert(alert)
    },
    { immediate: true },
)
</script>

<template>
    <div class="flex h-dvh overflow-hidden bg-background text-text">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/35 md:hidden"
            @click="toggleSidebar"
        />

        <aside
            class="fixed left-0 top-0 z-50 flex h-dvh flex-col overflow-hidden border-r border-secondary bg-background shadow-sm transition-all duration-300 md:static md:z-auto md:translate-x-0"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                desktopSidebarCollapsed ? 'w-72 md:w-20' : 'w-72',
            ]"
        >
            <div class="shrink-0 border-b border-secondary py-3" :class="desktopSidebarCollapsed ? 'px-2' : 'px-4'">
                <div
                    class="flex min-h-16 items-center gap-3"
                    :class="desktopSidebarCollapsed ? 'justify-center' : 'justify-between'"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="group relative flex shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-[#d6a92e] bg-[#fff8df] shadow-md shadow-amber-900/20 transition hover:scale-[1.04] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary"
                            :class="desktopSidebarCollapsed ? 'h-16 w-16' : 'h-[4.5rem] w-[4.5rem]'"
                            @click="toggleDesktopSidebar"
                        >
                            <img
                                src="/icons/super-kay-navidad.png"
                                alt="Logotipo navideño de Super Kay"
                                class="h-full w-full scale-110 rounded-full object-cover"
                            />
                        </button>

                        <div v-if="!desktopSidebarCollapsed" class="min-w-0">
                            <p class="text-sm font-semibold text-text">
                                Super Kay
                            </p>

                            <p class="text-xs text-text opacity-50">
                                Menu principal
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <SidebarItem
                :items="menuItems"
                :extended="isDesktopViewport ? !desktopSidebarCollapsed : true"
                :branch-keys="branchKeys"
                @expand-request="expandDesktopSidebar"
                @navigate="closeSidebarAfterNavigation"
            />
        </aside>

        <section class="flex min-w-0 flex-1 flex-col overflow-visible" @click="handleMainClick">
            <header class="shrink-0 border-b border-secondary bg-background px-4 py-3 shadow-sm md:px-8">
                <div class="flex min-h-16 items-center justify-between gap-4 md:justify-end">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            class="rounded-xl bg-secondary p-2.5 transition hover:brightness-95 md:hidden"
                            @click.stop="toggleSidebar"
                        >
                            <span class="material-symbols-outlined text-[22px] text-text">
                                menu
                            </span>
                        </button>
                    </div>

                    <div class="shrink-0">
                        <div class="flex items-center gap-3">
                            <details ref="profileMenu" class="profile-menu shrink-0">
                                <summary class="profile-menu-trigger flex h-16 min-w-[15rem] cursor-pointer list-none items-center gap-3 rounded-2xl border border-secondary bg-background px-4 text-left shadow-sm transition hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-primary">
                                    <img src="/icons/profile-santa-flaticon.png" alt="Perfil navideño" class="h-12 w-12 shrink-0 object-contain">

                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-text">
                                                {{ page.props.auth.user.name }}
                                            </span>

                                            <span class="block truncate text-xs text-text opacity-70">
                                                {{ role }}
                                            </span>
                                        </span>
                                    <span class="material-symbols-outlined profile-menu-chevron text-[20px] text-text/60">expand_more</span>
                                </summary>

                                <div class="profile-menu-panel absolute right-0 top-full z-50 mt-2 min-w-[15rem] overflow-hidden rounded-2xl border border-secondary bg-background shadow-xl">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 border-b border-secondary px-4 py-3 text-left text-sm font-medium text-text transition hover:bg-secondary focus:bg-secondary focus:outline-none"
                                            :title="currentTheme === 'dark' ? t('theme.switchToLight') : t('theme.switchToDark')"
                                            @click="toggleTheme(); closeProfileMenu()"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">
                                                {{ currentTheme === 'dark' ? 'light_mode' : 'dark_mode' }}
                                            </span>

                                            <span>
                                                {{ currentTheme === 'dark' ? t('theme.switchToLight') : t('theme.switchToDark') }}
                                            </span>
                                        </button>

                                        <Link
                                            :href="route('profile.show')"
                                            class="flex items-center gap-3 border-b border-secondary px-4 py-3 text-sm font-medium text-text transition hover:bg-secondary focus:bg-secondary"
                                            @click="closeProfileMenu"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">
                                                manage_accounts
                                            </span>

                                            <span>Editar perfil</span>
                                        </Link>

                                        <form @submit.prevent="logout">
                                            <button type="submit" class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-medium text-text transition hover:bg-secondary focus:bg-secondary focus:outline-none">
                                                <span class="material-symbols-outlined text-[20px]">
                                                    logout
                                                </span>

                                                <span>Cerrar sesión</span>
                                            </button>
                                        </form>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </main>
        </section>

        <SessionExpiryWarningModal
            v-if="showSessionExpiryWarning"
            :remaining-seconds="sessionWarningRemainingSeconds"
            :warning-seconds="Number(sessionPolicy?.warning_minutes || 5) * 60"
            :processing="continuingRoleSession"
            @continue="continueRoleSession"
            @logout="closeExpiredSession"
        />
    </div>
</template>

<style scoped>
.profile-menu {
    position: relative;
}

.profile-menu-trigger::-webkit-details-marker {
    display: none;
}

.profile-menu[open] .profile-menu-trigger {
    border-color: color-mix(in srgb, var(--primary) 54%, var(--secondary));
    border-bottom-left-radius: 1rem;
    border-bottom-right-radius: 1rem;
    box-shadow: 0 8px 20px color-mix(in srgb, var(--text) 10%, transparent);
}

.profile-menu-chevron {
    transition: transform 0.18s ease;
}

.profile-menu[open] .profile-menu-chevron {
    transform: rotate(180deg);
}

.profile-menu-panel {
    animation: profile-menu-in 0.16s ease-out;
}

@keyframes profile-menu-in {
    from { opacity: 0; transform: translateY(-5px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
