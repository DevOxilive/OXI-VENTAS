<script setup>
import { Link } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => []
    },
    extended: {
        type: Boolean,
        default: true
    },
    parentColor: {
        type: String,
        default: null
    }
})

const hoveredItem = ref(null)

const savedOpenItems = JSON.parse(localStorage.getItem('sidebarOpenItems') || '{}')
const openItems = reactive(savedOpenItems)

function saveOpenItems() {
    localStorage.setItem('sidebarOpenItems', JSON.stringify(openItems))
}

function normalizeUrl(url) {
    return url?.replace(/\/$/, '')
}
function getBranchKey(item) {
    if (item.isBranch) {
        return item.slug || item.key
    }

    const parts = item.key?.split('.') || []

    if ((parts[0] === 'inventario' || parts[0] === 'inventory') && parts[1]) {
        return parts[1]
    }

    return null
}function toggleItem(item) {
    const nextValue = !isOpen(item)
    const branchKey = getBranchKey(item)

    const isTopLevelSection =
        item.children?.length &&
        !item.isBranch &&
        item.key !== localStorage.getItem('activeBranch')

    if (isTopLevelSection) {
        Object.keys(openItems).forEach((key) => {
            openItems[key] = false
        })
    }

 if (item.isBranch) {
    props.items.forEach((sibling) => {
        if (sibling.isBranch && sibling.key !== item.key) {
            openItems[sibling.key] = false
        }
    })

    localStorage.setItem('activeBranch', branchKey)
    openItems.branches = true
    openItems[item.key] = nextValue
    openItems[`${item.key}:manual`] = true

    saveOpenItems()
    return
}

    openItems[item.key] = nextValue

    if (branchKey) {
        localStorage.setItem('activeBranch', branchKey)
        openItems.branches = true
        openItems[branchKey] = true
    }

    saveOpenItems()
}
function isActiveItem(item) {
    if (!item.url) return false

    const current = normalizeUrl(window.location.pathname)

    const target = normalizeUrl(
        new URL(item.url, window.location.origin).pathname
    )

    // Caso especial Dashboard
    if (target === '/inventory/dashboard') {
        const activeBranch = localStorage.getItem('activeBranch')

        return (
            current === target &&
            activeBranch &&
            item.key?.includes(activeBranch)
        )
    }

    return current === target
}
function hasActiveChild(item) {
    if (!item.children || !item.children.length) {
        return false
    }

    return item.children.some(child =>
        isActiveItem(child) || hasActiveChild(child)
    )
}
function isOpen(item) {
    const activeBranch = localStorage.getItem('activeBranch')

    if (item.isBranch) {
        const wasManuallyTouched = !!openItems[`${item.key}:manual`]

        if (wasManuallyTouched) {
            return !!openItems[item.key]
        }

        return (
            item.key === activeBranch ||
            item.slug === activeBranch ||
            !!openItems[item.key]
        )
    }

    if (item.key === 'branches' || item.key === 'sucursales') {
        return hasActiveChild(item) || !!openItems[item.key]
    }

    if (hasActiveChild(item)) {
        return true
    }

    return !!openItems[item.key]
}
function itemStyle(item) {
    const active = isActiveItem(item)
    const activeBranch = localStorage.getItem('activeBranch')

    const isActiveBranch =
        item.isBranch &&
        activeBranch &&
        (
            item.key === activeBranch ||
            item.slug === activeBranch
        )

    if (active) {
        return {
            backgroundColor: '#eff6ff',
            color: '#2563eb',
            fontWeight: '700'
        }
    }

    if (isActiveBranch && item.color) {
        return {
            backgroundColor: `${item.color}20`,
            color: item.color,
            fontWeight: '700'
        }
    }

    if (hoveredItem.value === item.key && item.color) {
        return {
            backgroundColor: `${item.color}20`,
            color: item.color
        }
    }

    return {}
}
function iconStyle(item) {
    const activeBranch = localStorage.getItem('activeBranch')

    const isActiveBranch =
        item.isBranch &&
        activeBranch &&
        (
            item.key === activeBranch ||
            item.slug === activeBranch
        )

    if (isActiveItem(item)) {
        return {
            color: '#2563eb'
        }
    }

    if (isActiveBranch && item.color) {
        return {
            color: item.color
        }
    }

    if (item.color) {
        return {
            color: item.color
        }
    }

    return {}
}
function saveCurrentBranch(item) {
    const branchKey = getBranchKey(item)

    if (!branchKey) return

    localStorage.setItem('activeBranch', branchKey)

    openItems.branches = true
    openItems[branchKey] = true
    openItems[`${branchKey}:manual`] = false

    saveOpenItems()
}
</script>

<template>
    <ul class="space-y-1 max-h-full overflow-y-auto overflow-x-hidden pr-1">
        <li v-for="item in items" :key="item.key || item.text">
            <div>
                <Link
                    v-if="item.url"
                    :href="item.url"
                  @click="saveCurrentBranch(item)"
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