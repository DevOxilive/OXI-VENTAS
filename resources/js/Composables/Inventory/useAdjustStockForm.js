import { computed, reactive, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import {
    WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import { getModalRequestOptions } from "@/Components/Modales/useModalConfig";
import { validateSingleField, validateForm } from "@/Validation/schemaBuilder";

const adjustmentFields = ["type", "reason", "quantity", "notes"];

export function useAdjustStockForm(props, emit) {
    const form = useForm({
        branch_product_id: props.product.id,
        type: "IN",
        reason: "PURCHASE",
        quantity: "",
        notes: "",
        batches: [],
        batch_allocation_method: "MANUAL",
        manual_batches: [],
    });

    const frontendErrors = reactive({});

    const typeOptions = [
        { label: "Entrada", value: "IN" },
        { label: "Salida", value: "OUT" },
        { label: "Ajuste manual", value: "ADJUSTMENT" },
    ];

    const reasonOptions = computed(() => {
        if (form.type === "IN") {
            return [{ label: "Compra", value: "PURCHASE" }];
        }

        if (form.type === "OUT") {
            return [
                { label: "Venta", value: "SALE" },
                { label: "Producto dañado", value: "DAMAGED" },
                { label: "Producto caducado", value: "EXPIRED" },
                { label: "Otros...", value: "OTHER" },
            ];
        }

        return [
            {
                label: "Diferencia de inventario",
                value: "INVENTORY_DIFFERENCE",
            },
        ];
    });

    const currentStock = computed(() => Number(props.product.stock ?? 0));

    const movementQuantity = computed(() => Number(form.quantity || 0));

    const absoluteMovementQuantity = computed(() => {
        return Math.abs(movementQuantity.value);
    });

    const isIncomingMovement = computed(() => form.type === "IN");
    const isOutgoingMovement = computed(() => form.type === "OUT");
    const isAdjustmentMovement = computed(() => form.type === "ADJUSTMENT");

    const isNegativeAdjustment = computed(() => {
        return isAdjustmentMovement.value && movementQuantity.value < 0;
    });

    const requiresManualBatchSelection = computed(() => {
        return isOutgoingMovement.value || isNegativeAdjustment.value;
    });

    const manualBatchTargetQuantity = computed(() => {
        return requiresManualBatchSelection.value
            ? absoluteMovementQuantity.value
            : 0;
    });

    const totalBatchQuantity = computed(() => {
        return form.batches.reduce((acc, batch) => {
            return acc + Number(batch.quantity || 0);
        }, 0);
    });

    const totalManualBatchQuantity = computed(() => {
        return form.manual_batches.reduce((acc, batch) => {
            return acc + Number(batch.quantity || 0);
        }, 0);
    });

    const projectedStock = computed(() => {
        if (isIncomingMovement.value) {
            return currentStock.value + absoluteMovementQuantity.value;
        }

        if (isOutgoingMovement.value) {
            return currentStock.value - absoluteMovementQuantity.value;
        }

        return currentStock.value + movementQuantity.value;
    });

    const errorSummary = computed(() => {
        const frontend = Object.values(frontendErrors).filter(Boolean);
        const backend = Object.values(form.errors || {}).filter(Boolean);

        return [...frontend, ...backend];
    });

    watch(
        () => form.type,
        () => {
            form.reason =
                reasonOptions.value[0]?.value ?? "INVENTORY_DIFFERENCE";
            form.batch_allocation_method = "MANUAL";
            form.batches = [];
            form.manual_batches = [];

            clearFrontendErrors();
        },
    );

    watch(
        () => form.quantity,
        () => {
            frontendErrors.quantity = "";
            frontendErrors.stock = "";
            frontendErrors.batches = "";
            frontendErrors.manual_batches = "";
        },
    );

    function addBatch() {
        form.batches.push({
            lot_number: "",
            expiration_date: "",
            quantity: "",
            supplier: "",
        });
    }

    function removeBatch(index) {
        form.batches.splice(index, 1);
    }

    function addManualBatch(batch) {
        const exists = form.manual_batches.find((item) => item.id === batch.id);

        if (exists) return;

        form.manual_batches.push({
            id: batch.id,
            lot_number: batch.lot_number,
            expiration_date: batch.expiration_date,
            available_quantity: Number(batch.quantity || 0),
            quantity: "",
        });
    }

    function removeManualBatch(index) {
        form.manual_batches.splice(index, 1);
    }

    function validateField(field) {
        if (!field) return;

        frontendErrors[field] = validateSingleField(field, form[field]);
    }

    function clearFrontendErrors() {
        adjustmentFields.forEach((field) => {
            frontendErrors[field] = "";
        });

        frontendErrors.stock = "";
        frontendErrors.batches = "";
        frontendErrors.manual_batches = "";

        Object.keys(frontendErrors).forEach((key) => {
            if (key.startsWith("batch_") || key.startsWith("manual_batch_")) {
                delete frontendErrors[key];
            }
        });
    }

    function validateBaseFields() {
        const errors = validateForm(adjustmentFields, form.data());

        Object.entries(errors).forEach(([field, message]) => {
            frontendErrors[field] = message;
        });

        if (!movementQuantity.value) {
            frontendErrors.quantity = "La cantidad no puede ser cero.";
            return;
        }

        if (
            (isIncomingMovement.value || isOutgoingMovement.value) &&
            movementQuantity.value <= 0
        ) {
            frontendErrors.quantity = "La cantidad debe ser mayor a cero.";
        }

        if (isAdjustmentMovement.value && movementQuantity.value === 0) {
            frontendErrors.quantity = "El ajuste no puede ser cero.";
        }
    }

    function validateIncomingBatches() {
        if (!isIncomingMovement.value || !form.batches.length) return;

        if (totalBatchQuantity.value !== absoluteMovementQuantity.value) {
            frontendErrors.batches =
                "La suma de los lotes debe coincidir con la cantidad total.";
        }

        form.batches.forEach((batch, index) => {
            if (!batch.quantity || Number(batch.quantity) <= 0) {
                frontendErrors[`batch_${index}`] =
                    "La cantidad del lote debe ser mayor a cero.";
            }
        });

        const duplicatedLotNumbers = form.batches
            .map((batch) => batch.lot_number?.trim().toUpperCase())
            .filter(Boolean)
            .filter((lotNumber, index, lotNumbers) => {
                return lotNumbers.indexOf(lotNumber) !== index;
            });

        if (duplicatedLotNumbers.length) {
            form.batches.forEach((batch, index) => {
                const lotNumber = batch.lot_number?.trim().toUpperCase();

                if (duplicatedLotNumbers.includes(lotNumber)) {
                    frontendErrors[`batch_${index}`] =
                        "Este número de lote ya fue agregado. Si pertenece al mismo lote, ajusta la cantidad en un solo registro.";
                }
            });
        }
    }

    function validateOutgoingStock() {
        if (!isOutgoingMovement.value && !isNegativeAdjustment.value) return;

        if (absoluteMovementQuantity.value > currentStock.value) {
            frontendErrors.stock =
                "La salida no puede ser mayor al stock actual.";
        }
    }

    function validateManualBatchSelection() {
        if (!requiresManualBatchSelection.value) return;

        if (!form.manual_batches.length) {
            frontendErrors.manual_batches =
                "Selecciona al menos un lote o stock general.";
            return;
        }

        if (
            totalManualBatchQuantity.value !== manualBatchTargetQuantity.value
        ) {
            frontendErrors.manual_batches =
                "La suma del stock seleccionado debe coincidir con la cantidad total.";
        }

        form.manual_batches.forEach((batch, index) => {
            if (!batch.quantity || Number(batch.quantity) <= 0) {
                frontendErrors[`manual_batch_${index}`] =
                    "La cantidad debe ser mayor a cero.";
            }

            if (Number(batch.quantity) > Number(batch.available_quantity)) {
                frontendErrors[`manual_batch_${index}`] =
                    "No puedes tomar más unidades de las disponibles.";
            }
        });
    }

    function validateCompleteForm() {
        clearFrontendErrors();

        validateBaseFields();
        validateIncomingBatches();
        validateOutgoingStock();
        validateManualBatchSelection();

        return Object.values(frontendErrors).every((error) => !error);
    }

    function resetForm() {
        form.reset();
        form.branch_product_id = props.product.id;
        form.type = "IN";
        form.reason = "PURCHASE";
        form.quantity = "";
        form.notes = "";
        form.batches = [];
        form.manual_batches = [];
        form.batch_allocation_method = "MANUAL";

        clearFrontendErrors();
    }

    function saveAdjustment() {
        if (!validateCompleteForm()) {
            WarningAlert({
                title: "Formulario incompleto",
                message:
                    "Debes corregir los campos marcados antes de continuar",
            });

            return;
        }
        console.log("PAYLOAD FINAL:", form.data());
        form.post(route("inventory.stock-movements.store"), getModalRequestOptions({
            mode: "create",
            entityName: "Movimiento de stock",
            close: () => emit("close"),
            successTitle: "Movimiento registrado correctamente",
            errorTitle: "Error en la operaci?n",
            errorMessage: "No fue posible registrar el movimiento de stock",
            onSuccess: () => {
                resetForm();
            },
        }));
    }

    return {
        form,
        frontendErrors,

        typeOptions,
        reasonOptions,

        currentStock,
        projectedStock,
        movementQuantity,
        absoluteMovementQuantity,
        manualBatchTargetQuantity,

        isIncomingMovement,
        isOutgoingMovement,
        isAdjustmentMovement,
        isNegativeAdjustment,
        requiresManualBatchSelection,

        errorSummary,

        validateField,
        saveAdjustment,

        addBatch,
        removeBatch,
        totalBatchQuantity,

        addManualBatch,
        removeManualBatch,
        totalManualBatchQuantity,
    };
}
