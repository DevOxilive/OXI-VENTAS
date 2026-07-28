export function getAttendanceIncidentsToolbarConfig({
  filters = {},
  statuses = [],
}) {
  return {
    icon: 'event_note',
    title: 'Incidencias',
    subtitle: 'Registra excepciones y autoriza su efecto sobre la clasificacion de asistencia.',
    showSearch: true,
    searchPlaceholder: 'Buscar por empleado...',
    showRecordsPerPage: false,
    showCounter: false,
    compactFilters: true,
    filters: [
      { key: 'from', type: 'date', label: 'Desde', value: filters.from ?? '', max: filters.to || undefined },
      { key: 'to', type: 'date', label: 'Hasta', value: filters.to ?? '', min: filters.from || undefined },
      { key: 'status', label: 'Estado', placeholder: 'Todos los estados', value: filters.status ?? '', options: statuses },
    ],
  }
}
