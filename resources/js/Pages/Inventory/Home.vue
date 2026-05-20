<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed } from 'vue'
import ProductFilters from '@/Components/Inventory/ProductFilters.vue'
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
    subcategoriesDB: Array,
    branchesDB: Array,
})

const productsDB = computed(() => props.productsDB ?? [])
const categoriesDB = computed(() => props.categoriesDB ?? [])
const subcategoriesDB = computed(() => props.subcategoriesDB ?? [])
const branchesDB = computed(() => props.branchesDB ?? [])

const {
    search,
    categoryFilter,
    subcategoryFilter,
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
    
    <div class="relative mt-5">

        <!-- FILTROS FIJOS -->
         
     <div class="fixed top-[80px] left-[288px] right-0 h-[165px] z-[40] bg-[#f1f5f9] px-8 pt-6 shadow-md">
        
    <ProductFilters
        :search="search"
        :categoryFilter="categoryFilter"
        :subcategoryFilter="subcategoryFilter"
        :subcategoriesDB="subcategoriesDB"
        :categoriesDB="categoriesDB"
        @update:search="search = $event"
        @update:categoryFilter="categoryFilter = $event; subcategoryFilter = 'Todas'"
        @update:subcategoryFilter="subcategoryFilter = $event"
        @create="openCreateModal"
    />
</div>

        <!-- ESPACIO PARA QUE LA TABLA NO SE META DEBAJO -->
       <div class="pt-[120px]">
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
            :subcategoriesDB="subcategoriesDB"
            :branchesDB="branchesDB"
            @close="closeModal"
        />

    </div>
</template>