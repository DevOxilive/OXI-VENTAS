<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { router, useForm } from '@inertiajs/vue3'

import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'

import InventoryStatsCards from '@/Components/Inventory/InventoryStatsCards.vue'
import AdjustStockModal from '@/Components/Inventory/AdjustStockModal.vue'
import InventoryToolbar from '@/Components/Inventory/InventoryToolbar.vue'
import InventoryTable from '@/Components/Inventory/InventoryTable.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branchProductsDB: {
        type: [Object, Array],
        default: () => ({ data: [] }),
    },
    productsDB: {
        type: Array,
        default: () => [],
    },
    branchesDB: {
        type: Array,
        default: () => [],
    },
    currentBranch: {
        type: Object,
        default: null,
    },
    inventoryStats: {
        type: Object,
        default: () => ({
            total_products: 0,
            total_stock: 0,
            inventory_value: 0,
            low_stock: 0,
            out_of_stock: 0,
            expiring_soon: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            per_page: 50,
        }),
    },
})

const products = computed(() => props.productsDB ?? [])
const branches = computed(() => props.branchesDB ?? [])
const currentBranch = computed(() => props.currentBranch ?? null)

const showModal = ref(false)
const showAdjustModal = ref(false)
const selectedProduct = ref(null)

const search = ref(props.filters?.search ?? '')
const categoryFilter = ref('')
const stockFilter = ref('')
const recordsToShow = ref(Number(props.filters?.per_page ?? 50))

const realtimeUpdates = ref({})

const rawBranchProducts = computed(() => {
    if (Array.isArray(props.branchProductsDB)) {
        return props.branchProductsDB
    }

    return props.branchProductsDB?.data ?? []
})

const paginationLinks = computed(() => {
    return Array.isArray(props.branchProductsDB?.links)
        ? props.branchProductsDB.links
        : []
})

const hasPagination = computed(() => {
    return !Array.isArray(props.branchProductsDB) && paginationLinks.value.length > 0
})

const visualProducts = computed(() => {
    return rawBranchProducts.value.map(item => {
        const realtime = realtimeUpdates.value[item.id] ?? {}

        const stock = Number(realtime.stock ?? item.stock ?? 0)
        const minStock = Number(item.min_stock ?? 0)
        const price = Number(item.price ?? item.product?.sale_price ?? 0)
        const tracksBatches = Boolean(item.tracks_batches ?? item.tracksBatches ?? false)

        let status = 'Disponible'

        if (stock <= 0) {
            status = 'Agotado'
        } else if (stock <= minStock) {
            status = 'Stock bajo'
        }

        return {
            id: item.id,
            name: item.product?.name ?? item.name ?? 'Producto sin nombre',
            code: item.product?.barcodes?.[0]?.code ?? item.barcode ?? `BP-${item.id}`,
            category: item.product?.category?.name ?? 'Sin categoría',
            branch: item.branch?.name ?? currentBranch.value?.name ?? 'Sucursal',
            status,
            stock,
            minStock,
            price,
            tracksBatches,
            expirationDate: item.next_expiration_date ?? item.expiration_date ?? null,
            active: item.active ?? true,
            activeBatchesCount: item.active_batches_count ?? 0,
            batches: item.batches ?? [],
            recentMovements: item.movements ?? [],
            raw: item,
        }
    })
})

const filteredProducts = computed(() => {
    return visualProducts.value.filter(product => {
        const matchesCategory =
            !categoryFilter.value ||
            product.category === categoryFilter.value

        const matchesStock =
            !stockFilter.value ||
            product.status === stockFilter.value

        return matchesCategory && matchesStock
    })
})

const stats = computed(() => ({
    total: props.inventoryStats?.total_products ?? 0,
    totalStock: props.inventoryStats?.total_stock ?? 0,
    inventoryValue: props.inventoryStats?.inventory_value ?? 0,
    lowStock: props.inventoryStats?.low_stock ?? 0,
    outOfStock: props.inventoryStats?.out_of_stock ?? 0,
    expiringSoon: props.inventoryStats?.expiring_soon ?? 0,
}))

let searchTimeout = null

watch(search, () => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        reloadInventory()
    }, 400)
})

watch(recordsToShow, () => {
    reloadInventory()
})

