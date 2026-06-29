<script setup>
import { computed, reactive, ref } from "vue"
import { Link, usePage } from "@inertiajs/vue3"

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    extended: {
        type: Boolean,
        default: true,
    },
    parentColor: {
        type: String,
        default: null,
    },
})

const emit = defineEmits(["expand-request"])

const page = usePage()
const hoveredItem = ref(null)
const openItems = reactive(
    typeof window !== "undefined"
        ? JSON.parse(window.localStorage.getItem("sidebarOpenItems") || "{}")
        : {},
)

const currentPath = computed(() =>
    normalizeUrl(page.url?.split("?")[0] || ""),
)

function saveOpenItems() {
    if (typeof window === "undefined") return

    window.localStorage.setItem("sidebarOpenItems", JSON.stringify(openItems))
}

function normalizeUrl(url) {
    return url?.replace(/\/$/, "") || ""
}

function isActiveItem(item) {
    if (!item.url) return false

    const target = normalizeUrl(
        new URL(
            item.url,
            typeof window !== "undefined" ? window.location.origin : "http://localhost",
        ).pathname,
    )

    return currentPath.value === target
}

function hasActiveChild(item) {
    if (!item.children?.length) {
        return false
    }

    return item.children.some((child) =>
        isActiveItem(child) || hasActiveChild(child),
    )
}

function isBranchActive(item) {
    return Boolean(item.isBranch && hasActiveChild(item))
}

function getItemTone(item) {
    return item.color || props.parentColor || null
}

function isOpen(item) {
    if (hasActiveChild(item)) {
        return true
    }

    return Boolean(openItems[item.key])
}

function toggleItem(item) {
    if (!props.extended && item.children?.length) {
        emit("expand-request", item)
        return
    }

    const nextValue = !isOpen(item)

    if (item.isBranch) {
        props.items.forEach((sibling) => {
            if (sibling.isBranch && sibling.key !== item.key) {
                openItems[sibling.key] = false
            }
        })
    }

    openItems[item.key] = nextValue
    saveOpenItems()
}

function itemStyle(item) {
    const tone = getItemTone(item)

    if (isActiveItem(item)) {
        if (tone) {
            return {
                backgroundColor: `${tone}20`,
                color: tone,
                fontWeight: "700",
            }
        }

        return {
            backgroundColor: "#eff6ff",
            color: "#2563eb",
            fontWeight: "700",
        }
    }

    if (isBranchActive(item) && tone) {
        return {
            backgroundColor: `${tone}20`,
            color: tone,
            fontWeight: "700",
        }
    }

    if (hoveredItem.value === item.key && tone) {
        return {
            backgroundColor: `${tone}18`,
            color: tone,
        }
    }

    return {}
}

function iconStyle(item) {
    const tone = getItemTone(item)

    if (isActiveItem(item)) {
        if (tone) {
            return {
                color: tone,
            }
        }

        return {
            color: "#2563eb",
        }
    }

    if (isBranchActive(item) && tone) {
        return {
            color: tone,
        }
    }

    if (tone) {
        return {
            color: tone,
        }
    }

    return {}
}
</script>

<template>
    <ul class="max-h-full space-y-1 overflow-y-auto overflow-x-hidden pr-1">
        <li v-for="item in items" :key="item.key || item.text">
            <div>
                <Link
                    v-if="item.url"
                    :href="item.url"
                    class="group flex items-center gap-3 rounded-xl px-3 py-3 text-slate-700 transition hover:bg-slate-100"
                    :style="itemStyle(item)"
                    @mouseenter="hoveredItem = item.key"
                    @mouseleave="hoveredItem = null"
                >
                    <span
                        class="material-symbols-outlined shrink-0 text-[22px]"
                        :style="iconStyle(item)"
                    >
                        {{ item.icon }}
                    </span>

                    <span v-if="extended" class="truncate text-sm font-medium">
                        {{ item.text }}
                    </span>
                </Link>

                <button
                    v-else
                    type="button"
                    class="group flex w-full items-center justify-between gap-3 rounded-xl px-3 py-3 text-slate-700 transition hover:bg-slate-100"
                    :style="itemStyle(item)"
                    @mouseenter="hoveredItem = item.key"
                    @mouseleave="hoveredItem = null"
                    @click="toggleItem(item)"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="material-symbols-outlined shrink-0 text-[22px]"
                            :style="iconStyle(item)"
                        >
                            {{ item.icon }}
                        </span>

                        <span v-if="extended" class="truncate text-sm font-medium">
                            {{ item.text }}
                        </span>
                    </div>

                    <span
                        v-if="item.children?.length && extended"
                        class="material-symbols-outlined text-[18px] text-slate-400 transition"
                    >
                        {{ isOpen(item) ? "expand_more" : "chevron_right" }}
                    </span>
                </button>
            </div>

            <div
                v-if="item.children?.length && isOpen(item) && extended"
                class="ml-6 mt-1 space-y-1 border-l border-slate-200 pl-3"
            >
                <SidebarItem
                    :items="item.children"
                    :extended="extended"
                    :parent-color="getItemTone(item)"
                    @expand-request="$emit('expand-request', $event)"
                />
            </div>
        </li>
    </ul>
</template>
