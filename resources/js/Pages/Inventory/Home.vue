<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ProductModal from '@/Components/Inventory/ProductModal.vue'
import ProductFilters from '@/Components/Inventory/ProductFilters.vue'
import ProductTable from '@/Components/Inventory/ProductTable.vue'
import ProductMobileCards from '@/Components/Inventory/ProductMobileCards.vue'

import { useProductFilters } from '@/Composables/Inventory/useProductFilters'
import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { usePermissions } from '@/Composables/usePermissions'

import { computed } from 'vue'

defineOptions({ layout: AdminLayout })

const { can } = usePermissions()

const props = defineProps({
    productsDB: {
        type: [Array, Object],
        default: () => ({ data: [] }),
    },
    categoriesDB: {
        type: Array,
        default: () => [],
    },
    subcategoriesDB: {
        type: Array,
        default: () => [],
    },
    branchesDB: {
        type: Array,
        default: () => [],
    },
    branch: {
        type: Object,
        default: () => ({}),
    },
})

const branch = computed(() => props.branch ?? {})

// IMPORTANTE:
// No usar props.productsDB.data aquí.
// El composable ya se encarga de sacar .data si viene paginado.
const productsDB = computed(() => props.productsDB ?? { data: [] })

const categoriesDB = computed(() => props.categoriesDB ?? [])
const subcategoriesDB = computed(() => props.subcategoriesDB ?? [])
const branchesDB = computed(() => props.branchesDB ?? [])

const {
    search,
    categoryFilter,
    subcategoryFilter,
    filteredProducts,
    paginatedProducts,
    recordsToShow,
    currentPage,
    totalPages,
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
            <ProductFilters v-model:records-to-show="recordsToShow" :search="search" :categoryFilter="categoryFilter"
                :subcategoryFilter="subcategoryFilter" :subcategoriesDB="subcategoriesDB" :categoriesDB="categoriesDB"
                @update:search="search = $event"
                @update:categoryFilter="categoryFilter = $event; subcategoryFilter = 'Todas'"
                @update:subcategoryFilter="subcategoryFilter = $event" @create="openCreateModal" />
        </div>

        <!-- ESPACIO PARA QUE LA TABLA NO SE META DEBAJO -->
        <div class="pt-[120px] px-8">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <ProductTable :products="paginatedProducts" @view="openViewModal" @edit="openEditModal"
                    @delete="deleteProduct" />
            </div>

            <ProductMobileCards :products="paginatedProducts" @view="openViewModal" @edit="openEditModal"
                @delete="deleteProduct" />

            <div class="flex items-center justify-between mt-6 px-2">
                <button @click="currentPage--" :disabled="currentPage === 1"
                    class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm disabled:opacity-40">
                    Anterior
                </button>

                <div class="text-sm text-slate-500">
                    Página {{ currentPage }} de {{ totalPages }}
                </div>

                <button @click="currentPage++" :disabled="currentPage === totalPages"
                    class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm disabled:opacity-40">
                    Siguiente
                </button>
            </div>
        </div>

        <ProductModal v-if="
            showModal &&
            (
                (modalMode === 'create' && can('inventario.crear')) ||
                (modalMode === 'edit' && can('inventario.editar')) ||
                (modalMode === 'view' && can('inventario.ver'))
            )
        " :mode="modalMode" :product="selectedProduct" :categoriesDB="categoriesDB" :subcategoriesDB="subcategoriesDB"
            :branchesDB="branchesDB" :branch="branch" @close="closeModal" />
    </div>
</template>