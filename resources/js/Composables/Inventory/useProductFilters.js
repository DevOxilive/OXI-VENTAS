import { ref, computed } from 'vue'

export function useProductFilters(productsDB) {

    const search = ref('')
    const categoryFilter = ref('Todas')
    const subcategoryFilter = ref('Todas')

    const filteredProducts = computed(() => {

        return productsDB.value.filter((product) => {

            /*
            |--------------------------------------------------------------------------
            | SEARCH
            |--------------------------------------------------------------------------
            */

            const searchValue = search.value.toLowerCase().trim()

            const searchableText = `
                ${product.name ?? ''}
                ${product.barcode ?? ''}
                ${product.presentation ?? ''}
                ${product.category_name ?? ''}
                ${product.subcategory_name ?? ''}
            `.toLowerCase()

            const matchesSearch =
                searchValue === '' ||
                searchableText.includes(searchValue)

            /*
            |--------------------------------------------------------------------------
            | CATEGORY
            |--------------------------------------------------------------------------
            */

            const matchesCategory =
                categoryFilter.value === 'Todas' ||
                String(product.category_id) === String(categoryFilter.value)

            /*
            |--------------------------------------------------------------------------
            | SUBCATEGORY
            |--------------------------------------------------------------------------
            */

            const matchesSubcategory =
                subcategoryFilter.value === 'Todas' ||
                String(product.subcategory_id) === String(subcategoryFilter.value)

            /*
            |--------------------------------------------------------------------------
            | FINAL
            |--------------------------------------------------------------------------
            */

            return (
                matchesSearch &&
                matchesCategory &&
                matchesSubcategory
            )

        })

    })

    return {
        search,
        categoryFilter,
        subcategoryFilter,
        filteredProducts,
    }
}