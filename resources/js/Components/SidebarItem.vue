<script setup>
import { Link } from '@inertiajs/vue3'
import { reactive } from 'vue'

defineProps({
    items: {
        type: Array,
        default: () => []
    },

    extended: {
        type: Boolean,
        default: true
    }
})
const openItems = reactive({})

function toggleItem(item) {
    openItems[item.key] = !openItems[item.key]
}

function isOpen(item) {
    return !!openItems[item.key]
}
</script>

<template>
    <ul class="space-y-1 max-h-full overflow-y-auto overflow-x-hidden pr-1">
        <li v-for="item in items" :key="item.key || item.text">
            <div>
                <Link
                    v-if="item.url"
                    :href="item.url"
                    class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-700 hover:bg-slate-100 transition group"
                >
                    <span class="material-symbols-outlined text-[22px] shrink-0 text-slate-500 group-hover:text-slate-700">
                        {{ item.icon }}
                    </span>

                    <span v-if="extended" class="text-sm font-medium truncate">
                        {{ item.text }}
                    </span>
                </Link>

                <button
                    v-else
                    type="button"
                    class="w-full flex items-center justify-between gap-3 px-3 py-3 rounded-xl text-slate-700 hover:bg-slate-100 transition group"
@click="toggleItem(item)"                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="material-symbols-outlined text-[22px] shrink-0 text-slate-500 group-hover:text-slate-700">
                            {{ item.icon }}
                        </span>

                        <span v-if="extended" class="text-sm font-medium truncate">
                            {{ item.text }}
                        </span>
                    </div>

                    <span
                        v-if="item.children && item.children.length && extended"
                        class="material-symbols-outlined text-[18px] text-slate-400 transition"
                    >
                  {{ isOpen(item) ? 'expand_more' : 'chevron_right' }}
                    </span>
                </button>
            </div>

            <div
      v-if="item.children && item.children.length && isOpen(item) && extended"
                class="ml-6 mt-1 space-y-1 border-l border-slate-200 pl-3"
            >
                <SidebarItem :items="item.children" :extended="extended" />
            </div>
        </li>
    </ul>
</template>