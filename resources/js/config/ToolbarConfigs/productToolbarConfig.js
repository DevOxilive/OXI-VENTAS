// resources/js/config/TableConfigs/productToolbarConfig.js

export function getProductToolbarConfig({
    branch,
    categories,
    categoryFilter,
    canCreate,
}) {
    return {
        title: branch?.name ? `Productos - ${branch.name}` : "Productos",

        subtitle: branch?.name
            ? `Administración de productos de la sucursal ${branch.name}`
            : "Administración de productos",

        searchPlaceholder: "Buscar producto",

        recordsPerPageOptions: [10, 20, 50, 100, 200],

        filters: [
            {
                key: "categoryFilter",
                label: "Categoría",
                placeholder: "Categoría",
                value: categoryFilter,
                emptyValue: "",
                options: categories,
                optionLabel: "name",
                optionValue: "id",
            },
        ],

        actions: [
            {
                id: "create",
                label: "Agregar",
                icon: "add",
                variant: "primary",
                hidden: () => !canCreate,
            },
        ],
    };
}
