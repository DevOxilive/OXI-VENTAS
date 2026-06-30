export function getPhysicalCountToolbarConfig({ branch, canCreate }) {
    return {
        title: 'Conteo fisico',
        subtitle: `Sucursal: ${branch?.name ?? 'No seleccionada'}`,
        searchPlaceholder: 'Buscar conteo, folio, usuario o estado...',
        showRecordsPerPage: false,
        filters: [
            {
                key: 'statusFilter',
                label: 'Estado',
                placeholder: 'Estado',
                value: '',
                options: [
                    { label: 'Abierto', value: 'open' },
                    { label: 'Cerrado', value: 'closed' },
                    { label: 'Aplicado', value: 'applied' },
                ],
            },
        ],
        actions: [
            ...(canCreate
                ? [
                      {
                          id: 'create',
                          label: 'Nueva auditoria',
                          icon: 'add',
                          variant: 'slate',
                      },
                  ]
                : []),
        ],
    }
}
