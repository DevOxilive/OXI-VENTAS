<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])
const query = ref('')
const open = ref(false)
const active = ref(0)
const matches = computed(() => {
    const term = query.value.trim().toLowerCase()
    if (!term) return []
    return props.options.filter((product) => [
        product.name, product.barcode,
        ...(product.barcodes || []).map((code) => code?.code ?? code),
    ].some((value) => String(value ?? '').toLowerCase().includes(term))).slice(0, 10)
})
watch(query, () => { active.value = 0 })
watch(() => props.modelValue, (ids) => {
    query.value = props.options.find((product) => String(product.id) === String(ids[0]))?.name || ''
}, { immediate: true })

function choose(product) {
    query.value = product.name
    open.value = false
    emit('update:modelValue', [String(product.id)])
}
function clear() {
    query.value = ''
    open.value = false
    emit('update:modelValue', [])
}
function keydown(event) {
    if (event.key === 'Escape') open.value = false
    if (event.key === 'Enter') {
        event.preventDefault()
        if (open.value && matches.value[active.value]) choose(matches.value[active.value])
    }
    if (['ArrowDown', 'ArrowUp'].includes(event.key) && matches.value.length) {
        event.preventDefault()
        open.value = true
        active.value = (active.value + (event.key === 'ArrowDown' ? 1 : -1) + matches.value.length) % matches.value.length
    }
}
</script>

<template>
    <div class="relative" @focusout="!$event.currentTarget.contains($event.relatedTarget) && (open = false)">
        <div class="flex gap-2">
            <input
                v-model="query"
                type="search"
                aria-label="Buscar producto por nombre o código de barras"
                placeholder="Código de barras o nombre"
                autocomplete="off"
                class="h-11 min-w-0 w-full rounded-xl border border-secondary bg-background px-3 text-sm text-text"
                @input="open = true"
                @focus="open = true"
                @keydown="keydown"
            />
            <button type="button" class="rounded-lg border border-secondary px-2 text-xs text-text hover:bg-secondary" @click="clear">
                Limpiar
            </button>
        </div>
        <div v-if="open && query.trim()" class="absolute left-0 top-full z-50 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-1 shadow-xl">
            <button
                v-for="(product, index) in matches"
                :key="product.id"
                type="button"
                class="block w-full rounded-lg px-3 py-2 text-left text-sm"
                :class="index === active ? 'bg-primary text-white' : 'text-text hover:bg-secondary'"
                @mousedown.prevent
                @click="choose(product)"
            >
                {{ product.name }}
            </button>
            <p v-if="!matches.length" class="px-3 py-2 text-sm text-text">Sin productos coincidentes.</p>
        </div>
    </div>
</template>
