export const SALES_REPORT_TABS = [
    {
        key: 'sales',
        label: 'Ventas registradas',
        icon: 'receipt_long',
    },
]

export const REPLENISHMENT_REPORT_TABS = [
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
    reportMode = 'sales',
} = {}) {
    const isSalesReport = reportMode === 'sales'

    return {
        icon: isSalesReport ? 'monitoring' : 'playlist_add_check',
        title: isSalesReport ? 'Reporte de ventas' : 'Planeacion de compra',
        subtitle: isSalesReport
            ? 'Consulta ventas registradas y revisa el detalle de sus productos.'
            : 'Genera criterios operativos para pedido, transferencias y ordenes de compra generales.',
        backButton: !isGlobalReport,
        backLabel: 'Vista global',
        search: filters?.search ?? '',
        searchPlaceholder: isSalesReport
            ? 'Buscar folio, producto o vendedor...'
            : 'Buscar producto, codigo, departamento o categoria...',
        showSearch: isSalesReport,
        showRecordsPerPage: isSalesReport,
        recordsPerPage: Number(filters?.perPage ?? 25),
        showCounter: false,
        tabs: isSalesReport ? [] : REPLENISHMENT_REPORT_TABS,
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
                visible: showBranchFilter,
            },
            {
                key: 'folio',
                label: 'Folio',
                type: 'text',
                placeholder: 'Folio de venta',
                value: filters?.folio ?? '',
                visible: isSalesReport,
                maxLength: 80,
            },
            {
                key: 'status',
                label: 'Estado',
                type: 'select',
                placeholder: 'Todos los estados',
                value: filters?.status ?? '',
                visible: isSalesReport,
                options: [
                    { label: 'Completadas', value: 'completed' },
                    { label: 'Canceladas', value: 'cancelled' },
                    { label: 'Abonos aplicados', value: 'payment' },
                ],
            },
            {
                key: 'paymentMethod',
                label: 'Forma de pago',
                type: 'select',
                placeholder: 'Todas las formas',
                value: filters?.paymentMethod ?? '',
                visible: isSalesReport,
                options: [
                    { label: 'Efectivo', value: 'cash' },
                    { label: 'Tarjeta', value: 'card' },
                    { label: 'Credito empleado', value: 'credit' },
                    { label: 'Abonos', value: 'payment' },
                ],
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
                visible: !isSalesReport,
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
                visible: !isSalesReport,
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
                visible: !isSalesReport,
            },
        ],
        actions: [
            {
                id: 'excel',
                label: 'Excel',
                icon: 'table_view',
                variant: 'amber',
                permission: 'reports.sales.export.excel',
            },
            {
                id: 'pdf',
                label: 'PDF',
                icon: 'picture_as_pdf',
                variant: 'red',
                permission: 'reports.sales.export.pdf',
                hidden: !isSalesReport,
            },
        ],
    }
}
