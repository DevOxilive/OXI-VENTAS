import assert from "node:assert/strict";
import test from "node:test";
import {
    createInventoryRealtimeState,
    readInventorySnapshot,
} from "../../resources/js/utils/inventoryRealtimeState.mjs";

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });

    return { promise, resolve, reject };
}

function productScreen(state = createInventoryRealtimeState()) {
    const products = new Map([[7, { id: 7, stock: 100 }]]);
    const errors = [];

    function refresh(load, productId = 7, field = "product") {
        return state.request(`product:${productId}`, {
            load: async () => readInventorySnapshot(await load(), field),
            onSuccess: (product) => {
                if (product === null) {
                    products.delete(productId);
                } else {
                    products.set(productId, product);
                }
            },
            onError: (error) => errors.push(error),
        });
    }

    return { state, products, errors, refresh };
}

test("an HTTP failure preserves an existing product and stock", async () => {
    const screen = productScreen();
    const failure = new Error("HTTP 503");
    const result = await screen.refresh(() => Promise.reject(failure));

    assert.equal(result.status, "failed");
    assert.deepEqual(screen.products.get(7), { id: 7, stock: 100 });
    assert.deepEqual(screen.errors, [failure]);
});

test("only an explicit null in a valid response confirms absence", async () => {
    for (const field of ["product", "branchProduct"]) {
        const screen = productScreen();
        const result = await screen.refresh(() => Promise.resolve({ [field]: null }), 7, field);

        assert.equal(result.status, "applied");
        assert.equal(screen.products.has(7), false);
    }
});

test("incomplete or malformed successful responses preserve the product", async () => {
    for (const payload of [null, {}, "<html>Error</html>", { product: undefined },
        { product: false }, { product: [] }, { product: {} }]) {
        const screen = productScreen();
        const result = await screen.refresh(() => Promise.resolve(payload));

        assert.equal(result.status, "failed");
        assert.equal(screen.products.get(7).stock, 100);
    }
});

test("a late old snapshot cannot overwrite a newer stock value", async () => {
    const screen = productScreen();
    const oldResponse = deferred();
    const oldRequest = screen.refresh(() => oldResponse.promise);
    await screen.refresh(() => Promise.resolve({ product: { id: 7, stock: 130 } }));
    oldResponse.resolve({ product: { id: 7, stock: 110 } });

    assert.equal((await oldRequest).status, "stale");
    assert.equal(screen.products.get(7).stock, 130);
});

test("a late null response cannot remove a product restored by a newer response", async () => {
    const screen = productScreen();
    const oldResponse = deferred();
    const oldRequest = screen.refresh(() => oldResponse.promise);
    await screen.refresh(() => Promise.resolve({ product: { id: 7, stock: 20 } }));
    oldResponse.resolve({ product: null });

    assert.equal((await oldRequest).status, "stale");
    assert.equal(screen.products.get(7).stock, 20);
});

test("a late success cannot resurrect a product after a confirmed deletion", async () => {
    const screen = productScreen();
    const response = deferred();
    const request = screen.refresh(() => response.promise);
    screen.state.invalidate("product:7");
    screen.products.delete(7);
    response.resolve({ product: { id: 7, stock: 100 } });

    assert.equal((await request).status, "stale");
    assert.equal(screen.products.has(7), false);
});

test("a late failure is ignored without discarding newer data", async () => {
    const screen = productScreen();
    const response = deferred();
    const request = screen.refresh(() => response.promise);
    await screen.refresh(() => Promise.resolve({ product: { id: 7, stock: 130 } }));
    response.reject(new Error("Old timeout"));

    assert.equal((await request).status, "stale");
    assert.equal(screen.products.get(7).stock, 130);
    assert.deepEqual(screen.errors, []);
});

test("requests for different products remain independent", async () => {
    const screen = productScreen();
    const response = deferred();
    const firstRequest = screen.refresh(() => response.promise);
    await screen.refresh(() => Promise.resolve({ product: { id: 8, stock: 40 } }), 8);
    response.resolve({ product: { id: 7, stock: 120 } });

    assert.equal((await firstRequest).status, "applied");
    assert.equal(screen.products.get(7).stock, 120);
    assert.equal(screen.products.get(8).stock, 40);
});

