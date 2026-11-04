// resources/js/Config/Toolbars/inventoryToolbarConfig.js

function normalizeMultiValue(value) {
    return (Array.isArray(value) ? value : value ? [value] : [])
        .map((item) => String(item))
        .filter(Boolean);
}

export function getInventoryToolbarConfig({
    branch,
    productDepartments = [],
    productDepartmentFilter,
    categories = [],
    categoryFilter,
    stockFilter,
    showStock = true,
    canCreate = false,
}) {
    return {
        icon: "inventory_2",
        title: branch?.name ? `Inventario-Producto - ${branch.name}` : "Inventario-Producto",
        subtitle: "Consulta y movimientos por sucursal",
        searchPlaceholder: "Buscar producto, código, lote o barcode...",

        showRecordsPerPage: true,
        actions: [
            {
                id: "create",
                label: "Agregar",
                icon: "add",
                variant: "primary",
                hidden: () => !canCreate,
            },
        ],
        filters: [
            {
                key: "productDepartmentFilter",
                label: "Departamento",
                placeholder: "Todos los departamentos",
                type: "multiselect",
                value: normalizeMultiValue(productDepartmentFilter),
                options: productDepartments,
                optionLabel: "name",
                optionValue: "id",
            },
            {
                key: "categoryFilter",
                label: "Categorías",
                placeholder: "Todas las categorías",
                type: "multiselect",
                value: normalizeMultiValue(categoryFilter),
                options: categories,
                optionLabel: "name",
                optionValue: "id",
            },
            {
                key: "stockFilter",
                label: "Stock",
                placeholder: "Stock",
                value: stockFilter,
                options: [
                    { label: "Disponible", value: "Disponible" },
                    { label: "Stock bajo", value: "Stock bajo" },
                    { label: "Agotado", value: "Agotado" },
                ],
            },
        ].filter(filter => showStock || filter.key !== "stockFilter"),
    };
}
