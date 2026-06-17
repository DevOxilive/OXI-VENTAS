/**
 * GlobalTable Index
 * Punto de entrada para todos los componentes y utilidades de tabla
 */

// Componentes
export { default as GlobalTable } from "./GlobalTable.vue";
export { default as TableDesktop } from "./TableDesktop.vue";
export { default as TableMobile } from "./TableMobile.vue";

export {
    formatCellValue,
    getNestedValue,
    truncateText,
    formatters,
} from "./tableFormatters.js";

export {
    getStatusClasses,
    getAvailableVariants,
    predefinedStatusMaps,
} from "./tableStatusClasses.js";

export { useTableConfig } from "./useTableConfig.js";
