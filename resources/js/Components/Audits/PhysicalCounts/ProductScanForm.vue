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
    <div class="relative h-fit rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm">
        <h2 class="text-xs font-semibold text-gray-900">
            Buscar producto
        </h2>

        <p class="sr-only">
            Escanea un código o escribe el nombre del producto.
        </p>

        <form class="mt-1.5 flex flex-col gap-1.5 sm:flex-row" @submit.prevent="scan">
            <div class="relative w-full">
                <input
                    ref="searchInput"
                    v-model="form.code"
                    type="text"
                    placeholder="Escanea código o escribe el nombre del producto"
                    class="h-8 w-full rounded-md border-gray-300 px-2 text-xs"
                    autocomplete="off"
                >

                <div
                    v-if="results.length"
                    class="absolute z-30 mt-1.5 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl"
                >
                    <button
                        v-for="product in results"
                        :key="product.branch_product_id"
                        type="button"
                        class="block w-full border-b px-3 py-2 text-left hover:bg-slate-50 last:border-b-0"
                        @click="selectProduct(product)"
                    >
                        <p class="text-xs font-semibold text-slate-900">
                            {{ product.name }}
                        </p>

                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Código: {{ product.barcode ?? 'Sin código' }}
                        </p>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                class="h-8 rounded-md bg-slate-900 px-3 text-xs font-semibold text-white disabled:opacity-50"
                :disabled="form.processing"
            >
                Buscar
            </button>
        </form>

        <p v-if="loading" class="mt-1.5 text-xs text-gray-500">
            Buscando productos...
        </p>

        <p v-if="form.errors.code" class="mt-1.5 text-xs text-red-600">
            {{ form.errors.code }}
        </p>
    </div>
</template>
