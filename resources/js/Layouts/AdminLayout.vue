<script setup>
import SidebarItem from '@/Components/SidebarItem.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { generateMenu } from '@/config/menuConfig'
import { ref } from 'vue'

const page = usePage()
const role = page.props.auth.user?.role?.name
const permissions = page.props.auth.permissions || []
const menuItems = generateMenu(role, permissions)

const sidebarOpen = ref(false)

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
}
</script>

<template>
    <div class="flex h-screen bg-slate-100 overflow-hidden">

        <!-- BACKDROP MOBILE -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 bg-black/40 z-40 md:hidden"
            @click="toggleSidebar"
        />

        <!-- SIDEBAR -->
        <aside
            class="fixed md:static z-50 md:z-auto bg-white border-r shadow-sm p-5 flex flex-col
                   w-72 h-full transform transition-transform duration-200
                   md:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        >

            <div class="mb-10">
                <h1 class="text-2xl font-bold text-slate-700">OXIVENTAS</h1>
                <p class="text-sm text-slate-400">Sistema Administrativo</p>
            </div>

            <SidebarItem :items="menuItems" :extended="true" />
        </aside>

        <!-- CONTENIDO -->
        <section class="flex-1 flex flex-col overflow-hidden">

            <!-- HEADER -->
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 border-b flex justify-between items-center">

                <div class="flex items-center gap-3">

                    <!-- BOTÓN MOBILE -->
                    <button
                        class="md:hidden p-2 rounded-lg bg-slate-100"
                        @click="toggleSidebar"
                    >
                        ☰
                    </button>

                    <div>
                        <h2 class="text-base md:text-xl font-semibold text-slate-700">
                            Panel General Administrativo
                        </h2>
                        <p class="text-xs md:text-sm text-slate-400">
                            Bienvenido {{ page.props.auth.user.name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 md:gap-4">

                    <div class="text-xs md:text-sm bg-slate-200 px-3 md:px-4 py-2 rounded-lg">
                        Rol: {{ role }}
                    </div>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="bg-red-500 hover:bg-red-600 text-white px-3 md:px-4 py-2 rounded-lg text-xs md:text-sm font-medium transition"
                    >
                        Salir
                    </Link>

                </div>
            </header>

            <!-- MAIN -->
            <main class="flex-1 overflow-y-auto p-4 md:p-8">
                <slot />
            </main>

        </section>
    </div>
</template>