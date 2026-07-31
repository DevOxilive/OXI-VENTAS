<script setup>
import SidebarItem from '@/Components/Layout/SidebarItem.vue'
import Dropdown from '@/Components/Layout/Dropdown.vue'
import DropdownLink from '@/Components/Layout/DropdownLink.vue'
import { usePage, router } from '@inertiajs/vue3'
import { generateMenu } from '@/config/menuConfig'
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import {
    usePermissions,
    updateLivePermissions,
} from '@/Composables/usePermissions'
import { ToastAlert } from '@/Components/Modales/UniversalActionModal'
import {
    REALTIME_BROWSER_EVENTS,
    REALTIME_CHANNELS,
    REALTIME_EVENTS,
    refreshRealtimeProps,
    subscribePrivateRealtime,
    subscribeRealtime,
} from '@/realtime'
import { t } from '@/i18n/es'

const page = usePage()
const desktopMediaQuery = '(min-width: 768px)'

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
let unsubscribeUserChanged = null
let unsubscribeRealtimeActivity = null
let unsubscribeGlobalDataChanged = null
let globalRefreshTimer = null
let desktopMediaQueryList = null
let handleDesktopViewportChange = null

const routesWithDedicatedRealtime = [
    'audits.physical-counts.*',
    'branches.index',
    'human-resources.attendance-incidents.index',
    'human-resources.attendance-schedule-assignments.index',
    'human-resources.attendance-schedules.index',
    'human-resources.attendance.index',
    'human-resources.departments.index',
    'human-resources.employees.index',
    'inventory.branches.inventory',
    'inventory.branches.products.index',
    'system-administration.audits.index',
    'system-administration.index',
    'system-administration.trash.index',
    'systems.attendance.index',
    'systems.users.index',
    'ventas.attendance.index',
    // El punto de venta conserva su captura local; una señal global no debe recargarlo.
    'ventas.home',
]

const relatedRealtimeScopes = Object.freeze({
    inventory: ['ventas'],
    ventas: ['inventory'],
})

function matchesRoutePattern(routeName, pattern) {
    if (!routeName) return false
    if (!pattern.endsWith('.*')) return routeName === pattern

    return routeName.startsWith(pattern.slice(0, -1))
}

function currentRouteName() {
    if (typeof route !== 'function') return null

    return route().current()
}

function shouldRefreshCurrentInterface(event) {
    if (event?.result && event.result !== 'success') return false
    if (!['create', 'update', 'delete', 'restore', 'force_delete'].includes(event?.action)) {
        return false
    }

    const activeRoute = currentRouteName()
    if (!activeRoute) return false

    if (routesWithDedicatedRealtime.some((pattern) => matchesRoutePattern(activeRoute, pattern))) {
        return false
    }

    if (activeRoute === 'dashboard') return true

    const activeScope = activeRoute.split('.')[0]
    const changedScope = event?.routeName?.split('.')[0] ?? event?.module

    return activeScope === changedScope
        || (relatedRealtimeScopes[activeScope] ?? []).includes(changedScope)
}

function distributeGlobalDataChange(event) {
    if (typeof window === 'undefined') return

    window.dispatchEvent(new CustomEvent(REALTIME_BROWSER_EVENTS.dataChanged, {
        detail: event,
    }))

    if (!shouldRefreshCurrentInterface(event)) return

    window.clearTimeout(globalRefreshTimer)
    globalRefreshTimer = window.setTimeout(() => {
        refreshRealtimeProps(page)
    }, 180)
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

    unsubscribeUserChanged?.()
    unsubscribeRealtimeActivity?.()
    unsubscribeGlobalDataChanged?.()

    if (typeof window !== 'undefined') {
        window.clearTimeout(globalRefreshTimer)
    }
})

watch(desktopSidebarCollapsed, (value) => {
    if (typeof window === 'undefined') return

    window.localStorage.setItem(desktopSidebarStorageKey, String(value))
})
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
            <div class="shrink-0 border-b border-secondary px-4 py-3">
                <div
                    class="flex min-h-16 items-center gap-3"
                    :class="desktopSidebarCollapsed ? 'justify-center' : 'justify-between'"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="group relative flex shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-secondary bg-secondary shadow-sm transition hover:scale-[1.02] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary"
                            :class="desktopSidebarCollapsed ? 'h-12 w-12' : 'h-14 w-14'"
                            @click="toggleDesktopSidebar"
                        >
                            <img
                                src="/icons/icon-192.png"
                                alt="Super Kay Logo"
                                class="h-full w-full object-contain p-1"
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

        <section class="flex min-w-0 flex-1 flex-col overflow-visible" @click="closeSidebarFromOutside">
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
                            <Dropdown
                                align="right"
                                width="60"
                                :content-classes="['bg-transparent']"
                                :panel-classes="['mt-0']"
                            >
                                <template #trigger="{ open }">
                                    <button
                                        type="button"
                                        class="flex h-16 min-w-[15rem] items-center gap-3 border border-secondary bg-background px-4 text-left shadow-sm transition hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-primary"
                                        :class="open ? 'rounded-t-2xl rounded-b-none border-b-0' : 'rounded-2xl'"
                                    >
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-secondary text-text">
                                            <span class="material-symbols-outlined text-[24px]">
                                                account_circle
                                            </span>
                                        </span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-text">
                                                {{ page.props.auth.user.name }}
                                            </span>

                                            <span class="block truncate text-xs text-text opacity-70">
                                                {{ role }}
                                            </span>
                                        </span>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="overflow-hidden rounded-b-2xl border border-t-0 border-secondary bg-background shadow-sm">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 border-b border-secondary px-4 py-3 text-left text-sm font-medium text-text transition hover:bg-secondary focus:bg-secondary focus:outline-none"
                                            :title="currentTheme === 'dark' ? t('theme.switchToLight') : t('theme.switchToDark')"
                                            @click="toggleTheme"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">
                                                {{ currentTheme === 'dark' ? 'light_mode' : 'dark_mode' }}
                                            </span>

                                            <span>
                                                {{ currentTheme === 'dark' ? t('theme.switchToLight') : t('theme.switchToDark') }}
                                            </span>
                                        </button>

                                        <DropdownLink
                                            :href="route('profile.show')"
                                            class="flex items-center gap-3 border-b border-secondary px-4 py-3 text-sm font-medium text-text hover:bg-secondary focus:bg-secondary"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">
                                                manage_accounts
                                            </span>

                                            <span>Editar perfil</span>
                                        </DropdownLink>

                                        <form @submit.prevent="logout">
                                            <DropdownLink
                                                as="button"
                                                class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-text hover:bg-secondary focus:bg-secondary"
                                            >
                                                <span class="material-symbols-outlined text-[20px]">
                                                    logout
                                                </span>

                                                <span>Cerrar sesión</span>
                                            </DropdownLink>
                                        </form>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </main>
        </section>
    </div>
</template>
