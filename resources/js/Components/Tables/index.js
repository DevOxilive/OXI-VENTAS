/**
 * GlobalTable Index
 * Punto de entrada para todos los componentes y utilidades de tabla
 */

// Componentes
export { default as GlobalToolbar } from "./GlobalToolbar.vue";
export { default as ToolbarDesktop } from "./ToolbarDesktop.vue";
export { default as ToolbarMobile } from "./ToolbarMobile.vue";
export { useToolbarConfig } from "./useToolbarConfig";
export {
    getToolbarActionClasses,
    toolbarActionVariants,
} from "./toolbarClasses";

export {
    formatCellValue,
    getNestedValue,
    truncateText,
    formatters,
} from "./tableFormatters";

export {
    getStatusClasses,
    getAvailableVariants,
    predefinedStatusMaps,
} from "./tableStatusClasses";

export { useTableConfig } from "./useTableConfig";
