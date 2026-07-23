import { modalPresets } from "./modalPresets";

export function getOrganizationStructureModalConfig({
    entity = "department",
    mode = "create",
    processing = false,
    totalErrors = 0,
} = {}) {
    const isDepartment = entity === "department";
    const entityLabel = isDepartment ? "departamento" : "puesto";

    return {
        mode,
        modeTitles: {
            create: `Registrar ${entityLabel}`,
            edit: `Actualizar ${entityLabel}`,
            view: `Detalle del ${entityLabel}`,
        },
        subtitle: isDepartment
            ? (mode === "view"
                ? "Nombre y puestos relacionados"
                : mode === "edit"
                    ? "Modifica únicamente el nombre"
                    : "Nombre del nuevo departamento")
            : "Información general y departamento responsable",
        processing,
        totalErrors,
        saveButtonText: mode === "edit"
            ? `Actualizar ${entityLabel}`
            : `Guardar ${entityLabel}`,
        closeButtonText: "Cancelar",
        ...modalPresets.standard,
    };
}
