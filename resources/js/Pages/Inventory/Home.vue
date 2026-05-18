<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed } from 'vue'

import InventoryToolbar from '@/Components/Inventory/InventoryToolbar.vue'
import InventoryTable from '@/Components/Inventory/ProductTable.vue'
import InventoryMobileCards from '@/Components/Inventory/ProductMobileCards.vue'
import ProductModal from '@/Components/Inventory/ProductModal.vue'

import { useProductFilters } from '@/Composables/Inventory/useProductFilters'
import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

const { can } = usePermissions()

const props = defineProps({
    productsDB: Array,
    categoriesDB: Array,
    branchesDB: Array,
})

const productsDB = computed(() => props.productsDB ?? [])
const categoriesDB = computed(() => props.categoriesDB ?? [])
const branchesDB = computed(() => props.branchesDB ?? [])

const {
    search,
    branchFilter,
    categoryFilter,
    filteredProducts,
} = useProductFilters(productsDB)

const {
    showModal,
    modalMode,
    selectedProduct,
    openCreateModal,
    openEditModal,
    openViewModal,
    closeModal,
    deleteProduct,
} = useProductActions()
</script>

<template>
    <div class="bg-[#f6f3f7] min-h-screen rounded-2xl md:rounded-3xl p-4 md:p-6">

        <InventoryToolbar
            :total="filteredProducts.length"
            @create="openCreateModal"
        />

       <div class="mt-5">

    <InventoryTable
        :products="filteredProducts"
        @view="openViewModal"
        @edit="openEditModal"
        @delete="deleteProduct"
    />

    <InventoryMobileCards
        :products="filteredProducts"
        @view="openViewModal"
        @edit="openEditModal"
        @delete="deleteProduct"
    />

</div>

        <ProductModal
            v-if="
                showModal &&
                (
                    (modalMode === 'create' && can('inventario.crear')) ||
(modalMode === 'edit' && can('inventario.editar')) ||
(modalMode === 'view' && can('inventario.ver'))
                )
            "
            :mode="modalMode"
            :product="selectedProduct"
            :categoriesDB="categoriesDB"
            :branchesDB="branchesDB"
            @close="closeModal"
        />

    </div>
</template>