export function getSalesToolbarConfig({
  selectorMode = false,
  selectorDescription = "",
  expirationAlertCount = 0,
  backButton = false,
} = {}) {
  if (selectorMode) {
    return {
      icon: "point_of_sale",
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
    icon: "point_of_sale",
    title: "Ventas",
    subtitle: "",
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    backButton,
    backLabel: "Sucursales",
    filters: [],
    actions: [
      {
        id: "open-cash-drawer",
        label: "Abrir caja",
        icon: "point_of_sale",
        variant: "danger",
      },
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
