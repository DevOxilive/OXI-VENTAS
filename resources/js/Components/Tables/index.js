/**
 * GlobalTable Index
 * Punto de entrada para todos los componentes y utilidades de tabla
 */

// Componentes
export { default as GlobalToolbar } from "../Toolbars/GlobalToolbar.vue/index.js";
export { default as ToolbarDesktop } from "../Toolbars/ToolbarDesktop.vue/index.js";
export { default as ToolbarMobile } from "../Toolbars/ToolbarMobile.vue/index.js";
export {
    getToolbarActionClasses,
    toolbarActionVariants,
} from "../Toolbars/toolbarClasses.js";

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

export { useToolbarConfig } from "../Toolbars/useToolbarConfig.js";
export { useTableConfig } from "./useTableConfig";
