import { computed, onBeforeUnmount, onMounted, reactive } from "vue";

function parseDateValue(value) {
    if (!value) return null;

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function useProductMovementsModal(props, emit) {
    const productName = computed(() => props.product?.name ?? "Producto");
    const productCode = computed(() => props.product?.code ?? "Sin código");
    const unit = computed(() => props.product?.unit ?? "pieza");
    const currentStock = computed(() => Number(props.product?.stock ?? 0));

    const filters = reactive({
        userName: "",
        dateFrom: "",
        dateTo: "",
        movementGroup: "all",
    });

    const movements = computed(() => props.product?.recentMovements ?? []);
    const totalMovements = computed(() => movements.value.length);

    function isAdministrativeAdjustment(movement) {
        return (
            movement.type === "ADJUSTMENT" &&
            Number(movement.quantity ?? 0) === 0 &&
            Number(movement.previous_stock ?? 0) ===
                Number(movement.new_stock ?? 0)
        );
    }

    function resolveGroupKey(movement) {
        if (movement.reason === "DAMAGED") {
            return "damaged";
        }

        if (movement.reason === "EXPIRED") {
            return "expired";
        }

        if (isAuditMovement(movement)) {
            return "audits";
        }

        if (movement.type === "ADJUSTMENT") {
            return "adjustments";
        }

        return "others";
    }

    function isAuditMovement(movement) {
        const notes = String(movement.notes ?? "").toLowerCase();

        return (
            movement.reason === "INVENTORY_DIFFERENCE" &&
            (
                notes.includes("auditoria") ||
                notes.includes("auditoría") ||
                notes.includes("conteo fisico") ||
                notes.includes("conteo físico")
            )
        );
    }

    const movementGroupOptions = computed(() => [
        { value: "all", label: "Todos" },
        { value: "audits", label: "Auditorías" },
        { value: "adjustments", label: "Ajustes manuales" },
        { value: "damaged", label: "Dañados" },
        { value: "expired", label: "Caducados" },
        { value: "others", label: "Otros" },
    ]);

    const userOptions = computed(() => {
        const users = new Map([["", "Todos"]]);

        movements.value.forEach((movement) => {
            const name = userLabel(movement);

            if (!name || users.has(name)) return;
            users.set(name, name);
        });

        return Array.from(users.entries()).map(([value, label]) => ({
            value,
            label,
        }));
    });

    function formatNumber(value) {
        return new Intl.NumberFormat("es-MX", {
            maximumFractionDigits: 3,
        }).format(Number(value ?? 0));
    }

    function formatDateTime(date) {
        if (!date) return "Sin fecha";

        const parsedDate = parseDateValue(date);

        if (!parsedDate) {
            return "Fecha inválida";
        }

        return new Intl.DateTimeFormat("es-MX", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        }).format(parsedDate);
    }

    function reasonLabel(reason, movement = null) {
        if (movement && isAdministrativeAdjustment(movement)) {
            return "Actualización de datos";
        }

        if (movement && isAuditMovement(movement)) {
            return "Auditoría";
        }

        if (movement?.type === "ADJUSTMENT" && reason === "INVENTORY_DIFFERENCE") {
            return "Ajuste manual";
        }

        return (
            {
                PURCHASE: "Compra",
                SALE: "Venta",
                DAMAGED: "Dañado",
                EXPIRED: "Caducado",
                OTHER: "Otro",
                INVENTORY_DIFFERENCE: "Ajuste manual",
            }[reason] ?? "Movimiento"
        );
    }

    function typeLabel(type, movement = null) {
        if (movement && isAdministrativeAdjustment(movement)) {
            return "Administrativo";
        }

        return (
            {
                IN: "Entrada",
                OUT: "Salida",
                ADJUSTMENT: "Ajuste",
            }[type] ?? "Movimiento"
        );
    }

    function userLabel(movement) {
        return (
            movement.user?.name ??
            movement.user?.full_name ??
            "Usuario no disponible"
        );
    }

    function movementBatches(movement) {
        if (isAdministrativeAdjustment(movement)) return [];

        return movement.batches ?? [];
    }

    function batchName(batchMovement) {
        return (
            batchMovement.product_batch?.lot_number ??
            batchMovement.productBatch?.lot_number ??
            "Existencia general"
        );
    }

    function batchSummary(movement) {
        const batches = movementBatches(movement);

        if (!batches.length) return "Sin lote";

        return batches.map((batch) => batchName(batch)).join(", ");
    }

    function quantityLabel(movement) {
        if (isAdministrativeAdjustment(movement)) {
            return "Sin cambio";
        }

        const quantity = formatNumber(movement.quantity ?? 0);

        if (movement.type === "IN") return `+${quantity}`;
        if (movement.type === "OUT") return `-${quantity}`;

        const previousStock = Number(movement.previous_stock ?? 0);
        const newStock = Number(movement.new_stock ?? 0);

        if (newStock > previousStock) return `+${quantity}`;
        if (newStock < previousStock) return `-${quantity}`;

        return quantity;
    }

    function quantityClass(movement) {
        if (isAdministrativeAdjustment(movement)) return "text-violet-700";
        if (movement.type === "IN") return "text-emerald-700";
        if (movement.type === "OUT") return "text-rose-700";

        return "text-slate-700";
    }

    function stockTransition(movement) {
        if (
            movement.previous_stock === null ||
            movement.previous_stock === undefined ||
            movement.new_stock === null ||
            movement.new_stock === undefined
        ) {
            return "Sin registro";
        }

        if (isAdministrativeAdjustment(movement)) {
            return "Sin cambio";
        }

        return `${formatNumber(movement.previous_stock)} -> ${formatNumber(
            movement.new_stock,
        )}`;
    }

    const filteredMovements = computed(() => {
        const fromDate = filters.dateFrom ? parseDateValue(filters.dateFrom) : null;
        const toDate = filters.dateTo ? parseDateValue(filters.dateTo) : null;

        return movements.value.filter((movement) => {
            if (
                filters.movementGroup &&
                filters.movementGroup !== "all" &&
                resolveGroupKey(movement) !== filters.movementGroup
            ) {
                return false;
            }

            if (filters.userName && userLabel(movement) !== filters.userName) {
                return false;
            }

            const movementDate = parseDateValue(movement.created_at);

            if (fromDate && movementDate && movementDate < fromDate) {
                return false;
            }

            if (toDate && movementDate) {
                const inclusiveToDate = new Date(toDate);
                inclusiveToDate.setHours(23, 59, 59, 999);

                if (movementDate > inclusiveToDate) {
                    return false;
                }
            }

            return true;
        });
    });

    const totalFilteredMovements = computed(() => filteredMovements.value.length);

    const tableRows = computed(() =>
        filteredMovements.value.map((movement) => ({
            ...movement,
            groupKey: resolveGroupKey(movement),
            displayDate: formatDateTime(movement.created_at),
            displayReason: reasonLabel(movement.reason, movement),
            displayType: typeLabel(movement.type, movement),
            displayUser: userLabel(movement),
            displayQuantity: quantityLabel(movement),
            displayBatches: batchSummary(movement),
            displayStock: stockTransition(movement),
            notesText: String(movement.notes ?? "").trim(),
        })),
    );

    function resetFilters() {
        filters.userName = "";
        filters.dateFrom = "";
        filters.dateTo = "";
        filters.movementGroup = "all";
    }

    function closeModal() {
        emit("close");
    }

    function handleEsc(e) {
        if (e.key === "Escape") closeModal();
    }

    onMounted(() => {
        window.addEventListener("keydown", handleEsc);
    });

    onBeforeUnmount(() => {
        window.removeEventListener("keydown", handleEsc);
    });

    return {
        productName,
        productCode,
        unit,
        currentStock,
        totalMovements,
        totalFilteredMovements,
        filters,
        movementGroupOptions,
        userOptions,
        tableRows,
        quantityClass,
        formatNumber,
        resetFilters,
        closeModal,
    };
}
