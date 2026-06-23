<script setup>
import axios from 'axios'
import { onMounted, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    physicalCountId: {
        type: Number,
        required: true,
    },
})

const searchInput = ref(null)
const results = ref([])
const loading = ref(false)

let timeout = null

const form = useForm({
    code: '',
})

function focusInput() {
    searchInput.value?.focus()
}

function isExactCodeMatch(product, value) {
    const cleanValue = String(value || '').trim()

    return (
        String(product.barcode || '').trim() === cleanValue ||
        String(product.matched_code || '').trim() === cleanValue
    )
}

watch(
    () => form.code,
    (value) => {
        clearTimeout(timeout)

        if (!value || value.trim().length < 2) {
            results.value = []
            return
        }

        timeout = setTimeout(async () => {
            loading.value = true

            try {
                const response = await axios.get(
                    route('audits.physical-counts.search-products', props.physicalCountId),
                    { params: { search: value } }
                )

                results.value = response.data

                const exactMatch = results.value.find((product) =>
                    isExactCodeMatch(product, value)
                )

                if (exactMatch) {
                    selectProduct(exactMatch, value)
                }
            } finally {
                loading.value = false
            }
        }, 300)
    }
)

function scan() {
    form.post(route('audits.physical-counts.scan', props.physicalCountId), {
        preserveScroll: true,
        onSuccess: () => {
            results.value = []
            focusInput()
        },
    })
}

function selectProduct(product, scannedCode = form.code) {
    results.value = []

    router.post(
        route('audits.physical-counts.scan', props.physicalCountId),
        {
            branch_product_id: product.branch_product_id,
            code: scannedCode || product.matched_code || product.barcode || '',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('code')
                focusInput()
            },
        }
    )
}

onMounted(() => {
    focusInput()
})
</script>

<template>
    <div class="relative rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">
            Buscar producto
        </h2>

        <p class="mt-1 text-sm text-gray-500">
            Escanea un codigo o escribe el nombre del producto.
        </p>

        <form class="mt-4 flex gap-3" @submit.prevent="scan">
            <div class="relative w-full">
                <input
                    ref="searchInput"
                    v-model="form.code"
                    type="text"
                    placeholder="Escanea codigo o escribe el nombre del producto"
                    class="w-full rounded-lg border-gray-300 text-sm"
                    autocomplete="off"
                >

                <div
                    v-if="results.length"
                    class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl"
                >
                    <button
                        v-for="product in results"
                        :key="product.branch_product_id"
                        type="button"
                        class="block w-full border-b px-4 py-3 text-left hover:bg-slate-50 last:border-b-0"
                        @click="selectProduct(product)"
                    >
                        <p class="text-sm font-semibold text-slate-900">
                            {{ product.name }}
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Codigo: {{ product.barcode ?? 'Sin codigo' }}
                        </p>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="form.processing"
            >
                Buscar
            </button>
        </form>

        <p v-if="loading" class="mt-2 text-sm text-gray-500">
            Buscando productos...
        </p>

        <p v-if="form.errors.code" class="mt-2 text-sm text-red-600">
            {{ form.errors.code }}
        </p>
    </div>
</template>
