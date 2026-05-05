<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
</script>

<template>
    <AdminLayout>

        <div class="space-y-6">

            <!-- HERO / HEADER -->
            <div class="bg-white p-5 md:p-8 rounded-2xl shadow-sm border">

                <h1 class="text-xl md:text-3xl font-bold text-slate-700 leading-tight">
                    Bienvenido, {{ page.props.auth.user.name }}
                </h1>

                <p class="text-sm md:text-base text-slate-500 mt-2">
                    Acceso confirmado al panel administrativo del sistema.
                </p>

            </div>

            <!-- KPI GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">

                <!-- ROLE -->
                <div class="bg-white p-5 md:p-6 rounded-2xl shadow-sm border">

                    <h2 class="text-xs md:text-sm text-slate-500">
                        Rol Actual
                    </h2>

                    <p class="text-lg md:text-xl font-bold text-slate-700 mt-2">
                        {{ page.props.auth.user.role.name }}
                    </p>

                </div>

                <!-- STATUS -->
                <div class="bg-white p-5 md:p-6 rounded-2xl shadow-sm border">

                    <h2 class="text-xs md:text-sm text-slate-500">
                        Estado del Sistema
                    </h2>

                    <p class="text-lg md:text-xl font-bold text-green-600 mt-2">
                        Operativo
                    </p>

                </div>

                <!-- ACCESS -->
                <div class="bg-white p-5 md:p-6 rounded-2xl shadow-sm border">

                    <h2 class="text-xs md:text-sm text-slate-500">
                        Acceso
                    </h2>

                    <p class="text-lg md:text-xl font-bold text-slate-700 mt-2">
                        Autorizado
                    </p>

                </div>

            </div>

        </div>

    </AdminLayout>
</template>