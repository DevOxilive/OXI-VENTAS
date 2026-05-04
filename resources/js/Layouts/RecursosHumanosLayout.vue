<!-- 
    Este componente representa el layout principal del módulo de Recursos Humanos.

    Su función es definir la estructura visual base de todas las vistas internas,
    integrando:

    - Una barra lateral de navegación (Sidebar)
    - Un encabezado superior (Topbar)
    - Un contenedor dinámico donde se renderizarán las demás interfaces mediante <slot>

    Esto permite reutilizar una misma plantilla visual en todas las páginas
    del sistema administrativo, manteniendo consistencia y mejor organización.

    SI NECESITAS REUTILIZAR ESTE RECURSO, GENERA UN ARCHIVO NUEVO SOBRE ESTA CARPETA
    Nombralo con el nombre de la interfaz que generaras basada en este layout 
    y Invocalo en el script setup de tu nueva vista, definiendo el layout a utilizar.
    Ejemplo: import RecursosHumanosLayout from '@/Layouts/RecursosHumanosLayout.vue'
-->

<script setup>

/*
    Se importa el componente SidebarItem, encargado de renderizar
    cada uno de los elementos del menú lateral de navegación.
*/
import SidebarItem from '@/Components/SidebarItem.vue'

/*
    Arreglo que contiene la configuración de las opciones que aparecerán
    en el menú lateral.

    Cada objeto representa un módulo del sistema con:
    - text: nombre visible en pantalla
    - icon: icono representativo
    - url: ruta hacia la vista correspondiente
*/
const menuItems = [
    {
        text: 'Inicio',
        icon: 'dashboard',
        url: route('rh.home')
    },
    {
        text: 'Empleados',
        icon: 'person',
        url: route('rh.empleados')
    },
]
</script>

<template>

    <div class="flex h-screen bg-slate-100">


        <aside class="w-72 bg-white border-r shadow-sm p-5 flex flex-col">

            <!-- Encabezado visual del sidebar -->
            <div class="mb-10">
                <h1 class="text-2xl font-bold text-slate-700">RH Panel</h1>
                <p class="text-sm text-slate-400">Sistema Administrativo</p>
            </div>

            <!-- 
                Componente reutilizable que recibe el arreglo de items
                y los muestra como menú de navegación extendido.
            -->
            <SidebarItem :items="menuItems" :extended="true" />
        </aside>

        <section class="flex-1 overflow-y-auto">

            <!-- TOPBAR -->
            <!-- 
                Barra superior que funciona como encabezado general
                del módulo actual del sistema.
            -->
            <header class="bg-white shadow-sm px-8 py-5 border-b">
                <h2 class="text-xl font-semibold text-slate-700">
                    Control de Personal y Candidatos
                </h2>
            </header>

            <!-- SLOT DINÁMICO -->
            <!-- 
                Área donde Laravel + Inertia renderizará automáticamente
                las diferentes vistas hijas que utilicen este layout.

                El <slot /> actúa como un contenedor dinámico reutilizable.
            -->
            <main class="p-8">
                <slot />
            </main>

        </section>
    </div>
</template>