export const physicalCountTableConfig = {
    columns: [
        {
            key: "name",
            label: "Nombre",
            format: "text",
            mobileLabel: "Nombre",
            mobileDisplay: true,
        },
        {
            key: "folio",
            label: "Folio",
            format: "text",
            mobileLabel: "Folio",
            mobileDisplay: true,
            fallback: "Sin folio",
        },
        {
            key: "status",
            label: "Estado",
            format: "badge",
            mobileLabel: "Estado",
            mobileDisplay: true,
            formatOptions: {
                statusMap: {
                    open: "Abierto",
                    closed: "Cerrado",
                    applied: "Aplicado",
                },
                colorMap: {
                    open: "green",
                    closed: "slate",
                    applied: "blue",
                },
            },
        },
    ],

    actions: [
        {
            id: "open",
            label: "Ingresar a auditoría",
            icon: "login",
            variant: "blue",
            permission: [
                "audits.physical-counts.view",
                "audits.physical-counts.count",
                "audits.physical-counts.update",
            ],
        },
        {
            id: "close",
            label: "Cerrar auditoría",
            icon: "lock",
            variant: "amber",
            permission: "audits.physical-counts.update",
            hidden: (item) => item.status !== "open",
        },
        {
            id: "reopen",
            label: "Reabrir auditoría",
            icon: "restart_alt",
            variant: "green",
            permission: "audits.physical-counts.update",
            hidden: (item) => item.status !== "closed",
        },
        {
            id: "apply",
            label: "Aplicar ajustes",
            icon: "check_circle",
            variant: "green",
            permission: "audits.physical-counts.update",
            hidden: (item) => item.status !== "closed",
        },
        {
            id: "delete",
            label: "Eliminar auditoría",
            icon: "delete",
            variant: "red",
            permission: "audits.physical-counts.delete",
            hidden: (item) => item.status !== "open",
        },
    ],

    mobileCardHeaderField: "name",
    noDataMessage: "Todavía no hay conteos físicos creados.",
}
