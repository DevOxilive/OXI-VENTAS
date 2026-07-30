export const physicalCountReportRoundsTableConfig = {
    columns: [
        { key: 'branch_name', label: 'Sucursal', format: 'text' },
        { key: 'audit_name', label: 'Auditoría', format: 'text' },
        { key: 'folio', label: 'Folio', format: 'text' },
        { key: 'round_number', label: 'Ronda', format: 'number' },
        { key: 'type_label', label: 'Tipo', format: 'text' },
        { key: 'scope_label', label: 'Alcance', format: 'text' },
        { key: 'opened_by', label: 'Abierta por', format: 'text' },
        { key: 'started_at', label: 'Inicio', format: 'datetime' },
        { key: 'closed_at', label: 'Cierre', format: 'datetime', fallback: 'Abierta' },
    ],
    noDataMessage: 'No hay rondas para los filtros seleccionados.',
}
