import { createInventoryRealtimeState } from "./inventoryRealtimeState.mjs";

/** Una ráfaga de avisos solicita la página visible una sola vez. */
export function createInventoryRealtimeBatch({ flush, onError, delay = 200, setTimer = setTimeout, clearTimer = clearTimeout }) {
    let active = true;
    let timer = null;
    let pending = [];
    let inFlight = null;

    function run() {
        timer = null;
        if (inFlight) return inFlight;
        if (!active || pending.length === 0) return;
        const events = pending;
        pending = [];
        inFlight = Promise.resolve().then(() => flush(events))
            .catch((error) => onError?.(error))
            .finally(() => {
                inFlight = null;
                // Los avisos recibidos durante la lectura comparten una sola pasada posterior.
                if (active && pending.length > 0) void run();
            });
        return inFlight;
    }

    function clear() {
        if (timer !== null) clearTimer(timer);
        timer = null;
        pending = [];
    }

    return {
        add(event = {}) {
            if (!active) return;
            pending.push(event);
            if (timer === null && !inFlight) timer = setTimer(run, delay);
        },
        flush() {
            if (timer !== null) clearTimer(timer);
            return run();
        },
        clear,
        dispose() {
            active = false;
            clear();
        },
    };
}

/** Reemplaza únicamente datos de la página; nunca inicia una navegación ni toca formularios. */
export function createInventoryPageRefresh({ getContext, load, apply, onError }) {
    const state = createInventoryRealtimeState();
    let controller = null;

    function invalidate() {
        controller?.abort();
        state.invalidateAll();
    }

    return {
        async refresh() {
            controller?.abort();
            controller = new AbortController();
            const context = getContext();
            const signal = controller.signal;
            return state.request("page", {
                load: () => load({ context, signal }),
                onSuccess: (value) => {
                    if (context === getContext()) apply(value);
                },
                onError: (error) => {
                    if (!signal.aborted) onError?.(error);
                },
            });
        },
        invalidate,
        dispose() {
            invalidate();
            state.dispose();
        },
    };
}

export function readInventoryPage(payload, component, field) {
    const value = payload?.props?.[field];
    if (payload?.component !== component || !value || typeof value !== "object" ||
        (!Array.isArray(value) && !Array.isArray(value.data))) {
        throw new Error("La respuesta no corresponde a esta página de inventario.");
    }
    return value;
}

export function inventoryPartialHeaders(page, fields) {
    return {
        Accept: "text/html, application/xhtml+xml",
        "X-Inertia": "true",
        "X-Inertia-Partial-Component": page.component,
        "X-Inertia-Partial-Data": fields.join(","),
        ...(page.version ? { "X-Inertia-Version": page.version } : {}),
    };
}
