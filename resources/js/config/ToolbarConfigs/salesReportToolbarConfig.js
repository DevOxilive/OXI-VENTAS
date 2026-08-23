export const SALES_REPORT_TABS = [
    {
        key: 'pedido',
        label: 'Pedido',
        icon: 'playlist_add',
    },
    {
        key: 'transferencias',
        label: 'Transferencias',
        icon: 'sync_alt',
    },
    {
        key: 'sin-movimiento',
        label: 'Sin movimiento',
        icon: 'inventory_2',
    },
    {
        key: 'pedido-tiendas',
        label: 'Pedido a tiendas',
        icon: 'local_shipping',
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
    departments = [],
    categories = [],
    products = [],
    activeTab = 'pedido',
    showBranchFilter = false,
    isGlobalReport = false,
} = {}) {
    const isSalesTab = activeTab === 'sales'

    return {
        icon: 'monitoring',
        title: 'Reportes de ventas',
        subtitle: isSalesTab
            ? 'Consulta ventas registradas y revisa el detalle de sus productos.'
            : 'Analiza rotacion comercial, stock por sucursal y sugerencias de reposicion.',
        backButton: !isGlobalReport,
        backLabel: 'Vista global',
        search: filters?.search ?? '',
        searchPlaceholder: isSalesTab
            ? 'Buscar folio, producto o vendedor...'
            : 'Buscar producto, codigo, departamento o categoria...',
        showSearch: isSalesTab,
        showRecordsPerPage: isSalesTab,
        recordsPerPage: Number(filters?.perPage ?? 25),
        showCounter: false,
        tabs: SALES_REPORT_TABS,
        activeTab,
        filters: [
            {
                key: 'dateFrom',
                label: 'Desde',
                type: 'date',
                value: filters?.dateFrom ?? '',
                visible: true,
            },
            {
                key: 'dateTo',
                label: 'Hasta',
                type: 'date',
                value: filters?.dateTo ?? '',
                visible: true,
            },
            {
                key: 'branchIds',
                label: 'Sucursales',
                placeholder: 'Todas las sucursales',
                type: 'multiselect',
                value: filters?.branchIds ?? [],
                options: branches,
                optionLabel: 'name',
                optionValue: 'id',
                visible: !isSalesTab && showBranchFilter,
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
                key: 'departmentIds',
                label: 'Departamentos',
                placeholder: 'Todos los departamentos',
                type: 'multiselect',
                value: filters?.departmentIds ?? [],
                options: departments,
                optionLabel: 'name',
                optionValue: 'id',
                visible: !isSalesTab,
            },
            {
                key: 'categoryIds',
                label: 'Categorias',
                placeholder: 'Todas las categorias',
                type: 'multiselect',
                value: filters?.categoryIds ?? [],
                options: categories,
                optionLabel: 'name',
                optionValue: 'id',
                visible: !isSalesTab,
            },
            {
                key: 'productIds',
                label: 'Productos',
                placeholder: 'Todos los productos',
                type: 'multiselect',
                value: filters?.productIds ?? [],
                options: products,
                optionLabel: 'name',
                optionValue: 'id',
                visible: !isSalesTab,
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
