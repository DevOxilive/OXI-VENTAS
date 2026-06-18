export function getPhysicalCountToolbarConfig({ branch, canCreate }) {
    return {
        title: "Conteo físico",
        subtitle: `Sucursal: ${branch?.name ?? "No seleccionada"}`,
        searchPlaceholder: "Buscar conteo, folio o estado",
        showRecordsPerPage: false,

        filters: [
            {
                key: "statusFilter",
                label: "Estado",
                placeholder: "Estado",
                value: "",
                options: [
                    { label: "Abierto", value: "open" },
                    { label: "Cerrado", value: "closed" },
                    { label: "Aplicado", value: "applied" },
                ],
            },
        ],

        actions: canCreate
            ? [
                  {
                      id: "create",
                      label: "Nuevo conteo físico",
                      icon: "add",
                      variant: "slate",
                  },
              ]
            : [],
    };
}
