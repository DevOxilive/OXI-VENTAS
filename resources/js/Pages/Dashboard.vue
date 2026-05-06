<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

// 🔥 REACTIVO
const permissions = computed(() => page.props.auth.permissions || [])

// 🔥 VALIDAR PERMISOS
const can = (modulo) => {
    return permissions.value.some(p => p.startsWith(modulo + '.'))
}
</script>
<template>
    <AppLayout title="Dashboard">

        <div class="min-h-screen bg-gradient-to-br from-green-400 to-green-600 p-6 md:p-10">

            <div class="text-center mb-8">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800">
                    Elige una categoría
                </h1>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 max-w-5xl mx-auto">

                <Link v-if="can('roles')" href="/roles"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">🛡️</div>
                    <h2 class="text-lg font-bold">Roles</h2>
                </Link>

       <Link    
    v-if="can('usuarios')" 
    :href="route('sistemas.empleados', { vista: 'usuarios' })"
    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition"
>
                    <div class="text-5xl mb-3">👤</div>
                    <h2 class="text-lg font-bold">Usuarios</h2>
                </Link>

                <Link v-if="can('empleados')" href="/empleados"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">🧑‍💼</div>
                    <h2 class="text-lg font-bold">Capital Humano</h2>
                </Link>

                <Link v-if="can('ventas')" href="/ventas"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">💰</div>
                    <h2 class="text-lg font-bold">Ventas</h2>
                </Link>

                <Link v-if="can('inventario')" href="/inventario"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">📦</div>
                    <h2 class="text-lg font-bold">Inventario</h2>
                </Link>

            </div>

        </div>

    </AppLayout>
</template>