export function getPrinterLabelsToolbarConfig({
  processing = false,
  printing = false,
} = {}) {
  return {
    title: "Etiquetas",
    subtitle: "Edita etiquetas de producto con codigo de barras, categoria, nombre, precio y variables.",
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
        id: "print",
        label: printing ? "Imprimiendo..." : "Probar impresion",
        icon: "print",
        variant: "green",
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
