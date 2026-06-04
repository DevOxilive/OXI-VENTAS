import { computed, onBeforeUnmount, onMounted } from "vue";

export function useProductMovementsModal(props, emit) {
    const productName = computed(() => props.product?.name ?? "Producto");
    const unit = computed(() => props.product?.unit ?? "pieza");
    const currentStock = computed(() => Number(props.product?.stock ?? 0));

    const movements = computed(() => props.product?.recentMovements ?? []);
    const totalMovements = computed(() => movements.value.length);

    const movementGroups = computed(() => [
        {
            key: "purchases",
            title: "Compras",
            icon: "add_circle",
            empty: "Sin compras recientes.",
            items: movements.value.filter((movement) => {
                return movement.type === "IN" || movement.reason === "PURCHASE";
            }),
        },
        {
            key: "damaged",
            title: "Dañados",
            icon: "production_quantity_limits",
            empty: "Sin salidas por daño.",
            items: movements.value.filter((movement) => {
                return movement.reason === "DAMAGED";
            }),
        },
        {
            key: "expired",
            title: "Caducados",
            icon: "event_busy",
            empty: "Sin salidas por caducidad.",
            items: movements.value.filter((movement) => {
                return movement.reason === "EXPIRED";
            }),
        },
        {
            key: "adjustments",
            title: "Ajustes",
            icon: "tune",
            empty: "Sin ajustes manuales.",
            items: movements.value.filter((movement) => {
                return movement.type === "ADJUSTMENT";
            }),
        },
    ]);

    function groupClass(key) {
        return (
            {
                purchases: "border-green-200 bg-green-50",
                damaged: "border-red-200 bg-red-50",
                expired: "border-amber-200 bg-amber-50",
                adjustments: "border-slate-200 bg-slate-50",
            }[key] ?? "border-slate-200 bg-slate-50"
        );
    }

    function groupIconClass(key) {
        return (
            {
                purchases: "text-green-600 bg-green-100",
                damaged: "text-red-600 bg-red-100",
                expired: "text-amber-600 bg-amber-100",
                adjustments: "text-slate-600 bg-slate-200",
            }[key] ?? "text-slate-600 bg-slate-200"
        );
    }

    function quantityLabel(movement) {
        const quantity = formatNumber(movement.quantity ?? 0);

        if (movement.type === "IN") return `+${quantity} ${unit.value}`;
        if (movement.type === "OUT") return `-${quantity} ${unit.value}`;

        const previousStock = Number(movement.previous_stock ?? 0);
        const newStock = Number(movement.new_stock ?? 0);

        if (newStock > previousStock) return `+${quantity} ${unit.value}`;
        if (newStock < previousStock) return `-${quantity} ${unit.value}`;

        return `${quantity} ${unit.value}`;
    }

    function quantityClass(movement) {
        if (movement.type === "IN") return "text-green-700";
        if (movement.type === "OUT") return "text-red-700";

        return "text-slate-700";
    }

    function reasonLabel(reason) {
        return (
            {
                PURCHASE: "Compra",
                SALE: "Venta",
                DAMAGED: "Producto dañado",
                EXPIRED: "Producto caducado",
                INVENTORY_DIFFERENCE: "Diferencia de inventario",
            }[reason] ?? "Movimiento"
        );
    }

    function formatNumber(value) {
        return new Intl.NumberFormat("es-MX", {
            maximumFractionDigits: 3,
        }).format(Number(value ?? 0));
    }

    function formatDateTime(date) {
        if (!date) return "Sin fecha";

        return new Intl.DateTimeFormat("es-MX", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(date));
    }

    function userLabel(movement) {
        return (
            movement.user?.name ??
            movement.user?.full_name ??
            "Usuario no disponible"
        );
    }

    function hasNotes(movement) {
        return Boolean(String(movement.notes ?? "").trim());
    }

    function movementBatches(movement) {
        return movement.batches ?? [];
    }

    function batchName(batchMovement) {
        return (
            batchMovement.product_batch?.lot_number ??
            batchMovement.productBatch?.lot_number ??
            "Existencia general"
        );
    }

    function batchQuantityLabel(batchMovement, movement) {
        const quantity = formatNumber(batchMovement.quantity ?? 0);

        if (movement.type === "IN") return `+${quantity} ${unit.value}`;
        if (movement.type === "OUT") return `-${quantity} ${unit.value}`;

        return `${quantity} ${unit.value}`;
    }

    function stockTransition(movement) {
        if (
            movement.previous_stock === null ||
            movement.previous_stock === undefined ||
            movement.new_stock === null ||
            movement.new_stock === undefined
        ) {
            return null;
        }

        return `${formatNumber(movement.previous_stock)} → ${formatNumber(
            movement.new_stock,
        )} ${unit.value}`;
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
        unit,
        currentStock,
        totalMovements,
        movementGroups,

        groupClass,
        groupIconClass,
        quantityLabel,
        quantityClass,
        reasonLabel,
        formatNumber,
        formatDateTime,
        userLabel,
        hasNotes,
        movementBatches,
        batchName,
        batchQuantityLabel,
        stockTransition,

        closeModal,
    };
}
