<template>
    <ul class="space-y-1">
        <li v-for="(item, index) in items" :key="index">

            <!-- ITEM NAVEGABLE -->
            <Link v-if="item.url && !hasChildren(item)" :href="item.url"
                class="flex items-center justify-between px-3 py-3 rounded-xl cursor-pointer hover:bg-slate-100 transition-all duration-200">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-500">
                        {{ item.icon }}
                    </span>

                    <span v-if="extended" class="text-slate-700 font-medium">
                        {{ item.text }}
                    </span>
                </div>
            </Link>

            <!-- ITEM CON SUBMENU -->
            <div v-else
                class="flex items-center justify-between px-3 py-3 rounded-xl cursor-pointer hover:bg-slate-100 transition-all duration-200"
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
                class="ml-5 mt-1" />
        </li>
    </ul>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
defineOptions({ name: 'SidebarItem' })

defineProps({
    items: Array,
    extended: Boolean
})

function hasChildren(item) {
    return item.children && item.children.length > 0
}

function toggle(item) {
    if (hasChildren(item)) {
        item.isOpen = !item.isOpen
    }
}
</script>