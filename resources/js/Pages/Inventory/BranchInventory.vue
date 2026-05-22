<script setup>

import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'

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
    branchProductsDB: Array,
    productsDB: Array,
    branchesDB: Array,
    currentBranch: Object,
})

const branchProducts = ref([])

watch(
    () => props.branchProductsDB,
    (products) => {
        branchProducts.value = products
            ? products.map(item => ({
                ...item,
                product: item.product ? { ...item.product } : null,
                branch: item.branch ? { ...item.branch } : null,
            }))
            : []
    },
    { immediate: true }
)

const products = computed(() => props.productsDB ?? [])
const branches = computed(() => props.branchesDB ?? [])
const currentBranch = computed(() => props.currentBranch ?? null)

const showModal = ref(false)
const showAdjustModal = ref(false)
const selectedProduct = ref(null)

const search = ref('')
const categoryFilter = ref('')
const stockFilter = ref('')
const recordsToShow = ref(10)

const visualProducts = computed(() => {
    return branchProducts.value.map(item => {
        const stock = Number(item.stock ?? 0)
        const minStock = Number(item.min_stock ?? 0)
        const price = Number(item.price ?? item.product?.sale_price ?? 0)

        let status = 'Disponible'

        if (stock <= 0) {
            status = 'Agotado'
        } else if (stock <= minStock) {
            status = 'Stock bajo'
        }

        return {
            id: item.id,
            name: item.product?.name ?? 'Producto sin nombre',
            code: item.product?.barcodes?.[0]?.code ?? `BP-${item.id}`,
            category: item.product?.category?.name ?? 'Sin categoría',
            branch: item.branch?.name ?? currentBranch.value?.name ?? 'Sucursal',
            status,
            stock,
            minStock,
            price,
            expirationDate: item.expiration_date ?? null,
            active: item.active ?? true,
            recentMovements: item.movements ?? [],
            raw: item,
            batches: item.batches ?? [],
            recentMovements: item.movements ?? [],
        }
    })
})

onMounted(() => {
    window.Echo.channel('inventory')
        .listen('.stock.updated', (event) => {
            const product = branchProducts.value.find(
                item => item.id === event.branch_product_id
            )

            if (!product) return

            product.stock = Number(event.stock)
            product.updated_at = event.updated_at
            product.batches = event.batches ?? []
            product.movements = event.recent_movements ?? []
        })
})

onBeforeUnmount(() => {
    window.Echo.leave('inventory')
})

const filteredProducts = computed(() => {
    return visualProducts.value
        .filter(product => {
            const searchText = search.value.toLowerCase()

            const matchesSearch =
                !search.value ||
                product.name.toLowerCase().includes(searchText) ||
                product.code.toLowerCase().includes(searchText)

            const matchesCategory =
                !categoryFilter.value ||
                product.category === categoryFilter.value

            const matchesStock =
                !stockFilter.value ||
                product.status === stockFilter.value

            return matchesSearch && matchesCategory && matchesStock
        })
        .slice(0, Number(recordsToShow.value))
})

const inventoryValue = computed(() => {
    return visualProducts.value.reduce((acc, item) => {
        return acc + (item.stock * item.price)
    }, 0)
})

const stats = computed(() => ({
    total: visualProducts.value.length,
    totalStock: visualProducts.value.reduce((acc, item) => acc + item.stock, 0),
    inventoryValue: inventoryValue.value,
    lowStock: visualProducts.value.filter(item => item.status === 'Stock bajo').length,
    outOfStock: visualProducts.value.filter(item => item.status === 'Agotado').length,
    expiringSoon: visualProducts.value.filter(item => item.expirationDate).length,
}))

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

        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl w-full max-w-2xl overflow-hidden">
                <GeneralModalHeader title="Ajustar stock" subtitle="Registrar movimiento de inventario"
                    :total-errors="Object.keys(form.errors).length" mode="create" @close="closeModal" />

                <GeneralModalContent>
                    <form id="inventoryForm" @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <SelectField v-model="form.product_id" label="Producto" :options="products.map(product => ({
                            label: product.name,
                            value: product.id
                        }))" />

                        <SelectField v-model="form.branch_id" label="Sucursal" :options="branches.map(branch => ({
                            label: branch.name,
                            value: branch.id
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