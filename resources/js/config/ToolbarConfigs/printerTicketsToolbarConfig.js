export function getPrinterTicketsToolbarConfig({
  processing = false,
  canSave = true,
  title = "Tickets",
  subtitle = "Ajusta el ticket con una plantilla visual y libre para impresion.",
} = {}) {
  return {
    icon: "receipt_long",
    title,
    subtitle,
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    tabs: [],
    actions: [
      ...(canSave
        ? [
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
          ]
        : []),
    ],
  }
}
