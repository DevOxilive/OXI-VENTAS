import { ref, computed, watch } from 'vue'

export function useProductFilters(productsDB) {

    const search = ref('')
    const categoryFilter = ref('Todas')
    const subcategoryFilter = ref('Todas')

    // 🔥 PAGINACIÓN
    const recordsToShow = ref(10)
    const currentPage = ref(1)

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

    // 🔥 PRODUCTOS PAGINADOS
    const paginatedProducts = computed(() => {

        const start =
            (currentPage.value - 1) *
            recordsToShow.value

        const end =
            start +
            recordsToShow.value

        return filteredProducts.value.slice(start, end)

    })

    // 🔥 TOTAL DE PÁGINAS
    const totalPages = computed(() => {

        return Math.max(
            1,
            Math.ceil(
                filteredProducts.value.length /
                recordsToShow.value
            )
        )

    })

    // 🔥 RESET PAGE
    watch([
        search,
        categoryFilter,
        subcategoryFilter,
        recordsToShow
    ], () => {

        currentPage.value = 1

    })

    return {
        search,
        categoryFilter,
        subcategoryFilter,

        filteredProducts,

        // 🔥 NUEVO
        paginatedProducts,
        recordsToShow,
        currentPage,
        totalPages,
    }
}