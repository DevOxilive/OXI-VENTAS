// resources/js/config/TableConfigs/productToolbarConfig.js

function normalizeMultiValue(value) {
    return (Array.isArray(value) ? value : value ? [value] : [])
        .map((item) => String(item))
        .filter(Boolean);
}

export function getProductToolbarConfig({
    branch,
    productDepartments = [],
    productDepartmentFilter,
    categories = [],
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
                label: "Departamentos",
                placeholder: "Todos los departamentos",
                type: "multiselect",
                value: normalizeMultiValue(productDepartmentFilter),
                options: productDepartments,
                optionLabel: "name",
                optionValue: "id",
            },
            {
                key: "categoryFilter",
                label: "Categorias",
                placeholder: "Todas las categorias",
                type: "multiselect",
                value: normalizeMultiValue(categoryFilter),
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
