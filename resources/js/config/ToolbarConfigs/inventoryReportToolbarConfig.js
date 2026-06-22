export const INVENTORY_REPORT_TYPES = [
    {
        id: 'dashboard',
        name: 'Inventario',
        dataKey: 'inventory',
        description: 'Lotes y existencias disponibles.',
    },
    {
        id: 'expirations',
        name: 'Caducidades',
        dataKey: 'expirations',
        description: 'Lotes caducados o proximos a vencer.',
    },
    {
        id: 'attention',
        name: 'Stock critico',
        dataKey: 'attentionProducts',
        description: 'Productos agotados, bajos o que necesitan revision.',
    },
    {
        id: 'movements',
        name: 'Movimientos',
        dataKey: 'movements',
        description: 'Entradas, salidas y ajustes del inventario.',
    },
    {
        id: 'rotation',
        name: 'Rotacion',
        dataKey: 'rotation',
        description: 'Productos con alta, baja o nula rotacion.',
    },
]

export const INVENTORY_REPORT_STATUS_FILTERS = [
    {
        id: '',
        label: 'Todos',
        report: 'dashboard',
        icon: 'inventory_2',
        tone: 'slate',
        description: 'Sin limitar por estado.',
    },
    {
        id: 'expired',
        label: 'Caducados',
        report: 'expirations',
        icon: 'warning',
        tone: 'red',
        description: 'Lotes vencidos.',
    },
    {
        id: 'near_expiration',
        label: 'Por vencer',
        report: 'expirations',
        icon: 'calendar_clock',
        tone: 'amber',
        description: 'Vencen pronto.',
    },
    {
        id: 'low_stock',
        label: 'Stock bajo',
        report: 'attention',
        icon: 'inventory',
        tone: 'blue',
        description: 'Debajo del minimo.',
    },
    {
        id: 'out_of_stock',
        label: 'Agotados',
        report: 'attention',
        icon: 'remove_shopping_cart',
        tone: 'rose',
        description: 'Sin existencia.',
    },
]

export function getInventoryReportType(reportId) {
    return INVENTORY_REPORT_TYPES.find((report) => report.id === reportId) ?? INVENTORY_REPORT_TYPES[0]
}

export function getInventoryReportStatus(statusId) {
    return INVENTORY_REPORT_STATUS_FILTERS.find((status) => status.id === statusId) ?? INVENTORY_REPORT_STATUS_FILTERS[0]
}

export function getInventoryReportToolbarConfig({
    branch,
    filters,
    categories = [],
    products = [],
} = {}) {
    return {
        title: 'Reportes de inventario',
        subtitle: branch?.name
            ? `Sucursal ${branch.name}`
            : 'Consulta el estado operativo del inventario.',
        backButton: true,
        backLabel: 'Centro de reportes',
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        filters: [
            {
                key: 'status',
                label: 'Estado del inventario',
                placeholder: 'Todos los estados',
                value: filters?.status ?? '',
                options: INVENTORY_REPORT_STATUS_FILTERS.filter((status) => status.id),
                optionLabel: 'label',
                optionValue: 'id',
            },
            {
                key: 'categoryId',
                label: 'Categoria',
                placeholder: 'Todas las categorias',
                value: filters?.categoryId ?? '',
                options: categories,
                optionLabel: 'name',
                optionValue: 'id',
            },
            {
                key: 'productId',
                label: 'Producto',
                placeholder: 'Todos los productos',
                value: filters?.productId ?? '',
                options: products,
                optionLabel: 'label',
                optionValue: 'id',
            },
            {
                key: 'dateFrom',
                label: 'Caducidad inicio',
                type: 'date',
                value: filters?.dateFrom ?? '',
            },
            {
                key: 'dateTo',
                label: 'Caducidad fin',
                type: 'date',
                value: filters?.dateTo ?? '',
            },
            {
                key: 'search',
                label: 'Busqueda',
                type: 'text',
                placeholder: 'Producto, lote o categoria',
                value: filters?.search ?? '',
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
            },
            {
                id: 'pdf',
                label: 'PDF',
                icon: 'picture_as_pdf',
                variant: 'red',
            },
        ],
        tabs: [],
    }
}
