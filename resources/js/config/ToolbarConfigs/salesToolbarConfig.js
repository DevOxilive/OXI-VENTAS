export function getSalesToolbarConfig({
  selectorMode = false,
  branchName = "",
  selectorDescription = "",
  branches = [],
  selectedBranchId = "",
  paymentMethods = [],
  selectedPaymentMethodId = "",
  cashBoxOptions = [],
  selectedCashBoxNumber = "",
  printerOptions = [],
  selectedPrinterName = "",
  printerBridgeReady = false,
  expirationAlertCount = 0,
  backButton = false,
} = {}) {
  if (selectorMode) {
    return {
      title: "Ventas",
      subtitle:
        selectorDescription ||
        "Selecciona la sucursal donde quieres abrir el punto de venta.",
      showSearch: false,
      showRecordsPerPage: false,
      showCounter: false,
      filters: [],
      actions: [],
      tabs: [],
    }
  }

  return {
    title: "Ventas",
    subtitle: `${branchName || "Sin sucursal"} · Escanea, agrega productos y cobra una venta real.`,
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    backButton,
    backLabel: "Sucursales",
    compactFilters: true,
    filters: [
      {
        key: "branch_id",
        label: "Sucursal",
        placeholder: "Selecciona una sucursal",
        value: selectedBranchId,
        options: branches,
        optionLabel: "name",
        optionValue: "id",
        visible: () => branches.length > 1,
      },
      {
        key: "payment_method_id",
        label: "Forma de pago",
        placeholder: "Selecciona pago",
        value: selectedPaymentMethodId,
        options: paymentMethods,
        optionLabel: "name",
        optionValue: "id",
      },
      {
        key: "cash_box",
        label: "Caja",
        placeholder: "Selecciona caja",
        value: selectedCashBoxNumber,
        options: cashBoxOptions,
      },
      {
        key: "ticket_printer",
        label: "Impresora",
        placeholder: printerBridgeReady
          ? "Selecciona impresora"
          : "QZ Tray no conectado",
        value: selectedPrinterName,
        options: printerOptions,
        disabled: !printerOptions.length,
      },
    ],
    actions: [
      {
        id: "toggle-expiration-alerts",
        label: "Alertas",
        icon: "notifications",
        variant: expirationAlertCount ? "danger" : "slate",
        badge: expirationAlertCount ? String(expirationAlertCount) : "",
      },
    ],
    tabs: [],
  }
}
