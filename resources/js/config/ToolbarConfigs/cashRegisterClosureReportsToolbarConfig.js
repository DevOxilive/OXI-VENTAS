export function getCashRegisterClosureReportsToolbarConfig({
    form,
    users = [],
    selectorMode = false,
    actions = [],
} = {}) {
    return {
        icon: 'assessment',
        title: 'Reportes de corte de cajas',
        backButton: true,
        backLabel: 'Centro de reportes',
        search: form.folio,
        searchPlaceholder: 'Buscar por folio...',
        showSearch: !selectorMode,
        showRecordsPerPage: !selectorMode,
        recordsPerPage: form.per_page,
        recordsPerPageOptions: [10, 20, 50, 100, 200],
        showCounter: false,
        filters: selectorMode
            ? []
            : [
                {
                    key: 'user_id',
                    label: 'Usuario',
                    placeholder: 'Todos los usuarios',
                    value: form.user_id,
                    options: users,
                    optionLabel: 'name',
                    optionValue: 'id',
                },
                {
                    key: 'status',
                    label: 'Estado',
                    placeholder: 'Todos los estados',
                    value: form.status,
                    options: [
                        { label: 'Cuadrado', value: 'balanced' },
                        { label: 'Diferencia', value: 'difference' },
                    ],
                },
                {
                    key: 'date_from',
                    label: 'Desde',
                    type: 'date',
                    value: form.date_from,
                },
                {
                    key: 'date_to',
                    label: 'Hasta',
                    type: 'date',
                    value: form.date_to,
                },
            ],
        actions,
    }
}
