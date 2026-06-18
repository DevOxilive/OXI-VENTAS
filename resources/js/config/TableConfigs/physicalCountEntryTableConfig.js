export function getPhysicalCountEntryTableConfig({ status }) {
    const isOpen = status === "open";

    return {
        mobileCardHeaderField: "product_name",
        noDataMessage: "No hay productos contados.",
        columns: [
            {
                key: "product_name",
                label: "Producto",
            },
            {
                key: "barcode",
                label: "Código",
                fallback: "Sin código",
                mobileSecondary: true,
            },
            {
                key: "counted_quantity",
                label: "Cantidad contada",
                format: "number",
            },
            {
                key: "damaged_quantity",
                label: "Dañados",
                format: "number",
            },
            {
                key: "expired_quantity",
                label: "Caducados",
                format: "number",
            },
            {
                key: "notes",
                label: "Notas",
                fallback: "Sin notas",
                mobileDisplay: false,
            },
        ],
        actions: [
            {
                id: "view",
                label: "Ver",
                icon: "visibility",
                variant: "blue",
            },
            {
                id: "edit",
                label: "Editar",
                icon: "edit",
                variant: "amber",
                hidden: () => !isOpen,
            },
            {
                id: "delete",
                label: "Eliminar",
                icon: "delete",
                variant: "red",
                hidden: () => !isOpen,
            },
        ],
    };
}
