<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const role = page.props.auth.user?.role?.name;

// helpers
const isAdmin = role === 'Administrador';
const isSistemas = role === 'Sistemas';
const isRH = role === 'Recursos Humanos';
const isVentas = role === 'Ventas';
const isInventario = role === 'Inventario';
</script>

<template>
    <AppLayout title="Dashboard">

        <div class="min-h-screen bg-gradient-to-br from-green-400 to-green-600 p-6 md:p-10">

            <!-- Título -->
            <div class="text-center mb-8">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800 drop-shadow-lg">
                    Elige una categoría
                </h1>
            </div>
            <!-- <pre class="text-white">
{{ $page.props.auth.user }}
</pre> -->

            <!-- GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 max-w-5xl mx-auto">

                <!-- ROLES (solo admin o sistemas) -->
                <Link v-if="isAdmin || isSistemas" href="/roles"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">🛡️</div>
                    <h2 class="text-lg font-bold">Roles</h2>
                </Link>

                <!-- USUARIOS -->
                <Link v-if="isAdmin || isSistemas" href="/usuarios"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">👤</div>
                    <h2 class="text-lg font-bold">Usuarios</h2>
                </Link>

                <!-- CAPITAL HUMANO -->
                <Link v-if="isAdmin || isRH" href="/empleados"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">🧑‍💼</div>
                    <h2 class="text-lg font-bold">Capital Humano</h2>
                </Link>

                <!-- VENTAS -->
                <Link v-if="isAdmin || isVentas" href="/ventas"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">💰</div>
                    <h2 class="text-lg font-bold">Ventas</h2>
                </Link>

                <!-- INVENTARIO -->
                <Link v-if="isAdmin || isInventario" href="/inventario"
                    class="bg-white rounded-xl shadow-md p-6 text-center hover:scale-105 transition">
                    <div class="text-5xl mb-3">📦</div>
                    <h2 class="text-lg font-bold">Inventario</h2>
                </Link>

            </div>

        </div>

    </AppLayout>
</template>
