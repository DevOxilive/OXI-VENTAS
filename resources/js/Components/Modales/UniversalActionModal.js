import Swal from "sweetalert2";

/* ===========================
   ALERTA DE CONFIRMACIÓN
=========================== */
export function UniversalActionModal({
    title = "Confirmar acción",
    message = "¿Deseas continuar con esta acción sobre",
    itemName = "",
    confirmText = "Confirmar",
    cancelText = "Cancelar",
    icon = "warning",
    confirmButtonColor = "#ef4444",
    cancelButtonColor = "#d1d5db",
} = {}) {
    return Swal.fire({
        title: title,
        text: `${message} ${itemName}?`,
        icon: icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: confirmButtonColor,
        cancelButtonColor: cancelButtonColor,
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: "rounded-2xl",
            confirmButton: "px-5 py-2 rounded-full",
            cancelButton: "px-5 py-2 rounded-full",
        },
    });
}

/* ===========================
   ALERTA DE ÉXITO
=========================== */
export function SuccessAlert({
    title = "Operación realizada",
    message = "La acción se ejecutó correctamente",
} = {}) {
    return Swal.fire({
        icon: "success",
        title: title,
        text: message,
        confirmButtonColor: "#22c55e",
        customClass: {
            popup: "rounded-2xl",
            confirmButton: "px-5 py-2 rounded-full",
        },
    });
}

/* ===========================
   ALERTA DE ERROR
=========================== */
export function ErrorAlert({
    title = "Ocurrió un error",
    message = "No fue posible completar la operación",
} = {}) {
    return Swal.fire({
        icon: "error",
        title: title,
        text: message,
        confirmButtonColor: "#ef4444",
        customClass: {
            popup: "rounded-2xl",
            confirmButton: "px-5 py-2 rounded-full",
        },
    });
}

/* ===========================
   ALERTA INFORMATIVA
=========================== */
export function InfoAlert({
    title = "Información",
    message = "Proceso ejecutado",
} = {}) {
    return Swal.fire({
        icon: "info",
        title: title,
        text: message,
        confirmButtonColor: "#3b82f6",
        customClass: {
            popup: "rounded-2xl",
            confirmButton: "px-5 py-2 rounded-full",
        },
    });
}

/* ===========================
   ALERTA WARNING SIMPLE
=========================== */
export function WarningAlert({
    title = "Advertencia",
    message = "Revisa esta acción antes de continuar",
} = {}) {
    return Swal.fire({
        icon: "warning",
        title: title,
        text: message,
        confirmButtonColor: "#f59e0b",
        customClass: {
            popup: "rounded-2xl",
            confirmButton: "px-5 py-2 rounded-full",
        },
    });
}

/* ===========================
   TOAST FLOTANTE
=========================== */
export function ToastAlert({
    icon = "success",
    title = "Operación realizada",
} = {}) {
    return Swal.fire({
        toast: true,
        position: "top-end",
        icon: icon,
        title: title,
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
    });
}
