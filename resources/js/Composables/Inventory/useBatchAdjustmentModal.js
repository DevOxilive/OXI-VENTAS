import { computed, reactive, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { getModalRequestOptions } from "@/Components/Modales/useModalConfig";
export function useBatchAdjustmentModal(products) {
    const showBatchAdjustmentModal = ref(false);
    const selectedBatchId = ref(null);
    const processing = ref(false);
    const usesLot = ref(false);

    const form = reactive({
        id: null,
        lot_number: "",
        expiration_date: "",
        supplier: "",
        received_at: "",
        original_quantity: 0,
        adjustment_type: "ADD",
        adjustment_amount: "",
        season_start_date: "",
        season_end_date: "",
        status: "ACTIVE",
        notes: "",
    });

    const frontendErrors = reactive({
        lot_number: "",
        expiration_date: "",
        supplier: "",
        received_at: "",
        adjustment_amount: "",
        season_start_date: "",
        season_end_date: "",
        status: "",
        notes: "",
    });

    function normalizeDateForInput(date) {
        if (!date) return "";

        return String(date).slice(0, 10);
    }

    const liveSelectedBatch = computed(() => {
        if (!selectedBatchId.value) return null;

        const allBatches = products.value.flatMap((product) => {
            return product.batches ?? [];
        });

        const liveBatch = allBatches.find((batch) => {
            return batch.id === selectedBatchId.value;
        });

        if (!liveBatch) return null;

        return {
            ...liveBatch,
            expiration_date: normalizeDateForInput(liveBatch.expiration_date),
            received_at: normalizeDateForInput(liveBatch.received_at),
            season_start_date: normalizeDateForInput(
                liveBatch.season_start_date,
            ),
            season_end_date: normalizeDateForInput(liveBatch.season_end_date),
            original_quantity: Number(liveBatch.quantity || 0),
            quantity: Number(liveBatch.quantity || 0),
        };
    });

    const totalErrors = computed(() => {
        return Object.values(frontendErrors).filter(Boolean).length;
    });

    const isSeasonal = computed(() => {
        return form.status === "SEASONAL";
    });

    const signedAdjustmentQuantity = computed(() => {
        const amount = Number(form.adjustment_amount || 0);

        return form.adjustment_type === "ADD" ? amount : amount * -1;
    });

    const calculatedQuantity = computed(() => {
        return (
            Number(form.original_quantity || 0) + signedAdjustmentQuantity.value
        );
    });

    const adjustmentText = computed(() => {
        const amount = Number(form.adjustment_amount || 0);

        if (!amount) {
            return "No se aplicará ajuste de cantidad.";
        }

        return form.adjustment_type === "ADD"
            ? `Se agregarán ${amount} unidad(es).`
            : `Se eliminarán ${amount} unidad(es).`;
    });

    const quantityResultColor = computed(() => {
        if (calculatedQuantity.value < form.original_quantity)
            return "text-red-600";
        if (calculatedQuantity.value > form.original_quantity)
            return "text-emerald-600";

        return "text-slate-900";
    });

    watch(
        liveSelectedBatch,
        (batch) => {
            if (!batch) return;

            form.id = batch.id;
            form.lot_number = batch.lot_number || "";
            form.expiration_date = batch.expiration_date || "";
            form.supplier = batch.supplier || "";
            form.received_at = batch.received_at || "";
            form.original_quantity = Number(batch.quantity || 0);
            form.adjustment_type = "ADD";
            form.adjustment_amount = "";
            form.season_start_date = batch.season_start_date || "";
            form.season_end_date = batch.season_end_date || "";
            form.status = batch.status || "ACTIVE";
            form.notes = "";

            usesLot.value = Boolean(batch.lot_number);

            clearErrors();
        },
        { immediate: true },
    );

    watch(
        () => form.status,
        (status) => {
            if (status !== "SEASONAL") {
                form.season_start_date = "";
                form.season_end_date = "";
                frontendErrors.season_start_date = "";
                frontendErrors.season_end_date = "";
            }
        },
    );

    watch(
        () => [form.adjustment_type, form.adjustment_amount],
        () => {
            validateField("adjustment_amount");
        },
    );

    function clearErrors() {
        Object.keys(frontendErrors).forEach((field) => {
            frontendErrors[field] = "";
        });
    }

    function toggleLot() {
        usesLot.value = !usesLot.value;

        if (!usesLot.value) {
            form.lot_number = "";
            frontendErrors.lot_number = "";
        }
    }

    function setAdjustmentType(type) {
        form.adjustment_type = type;
        validateField("adjustment_amount");
    }

    function validateField(field) {
        frontendErrors[field] = "";

        if (field === "adjustment_amount") {
            if (
                form.adjustment_amount === "" ||
                form.adjustment_amount === null
            )
                return;

            const amount = Number(form.adjustment_amount);

            if (Number.isNaN(amount)) {
                frontendErrors.adjustment_amount =
                    "La cantidad debe ser un número.";
                return;
            }

            if (amount < 0) {
                frontendErrors.adjustment_amount =
                    "Captura solo números positivos.";
                return;
            }

            if (amount === 0) {
                frontendErrors.adjustment_amount =
                    "La cantidad debe ser mayor a 0.";
                return;
            }

            if (calculatedQuantity.value < 0) {
                frontendErrors.adjustment_amount =
                    "No puedes eliminar más unidades de las disponibles.";
                return;
            }

            return;
        }

        if (field === "lot_number" && usesLot.value && !form.lot_number) {
            frontendErrors.lot_number = "El número de lote es obligatorio.";
        }

        if (field === "status" && !form.status) {
            frontendErrors.status = "Selecciona un estado para el lote.";
        }

        if (
            field === "season_start_date" &&
            isSeasonal.value &&
            !form.season_start_date
        ) {
            frontendErrors.season_start_date =
                "La fecha inicial es obligatoria para temporada.";
        }

        if (
            field === "season_end_date" &&
            isSeasonal.value &&
            !form.season_end_date
        ) {
            frontendErrors.season_end_date =
                "La fecha final es obligatoria para temporada.";
            return;
        }

        if (
            field === "season_end_date" &&
            isSeasonal.value &&
            form.season_start_date &&
            form.season_end_date &&
            form.season_end_date < form.season_start_date
        ) {
            frontendErrors.season_end_date =
                "La fecha final no puede ser menor a la fecha inicial.";
        }

        if (field === "notes" && form.notes && form.notes.length > 500) {
            frontendErrors.notes =
                "La nota no puede superar los 500 caracteres.";
        }
    }

    function validateForm() {
        clearErrors();

        validateField("lot_number");
        validateField("status");
        validateField("adjustment_amount");
        validateField("notes");

        if (isSeasonal.value) {
            validateField("season_start_date");
            validateField("season_end_date");
        }

        return totalErrors.value === 0;
    }

    function adjustBatch(batch) {
        selectedBatchId.value = batch.id;
        showBatchAdjustmentModal.value = true;
    }

    function closeBatchAdjustmentModal() {
        if (processing.value) return;

        showBatchAdjustmentModal.value = false;
        selectedBatchId.value = null;
    }

    function saveEditedBatch() {
        if (!validateForm()) return;

        processing.value = true;

        router.put(
            route("inventory.product-batches.update", form.id),
            {
                lot_number: usesLot.value ? form.lot_number || null : null,
                expiration_date: form.expiration_date || null,
                supplier: form.supplier || null,
                received_at: form.received_at || null,
                quantity: calculatedQuantity.value,
                season_start_date: isSeasonal.value
                    ? form.season_start_date || null
                    : null,
                season_end_date: isSeasonal.value
                    ? form.season_end_date || null
                    : null,
                status: form.status || "ACTIVE",
                notes: form.notes || null,
            },
            {
                ...getModalRequestOptions({
                    mode: "update",
                    entityName: "Lote",
                    close: () => {
                        showBatchAdjustmentModal.value = false;
                        selectedBatchId.value = null;
                    },
                    successTitle: "Lote actualizado",
                    errorTitle: "No se pudo actualizar",
                    errorMessage:
                        "Revisa la información del lote e intenta nuevamente.",
                    onSuccess: () => {
                        emit("created", form.lot_number);
                    },
                }),
                onFinish: () => {
                    processing.value = false;
                },
            },
        );
    }
    return {
        showBatchAdjustmentModal,
        liveSelectedBatch,
        selectedBatchId,

        processing,
        usesLot,
        form,
        frontendErrors,
        totalErrors,
        isSeasonal,
        calculatedQuantity,
        adjustmentText,
        quantityResultColor,

        adjustBatch,
        closeBatchAdjustmentModal,
        toggleLot,
        setAdjustmentType,
        validateField,
        saveEditedBatch,
    };
}
