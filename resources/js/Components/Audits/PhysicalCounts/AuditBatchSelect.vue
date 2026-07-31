<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    batches: {
        type: Array,
        default: () => [],
    },
    canViewStock: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['update:modelValue'])
const root = ref(null)
const open = ref(false)
const selectedBatch = computed(() =>
    props.batches.find((batch) => String(batch.id) === String(props.modelValue))
)

function formatDate(value) {
    if (!value) return 'Sin fecha'

    const [year, month, day] = String(value).slice(0, 10).split('-')
    return year && month && day ? `${day}/${month}/${year}` : value
}

function countedBy(batch) {
    return (batch.counted_by || [])
        .map((user) => user.name)
        .filter(Boolean)
        .join(', ')
}

function selectBatch(batch) {
    emit('update:modelValue', batch.id)
    open.value = false
}

function closeOnOutsideClick(event) {
    if (!root.value?.contains(event.target)) open.value = false
}

onMounted(() => document.addEventListener('click', closeOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick))
</script>

<template>
    <div ref="root" class="relative">
        <label class="mb-1.5 block text-sm font-semibold text-text">Lote y caducidad</label>

        <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border bg-background px-4 py-2.5 text-left text-sm transition"
            :class="error ? 'border-primary' : 'border-secondary hover:border-accent'"
            :aria-expanded="open"
            @click="open = !open"
            @keydown.esc="open = false"
        >
            <span v-if="selectedBatch" class="min-w-0">
                <strong class="block truncate text-text">{{ selectedBatch.lot_number || 'Sin lote' }}</strong>
                <span class="text-xs text-text opacity-60">
                    Cad. {{ formatDate(selectedBatch.expiration_date) }}
                    <template v-if="canViewStock"> · Exist. {{ selectedBatch.quantity ?? 0 }}</template>
                </span>
            </span>
            <span v-else class="text-text opacity-55">Selecciona un lote</span>
            <span class="material-symbols-outlined text-xl text-text opacity-55">
                {{ open ? 'expand_less' : 'expand_more' }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute z-40 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-2 shadow-xl"
        >
            <button
                v-for="batch in batches"
                :key="batch.id"
                type="button"
                class="grid w-full grid-cols-[minmax(150px,1fr)_auto] items-center gap-4 rounded-lg px-3 py-2.5 text-left transition hover:bg-secondary"
                :class="{ 'bg-secondary': String(batch.id) === String(modelValue) }"
                @click="selectBatch(batch)"
            >
                <span class="min-w-0">
                    <strong class="block truncate text-sm text-text">
                        {{ batch.lot_number || 'Sin lote' }}
                    </strong>
                    <span class="mt-0.5 block text-xs text-text opacity-60">
                        Cad. {{ formatDate(batch.expiration_date) }}
                        <template v-if="canViewStock"> · Existencia {{ batch.quantity ?? 0 }}</template>
                    </span>
                </span>

                <span class="max-w-64 text-right">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="batch.is_counted
                            ? 'bg-accent/10 text-accent'
                            : 'bg-secondary text-text opacity-65'"
                    >
                        {{ batch.is_counted ? 'Contado' : 'Pendiente' }}
                    </span>
                    <span
                        v-if="batch.is_counted"
                        class="mt-1 block truncate text-xs font-medium text-text opacity-70"
                    >
                        Por {{ countedBy(batch) || 'Usuario sin nombre' }}
                    </span>
                </span>
            </button>

            <p v-if="batches.length === 0" class="px-3 py-5 text-center text-sm text-text opacity-55">
                No hay lotes disponibles.
            </p>
        </div>

        <p v-if="error" class="mt-1.5 text-sm text-primary">{{ error }}</p>
    </div>
</template>
