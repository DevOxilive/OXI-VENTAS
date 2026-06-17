export function getReportsToolbarConfig({ tabs = [], activeTab = "" } = {}) {
    return {
        title: "Reportes de inventario",
        subtitle:
            "Consulta movimientos, caducidades, rotación y productos que requieren atención.",
        tabs,
        activeTab,
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
    };
}
