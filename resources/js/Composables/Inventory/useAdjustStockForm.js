import { computed, reactive, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import {
    WarningAlert,
    ToastAlert,
    ErrorAlert,
} from "@/Components/Modales/UniversalActionModal";
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
        batch_allocation_method: "FEFO_AUTO",
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
            return [
                { label: "Compra", value: "PURCHASE" },
                { label: "Transferencia", value: "TRANSFER" },
                { label: "Ajuste manual", value: "MANUAL" },
            ];
        }

        if (form.type === "OUT") {
            return [
                { label: "Venta", value: "SALE" },
                { label: "Producto dañado", value: "DAMAGED" },
                { label: "Producto robado", value: "STOLEN" },
                { label: "Producto caducado", value: "EXPIRED" },
                { label: "Transferencia", value: "TRANSFER" },
                { label: "Ajuste manual", value: "MANUAL" },
            ];
        }

        return [{ label: "Ajuste manual", value: "MANUAL" }];
    });

    const currentStock = computed(() => Number(props.product.stock ?? 0));

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
        const quantity = Number(form.quantity || 0);

        if (form.type === "IN") return currentStock.value + quantity;
        if (form.type === "OUT") return currentStock.value - quantity;

        return quantity;
    });

    const errorSummary = computed(() => {
        const frontend = Object.values(frontendErrors).filter(Boolean);
        const backend = Object.values(form.errors || {}).filter(Boolean);

        return [...frontend, ...backend];
    });

    watch(
        () => form.type,
        () => {
            form.reason = reasonOptions.value[0]?.value ?? "MANUAL";
            form.batch_allocation_method =
                form.type === "OUT" ? "FEFO_AUTO" : null;
            form.manual_batches = [];

            frontendErrors.type = "";
            frontendErrors.reason = "";
            frontendErrors.stock = "";
            frontendErrors.manual_batches = "";
        },
    );

    watch(
        () => form.quantity,
        () => {
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
        const exists = form.manual_batches.find(
            (item) => item.product_batch_id === batch.id,
        );

        if (exists) return;

        form.manual_batches.push({
            product_batch_id: batch.id,
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

    function validateCompleteForm() {
        clearFrontendErrors();

        const errors = validateForm(adjustmentFields, form.data());

        Object.entries(errors).forEach(([field, message]) => {
            frontendErrors[field] = message;
        });

        const quantity = Number(form.quantity);

        if (!quantity || quantity <= 0) {
            frontendErrors.quantity = "La cantidad debe ser mayor a cero.";
        }

        if (form.type === "IN" && form.batches.length > 0) {
            if (totalBatchQuantity.value !== quantity) {
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

        if (form.type === "OUT" && quantity > currentStock.value) {
            frontendErrors.stock =
                "La salida no puede ser mayor al stock actual.";
        }

        if (form.type === "OUT" && form.batch_allocation_method === "MANUAL") {
            if (!form.manual_batches.length) {
                frontendErrors.manual_batches = "Selecciona al menos un lote.";
            }

            if (totalManualBatchQuantity.value !== quantity) {
                frontendErrors.manual_batches =
                    "La suma de los lotes seleccionados debe coincidir con la cantidad total.";
            }

            form.manual_batches.forEach((batch, index) => {
                if (!batch.quantity || Number(batch.quantity) <= 0) {
                    frontendErrors[`manual_batch_${index}`] =
                        "La cantidad del lote debe ser mayor a cero.";
                }

                if (Number(batch.quantity) > Number(batch.available_quantity)) {
                    frontendErrors[`manual_batch_${index}`] =
                        "No puedes tomar más piezas de las disponibles en este lote.";
                }
            });
        }

        return Object.values(frontendErrors).every((error) => !error);
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

        form.post(route("inventario.stock-movements.store"), {
            preserveScroll: true,

            onSuccess: () => {
                ToastAlert({
                    icon: "success",
                    title: "Stock ajustado correctamente",
                });

                emit("close");
                form.reset();
                form.batches = [];
                form.manual_batches = [];
                clearFrontendErrors();
            },

            onError: () => {
                ErrorAlert({
                    title: "Error en la operación",
                    message: "No fue posible registrar el ajuste de stock",
                });
            },
        });
    }

    return {
        form,
        frontendErrors,
        typeOptions,
        reasonOptions,
        currentStock,
        projectedStock,
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
