export function getPrinterLabelsToolbarConfig({
  processing = false,
  printing = false,
  canSave = true,
  canPrint = true,
} = {}) {
  return {
    icon: "barcode",
    title: "Etiquetas",
    subtitle: "Productos, papel y diseño en un solo lugar.",
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    tabs: [],
    actions: [
      {id: "visual-editor", label: "Editor visual", icon: "draw", variant: "slate"},
      ...(canSave ? [
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
      ] : []),
      ...(canPrint ? [
        {
          id: "print",
          label: printing ? "Imprimiendo..." : "Imprimir etiquetas",
          icon: "print",
          variant: "green",
        },
      ] : []),
    ],
  }
}
