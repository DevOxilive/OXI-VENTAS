<template>
    <ul class="space-y-1">
        <li v-for="(item, index) in localItems" :key="index">

            <!-- ITEM NAVEGABLE -->
            <Link v-if="item.url && !hasChildren(item)" :href="item.url"
                class="flex items-center justify-between px-3 py-3 rounded-xl cursor-pointer transition-all duration-200"
                :class="isActive(item.url)
                    ? 'bg-slate-700 text-white shadow-sm'
                    : 'hover:bg-slate-100 text-slate-700'">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined"
                        :class="isActive(item.url) ? 'text-white' : 'text-slate-500'">
                        {{ item.icon }}
                    </span>

                    <span v-if="extended" class="font-medium">
                        {{ item.text }}
                    </span>
                </div>
            </Link>

            <!-- ITEM CON SUBMENU -->
            <div v-else
                class="flex items-center justify-between px-3 py-3 rounded-xl cursor-pointer transition-all duration-200 hover:bg-slate-100"
                @click="toggle(item)">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-500">
                        {{ item.icon }}
                    </span>

                    <span v-if="extended" class="text-slate-700 font-medium">
                        {{ item.text }}
                    </span>
                </div>

                <span v-if="hasChildren(item)" class="material-symbols-outlined text-slate-400 text-[20px]">
                    {{ item.isOpen ? 'expand_less' : 'expand_more' }}
                </span>
            </div>

            <!-- CHILDREN -->
            <SidebarItem v-if="item.isOpen && hasChildren(item)" :items="item.children" :extended="extended"
                class="ml-5 mt-1 border-l pl-2" />
        </li>
    </ul>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { reactive } from 'vue'

defineOptions({ name: 'SidebarItem' })

const props = defineProps({
    items: Array,
    extended: Boolean
})

const page = usePage()

/*
|--------------------------------------------------------------------------
| COPIA LOCAL REACTIVA DEL MENÚ
|--------------------------------------------------------------------------
| Evita mutar props directamente al abrir/cerrar submenús.
*/
const localItems = reactive(
    props.items.map(item => ({
        ...item,
        isOpen: item.isOpen || false
    }))
)

function hasChildren(item) {
    return item.children && item.children.length > 0
}

function toggle(item) {
    if (hasChildren(item)) {
        item.isOpen = !item.isOpen
    }
}

/*
|--------------------------------------------------------------------------
| DETECTAR RUTA ACTIVA
|--------------------------------------------------------------------------
*/
function isActive(url) {
    return page.url === new URL(url, window.location.origin).pathname
}
</script>