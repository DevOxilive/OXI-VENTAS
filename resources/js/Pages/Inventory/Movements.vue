<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { GlobalToolbar } from '@/Components/Toolbars'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { getInventoryMovementModalConfig } from '@/config/ModalConfigs/inventoryMovementModalConfig'
import { inventoryMovementsTableConfig } from '@/config/TableConfigs/inventoryMovementsTableConfig'

defineOptions({
    layout: AdminLayout,
})

const props = defineProps({
    movementsDB: Object,
    filters: { type: Object, default: () => ({}) },
    branchOptions: { type: Array, default: () => [] },
})

const movements = computed(() => props.movementsDB?.data ?? [])
const { handlePageChange } = useGlobalTablePagination()
const showModal = ref(false)
const productSearch = ref('')
const productOptions = ref([])
const selectedProduct = ref(null)
const searchingProducts = ref(false)
const localFilters = ref({
    search: props.filters?.search ?? '',
    branch_id: props.filters?.branch_id ?? '',
    type: props.filters?.type ?? '',
    reason: props.filters?.reason ?? '',
    per_page: Number(props.filters?.per_page ?? 50),
})
let filterTimer = null
let productSearchTimer = null
let productSearchController = null

const typeOptions = [
    { label: 'Entrada', value: 'IN' },
    { label: 'Salida', value: 'OUT' },
    { label: 'Ajuste', value: 'ADJUSTMENT' },
]

const reasonOptions = [
    { label: 'Compra', value: 'PURCHASE' },
    { label: 'Venta', value: 'SALE' },
    { label: 'Producto danado', value: 'DAMAGED' },
    { label: 'Producto caducado', value: 'EXPIRED' },
    { label: 'Otros...', value: 'OTHER' },
    { label: 'Diferencia de inventario', value: 'INVENTORY_DIFFERENCE' },
]

const toolbarFilters = computed(() => [
    {
        key: 'branch_id',
        label: 'Sucursal',
        placeholder: 'Todas las sucursales',
        value: localFilters.value.branch_id,
        options: props.branchOptions,
    },
    {
        key: 'type',
        label: 'Tipo',
        placeholder: 'Todos los tipos',
        value: localFilters.value.type,
        options: typeOptions,
    },
    {
        key: 'reason',
        label: 'Motivo',
        placeholder: 'Todos los motivos',
        value: localFilters.value.reason,
        options: reasonOptions,
    },
])

const form = useForm({
    branch_product_id: '',
    type: 'OUT',
    reason: '',
    quantity: '',
    notes: '',
})

const totalErrors = computed(() => Object.keys(form.errors || {}).length)

const modalConfig = computed(() => getInventoryMovementModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
}))

