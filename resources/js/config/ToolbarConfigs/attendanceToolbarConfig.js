export function getAttendanceFiltersToolbarConfig({
    filters = {},
    types = [],
    branches = [],
    departments = [],
    employees = [],
    canManage = false,
}) {
    return {
        title: 'Filtros de asistencia',
        subtitle: 'La tabla muestra el día actual de forma inicial. Ajusta el periodo o busca a una persona para consultar otros registros.',
        showSearch: true,
        searchPlaceholder: 'Buscar por empleado, usuario o correo...',
        showRecordsPerPage: false,
        showCounter: false,
        compactFilters: true,
        filters: [
            { key: 'from', type: 'date', label: 'Desde', value: filters.from ?? '', max: filters.to || undefined },
            { key: 'to', type: 'date', label: 'Hasta', value: filters.to ?? '', min: filters.from || undefined },
            { key: 'type', label: 'Tipo de registro', placeholder: 'Todos los tipos', value: filters.type ?? '', options: types },
            { key: 'branch', label: 'Sucursal', placeholder: 'Todas las sucursales', value: filters.branch ?? '', options: branches, visible: canManage },
            { key: 'department', label: 'Departamento', placeholder: 'Todos los departamentos', value: filters.department ?? '', options: departments, visible: canManage },
            { key: 'employee', label: 'Empleado', placeholder: 'Todo el personal', value: filters.employee ?? '', options: employees, visible: canManage },
        ],
    }
}
