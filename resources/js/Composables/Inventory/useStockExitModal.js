import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { useAdjustStockForm } from "@/Composables/Inventory/useAdjustStockForm";

export function useStockExitModal(props, emit) {
    const {
        form,
        frontendErrors,
        currentStock,
        errorSummary,
        validateField,
        saveAdjustment,
    } = useAdjustStockForm(props, emit);

    const selectedSourceKey = ref(null);

    const allowedReasons = ["DAMAGED", "EXPIRED"];

    const productName = computed(() => {
        return (
            props.product?.name ?? props.product?.product?.name ?? "Producto"
        );
    });

    const unit = computed(() => props.product?.unit ?? "pieza");

    const reasonOptions = [
        { label: "Producto dañado", value: "DAMAGED" },
        { label: "Producto caducado", value: "EXPIRED" },
    ];

    const activeBatches = computed(() => {
        return (props.product?.batches ?? []).filter((batch) => {
            return Number(batch.quantity || 0) > 0;
        });
    });

    const availableSources = computed(() => {
        return activeBatches.value.map((batch) => ({
            key: `batch-${batch.id}`,
            id: batch.id,
            lot_number: batch.lot_number,
            expiration_date: normalizeDate(batch.expiration_date),
            quantity: Number(batch.quantity || 0),
            type: "batch",
        }));
    });

    const selectedSource = computed(() => {
        return (
            availableSources.value.find((source) => {
                return source.key === selectedSourceKey.value;
            }) ?? null
        );
    });

    const totalErrors = computed(() => errorSummary.value.length);

    function normalizeDate(date) {
        if (!date) return null;

        return String(date).slice(0, 10);
    }

    function formatDate(date) {
        if (!date) return "Sin caducidad";

        const [year, month, day] = normalizeDate(date).split("-");

        return `${day}/${month}/${year}`;
    }

    function expirationStatus(date) {
        if (!date) return "available";

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const expiration = new Date(normalizeDate(date));
        expiration.setHours(0, 0, 0, 0);

        const diffDays = Math.ceil((expiration - today) / 86400000);

        if (diffDays < 0) return "expired";
        if (diffDays <= 30) return "near";

        return "available";
    }

    function expirationLabel(date) {
        return {
            expired: "Vencido",
            near: "Por vencer",
            available: "Disponible",
        }[expirationStatus(date)];
    }

    function expirationClass(date, selected) {
        if (selected) {
            return "border-[#1f1d2b] bg-slate-900 text-white";
        }

        return {
            expired: "border-red-200 bg-red-50 text-red-800 hover:bg-red-100",
            near: "border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100",
            available:
                "border-green-200 bg-green-50 text-green-800 hover:bg-green-100",
        }[expirationStatus(date)];
    }

    function expirationBadgeClass(date, selected) {
        if (selected) {
            return "bg-white/15 text-white";
        }

        return {
            expired: "bg-red-100 text-red-700",
            near: "bg-amber-100 text-amber-700",
            available: "bg-green-100 text-green-700",
        }[expirationStatus(date)];
    }

    function sourceLabel(source) {
        return source.lot_number
            ? `Lote ${source.lot_number}`
            : "Entrada sin número de lote";
    }

    function ensureExitReady() {
        form.type = "OUT";
        form.reason = "";
        form.batches = [];
        form.manual_batches = [];
        form.batch_allocation_method = "MANUAL";

        nextTick(() => {
            if (!allowedReasons.includes(form.reason)) {
                form.reason = "";
            }
        });

        syncManualBatch();
    }

    function selectSource(source) {
        if (form.processing || Number(source.quantity || 0) <= 0) return;

        selectedSourceKey.value = source.key;
        form.batch_allocation_method = "MANUAL";

        syncManualBatch();
    }

    function syncManualBatch() {
        if (!selectedSource.value) {
            form.manual_batches = [];
            return;
        }

        form.manual_batches = [
            {
                id: selectedSource.value.id,
                lot_number: selectedSource.value.lot_number,
                expiration_date: selectedSource.value.expiration_date,
                available_quantity: selectedSource.value.quantity,
                quantity: form.quantity || "",
            },
        ];
    }

    function clearExitErrors() {
        frontendErrors.quantity = "";
        frontendErrors.reason = "";
        frontendErrors.manual_batches = "";
        frontendErrors.stock = "";
    }

    function validateExit() {
        clearExitErrors();
        syncManualBatch();

        if (
            !selectedSource.value ||
            Number(selectedSource.value.quantity) <= 0
        ) {
            frontendErrors.manual_batches =
                "Selecciona una entrada disponible.";
        }

        if (!form.quantity || Number(form.quantity) <= 0) {
            frontendErrors.quantity = "La cantidad debe ser mayor a cero.";
        }

        if (
            selectedSource.value &&
            Number(form.quantity) > Number(selectedSource.value.quantity)
        ) {
            frontendErrors.quantity =
                "La cantidad supera la existencia disponible.";
        }

        if (!allowedReasons.includes(form.reason)) {
            frontendErrors.reason = "Selecciona un motivo.";
        }

        if (Number(form.quantity || 0) > currentStock.value) {
            frontendErrors.stock =
                "La salida no puede ser mayor al stock actual.";
        }

        syncManualBatch();

        return (
            !frontendErrors.quantity &&
            !frontendErrors.reason &&
            !frontendErrors.manual_batches &&
            !frontendErrors.stock
        );
    }

    function submitExit() {
        form.batch_allocation_method = "MANUAL";
        syncManualBatch();

        if (!validateExit()) return;

        saveAdjustment();
    }

    function formatNumber(value) {
        const number = Number(value ?? 0);

        return new Intl.NumberFormat("es-MX", {
            maximumFractionDigits: 3,
        }).format(number);
    }

    function closeModal() {
        if (form.processing) return;

        emit("close");
    }

    function handleEsc(e) {
        if (e.key === "Escape") closeModal();
    }

    watch(
        () => form.quantity,
        () => {
            frontendErrors.quantity = "";
            frontendErrors.manual_batches = "";
            frontendErrors.stock = "";

            syncManualBatch();
        },
    );

    watch(
        () => form.reason,
        () => {
            if (!allowedReasons.includes(form.reason)) {
                form.reason = "";
                return;
            }

            frontendErrors.reason = "";
        },
    );

    watch(selectedSource, () => {
        frontendErrors.manual_batches = "";
        frontendErrors.quantity = "";

        syncManualBatch();
    });

    onMounted(() => {
        ensureExitReady();
        window.addEventListener("keydown", handleEsc);
    });

    onBeforeUnmount(() => {
        window.removeEventListener("keydown", handleEsc);
    });

    return {
        form,
        frontendErrors,
        currentStock,
        productName,
        unit,
        reasonOptions,
        availableSources,
        selectedSourceKey,
        totalErrors,

        formatNumber,
        validateField,
        closeModal,
        selectSource,
        submitExit,
        sourceLabel,
        formatDate,
        expirationLabel,
        expirationClass,
        expirationBadgeClass,
    };
}