watch(localFilters, () => {
    clearTimeout(filterTimer)
    filterTimer = setTimeout(() => {
        router.get(route('inventory.movements'), localFilters.value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
}, { deep: true })

watch(productSearch, (value) => {
    clearTimeout(productSearchTimer)
    productSearchController?.abort()

    const term = String(value ?? '').trim()
    if (selectedProduct.value && selectedProduct.value.name !== term) {
        selectedProduct.value = null
        form.branch_product_id = ''
    }

    if (term.length < 2 || selectedProduct.value?.name === term) {
        productOptions.value = []
        searchingProducts.value = false
        return
    }

    productSearchTimer = setTimeout(() => searchProducts(term), 250)
})

onBeforeUnmount(() => {
    clearTimeout(filterTimer)
    clearTimeout(productSearchTimer)
    productSearchController?.abort()
})

function openModal() {
    form.reset()
    form.type = 'OUT'
    productSearch.value = ''
    productOptions.value = []
    selectedProduct.value = null
    showModal.value = true
}

function closeModal() {
    if (form.processing) return

    showModal.value = false
}

function submit() {
    form.post(route('inventory.stock-movements.store'), getModalRequestOptions({
        mode: 'create',
        entityName: modalConfig.value.alerts.entityName,
        close: closeModal,
        successTitle: modalConfig.value.alerts.create.successTitle,
        errorTitle: modalConfig.value.alerts.create.errorTitle,
        errorMessage: modalConfig.value.alerts.create.errorMessage,
    }))
}

function updateToolbarFilter({ key, value }) {
    localFilters.value[key] = value
}

async function searchProducts(term) {
    const controller = new AbortController()
    productSearchController = controller
    searchingProducts.value = true

    try {
        const { data } = await window.axios.get(
            route('inventory.stock-movements.products.search'),
            {
                params: { search: term },
                signal: controller.signal,
            },
        )
        if (productSearchController === controller) {
            productOptions.value = data?.products ?? []
        }
    } catch (error) {
        if (error?.code !== 'ERR_CANCELED' && productSearchController === controller) {
            productOptions.value = []
        }
    } finally {
        if (productSearchController === controller) {
            searchingProducts.value = false
        }
    }
}

function selectProduct(product) {
    selectedProduct.value = product
    form.branch_product_id = product.id
    productSearch.value = product.name
    productOptions.value = []
}
</script>

<template>
    <div class="min-h-screen rounded-3xl bg-background p-6">
        <GlobalToolbar
            icon="swap_horiz"
            title="Movimientos de inventario"
            subtitle="Entradas, salidas, robos, daños y caducidades."
            class="mb-6"
            :search="localFilters.search"
            search-placeholder="Buscar producto, código o sucursal..."
            :filters="toolbarFilters"
            :records-per-page="localFilters.per_page"
            :records-per-page-options="[10, 25, 50, 100]"
            :filtered-records="Number(movementsDB?.total ?? 0)"
            :total-records="Number(movementsDB?.total ?? 0)"
            :actions="[{ id: 'create', label: 'Nuevo movimiento', icon: 'add', variant: 'primary' }]"
            @update:search="localFilters.search = $event"
            @update:filter="updateToolbarFilter"
            @update:records-per-page="localFilters.per_page = $event"
            @action="openModal"
        />

        <GlobalTable
            :items="movements"
            v-bind="inventoryMovementsTableConfig"
            :pagination="movementsDB"
            @page-change="handlePageChange"
        />

        <GlobalModal
            v-if="showModal"
            v-bind="modalConfig"
            @save="submit"
            @close="closeModal"
        >
            <form class="grid grid-cols-1 md:grid-cols-2 gap-5" @submit.prevent="submit">
                <div class="md:col-span-2">
                    <div class="relative">
                        <InputField
                            v-model="productSearch"
                            label="Producto / Sucursal"
                            field="movement-product-search"
                            validation-field="toolbar_search"
                            placeholder="Escribe nombre, código o sucursal"
                            autocomplete="off"
                            :show-counter="false"
                            :error="form.errors.branch_product_id"
                        />

                        <span
                            v-if="searchingProducts"
                            class="pointer-events-none absolute right-3 top-10 text-xs text-text opacity-60"
                        >
                            Buscando...
                        </span>

                        <div
                            v-if="productOptions.length"
                            class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-1 shadow-xl"
                        >
                            <button
                                v-for="product in productOptions"
                                :key="product.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-secondary"
                                @click="selectProduct(product)"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-text">{{ product.name }}</span>
                                    <span class="block truncate text-xs text-text opacity-60">
                                        {{ product.branch }} · {{ product.code || 'Sin código' }}
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs text-text opacity-60">Stock {{ product.stock }}</span>
                            </button>
                        </div>

                        <p v-if="selectedProduct" class="mt-2 text-xs text-text opacity-70">
                            Seleccionado en {{ selectedProduct.branch }} · Stock {{ selectedProduct.stock }}
                        </p>
                    </div>
                </div>

                <SelectField
                    v-model="form.type"
                    label="Tipo"
                    field="type"
                    :options="typeOptions"
                    :error="form.errors.type"
                />

                <SelectField
                    v-model="form.reason"
                    label="Motivo"
                    field="reason"
                    placeholder="Selecciona un motivo"
                    :options="reasonOptions"
                    :error="form.errors.reason"
                />

                <div class="md:col-span-2">
                    <InputField
                        v-model="form.quantity"
                        label="Cantidad"
                        field="quantity"
                        type="number"
                        :error="form.errors.quantity"
                    />
                </div>

                <div class="md:col-span-2">
                    <TextareaField
                        v-model="form.notes"
                        label="Observaciones"
                        field="notes"
                        :rows="4"
                        :error="form.errors.notes"
                    />
                </div>
            </form>
        </GlobalModal>
    </div>
</template>
