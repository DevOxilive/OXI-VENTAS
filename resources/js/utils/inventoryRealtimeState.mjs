/** Conserva solo la respuesta mas reciente de cada producto o grupo de datos. */
export function createInventoryRealtimeState() {
    const latestRequests = new Map();
    let sequence = 0;
    let active = true;

    function begin(keys) {
        const normalizedKeys = [...new Set((Array.isArray(keys) ? keys : [keys])
            .filter((key) => key !== null && key !== undefined && key !== "")
            .map(String))];
        const token = { sequence: ++sequence, keys: normalizedKeys };

        if (active) {
            normalizedKeys.forEach((key) => latestRequests.set(key, token.sequence));
        }

        return token;
    }

    function isCurrent(token) {
        return active && token.keys.length > 0 && token.keys.every((key) => (
            latestRequests.get(key) === token.sequence
        ));
    }

    async function request(keys, { load, onSuccess, onError } = {}) {
        const token = begin(keys);
        if (!isCurrent(token)) return { status: "stale" };

        let data;
        try {
            data = await load();
        } catch (error) {
            if (!isCurrent(token)) return { status: "stale" };

            onError?.(error);
            return { status: "failed" };
        }

        if (!isCurrent(token)) return { status: "stale" };

        onSuccess?.(data);
        return { status: "applied", data };
    }

    return {
        begin,
        isCurrent,
        request,
        invalidate: begin,
        invalidateAll: () => latestRequests.clear(),
        isActive: () => active,
        dispose: () => {
            active = false;
            latestRequests.clear();
        },
    };
}

/** Un error o una respuesta incompleta nunca confirma la ausencia de un producto. */
export function readInventorySnapshot(payload, field) {
    if (!payload || typeof payload !== "object" ||
        !Object.prototype.hasOwnProperty.call(payload, field)) {
        throw new Error("La respuesta de inventario esta incompleta.");
    }

    const value = payload[field];
    if (value !== null && (typeof value !== "object" || Array.isArray(value) || !value?.id)) {
        throw new Error("La respuesta de inventario no contiene un producto valido.");
    }

    return value;
}
