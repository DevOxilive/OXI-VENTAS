import { ref, computed } from 'vue'

export function useProductFilters(productsDB) {
    const search = ref('')
    const branchFilter = ref('Todas')
    const categoryFilter = ref('Todas')

    const filteredProducts = computed(() => {
        return productsDB.value.filter((product) => {
            const text = `${product.name ?? ''} ${product.barcode ?? ''} ${product.sku ?? ''}`
                .toLowerCase()

            const matchesSearch = text.includes(search.value.toLowerCase())

            const matchesBranch =
                branchFilter.value === 'Todas' ||
                product.branch_name === branchFilter.value

            const matchesCategory =
                categoryFilter.value === 'Todas' ||
                product.category_name === categoryFilter.value

            return matchesSearch && matchesBranch && matchesCategory
        })
    })

    return {
        search,
        branchFilter,
        categoryFilter,
        filteredProducts,
    }
}