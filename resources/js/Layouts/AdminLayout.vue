    <script setup>
    import SidebarItem from '@/Components/SidebarItem.vue'
    import { Link, usePage } from '@inertiajs/vue3'
    import { generateMenu } from '@/config/menuConfig'
    import { ref } from 'vue'

    const page = usePage()
    const menuItems = generateMenu(
        page.props.auth.user?.role?.name,
        page.props.auth.permissions,
        page.props.branches ?? [])

    const role = page.props.auth.user?.role?.name
    const permissions = page.props.auth.permissions || []

    const sidebarOpen = ref(false)
    const desktopSidebarCollapsed = ref(false)

    function toggleSidebar() {
        sidebarOpen.value = !sidebarOpen.value
    }

    function toggleDesktopSidebar() {
        desktopSidebarCollapsed.value = !desktopSidebarCollapsed.value
    }
    console.table(JSON.parse(JSON.stringify(page.props.branches ?? [])))

</script>

<template>

<div class="flex h-dvh bg-white overflow-hidden">
        <div v-if="sidebarOpen" class="fixed inset-0 bg-black/40 z-40 md:hidden" @click="toggleSidebar" />

        <aside class="fixed md:static z-50 md:z-auto bg-white border-r shadow-sm flex flex-col
                   h-dvh transform transition-all duration-300 overflow-hidden md:translate-x-0" :class="[
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                    desktopSidebarCollapsed ? 'w-20' : 'w-72'
                ]">
            <div class="shrink-0 p-5 border-b border-slate-100">
                <div class="flex items-center gap-3"
                    :class="desktopSidebarCollapsed ? 'justify-center' : 'justify-between'">
                    <div class="flex items-center gap-3 min-w-0">
                        <div v-if="!desktopSidebarCollapsed"
                            class="w-12 h-12 rounded-2xl bg-[#1f1d2b] flex items-center justify-center shadow-md shrink-0">
                            <span class="text-white font-black text-sm tracking-wide">
                                OX
                            </span>
                        </div>

                        <div v-if="!desktopSidebarCollapsed" class="min-w-0">
                            <h1 class="text-xl font-black tracking-tight text-slate-800 leading-none">
                                OXIVENTAS
                            </h1>

                            <p class="text-xs text-slate-400 mt-1">
                                Sistema Administrativo
                            </p>
                        </div>
                    </div>

                    <button type="button"
                        class="hidden md:flex w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 items-center justify-center transition shrink-0"
                        @click="toggleDesktopSidebar">
                        <span class="material-symbols-outlined text-[20px] text-slate-600">
                            {{ desktopSidebarCollapsed ? 'chevron_right' : 'chevron_left' }}
                        </span>
                    </button>
                </div>
            </div>

            <SidebarItem :items="menuItems" :extended="!desktopSidebarCollapsed" />
        </aside>

        <section class="flex-1 min-w-0 flex flex-col overflow-hidden">

            <header
                class="shrink-0 bg-white shadow-sm px-4 md:px-8 py-4 border-b flex justify-between items-center gap-4">
                <div class="flex items-center gap-3 min-w-0">

                    <button class="md:hidden p-2 rounded-lg bg-slate-100" @click="toggleSidebar">
                        ☰
                    </button>

                    <div class="min-w-0">
                        <h2 class="text-base md:text-xl font-semibold text-slate-700 truncate">
                            Panel General Administrativo
                        </h2>

                        <p class="text-xs md:text-sm text-slate-400 truncate">
                            Bienvenido {{ page.props.auth.user.name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 md:gap-4 shrink-0">
                    <div class="hidden sm:block text-xs md:text-sm bg-slate-200 px-3 md:px-4 py-2 rounded-lg">
                        Rol: {{ role }}
                    </div>

                    <Link :href="route('logout')" method="post" as="button"
                        class="bg-red-500 hover:bg-red-600 text-white px-3 md:px-4 py-2 rounded-lg text-xs md:text-sm font-medium transition">
                        Salir
                    </Link>
                </div>
            </header>

            <main class="flex-1 min-h-0 overflow-y-auto p-4 md:p-8">
                <slot />
            </main>

        </section>
    </div>
</template>