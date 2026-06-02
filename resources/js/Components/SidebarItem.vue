<script setup>
import { Link } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

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

const hoveredItem = ref(null)

const savedOpenItems = JSON.parse(localStorage.getItem('sidebarOpenItems') || '{}')
const openItems = reactive(savedOpenItems)

function saveOpenItems() {
    localStorage.setItem('sidebarOpenItems', JSON.stringify(openItems))
}

function toggleItem(item) {
    openItems[item.key] = !isOpen(item)
    saveOpenItems()
}

function normalizeUrl(url) {
    return url?.replace(/\/$/, '')
}

function isActiveUrl(url) {
    if (!url) return false

    const current = normalizeUrl(window.location.pathname)
    const target = normalizeUrl(url)

    return current === target || current.startsWith(target + '/')
}

function hasActiveChild(item) {
    return item.children?.some(child =>
        isActiveUrl(child.url) || hasActiveChild(child)
    )
}

function isOpen(item) {
    if (hasActiveChild(item)) {
        return true
    }

    return !!openItems[item.key]
}

function itemStyle(item) {
    if (hoveredItem.value === item.key && item.color) {
        return {
            backgroundColor: `${item.color}20`,
            color: item.color
        }
    }

    if (isActiveUrl(item.url) && item.color) {
        return {
            backgroundColor: `${item.color}20`,
            color: item.color
        }
    }

    return {}
}

function iconStyle(item) {
    if (item.color) {
        return {
            color: item.color
        }
    }

    return {}
}
</script>

<template>
    <ul class="space-y-1 max-h-full overflow-y-auto overflow-x-hidden pr-1">
        <li v-for="item in items" :key="item.key || item.text">
            <div>
                <Link
                    v-if="item.url"
                    :href="item.url"
                    @mouseenter="hoveredItem = item.key"
                    @mouseleave="hoveredItem = null"
                    class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-700 hover:bg-slate-100 transition group"
                    :style="itemStyle(item)"
                >
                    <span
                        class="material-symbols-outlined text-[22px] shrink-0"
                        :style="iconStyle(item)"
                    >
                        {{ item.icon }}
                    </span>

                    <span v-if="extended" class="text-sm font-medium truncate">
                        {{ item.text }}
                    </span>
                </Link>

                <button
                    v-else
                    type="button"
                    @mouseenter="hoveredItem = item.key"
                    @mouseleave="hoveredItem = null"
                    class="w-full flex items-center justify-between gap-3 px-3 py-3 rounded-xl text-slate-700 hover:bg-slate-100 transition group"
                    :style="itemStyle(item)"
                    @click="toggleItem(item)"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span
                            class="material-symbols-outlined text-[22px] shrink-0"
                            :style="iconStyle(item)"
                        >
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