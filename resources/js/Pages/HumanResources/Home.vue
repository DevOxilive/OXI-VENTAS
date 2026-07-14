<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
defineOptions({ layout: AdminLayout })
</script>

<template>
    <div>
        <h1 class="mb-6 text-3xl font-bold text-text">
            Dashboard General
        </h1>

        <div class="grid grid-cols-3 gap-6">
            <div class="rounded-2xl border border-secondary bg-background p-6 shadow-sm">
                Empleados Registrados
            </div>

            <div class="rounded-2xl border border-secondary bg-background p-6 shadow-sm">
                Usuarios Activos
            </div>

            <div class="rounded-2xl border border-secondary bg-background p-6 shadow-sm">
                Roles del Sistema
            </div>
        </div>
    </div>
</template>