test("product and branch-product aliases share the same ordering", async () => {
    const state = createInventoryRealtimeState();
    const response = deferred();
    const values = [];
    const firstRequest = state.request(["product:7", "branch-product:12"], {
        load: () => response.promise,
        onSuccess: (value) => values.push(value),
    });
    await state.request("product:7", {
        load: async () => 130,
        onSuccess: (value) => values.push(value),
    });
    response.resolve(110);

    assert.equal((await firstRequest).status, "stale");
    assert.deepEqual(values, [130]);
});

test("new page props invalidate pending snapshots without preventing future refreshes", async () => {
    const screen = productScreen();
    const response = deferred();
    const request = screen.refresh(() => response.promise);
    screen.state.invalidateAll();
    screen.products.set(7, { id: 7, stock: 150 });
    response.resolve({ product: { id: 7, stock: 100 } });

    assert.equal((await request).status, "stale");
    assert.equal(screen.products.get(7).stock, 150);
    assert.equal((await screen.refresh(async () => ({ product: { id: 7, stock: 160 } }))).status, "applied");
    assert.equal(screen.products.get(7).stock, 160);
});

test("unmount prevents late data, errors, and new network requests", async () => {
    const screen = productScreen();
    const success = deferred();
    const failure = deferred();
    const pendingSuccess = screen.refresh(() => success.promise);
    const pendingFailure = screen.refresh(() => failure.promise, 8);
    screen.state.dispose();
    success.resolve({ product: null });
    failure.reject(new Error("Late timeout"));

    assert.equal((await pendingSuccess).status, "stale");
    assert.equal((await pendingFailure).status, "stale");
    assert.equal(screen.products.get(7).stock, 100);
    assert.deepEqual(screen.errors, []);
    assert.equal(screen.state.isActive(), false);
    assert.equal((await screen.refresh(() => assert.fail("must not request after unmount"))).status, "stale");
});

test("summary ordering does not discard an independent product response", async () => {
    const products = createInventoryRealtimeState();
    const summary = createInventoryRealtimeState();
    const response = deferred();
    const rows = [];
    let stockTotal = 100;
    const firstSummary = summary.begin("summary");
    const firstRequest = products.request("product:7", {
        load: () => response.promise,
        onSuccess: (snapshot) => {
            rows.push(snapshot.id);
            if (summary.isCurrent(firstSummary)) stockTotal = snapshot.total;
        },
    });
    const latestSummary = summary.begin("summary");
    await products.request("product:8", {
        load: async () => ({ id: 8, total: 170 }),
        onSuccess: (snapshot) => {
            rows.push(snapshot.id);
            if (summary.isCurrent(latestSummary)) stockTotal = snapshot.total;
        },
    });
    response.resolve({ id: 7, total: 120 });

    assert.equal((await firstRequest).status, "applied");
    assert.deepEqual(rows, [8, 7]);
    assert.equal(stockTotal, 170);
});

test("a pending detail lookup cannot overwrite details from a newer snapshot", async () => {
    const details = createInventoryRealtimeState();
    const response = deferred();
    let batches = [100];
    const lookup = details.request(["product:7", "branch-product:12"], {
        load: () => response.promise,
        onSuccess: (value) => { batches = value; },
    });
    const snapshot = details.begin(["product:7", "branch-product:12"]);
    if (details.isCurrent(snapshot)) batches = [100, 30];
    response.resolve([100]);

    assert.equal((await lookup).status, "stale");
    assert.deepEqual(batches, [100, 30]);
});

test("opening details does not cancel a pending row update for that product", async () => {
    const rows = createInventoryRealtimeState();
    const details = createInventoryRealtimeState();
    const response = deferred();
    const keys = ["product:7", "branch-product:12"];
    const snapshotDetails = details.begin(keys);
    let stock = 100;
    let batches = [];
    const snapshot = rows.request(keys, {
        load: () => response.promise,
        onSuccess: (value) => {
            stock = value.stock;
            if (details.isCurrent(snapshotDetails)) batches = value.batches;
        },
    });
    await details.request(keys, {
        load: async () => [100, 30],
        onSuccess: (value) => { batches = value; },
    });
    response.resolve({ stock: 130, batches: [100] });

    assert.equal((await snapshot).status, "applied");
    assert.equal(stock, 130);
    assert.deepEqual(batches, [100, 30]);
});
