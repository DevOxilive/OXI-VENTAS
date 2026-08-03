export function getAttendanceRegistrationToolbarConfig({
    filters = {},
    types = [],
    branches = [],
    canViewAttendance = false,
    total = 0,
} = {}) {
    const visibleFilters = canViewAttendance
        ? [
            { key: 'branch', label: 'Sucursal', placeholder: 'Todas las sucursales', value: filters.branch ?? '', options: branches },
            { key: 'type', label: 'Tipo de registro', placeholder: 'Todos los tipos', value: filters.type ?? '', options: types },
            { key: 'from', type: 'date', label: 'Desde', value: filters.from ?? '', max: filters.to || undefined },
            { key: 'to', type: 'date', label: 'Hasta', value: filters.to ?? '', min: filters.from || undefined },
        ]
        : []

    return {
        icon: 'fact_check',
        title: 'Sistema de Asistencias',
        subtitle: 'Registra, consulta y filtra la asistencia del personal con validación del dispositivo.',
        showSearch: canViewAttendance,
        searchPlaceholder: 'Buscar por empleado, usuario o correo...',
        recordsPerPage: Number(filters.per_page ?? 30),
        recordsPerPageOptions: [10, 30, 50, 100],
        showRecordsPerPage: canViewAttendance,
        filteredRecords: Number(total),
        totalRecords: Number(total),
        showCounter: canViewAttendance,
        compactFilters: true,
        filters: visibleFilters,
    }
}
