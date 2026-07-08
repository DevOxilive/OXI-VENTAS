<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue"
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
    branchKeys: {
        type: Array,
        default: () => [],
    },
    ancestorKeys: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(["expand-request", "navigate"])

const page = usePage()
const hoveredItem = ref(null)
const collapseEventName = "sidebar:collapse-all"
const openItems = reactive(
    typeof window !== "undefined"
        ? JSON.parse(window.localStorage.getItem("sidebarOpenItems") || "{}")
        : {},
)

const currentPath = computed(() =>
    normalizeUrl(page.url || ""),
)

function saveOpenItems() {
    if (typeof window === "undefined") return

    window.localStorage.setItem("sidebarOpenItems", JSON.stringify(openItems))
}

function normalizeUrl(url) {
    if (!url) return ""

    const parsedUrl = new URL(
        url,
        typeof window !== "undefined" ? window.location.origin : "http://localhost",
    )

    const normalizedPath = parsedUrl.pathname.replace(/\/$/, "")

    return `${normalizedPath}${parsedUrl.search || ""}`
}

function isActiveItem(item) {
    if (!item.url) return false

    const target = normalizeUrl(item.url)

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

function resolveBranchKey(item) {
    if (!item) return null

    if (item.isBranch) {
        return item.key
    }

    const parts = String(item.key || "").split(".")

    if (parts[0] === "inventory" && parts[1]) {
        return parts[1]
    }

    return null
}

function collapseOtherBranches(activeBranchKey) {
    if (!activeBranchKey) return

    props.branchKeys.forEach((branchKey) => {
        if (branchKey === activeBranchKey) {
            openItems[branchKey] = true
            return
        }

        openItems[branchKey] = false

        Object.keys(openItems).forEach((key) => {
            if (key.startsWith(`inventory.${branchKey}.`)) {
                openItems[key] = false
            }
        })
    })

    saveOpenItems()
}

function isOpen(item) {
    return Boolean(openItems[item.key])
}

function closeAllItems() {
    Object.keys(openItems).forEach((key) => {
        openItems[key] = false
    })

    saveOpenItems()
}

function toggleItem(item) {
    if (!props.extended && item.children?.length) {
        closeAllItems()

        if (item.isBranch) {
            collapseOtherBranches(item.key)
        }

        openItems[item.key] = true
        saveOpenItems()
        emit("expand-request", item)
        return
    }

    const nextValue = !isOpen(item)

    if (item.isBranch) {
        collapseOtherBranches(item.key)
    }

    openItems[item.key] = nextValue
    saveOpenItems()
}

function closeItems(keys = []) {
    keys.forEach((key) => {
        if (!key) return

        openItems[key] = false
    })

    saveOpenItems()
}

function handleLinkClick(item) {
    const activeBranchKey = resolveBranchKey(item)

    if (activeBranchKey) {
        collapseOtherBranches(activeBranchKey)
    }

    closeItems(props.ancestorKeys)
    emit("navigate")
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

function handleCollapseAll() {
    closeAllItems()
}

onMounted(() => {
    if (typeof window === "undefined") return

    window.addEventListener(collapseEventName, handleCollapseAll)
})

onBeforeUnmount(() => {
    if (typeof window === "undefined") return

    window.removeEventListener(collapseEventName, handleCollapseAll)
})
</script>

<template>
    <ul class="max-h-full space-y-1 overflow-y-auto overflow-x-hidden px-2 pb-3">
        <li v-for="item in items" :key="item.key || item.text">
            <div>
                <Link
                    v-if="item.url"
                    :href="item.url"
                    class="group flex items-center rounded-xl text-slate-700 transition hover:bg-slate-100"
                    :class="extended ? 'gap-3 px-3 py-3' : 'justify-center px-2 py-3'"
                    :style="itemStyle(item)"
                    @click="handleLinkClick(item)"
                    @mouseenter="hoveredItem = item.key"
                    @mouseleave="hoveredItem = null"
                >
                    <span
                        class="material-symbols-outlined flex h-10 w-10 shrink-0 items-center justify-center text-[22px]"
                        :style="iconStyle(item)"
                    >
                        {{ item.icon }}
                    </span>

                    <span
                        v-if="extended"
                        class="min-w-0 flex-1 whitespace-normal break-words text-sm font-medium leading-5"
                    >
                        {{ item.text }}
                    </span>
                </Link>

                <button
                    v-else
                    type="button"
                    class="group flex w-full items-center rounded-xl text-slate-700 transition hover:bg-slate-100"
                    :class="extended ? 'justify-between gap-3 px-3 py-3' : 'justify-center px-2 py-3'"
                    :style="itemStyle(item)"
                    @mouseenter="hoveredItem = item.key"
                    @mouseleave="hoveredItem = null"
                    @click="toggleItem(item)"
                >
                    <div class="flex min-w-0 items-center" :class="extended ? 'gap-3' : 'justify-center'">
                        <span
                            class="material-symbols-outlined flex h-10 w-10 shrink-0 items-center justify-center text-[22px]"
                            :style="iconStyle(item)"
                        >
                            {{ item.icon }}
                        </span>

                        <span
                            v-if="extended"
                            class="min-w-0 flex-1 whitespace-normal break-words text-left text-sm font-medium leading-5"
                        >
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
                class="ml-5 mt-1 space-y-1 border-l border-slate-200 pl-2.5"
            >
                <SidebarItem
                    :items="item.children"
                    :extended="extended"
                    :parent-color="getItemTone(item)"
                    :branch-keys="branchKeys"
                    :ancestor-keys="[...ancestorKeys, item.key]"
                    @expand-request="$emit('expand-request', $event)"
                    @navigate="$emit('navigate')"
                />
            </div>
        </li>
    </ul>
</template>
