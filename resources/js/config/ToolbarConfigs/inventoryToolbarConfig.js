// resources/js/Config/Toolbars/inventoryToolbarConfig.js

export function getInventoryToolbarConfig({
    branch,
    categories,
    categoryFilter,
    stockFilter,
    statusFilter,
    expirationStatusFilter,
    inactiveCandidateFilter,
}) {
    return {
        title: branch?.name ? `Inventario - ${branch.name}` : "Inventario",
        subtitle: "Consulta y movimientos por sucursal",
        searchPlaceholder: "Buscar producto, código, lote o barcode...",

        filters: [
            {
                key: "categoryFilter",
                label: "Categoría",
                placeholder: "Categoría",
                value: categoryFilter,
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
