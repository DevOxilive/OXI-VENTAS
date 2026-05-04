<script setup>
import RecursosHumanosLayout from '@/Lsayouts/RecursosHumanosLayout.vue'
defineOptions({ layout: RecursosHumanoLayout })
</script>

<template>
    <div>
        <h1 class="text-3xl font-bold text-slate-700 mb-6">
            Dashboard General
        </h1>

        <div class="grid grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl p-6 shadow-sm">
                Empleados Registrados
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm">
                Usuarios Activos
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm">
                Roles del Sistema
            </div>
        </div>
    </div>
</template>