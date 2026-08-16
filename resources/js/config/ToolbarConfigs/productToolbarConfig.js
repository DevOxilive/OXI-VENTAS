// resources/js/config/TableConfigs/productToolbarConfig.js

export function getProductToolbarConfig({
    branch,
    productDepartments,
    productDepartmentFilter,
    categories,
    categoryFilter,
    canCreate,
}) {
    return {
        icon: "inventory_2",
        title: branch?.name ? `Productos - ${branch.name}` : "Productos",

        subtitle: branch?.name
            ? `Administracion de productos de la sucursal ${branch.name}`
            : "Administracion de productos",

        searchPlaceholder: "Buscar producto, codigo o codigo alterno...",

        compactFilters: true,
        showRecordsPerPage: true,

        filters: [
            {
                key: "productDepartmentFilter",
                label: "Departamento",
                placeholder: "Departamento",
                value: productDepartmentFilter,
                emptyValue: "",
                options: productDepartments,
                optionLabel: "name",
                optionValue: "id",
            },
            {
                key: "categoryFilter",
                label: "Categoria",
                placeholder: "Categoria",
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
