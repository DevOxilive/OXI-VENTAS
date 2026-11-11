const STORAGE_PREFIX = "ventas_pending_sales";
const SESSION_KEY = "ventas_pending_sales_tab_id";

function createId() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/**
 * Las ventas en espera viven solamente durante la visita actual al POS.
 * El identificador de sessionStorage aisla cada pestaña; localStorage solo
 * se usa como respaldo mientras esa página continúa abierta.
 */
export function createPendingSalesStorage({ localStorage, sessionStorage }) {
  let fallbackTabId = null;

  function tabId() {
    let value = null;

    try {
      value = sessionStorage?.getItem(SESSION_KEY);
    } catch (error) {
      // Si el navegador bloquea el almacenamiento, la cola sigue funcionando
      // en memoria dentro del componente.
    }

    value ||= fallbackTabId;

    if (!value) {
      value = createId();
      fallbackTabId = value;
      try {
        sessionStorage?.setItem(SESSION_KEY, value);
      } catch (error) {
        // No interrumpir una venta normal por una preferencia local bloqueada.
      }
    }

    return value;
  }

  function key() {
    return `${STORAGE_PREFIX}:${tabId()}`;
  }

  function clearForPageLoad() {
    try {
      localStorage?.removeItem(key());
    } catch (error) {
      // Sin almacenamiento disponible no existe información temporal que limpiar.
    }
  }

  function save(sales) {
    try {
      localStorage?.setItem(key(), JSON.stringify(Array.isArray(sales) ? sales : []));
    } catch (error) {
      // El punto de venta continúa; las ventas en espera solo vivirán en memoria.
    }
  }

  function clear() {
    try {
      localStorage?.removeItem(key());
    } catch (error) {
      // Ver comentario en clearForPageLoad.
    }
  }

  return { clear, clearForPageLoad, key, save };
}
