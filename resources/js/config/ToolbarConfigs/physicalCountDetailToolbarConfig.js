export function getPhysicalCountDetailToolbarConfig({ physicalCount }) {
    const canExport = ["closed", "applied"].includes(physicalCount.status);

    return {
        title: physicalCount.name || "Detalle de auditoría",
        subtitle: `Sucursal: ${physicalCount.branch?.name || "Sin sucursal"} · Estado: ${physicalCount.status}`,
        actions: [
            {
                id: "back",
                label: "Volver",
                icon: "arrow_back",
                variant: "slate",
            },
            {
                id: "exportPdf",
                label: "Exportar PDF",
                icon: "picture_as_pdf",
                variant: "red",
                hidden: () => !canExport,
            },
            {
                id: "exportExcel",
                label: "Exportar Excel",
                icon: "table_view",
                variant: "green",
                hidden: () => !canExport,
            },
        ],
    };
}
