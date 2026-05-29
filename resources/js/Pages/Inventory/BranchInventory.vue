<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'

import InventoryStatsCards from '@/Components/Inventory/BranchProducts/InventoryStatsCards.vue'
import InventoryToolbar from '@/Components/Inventory/BranchProducts/InventoryToolbar.vue'
import InventoryTable from '@/Components/Inventory/BranchProducts/InventoryTable.vue'
import InventoryMobileCards from '@/Components/Inventory/BranchProducts/InventoryMobileCards.vue'
import InventoryCreateModal from '@/Components/Inventory/BranchProducts/InventoryCreateModal.vue'
import InventoryAlertsModal from '@/Components/Inventory/BranchProducts/InventoryAlertsModal.vue'
import AdjustStockModal from '@/Components/Inventory/BranchProducts/AdjustStockModal.vue'

import { useBranchInventory } from '@/Composables/Inventory/useBranchInventory'

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
    inventoryAlerts: {
        type: Object,
        default: () => ({
            expired_batches: 0,
            near_expiration_batches: 0,
            low_stock_products: 0,
            expired_batches_list: [],
            near_expiration_batches_list: [],
        }),
    },
})

const {
    products,
    branches,
    currentBranch,

    showCreateModal,
    showAdjustModal,
    liveSelectedProduct,

    search,
    categoryFilter,
    stockFilter,
    recordsToShow,

    paginationLinks,
    hasPagination,
    visualProducts,
    filteredProducts,
    stats,
    alerts,

    selectedAlertType,
    selectedAlertTitle,
    selectedAlertBatches,
    showAlertModal,

    openCreateModal,
    closeCreateModal,
    openAlertModal,
    closeAlertModal,

    adjustStock,
    closeAdjustModal,

    goToPage,

    exportExcel,
    viewProduct,
    editProduct,
    deleteProduct,
} = useBranchInventory(props)

const form = useForm({
    branch_id: '',
    product_id: '',
    price: '',
    stock: '',
    min_stock: '',
})

const openCreateInventoryModal = () => {
    form.reset()
    form.clearErrors()

    if (currentBranch.value?.id) {
        form.branch_id = currentBranch.value.id
    }

    openCreateModal()
}

const closeCreateInventoryModal = () => {
    closeCreateModal()
}

const submit = () => {
    form.post(route('inventario.branch-inventory.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateInventoryModal(),
    })
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-3xl font-black text-slate-800">
                Inventario
                <span v-if="currentBranch" class="text-slate-500">
                    - {{ currentBranch.name }}
                </span>
            </h1>

            <p class="text-slate-500 mt-1">
                Gestión operativa del inventario por sucursal
            </p>
        </header>

        <InventoryStatsCards :stats="stats" :alerts="alerts" @open-alert="openAlertModal" />

        <InventoryToolbar :filtered-products="filteredProducts" :products-db="visualProducts"
            :records-to-show="recordsToShow" @create="openCreateInventoryModal" @excel="exportExcel"
            @update:recordsToShow="recordsToShow = $event" />

        <InventoryTable :filtered-products="filteredProducts" :search="search" :category-filter="categoryFilter"
            :stock-filter="stockFilter" @update:search="search = $event"
            @update:categoryFilter="categoryFilter = $event" @update:stockFilter="stockFilter = $event"
            @view="viewProduct" @edit="editProduct" @adjust="adjustStock" @delete="deleteProduct" />

        <InventoryMobileCards :filtered-products="filteredProducts" @view="viewProduct" @edit="editProduct"
            @adjust="adjustStock" @delete="deleteProduct" />

        <nav v-if="hasPagination" class="flex flex-wrap items-center justify-center gap-2">
            <button v-for="link in paginationLinks" :key="link.label" type="button" :disabled="!link.url"
                class="px-3 py-2 rounded-lg text-sm border transition disabled:opacity-40 disabled:cursor-not-allowed"
                :class="link.active
                    ? 'bg-slate-900 text-white border-slate-900'
                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'" @click="goToPage(link.url)"
                v-html="link.label" />
        </nav>

        <InventoryCreateModal v-if="showCreateModal" :form="form" :products="products" :branches="branches"
            @submit="submit" @close="closeCreateInventoryModal" />

        <AdjustStockModal v-if="showAdjustModal && liveSelectedProduct" :product="liveSelectedProduct"
            @close="closeAdjustModal" />

        <InventoryAlertsModal v-if="showAlertModal" :title="selectedAlertTitle" :type="selectedAlertType"
            :batches="selectedAlertBatches" @close="closeAlertModal" />
    </div>
</template>
