export function getPhysicalCountToolbarConfig({ branch, canCreate, status = '' }) {
    return {
        icon: 'inventory',
        title: 'Conteo físico',
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
                    { label: 'Finalizado', value: 'finalized' },
                    { label: 'Aplicado', value: 'applied' },
                ],
            },
        ],
        actions: [
            ...(canCreate
                ? [
                      {
                          id: 'create',
                          label: 'Nueva auditoría',
                          icon: 'add',
                          variant: 'slate',
                      },
                  ]
                : []),
        ],
    }
}
