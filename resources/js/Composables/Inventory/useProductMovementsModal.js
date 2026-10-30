import { computed, onBeforeUnmount, onMounted, reactive, watch } from "vue";
import { formatInventoryQuantity } from "@/utils/quantityFormatter";

function parseDateValue(value) {
    if (!value) return null;

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function useProductMovementsModal(props, emit) {
    const productName = computed(() => props.product?.name ?? "Producto");
    const productCode = computed(() => props.product?.code ?? "Sin código");
    const unit = computed(() => props.product?.inventory_unit ?? props.product?.unit ?? "pza");
    const currentStock = computed(() => Number(props.product?.stock ?? 0));

    const filters = reactive({
        userId: "",
        dateFrom: "",
        dateTo: "",
        movementGroup: "all",
    });

    const movements = computed(() => props.product?.recentMovements ?? []);
    const pagination = computed(() => props.product?.movementsPagination ?? null);
    const totalMovements = computed(() => props.product?.movementsTotal ?? movements.value.length);
    let filterTimer = null;

    function loadPage(number) {
        clearTimeout(filterTimer);
        emit("load-page", {
            movements_page: number,
            movement_user_id: filters.userId === "" ? undefined : filters.userId,
            movement_group: filters.movementGroup,
            movement_date_from: filters.dateFrom || undefined,
            movement_date_to: filters.dateTo || undefined,
        });
    }

    watch(() => [filters.userId, filters.movementGroup, filters.dateFrom, filters.dateTo], () => {
        if (!pagination.value) return;
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => loadPage(1), 200);
    });

    function isAdministrativeAdjustment(movement) {
        return (
            movement.type === "ADJUSTMENT" &&
            Number(movement.quantity ?? 0) === 0 &&
            Number(movement.previous_stock ?? 0) ===
                Number(movement.new_stock ?? 0)
        );
    }

    function resolveGroupKey(movement) {
        if (movement.reason === "PURCHASE") {
            return "purchases";
        }

        if (movement.reason === "SALE") {
            return "sales";
        }

        if (movement.reason === "RETURN") {
            return "returns";
        }

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
        { value: "purchases", label: "Compras" },
        { value: "sales", label: "Ventas" },
        { value: "returns", label: "Devoluciones" },
        { value: "audits", label: "Auditorías" },
        { value: "adjustments", label: "Ajustes manuales" },
        { value: "damaged", label: "Dañados" },
        { value: "expired", label: "Caducados" },
        { value: "others", label: "Otros" },
    ]);

    const userOptions = computed(() => {
        if (pagination.value) {
            return [{ value: "", label: "Todos" }, ...(props.product?.movementsUsers ?? []).map((user) => ({
                value: String(user.id), label: user.name,
            }))];
        }
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

    const movementTableColumns = computed(() => [
        {
            key: "displayDate",
            label: "Fecha",
            format: "text",
            width: "180px",
            mobileSecondary: true,
        },
        {
            key: "displayReason",
            label: "Motivo",
            format: "text",
            width: "190px",
        },
        {
            key: "displayType",
            label: "Tipo",
            format: "badge",
            width: "140px",
            mobileBadge: true,
            formatOptions: {
                statusMap: {
                    Entrada: "green",
                    Salida: "red",
                    Ajuste: "blue",
                    Administrativo: "purple",
                },
            },
        },
        {
            key: "displayUser",
            label: "Usuario",
            format: "text",
            width: "190px",
            mobileDisplay: true,
        },
        {
            key: "displayBatches",
            label: "Lote / nota",
            format: "text",
            width: "260px",
            cellClass: "max-w-[260px]",
            mobileDisplay: true,
        },
        {
            key: "displayQuantity",
            label: "Cantidad",
            format: "text",
            width: "140px",
            mobileDisplay: true,
        },
        {
            key: "displayStock",
            label: "Stock",
            format: "text",
            width: "170px",
            mobileDisplay: true,
        },
    ]);

    function formatNumber(value) {
        return formatInventoryQuantity(value, unit.value);
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

    function groupLabel(groupKey, movement) {
        return (
            {
                purchases: "Compras",
                sales: "Ventas",
                returns: "Devoluciones",
                audits: "Auditorias",
                adjustments: "Ajustes",
                damaged: "Dañados",
                expired: "Caducados",
                others: "Otros",
            }[groupKey] ?? reasonLabel(movement.reason, movement)
        );
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
                RETURN: "Devolución",
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

        const quantity = formatNumber(Math.abs(Number(movement.quantity ?? 0)));

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
        // El servidor filtra todo el historial antes de paginarlo.
        if (pagination.value) return movements.value;
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

            if (filters.userId && userLabel(movement) !== filters.userId) {
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

    const totalFilteredMovements = computed(() => pagination.value?.total ?? filteredMovements.value.length);

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

    function signedQuantity(movement) {
        if (isAdministrativeAdjustment(movement)) return 0;

        const quantity = Number(movement.quantity ?? 0);

        if (movement.type === "IN") return Math.abs(quantity);
        if (movement.type === "OUT") return -Math.abs(quantity);

        const previousStock = Number(movement.previous_stock ?? 0);
        const newStock = Number(movement.new_stock ?? 0);

        return newStock - previousStock;
    }

    const movementSummary = computed(() => {
        if (pagination.value) {
            return (props.product?.movementsSummary ?? []).map((item) => ({
                ...item, label: groupLabel(item.key, {}),
            })).filter((item) => Number(item.count) > 0)
                .sort((a, b) => a.label.localeCompare(b.label, "es"));
        }
        const summaries = new Map();

        filteredMovements.value.forEach((movement) => {
            const key = resolveGroupKey(movement);

            if (!summaries.has(key)) {
                summaries.set(key, {
                    key,
                    label: groupLabel(key, movement),
                    count: 0,
                });
            }

            summaries.get(key).count += 1;
        });

        return Array.from(summaries.values())
            .sort((a, b) => a.label.localeCompare(b.label, "es"));
    });

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
        clearTimeout(filterTimer);
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
        movementTableColumns,
        movementSummary,
        quantityClass,
        formatNumber,
        pagination,
        loadPage,
        closeModal,
    };
}
