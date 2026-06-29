import { computed, reactive, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { getModalRequestOptions } from "@/Components/Modales";

export function useEditBranchProductConfig(product) {
    const frontendErrors = reactive({});

    const form = useForm({
        min_stock: 0,
        status: "active",
        season_start_date: "",
        season_end_date: "",
    });

    const statusOptions = [
        { label: "Activo", value: "active" },
        { label: "Inactivo", value: "inactive" },
        { label: "Temporada", value: "seasonal" },
    ];

    const totalErrors = computed(() => {
        return (
            Object.values(frontendErrors).filter(Boolean).length +
            Object.values(form.errors || {}).filter(Boolean).length
        );
    });

    const saveButtonText = computed(() => {
        return form.processing ? "Guardando..." : "Guardar configuración";
    });

    const productName = computed(() => {
        return (
            product.value?.name ?? product.value?.product?.name ?? "Producto"
        );
    });

    const unit = computed(() => {
        return product.value?.unit ?? "pieza";
    });

    const stockLabel = computed(() => {
        return (
            product.value?.stockLabel ??
            `${product.value?.stock ?? 0} ${unit.value}`
        );
    });

    const isSeasonal = computed(() => {
        return form.status === "seasonal";
    });

    const statusHelpText = computed(() => {
        if (form.status === "active") {
            return "El producto estará disponible para operación normal en esta sucursal.";
        }

        if (form.status === "inactive") {
            return "El producto quedará inactivo a nivel general en esta sucursal.";
        }

        if (form.status === "seasonal") {
            return "El producto tendrá una temporada general sugerida para la sucursal.";
        }

        return "Selecciona cómo debe comportarse el producto en esta sucursal.";
    });

    watch(
        product,
        (currentProduct) => {
            if (!currentProduct) return;

            form.min_stock = currentProduct.minStock ?? 0;
            form.status = currentProduct.administrativeStatus ?? "active";
            form.season_start_date = currentProduct.seasonStartDate ?? "";
            form.season_end_date = currentProduct.seasonEndDate ?? "";
        },
        { immediate: true },
    );

    function validateField(field) {
        frontendErrors[field] = "";

        if (field === "min_stock" && Number(form.min_stock) < 0) {
            frontendErrors.min_stock =
                "El stock mínimo no puede ser menor a cero.";
        }

        if (field === "status" && !form.status) {
            frontendErrors.status = "Selecciona un estado operativo.";
        }

        if (
            field === "season_end_date" &&
            form.season_start_date &&
            form.season_end_date &&
            form.season_end_date < form.season_start_date
        ) {
            frontendErrors.season_end_date =
                "La fecha final no puede ser menor a la fecha inicial.";
        }
    }

    function validateForm() {
        validateField("min_stock");
        validateField("status");
        validateField("season_end_date");

        return !Object.values(frontendErrors).some(Boolean);
    }

    function saveConfig(productId, onSuccess) {
        if (!validateForm()) return;

        form.patch(
            route("inventory.branch-inventory.update-config", productId),
            getModalRequestOptions({
                mode: "edit",
                entityName: "ConfiguraciÃ³n",
                successTitle: "ConfiguraciÃ³n actualizada correctamente",
                errorTitle: "Error al actualizar configuraciÃ³n",
                errorMessage: "No fue posible guardar la configuraciÃ³n del producto.",
                onSuccess,
            }),
        );
    }

    return {
        form,
        frontendErrors,
        statusOptions,
        totalErrors,
        saveButtonText,
        productName,
        unit,
        stockLabel,
        isSeasonal,
        statusHelpText,
        validateField,
        saveConfig,
    };
}