const reloadInventory = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            per_page: recordsToShow.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

const goToPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

onMounted(() => {
    window.Echo.channel('inventory')
        .listen('.stock.updated', (event) => {
            realtimeUpdates.value = {
                ...realtimeUpdates.value,
                [event.branch_product_id]: {
                    stock: Number(event.stock),
                    updated_at: event.updated_at,
                    batches: event.batches ?? [],
                    movements: event.recent_movements ?? [],
                },
            }
        })
})

onBeforeUnmount(() => {
    window.Echo.leave('inventory')
})

const form = useForm({
    branch_id: '',
    product_id: '',
    price: '',
    stock: '',
    min_stock: '',
})

const openModal = () => {
    form.reset()

    if (currentBranch.value?.id) {
        form.branch_id = currentBranch.value.id
    }

    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const submit = () => {
    form.post(route('inventario.branch-inventory.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    })
}

const exportExcel = () => {
    console.log('Exportar inventario', visualProducts.value)
}

const viewProduct = (product) => {
    console.log('Ver producto', product)
}

const editProduct = (product) => {
    console.log('Editar producto', product)
}

const adjustStock = (product) => {
    selectedProduct.value = product
    showAdjustModal.value = true
}

const closeAdjustModal = () => {
    showAdjustModal.value = false
    selectedProduct.value = null
}

const deleteProduct = (product) => {
    console.log('Eliminar producto', product)
}
</script>

<template>
    <div class="space-y-6">

        <div>
            <h1 class="text-3xl font-black text-slate-800">
                Inventario
                <span v-if="currentBranch" class="text-slate-500">
                    - {{ currentBranch.name }}
                </span>
            </h1>

            <p class="text-slate-500 mt-1">
                Gestión operativa del inventario por sucursal
            </p>
        </div>

        <InventoryStatsCards :stats="stats" />

        <InventoryToolbar :filtered-products="filteredProducts" :products-db="visualProducts"
            :records-to-show="recordsToShow" @create="openModal" @excel="exportExcel"
            @update:recordsToShow="recordsToShow = $event" />

        <InventoryTable :filtered-products="filteredProducts" :search="search" :category-filter="categoryFilter"
            :stock-filter="stockFilter" @update:search="search = $event"
            @update:categoryFilter="categoryFilter = $event" @update:stockFilter="stockFilter = $event"
            @view="viewProduct" @edit="editProduct" @adjust="adjustStock" @delete="deleteProduct" />

        <div v-if="hasPagination" class="flex flex-wrap items-center justify-center gap-2">
            <button v-for="link in paginationLinks" :key="link.label" type="button" :disabled="!link.url"
                class="px-3 py-2 rounded-lg text-sm border transition disabled:opacity-40 disabled:cursor-not-allowed"
                :class="link.active
                    ? 'bg-slate-900 text-white border-slate-900'
                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'" @click="goToPage(link.url)"
                v-html="link.label" />
        </div>

        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl w-full max-w-2xl overflow-hidden">
                <GeneralModalHeader title="Ajustar stock" subtitle="Registrar movimiento de inventario"
                    :total-errors="Object.keys(form.errors).length" mode="create" @close="closeModal" />

                <GeneralModalContent>
                    <form id="inventoryForm" @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <SelectField v-model="form.product_id" label="Producto" :options="products.map(product => ({
                            label: product.name,
                            value: product.id,
                        }))" />

                        <SelectField v-model="form.branch_id" label="Sucursal" :options="branches.map(branch => ({
                            label: branch.name,
                            value: branch.id,
                        }))" />

                        <InputField v-model="form.price" label="Precio" type="number" />

                        <InputField v-model="form.stock" label="Stock inicial" type="number" />

                        <div class="md:col-span-2">
                            <InputField v-model="form.min_stock" label="Stock mínimo" type="number" />
                        </div>
                    </form>
                </GeneralModalContent>

                <GeneralModalFooter :processing="form.processing" save-button-text="Guardar" mode="create"
                    @save="submit" @close="closeModal" />
            </div>
        </div>

        <AdjustStockModal v-if="showAdjustModal && selectedProduct" :product="selectedProduct"
            @close="closeAdjustModal" />
    </div>
</template>