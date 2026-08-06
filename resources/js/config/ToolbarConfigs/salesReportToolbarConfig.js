export const SALES_REPORT_TABS = [
    {
        key: 'products',
        label: 'Productos vendidos',
        icon: 'inventory_2',
    },
    {
        key: 'sales',
        label: 'Ventas registradas',
        icon: 'receipt_long',
    },
]

export function getSalesReportToolbarConfig({
    filters,
    branches = [],
    activeTab = 'products',
    showBranchFilter = false,
    isGlobalReport = false,
} = {}) {
    const isSalesTab = activeTab === 'sales'

    return {
        icon: 'monitoring',
        title: 'Reportes de ventas',
        subtitle: isSalesTab
            ? 'Consulta ventas registradas y revisa el detalle de sus productos.'
            : 'Analiza rotación comercial por producto, sucursal y periodo.',
        backButton: !isGlobalReport,
        backLabel: 'Vista global',
        search: filters?.search ?? '',
        searchPlaceholder: isSalesTab
            ? 'Buscar folio, producto o vendedor...'
            : 'Buscar producto, código o categoría...',
        showSearch: true,
        showRecordsPerPage: true,
        recordsPerPage: Number(filters?.perPage ?? 25),
        recordsPerPageOptions: [10, 25, 50, 100, 200],
        showCounter: false,
        tabs: SALES_REPORT_TABS,
        activeTab,
        filters: [
            {
                key: 'branchId',
                label: 'Sucursal',
                placeholder: 'Selecciona una sucursal',
                value: filters?.branchId ?? '',
                options: branches,
                optionLabel: 'name',
                optionValue: 'id',
                visible: showBranchFilter,
                hidePlaceholderOption: true,
            },
            {
                key: 'folio',
                label: 'Folio',
                type: 'text',
                placeholder: 'Folio de venta',
                value: filters?.folio ?? '',
                visible: isSalesTab,
                maxLength: 80,
            },
            {
                key: 'dateFrom',
                label: 'Desde',
                type: 'date',
                value: filters?.dateFrom ?? '',
            },
            {
                key: 'dateTo',
                label: 'Hasta',
                type: 'date',
                value: filters?.dateTo ?? '',
            },
        ],
        actions: [
            {
                id: 'clear',
                label: 'Limpiar',
                icon: 'restart_alt',
                variant: 'slate',
            },
            {
                id: 'excel',
                label: 'Excel',
                icon: 'table_view',
                variant: 'green',
                permission: 'reports.sales.export.excel',
            },
            {
                id: 'pdf',
                label: 'PDF',
                icon: 'picture_as_pdf',
                variant: 'red',
                permission: 'reports.sales.export.pdf',
                hidden: !isSalesTab,
            },
        ],
    }
}
