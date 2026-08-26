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
    statusFilter,
    expirationStatusFilter,
    inactiveCandidateFilter,
}) {
    return {
        icon: "inventory_2",
        title: branch?.name ? `Inventario - ${branch.name}` : "Inventario",
        subtitle: "Consulta y movimientos por sucursal",
        searchPlaceholder: "Buscar producto, código, lote o barcode...",

        showRecordsPerPage: true,
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
            {
                key: "statusFilter",
                label: "Estado",
                placeholder: "Estado",
                value: statusFilter,
                options: [
                    { label: "Activo", value: "active" },
                    { label: "Inactivo", value: "inactive" },
                    { label: "Temporada", value: "seasonal" },
                ],
            },
            {
                key: "expirationStatusFilter",
                label: "Caducidad",
                placeholder: "Caducidad",
                value: expirationStatusFilter,
                options: [
                    { label: "Vencidos", value: "expired" },
                    { label: "Por vencer", value: "near_expiration" },
                    { label: "Vigentes", value: "valid" },
                    { label: "Sin caducidad", value: "without_expiration" },
                ],
            },
            {
                key: "inactiveCandidateFilter",
                label: "Sin rotación",
                placeholder: "Sin rotación",
                value: inactiveCandidateFilter,
                options: [{ label: "Productos sin rotación", value: "1" }],
            },
        ],
    };
}
