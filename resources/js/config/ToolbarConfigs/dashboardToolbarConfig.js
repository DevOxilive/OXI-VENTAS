export const DASHBOARD_PERIOD_OPTIONS = [
    { value: 'day', label: 'Dia' },
    { value: 'week', label: 'Semana' },
    { value: 'month', label: 'Mes' },
    { value: 'year', label: 'Anio' },
]

export function getDashboardToolbarConfig({
    branchName = '',
    rangeLabel = '',
    branches = [],
    selectedBranchId = '',
    period = 'month',
    chartPeriod = '',
    dateFrom = '',
    dateTo = '',
    maxDate = '',
    isLayoutEditing = false,
} = {}) {
    return {
        title: 'Dashboard',
        subtitle: `${branchName || 'Todas las sucursales'} - ${rangeLabel || 'Resumen ejecutivo'}`,
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        compactFilters: true,
        filters: [
            {
                key: 'period',
                label: 'Resumen',
                placeholder: 'Periodo del resumen',
                value: period,
                options: DASHBOARD_PERIOD_OPTIONS,
            },
            {
                key: 'chart_period',
                label: 'Graficas',
                placeholder: 'Periodo por grafica',
                value: chartPeriod,
                options: DASHBOARD_PERIOD_OPTIONS,
            },
            {
                key: 'branch_id',
                label: 'Sucursal',
                placeholder: 'Todas las sucursales',
                value: selectedBranchId,
                options: branches,
                optionLabel: 'name',
                optionValue: 'id',
            },
            {
                key: 'date_from',
                label: 'Desde',
                type: 'date',
                value: dateFrom,
                max: maxDate,
            },
            {
                key: 'date_to',
                label: 'Hasta',
                type: 'date',
                value: dateTo,
                max: maxDate,
            },
        ],
        actions: [
            {
                id: 'toggle-layout-edit',
                label: isLayoutEditing ? 'Bloquear layout' : 'Editar layout',
                icon: isLayoutEditing ? 'lock' : 'drag_indicator',
                variant: isLayoutEditing ? 'amber' : 'slate',
            },
            {
                id: 'reset-layout',
                label: 'Restaurar layout',
                icon: 'dashboard_customize',
                variant: 'slate',
            },
            {
                id: 'reset-chart-periods',
                label: 'Reset periodos',
                icon: 'restart_alt',
                variant: 'slate',
            },
        ],
        tabs: [],
    }
}
