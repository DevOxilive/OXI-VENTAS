export function getPrinterTicketsToolbarConfig({ processing = false } = {}) {
  return {
    title: "Tickets",
    subtitle: "Ajusta el ticket con una plantilla visual y libre para impresion.",
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    tabs: [],
    actions: [
      {
        id: "reset",
        label: "Restablecer",
        icon: "restart_alt",
        variant: "slate",
      },
      {
        id: "save",
        label: processing ? "Guardando..." : "Guardar",
        icon: "save",
        variant: "primary",
      },
    ],
  }
}
