export function getPhysicalCountToolbarConfig({ branch, canCreate, status = '' }) {
    return {
        title: 'Conteo fisico',
        subtitle: `Sucursal: ${branch?.name ?? 'No seleccionada'}`,
        searchPlaceholder: 'Buscar conteo, folio, usuario o estado...',
        showRecordsPerPage: true,
        filters: [
            {
                key: 'statusFilter',
                label: 'Estado',
                placeholder: 'Estado',
                value: status,
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
