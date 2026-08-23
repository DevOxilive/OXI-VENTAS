export function getAttendanceIncidentsToolbarConfig({
  filters = {},
  statuses = [],
  total = 0,
  actions = [],
}) {
  return {
    icon: 'event_note',
    title: 'Incidencias',
    subtitle: 'Registra excepciones y autoriza su efecto sobre la clasificacion de asistencia.',
    showSearch: true,
    searchPlaceholder: 'Buscar por empleado...',
    showRecordsPerPage: true,
    recordsPerPage: Number(filters.per_page ?? 25),
    showCounter: true,
    filteredRecords: Number(total),
    totalRecords: Number(total),
    compactFilters: true,
    filters: [
      { key: 'from', type: 'date', label: 'Desde', value: filters.from ?? '', max: filters.to || undefined },
      { key: 'to', type: 'date', label: 'Hasta', value: filters.to ?? '', min: filters.from || undefined },
      { key: 'status', label: 'Estado', placeholder: 'Todos los estados', value: filters.status ?? '', options: statuses },
    ],
    actions,
  }
}
