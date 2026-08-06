<script setup>
import axios from 'axios'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

import FormPanel from '@/Components/Cards/FormPanel.vue'
import InputField from '@/Components/Forms/InputField.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'

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
                    { params: { search: value } },
                )

                results.value = response.data

                const exactMatch = results.value.find((product) =>
                    isExactCodeMatch(product, value),
                )

                if (exactMatch) {
                    selectProduct(exactMatch, value)
                }
            } finally {
                loading.value = false
            }
        }, 300)
    },
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
        },
    )
}

onMounted(focusInput)
onBeforeUnmount(() => clearTimeout(timeout))
</script>

<template>
    <FormPanel
        title="Buscar producto"
        description="Escanea un código o escribe el nombre del producto."
        panel-class="relative h-fit"
        body-class="relative"
    >
        <form class="flex flex-col gap-2 sm:flex-row sm:items-start" @submit.prevent="scan">
            <div class="relative min-w-0 flex-1">
                <InputField
                    ref="searchInput"
                    v-model="form.code"
                    hide-label
                    field="audit_product_search"
                    validation-field="toolbar_search"
                    icon="barcode_scanner"
                    placeholder="Código, nombre o categoría"
                    :show-counter="false"
                    :error="form.errors.code"
                    autocomplete="off"
                />

                <div
                    v-if="results.length"
                    class="absolute z-30 mt-1.5 max-h-64 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-1.5 shadow-xl"
                >
                    <button
                        v-for="product in results"
                        :key="product.branch_product_id"
                        type="button"
                        class="block w-full rounded-lg px-3 py-2.5 text-left transition hover:bg-secondary"
                        @click="selectProduct(product)"
                    >
                        <p class="text-sm font-semibold text-text">
                            {{ product.name }}
                        </p>
                        <p class="mt-0.5 text-xs text-text opacity-60">
                            Código principal: {{ product.primary_code || product.barcode || 'Sin código' }}
                            <template v-if="product.related_codes?.length">
                                · Relacionados: {{ product.related_codes.join(', ') }}
                            </template>
                        </p>
                    </button>
                </div>
            </div>

            <AppButton
                type="submit"
                class="sm:min-h-12"
                :disabled="form.processing || !form.code"
            >
                Buscar
            </AppButton>
        </form>

        <p v-if="loading" class="mt-2 text-xs text-text opacity-60">
            Buscando productos...
        </p>
    </FormPanel>
</template>
