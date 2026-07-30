export function getAttendanceRegistrationToolbarConfig({
    filters = {},
    types = [],
    branches = [],
    departments = [],
    canManage = false,
    total = 0,
} = {}) {
    return {
        icon: 'fact_check',
        title: 'Sistema de Asistencias',
        subtitle: 'Registra, consulta y filtra la asistencia del personal con validación del dispositivo.',
        showSearch: true,
        searchPlaceholder: 'Buscar por empleado, usuario o correo...',
        recordsPerPage: Number(filters.per_page ?? 30),
        recordsPerPageOptions: [10, 30, 50, 100],
        showRecordsPerPage: true,
        filteredRecords: Number(total),
        totalRecords: Number(total),
        showCounter: true,
        compactFilters: true,
        filters: [
            { key: 'from', type: 'date', label: 'Desde', value: filters.from ?? '', max: filters.to || undefined },
            { key: 'to', type: 'date', label: 'Hasta', value: filters.to ?? '', min: filters.from || undefined },
            { key: 'type', label: 'Tipo de registro', placeholder: 'Todos los tipos', value: filters.type ?? '', options: types },
            { key: 'branch', label: 'Sucursal', placeholder: 'Todas las sucursales', value: filters.branch ?? '', options: branches, visible: canManage },
            { key: 'department', label: 'Departamento', placeholder: 'Todos los departamentos', value: filters.department ?? '', options: departments, visible: canManage },
        ],
    }
}
