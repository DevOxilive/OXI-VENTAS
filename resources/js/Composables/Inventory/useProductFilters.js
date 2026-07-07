import { ref, computed, watch } from "vue";

export function useProductFilters(productsDB) {
    const search = ref("");
    const categoryFilter = ref("Todas");

    const recordsToShow = ref(50);
    const currentPage = ref(1);

    const productList = computed(() => {
        const value = productsDB.value ?? productsDB;

        if (Array.isArray(value)) {
            return value;
        }

        return value?.data ?? [];
    });

    const filteredProducts = computed(() => {
        return productList.value.filter((product) => {
            const searchValue = search.value.toLowerCase().trim();

           const searchableText = `
    ${product.name ?? ""}
    ${product.barcode ?? ""}
    ${(product.barcodes ?? []).join(" ")}
    ${product.presentation ?? ""}
    ${product.category_name ?? ""}
`.toLowerCase();

            const matchesSearch =
                searchValue === "" || searchableText.includes(searchValue);

            const matchesCategory =
                categoryFilter.value === "Todas" ||
                String(product.category_id) === String(categoryFilter.value);

            return matchesSearch && matchesCategory;
        });
    });

   const paginatedProducts = computed(() => {
    const start = (currentPage.value - 1) * recordsToShow.value;
    const end = start + recordsToShow.value;

    return filteredProducts.value.slice(start, end);
});

    const totalPages = computed(() => {
        const value = productsDB.value ?? productsDB;

        if (!Array.isArray(value) && value?.last_page) {
            return value.last_page;
        }

        return Math.max(
            1,
            Math.ceil(filteredProducts.value.length / recordsToShow.value),
        );
    });

    watch([search, categoryFilter, recordsToShow], () => {
        currentPage.value = 1;
    });

    return {
        search,
        categoryFilter,
        filteredProducts,
        paginatedProducts,
        recordsToShow,
        currentPage,
        totalPages,
    };
}
