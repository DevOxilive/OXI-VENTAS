import Swal from "sweetalert2";

const ALERT_Z_INDEX = 2147483647;

function raiseAlertContainer() {
    document.querySelectorAll(".swal2-container").forEach((container) => {
        container.style.zIndex = String(ALERT_Z_INDEX);
    });
}

function mergeAlertClasses(customClass = {}) {
    return {
        ...customClass,
        popup: ["oxi-swal-popup rounded-2xl", customClass.popup].filter(Boolean).join(" "),
        confirmButton: ["oxi-swal-confirm px-5 py-2 rounded-full", customClass.confirmButton].filter(Boolean).join(" "),
        cancelButton: ["oxi-swal-cancel px-5 py-2 rounded-full", customClass.cancelButton].filter(Boolean).join(" "),
    };
}

function withAlertDefaults(options = {}) {
    const { didOpen, customClass = {}, ...rest } = options;

    return {
        ...rest,
        target: "body",
        heightAuto: false,
        didOpen: (...args) => {
            raiseAlertContainer();
            didOpen?.(...args);
        },
        customClass: mergeAlertClasses(customClass),
    };
}

export function UniversalActionModal({
    title = "Confirmar accion",
    message = "Deseas continuar con esta accion sobre",
    itemName = "",
    html = null,
    confirmText = "Confirmar",
    cancelText = "Cancelar",
    icon = "warning",
    confirmButtonColor = "#ef4444",
    cancelButtonColor = "#d1d5db",
    customClass = {},
    ...options
} = {}) {
    return Swal.fire(withAlertDefaults({
        title,
        ...(html
            ? { html }
            : { text: itemName ? `${message} ${itemName}?` : message }),
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor,
        cancelButtonColor,
        reverseButtons: true,
        focusCancel: true,
        ...options,
        customClass,
    }));
}

export function SuccessAlert({
    title = "Operacion realizada",
    message = "La accion se ejecuto correctamente",
} = {}) {
    return Swal.fire(withAlertDefaults({
        toast: true,
        position: "top-start",
        icon: "success",
        title,
        text: message,
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
    }));
}

export function ErrorAlert({
    title = "Ocurrio un error",
    message = "No fue posible completar la operacion",
} = {}) {
    return Swal.fire(withAlertDefaults({
        icon: "error",
        title,
        html: message,
        confirmButtonColor: "#ef4444",
    }));
}

export function WarningAlert({
    title = "Advertencia",
    message = "Revisa esta accion antes de continuar",
} = {}) {
    return Swal.fire(withAlertDefaults({
        icon: "warning",
        title,
        text: message,
        confirmButtonColor: "#f59e0b",
    }));
}

export function BlockingWarningAlert({
    title = "Advertencia",
    message = "Revisa esta accion antes de continuar",
    confirmText = "OK",
    confirmButtonColor = "#e60012",
} = {}) {
    return Swal.fire(withAlertDefaults({
        icon: "warning",
        title,
        html: message,
        confirmButtonText: confirmText,
        confirmButtonColor,
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: true,
        showCloseButton: false,
    }));
}

export function ToastAlert({
    icon = "success",
    title = "Operacion realizada",
} = {}) {
    return Swal.fire(withAlertDefaults({
        toast: true,
        position: "top-start",
        icon,
        title,
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
    }));
}
