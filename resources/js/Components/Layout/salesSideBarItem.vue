<script setup>
import { computed, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    branch: { type: Object, required: true },
    actions: { type: Array, default: () => [] },
    userId: { type: [String, Number], required: true },
    extended: { type: Boolean, default: true },
})

const emit = defineEmits(['navigate', 'expand-request'])
const isOpen = ref(false)
const isCustomizing = ref(false)
const draggedKey = ref(null)
const order = ref([])
const storageKey = computed(() => `sales-navbar-order:${props.userId}:${props.branch.slug || props.branch.id}`)

const defaultActionRank = (action) => {
    if (action.key === 'sales.pos') return 0
    if (action.key?.startsWith('inventory.') && action.key.endsWith('.inventory')) return 1
    if (action.key === 'sales.history') return 2
    if (action.key === 'sales.cash-closures') return 3
    if (action.key === 'sales.print-prices') return 4
    if (action.key === 'sales.purchase-lists') return 5
    if (action.key === 'sales.purchase-orders') return 6
    if (action.key === 'sales.attendance') return 7
    return 8
}

const defaultActions = computed(() => props.actions
    .map((action, index) => ({ action, index }))
    .sort((a, b) => defaultActionRank(a.action) - defaultActionRank(b.action) || a.index - b.index)
    .map(({ action }) => action))

const orderedActions = computed(() => {
    const byKey = new Map(props.actions.map(action => [action.key, action]))
    const saved = order.value.map(key => byKey.get(key)).filter(Boolean)
    const savedKeys = new Set(saved.map(action => action.key))
    return [...saved, ...defaultActions.value.filter(action => !savedKeys.has(action.key))]
})

function readOrder() {
    if (typeof window === 'undefined') return
    try {
        const saved = JSON.parse(window.localStorage.getItem(storageKey.value) || '[]')
        order.value = Array.isArray(saved) ? saved : []
    } catch {
        order.value = []
    }
}

function moveAction(index, direction) {
    const next = [...orderedActions.value]
    const target = index + direction
    if (target < 0 || target >= next.length) return
    ;[next[index], next[target]] = [next[target], next[index]]
    order.value = next.map(action => action.key)
    window.localStorage.setItem(storageKey.value, JSON.stringify(order.value))
}

function onDrop(targetKey) {
    const actions = [...orderedActions.value]
    const from = actions.findIndex(action => action.key === draggedKey.value)
    const to = actions.findIndex(action => action.key === targetKey)
    if (from < 0 || to < 0 || from === to) return
    const [moved] = actions.splice(from, 1)
    actions.splice(to, 0, moved)
    order.value = actions.map(action => action.key)
    window.localStorage.setItem(storageKey.value, JSON.stringify(order.value))
    draggedKey.value = null
}

function resetOrder() {
    order.value = []
    if (typeof window !== 'undefined') window.localStorage.removeItem(storageKey.value)
}

function setCustomizing(value) {
    isCustomizing.value = value
    isOpen.value = value
}

function openBranchMenu() {
    if (!props.extended) {
        emit('expand-request')
        return
    }
    isOpen.value = !isOpen.value
}

watch(storageKey, readOrder, { immediate: true })

defineExpose({ setCustomizing, resetOrder })
</script>

<template>
    <ul class="max-h-full space-y-1 overflow-y-auto overflow-x-hidden px-2 pb-3">
        <li>
            <Link
                :href="route('dashboard')"
                class="group flex items-center gap-3 rounded-xl px-3 py-3 text-text transition hover:bg-secondary"
                @click="emit('navigate')"
            >
                <span class="material-symbols-outlined flex h-10 w-10 shrink-0 items-center justify-center text-[22px]">dashboard</span>
                <span v-if="extended" class="text-sm font-medium">Inicio</span>
            </Link>
        </li>
        <li>
            <button
                type="button"
                class="group flex w-full items-center justify-between gap-3 rounded-xl px-3 py-3 text-text transition hover:bg-secondary"
                :style="branch.color ? { color: branch.color, backgroundColor: `${branch.color}18` } : {}"
                :aria-expanded="isOpen"
                @click="openBranchMenu"
            >
                <span class="flex min-w-0 items-center gap-3">
                    <span class="material-symbols-outlined flex h-10 w-10 shrink-0 items-center justify-center text-[22px]">store</span>
                    <span v-if="extended" class="overflow-hidden text-ellipsis whitespace-nowrap text-left text-sm font-semibold">{{ branch.name }}</span>
                </span>
                <span v-if="extended" class="material-symbols-outlined text-[18px] opacity-60">{{ isOpen ? 'expand_more' : 'chevron_right' }}</span>
            </button>

            <ul v-if="isOpen && extended" class="ml-5 mt-1 space-y-1 border-l border-secondary pl-2.5">
                <li
                    v-for="(action, index) in orderedActions"
                    :key="action.key"
                    class="group flex items-center gap-1 rounded-xl"
                    :draggable="isCustomizing"
                    @dragstart="draggedKey = action.key"
                    @dragover.prevent
                    @drop.prevent="onDrop(action.key)"
                >
                    <Link
                        :href="action.url"
                        class="flex min-w-0 flex-1 items-center gap-2 rounded-xl px-2 py-2.5 text-sm text-text transition hover:bg-secondary"
                        @click="isCustomizing ? $event.preventDefault() : emit('navigate')"
                    >
                        <span class="material-symbols-outlined flex h-8 w-8 shrink-0 items-center justify-center text-[19px]">{{ action.icon }}</span>
                        <span class="min-w-0 flex-1 overflow-hidden text-ellipsis whitespace-nowrap">{{ action.text }}</span>
                    </Link>
                    <template v-if="isCustomizing">
                        <button type="button" class="grid h-8 w-7 shrink-0 place-items-center rounded-md text-text/70 hover:bg-secondary" :disabled="index === 0" :aria-label="`Subir ${action.text}`" @click="moveAction(index, -1)">↑</button>
                        <button type="button" class="grid h-8 w-7 shrink-0 place-items-center rounded-md text-text/70 hover:bg-secondary" :disabled="index === orderedActions.length - 1" :aria-label="`Bajar ${action.text}`" @click="moveAction(index, 1)">↓</button>
                    </template>
                </li>
                <li v-if="!orderedActions.length" class="px-3 py-2 text-xs text-text/60">No hay opciones disponibles.</li>
            </ul>
        </li>
    </ul>
</template>
