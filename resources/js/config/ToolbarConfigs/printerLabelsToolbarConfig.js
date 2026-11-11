export function getPrinterLabelsToolbarConfig({
  processing = false,
  printing = false,
  canSave = true,
  canPrint = true,
  showVisualEditor = true,
  title = "Etiquetas",
  subtitle = "Productos, papel y diseño en un solo lugar.",
  printLabel = "Imprimir etiquetas",
} = {}) {
  return {
    icon: "barcode",
    title,
    subtitle,
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    tabs: [],
    actions: [
      ...(showVisualEditor ? [{id: "visual-editor", label: "Editor visual", icon: "draw", variant: "slate"}] : []),
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
          label: printing ? "Imprimiendo..." : printLabel,
          icon: "print",
          variant: "green",
        },
      ] : []),
    ],
  }
}
