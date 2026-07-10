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

const page = usePage()
const desktopMediaQuery = '(min-width: 768px)'

const { permissions, role } = usePermissions()

const menuItems = computed(() => generateMenu(
    role.value,
    permissions.value,
    page.props.branches ?? [],
))

const branchKeys = computed(() =>
    menuItems.value
        .find((item) => item.key === 'branches')
        ?.children?.map((branch) => branch.key) ?? [],
)

const sidebarOpen = ref(false)
const desktopSidebarCollapsed = ref(false)
const desktopSidebarStorageKey = 'desktopSidebarCollapsed'
const collapseEventName = 'sidebar:collapse-all'
const isDesktopViewport = ref(false)
let systemsChannel = null
let activityChannel = null
let handleUserChanged = null
let handleRealtimeActivity = null
let desktopMediaQueryList = null
let handleDesktopViewportChange = null

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

onMounted(() => {
    if (typeof window !== 'undefined') {
        desktopSidebarCollapsed.value =
            window.localStorage.getItem(desktopSidebarStorageKey) === 'true'

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

    if (!window.Echo || !page.props.auth?.user?.id) return

    handleUserChanged = (event) => {
        if (Number(page.props.auth.user.id) !== Number(event.userId)) return

        updateLivePermissions({
            permissions: event.permissions || [],
            role: event.role,
        })

        router.reload({
            only: ['auth', 'branches'],
            preserveScroll: true,
            preserveState: true,
        })
    }

    handleRealtimeActivity = (event) => {
        ToastAlert({
            icon: 'info',
            title: event.message || 'Hay una actualizacion en tiempo real',
        })
    }

    systemsChannel = window.Echo.channel('systems')
        .listen('.UserChanged', handleUserChanged)

    activityChannel = window.Echo.channel('activity')
        .listen('.realtime.activity', handleRealtimeActivity)
})

onBeforeUnmount(() => {
    if (desktopMediaQueryList && handleDesktopViewportChange) {
        desktopMediaQueryList.removeEventListener('change', handleDesktopViewportChange)
    }

    if (systemsChannel && handleUserChanged) {
        systemsChannel.stopListening('.UserChanged', handleUserChanged)
    }

    if (activityChannel && handleRealtimeActivity) {
        activityChannel.stopListening('.realtime.activity', handleRealtimeActivity)
    }
})

watch(desktopSidebarCollapsed, (value) => {
    if (typeof window === 'undefined') return

    window.localStorage.setItem(desktopSidebarStorageKey, String(value))
})
</script>

<template>
    <div class="flex h-dvh overflow-hidden bg-white">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/40 md:hidden"
            @click="toggleSidebar"
        />

        <aside
            class="fixed left-0 top-0 z-50 flex h-dvh flex-col overflow-hidden border-r bg-white shadow-sm transition-all duration-300 md:static md:z-auto md:translate-x-0"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                desktopSidebarCollapsed ? 'w-72 md:w-20' : 'w-72',
            ]"
        >
            <div class="shrink-0 border-b border-slate-100 px-4 py-3">
                <div
                    class="flex min-h-16 items-center gap-3"
                    :class="desktopSidebarCollapsed ? 'justify-center' : 'justify-between'"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="group relative flex shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:scale-[1.02] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-200"
                            :class="desktopSidebarCollapsed ? 'h-12 w-12' : 'h-14 w-14'"
                            @click="toggleDesktopSidebar"
                        >
                            <img
                                src="/icons/super-kay-source.png"
                                alt="Super Kay Logo"
                                class="h-full w-full object-contain p-1"
                            />
                        </button>

                        <div v-if="!desktopSidebarCollapsed" class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">
                                Super Kay
                            </p>

                            <p class="text-xs text-slate-400">
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

        <section class="flex min-w-0 flex-1 flex-col overflow-hidden" @click="closeSidebarFromOutside">
            <header class="shrink-0 border-b bg-white px-4 py-3 shadow-sm md:px-8">
                <div class="flex min-h-16 items-center justify-between gap-4 md:justify-end">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            class="rounded-xl bg-slate-100 p-2.5 transition hover:bg-slate-200 md:hidden"
                            @click.stop="toggleSidebar"
                        >
                            <span class="material-symbols-outlined text-[22px] text-slate-700">
                                menu
                            </span>
                        </button>
                    </div>

                    <div class="shrink-0">
                        <Dropdown
                            align="right"
                            width="60"
                            :content-classes="['bg-transparent']"
                            :panel-classes="['mt-0']"
                        >
                            <template #trigger="{ open }">
                                <button
                                    type="button"
                                    class="flex h-16 min-w-[15rem] items-center gap-3 border border-slate-200 bg-white px-4 text-left shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-100"
                                    :class="open ? 'rounded-t-2xl rounded-b-none border-b-0' : 'rounded-2xl'"
                                >
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-700">
                                        <span class="material-symbols-outlined text-[24px]">
                                            account_circle
                                        </span>
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-slate-800">
                                            {{ page.props.auth.user.name }}
                                        </span>

                                        <span class="block truncate text-xs text-slate-500">
                                            {{ role }}
                                        </span>
                                    </span>
                                </button>
                            </template>

                            <template #content>
                                <div class="overflow-hidden rounded-b-2xl border border-t-0 border-slate-200 bg-white shadow-sm">
                                    <DropdownLink
                                        :href="route('profile.show')"
                                        class="border-b border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:bg-slate-50"
                                    >
                                        Editar perfil
                                    </DropdownLink>

                                    <form @submit.prevent="logout">
                                        <DropdownLink
                                            as="button"
                                            class="px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:bg-slate-50"
                                        >
                                            Cerrar sesion
                                        </DropdownLink>
                                    </form>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </main>
        </section>
    </div>
</template>
