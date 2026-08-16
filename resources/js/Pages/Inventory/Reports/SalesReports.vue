<script setup>
import { computed, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { GlobalToolbar } from '@/Components/Toolbars'
import { GlobalTable } from '@/Components/Tables'
import { GlobalModal } from '@/Components/Modales'
import MetricCard from '@/Components/Cards/MetricCard.vue'
import SalesReplenishmentTable from '@/Components/Inventory/Reports/SalesReplenishmentTable.vue'
import { getSalesReportToolbarConfig } from '@/config/ToolbarConfigs/salesReportToolbarConfig'
import { salesProductsReportTableConfig } from '@/config/TableConfigs/salesProductsReportTableConfig'
import { salesRegisteredReportTableConfig } from '@/config/TableConfigs/salesRegisteredReportTableConfig'
import { useSalesReport } from '@/Composables/Ventas/useSalesReport'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: {
        type: Object,
        default: null,
    },
    reportScope: {
        type: String,
        default: 'branch',
    },
    branchesDB: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    activeTab: {
        type: String,
        default: 'products',
    },
    selectionCatalogs: {
        type: Object,
        default: () => ({
            branches: [],
            departments: [],
            categories: [],
            products: [],
        }),
    },
    productsSold: {
        type: Object,
        default: () => ({ data: [] }),
    },
    registeredSales: {
        type: Object,
        default: () => ({ data: [] }),
    },
    salesReplenishment: {
        type: Object,
        default: () => ({ branches: [], sections: {} }),
    },
})

const selectedSale = ref(null)

const {
    filtersState,
    activeTab,
    isSalesTab,
    isReplenishmentTab,
    isGlobalReport,
    branchOptions,
    showBranchFilter,
    tableRows,
    tablePagination,
    salesActions,
    backToReportsCenter,
    updateSearch,
    updateTab,
    updateFilter,
    handlePageChange,
    handleToolbarAction,
    downloadSale,
    updateSectionPeriod,
} = useSalesReport(props)

const toolbarConfig = computed(() => getSalesReportToolbarConfig({
    filters: filtersState,
    branches: branchOptions.value,
    departments: props.selectionCatalogs?.departments ?? [],
    categories: filteredCategories.value,
    products: filteredProducts.value,
    activeTab: activeTab.value,
    showBranchFilter: showBranchFilter.value,
    isGlobalReport: isGlobalReport.value,
}))

const selectedDepartmentIds = computed(() => new Set((filtersState.departmentIds || []).map(String)))
const selectedCategoryIds = computed(() => new Set((filtersState.categoryIds || []).map(String)))

const filteredCategories = computed(() => {
    const categories = props.selectionCatalogs?.categories ?? []

    if (!selectedDepartmentIds.value.size) {
        return categories
    }

    return categories.filter((category) =>
        selectedDepartmentIds.value.has(String(category.product_department_id))
    )
})

const filteredProducts = computed(() => {
    const products = props.selectionCatalogs?.products ?? []

    return products.filter((product) => {
        const matchesDepartment = !selectedDepartmentIds.value.size
            || selectedDepartmentIds.value.has(String(product.product_department_id))
        const matchesCategory = !selectedCategoryIds.value.size
            || selectedCategoryIds.value.has(String(product.category_id))

        return matchesDepartment && matchesCategory
    })
})

watch(filteredCategories, (categories) => {
    const validCategoryIds = new Set(categories.map((category) => String(category.id)))
    const nextCategoryIds = (filtersState.categoryIds || []).filter((id) => validCategoryIds.has(String(id)))

    if (nextCategoryIds.length !== (filtersState.categoryIds || []).length) {
        filtersState.categoryIds = nextCategoryIds
    }
})

watch(filteredProducts, (products) => {
    const validProductIds = new Set(products.map((product) => String(product.id)))
    const nextProductIds = (filtersState.productIds || []).filter((id) => validProductIds.has(String(id)))

    if (nextProductIds.length !== (filtersState.productIds || []).length) {
        filtersState.productIds = nextProductIds
    }
})

const tableConfig = computed(() => (
    isSalesTab.value
        ? {
            ...salesRegisteredReportTableConfig,
            actions: salesActions,
        }
        : salesProductsReportTableConfig
))

const detailColumns = [
    { key: 'product', label: 'Producto', format: 'text', mobileSecondary: true },
    { key: 'code', label: 'Código', format: 'text', mobileDisplay: true },
    { key: 'presentation', label: 'Presentación', format: 'text', mobileDisplay: true },
    { key: 'quantity_display', label: 'Cantidad visual', format: 'text', mobileDisplay: true },
    { key: 'base_quantity_display', label: 'Cantidad base descontada', format: 'text', mobileDisplay: true },
    { key: 'unit_price', label: 'Precio unitario', format: 'currency', mobileDisplay: true },
    { key: 'discount_amount', label: 'Descuento', format: 'currency', mobileDisplay: true },
    { key: 'subtotal', label: 'Subtotal', format: 'currency', mobileDisplay: true },
]

const saleCards = computed(() => {
    if (!selectedSale.value) return []

    return [
        { label: 'Folio', value: selectedSale.value.folio, tone: 'neutral' },
        { label: 'Fecha', value: selectedSale.value.date_display, tone: 'neutral' },
        { label: 'Vendedor', value: selectedSale.value.seller, tone: 'neutral' },
        { label: 'Total pagado', value: money(selectedSale.value.total), tone: 'dark' },
    ]
})

function money(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0))
}

function handleTableAction({ action, row }) {
    if (action === 'view') {
        selectedSale.value = row
        return
    }

    if (action === 'pdf') {
        downloadSale(row, 'pdf')
        return
    }

    if (action === 'excel') {
        downloadSale(row, 'excel')
    }
}

function closeSaleModal() {
    selectedSale.value = null
}
</script>

<template>
    <Head title="Reportes de ventas" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="backToReportsCenter"
                @update:search="updateSearch"
                @update:filter="updateFilter"
                @update:records-per-page="filtersState.perPage = $event"
                @update:active-tab="updateTab"
                @action="handleToolbarAction"
            />
        </template>

        <section class="space-y-5">
            <SalesReplenishmentTable
                v-if="isReplenishmentTab"
                :report="salesReplenishment"
                :active-tab="activeTab"
                @update-section-period="updateSectionPeriod"
            />

            <GlobalTable
                v-else
                :items="tableRows"
                v-bind="tableConfig"
                :pagination="tablePagination"
                @page-change="handlePageChange"
                @action="handleTableAction"
            />
        </section>

        <GlobalModal
            v-if="selectedSale"
            title="Detalle de venta"
            :subtitle="selectedSale.folio"
            mode="view"
            size="5xl"
            :columns="1"
            :show-save="false"
            close-button-text="Cerrar"
            @close="closeSaleModal"
        >
            <div class="space-y-5">
                <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        v-for="card in saleCards"
                        :key="card.label"
                        :label="card.label"
                        :value="card.value"
                        :tone="card.tone"
                        size="sm"
                    />
                </section>

                <GlobalTable
                    :items="selectedSale.details || []"
                    :columns="detailColumns"
                    :actions="[]"
                    row-key="id"
                    mobile-card-header-field="product"
                    no-data-message="Esta venta no tiene productos registrados."
                    :show-pagination="false"
                />
            </div>
        </GlobalModal>
    </PageLayout>
</template>
