import assert from "node:assert/strict";
import test from "node:test";
import { createInventoryRealtimeBatch, createInventoryPageRefresh, readInventoryPage, inventoryPartialHeaders } from "../../resources/js/utils/inventoryRealtimeBatch.mjs";

function clock() {
    let id = 0;
    const timers = new Map();
    return {
        setTimer: (callback) => { timers.set(++id, callback); return id; },
        clearTimer: (key) => timers.delete(key),
        run: async () => {
            const current = [...timers.values()];
            timers.clear();
            await Promise.all(current.map((callback) => callback()));
        },
        get size() { return timers.size; },
    };
}

function deferred() {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
}

test("a hundred repeated product events cause one page refresh per group", async () => {
    const timer = clock();
    const groups = [];
    const batch = createInventoryRealtimeBatch({ ...timer, flush: (events) => groups.push(events) });
    for (let i = 0; i < 100; i++) batch.add({ productId: i % 4 });
    assert.equal(timer.size, 1);
    await timer.run();
    assert.equal(groups.length, 1);
    assert.equal(groups[0].length, 100);
    batch.add({ productId: 5 });
    await timer.run();
    assert.equal(groups.length, 2);
});

test("dispose cancels grouped work and ignores later events", async () => {
    const timer = clock();
    const batch = createInventoryRealtimeBatch({ ...timer, flush: () => assert.fail("disposed") });
    batch.add({ productId: 7 });
    batch.dispose();
    batch.add({ productId: 8 });
    await timer.run();
    assert.equal(timer.size, 0);
});

test("events during a pending flush share one trailing refresh with no overlapping work", async () => {
    const timer = clock();
    const response = deferred();
    const secondResponse = deferred();
    let active = 0;
    let maximum = 0;
    const groups = [];
    const batch = createInventoryRealtimeBatch({ ...timer, flush: async (events) => {
        active++;
        maximum = Math.max(maximum, active);
        groups.push(events);
        await (groups.length === 1 ? response.promise : secondResponse.promise);
        active--;
    } });
    batch.add({ productId: 7 });
    const first = timer.run();
    await Promise.resolve();
    for (let i = 0; i < 100; i++) batch.add({ productId: 8 });
    assert.equal(groups.length, 1);
    assert.equal(timer.size, 0);
    response.resolve();
    await first;
    await Promise.resolve();
    assert.equal(groups.length, 2);
    assert.equal(groups[1].length, 100);
    assert.equal(maximum, 1);
    secondResponse.resolve();
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(timer.size, 0);
    assert.equal(groups.length, 2);
});

test("disposal during a request prevents the pending trailing refresh", async () => {
    const timer = clock();
    const response = deferred();
    let calls = 0;
    const batch = createInventoryRealtimeBatch({ ...timer, flush: async () => { calls++; await response.promise; } });
    batch.add({ productId: 7 });
    const first = timer.run();
    await Promise.resolve();
    batch.add({ productId: 8 });
    batch.dispose();
    response.resolve();
    await first;
    assert.equal(calls, 1);
    assert.equal(timer.size, 0);
});

test("a failed flush is reported once and pending work can still finish", async () => {
    const timer = clock();
    const response = deferred();
    let calls = 0;
    const errors = [];
    const batch = createInventoryRealtimeBatch({ ...timer, onError: (error) => errors.push(error.message),
        flush: async () => { if (++calls === 1) { await response.promise; throw new Error("HTTP failure"); } } });
    batch.add({ productId: 7 });
    const first = timer.run();
    await Promise.resolve();
    batch.add({ productId: 8 });
    response.resolve();
    await first;
    await Promise.resolve();
    assert.equal(calls, 2);
    assert.deepEqual(errors, ["HTTP failure"]);
    assert.equal(timer.size, 0);
});

test("page refresh preserves filters, pagination, and a separate draft", async () => {
    const draft = { notes: "Lote en captura", amount: 30 };
    const originalContext = `/inventory/branches/1/inventory${String.fromCharCode(63)}search=coc&page=3`;
    let context = originalContext;
    let products = { current_page: 3, total: 40, data: [{ id: 7, stock: 100 }] };
    const refresh = createInventoryPageRefresh({
        getContext: () => context,
        load: async () => ({ current_page: 3, total: 40, data: [{ id: 8, stock: 120 }] }),
        apply: (value) => { products = value; },
    });
    await refresh.refresh();
    assert.equal(products.current_page, 3);
    assert.deepEqual(products.data.map((item) => item.id), [8]);
    assert.equal(context, originalContext);
    assert.deepEqual(draft, { notes: "Lote en captura", amount: 30 });
    context = "/inventory/branches/2/inventory";
    refresh.dispose();
});

test("HTTP failure retains the whole current page", async () => {
    const products = [{ id: 7, stock: 100 }];
    const errors = [];
    const refresh = createInventoryPageRefresh({
        getContext: () => "branch1",
        load: async () => { throw new Error("HTTP 503"); },
        apply: () => assert.fail("must preserve current rows"),
        onError: (error) => errors.push(error.message),
    });
    assert.equal((await refresh.refresh()).status, "failed");
    assert.deepEqual(products, [{ id: 7, stock: 100 }]);
    assert.deepEqual(errors, ["HTTP 503"]);
});

test("a newer page request aborts and rejects the old response even if the transport ignores cancellation", async () => {
    const old = deferred();
    const signals = [];
    const rows = [];
    let calls = 0;
    const refresh = createInventoryPageRefresh({
        getContext: () => "branch1:search=coc:page3",
        load: ({ signal }) => { signals.push(signal); return ++calls === 1 ? old.promise : Promise.resolve(130); },
        apply: (value) => rows.push(value),
    });
    const first = refresh.refresh();
    await refresh.refresh();
    old.resolve(100);
    assert.equal((await first).status, "stale");
    assert.equal(signals[0].aborted, true);
    assert.deepEqual(rows, [130]);
});

test("a response from a previous branch, URL, or search cannot change the current page", async () => {
    for (const next of ["branch2:page3:coc", "branch1:page4:coc", "branch1:page3:agua"]) {
        const response = deferred();
        let context = "branch1:page3:coc";
        const refresh = createInventoryPageRefresh({
            getContext: () => context,
            load: () => response.promise,
            apply: () => assert.fail("wrong page"),
        });
        const pending = refresh.refresh();
        context = next;
        response.resolve([]);
        await pending;
    }
});

test("valid partial payloads replace only the requested dataset", () => {
    const products = { current_page: 2, data: [{ id: 7 }] };
    const payload = { component: "Inventory/Home", props: { productsDB: products } };
    assert.equal(readInventoryPage(payload, "Inventory/Home", "productsDB"), products);
    for (const invalid of [{}, { component: "Auth/Login", props: { productsDB: products } },
        { component: "Inventory/Home", props: {} }, { component: "Inventory/Home", props: { productsDB: null } }]) {
        assert.throws(() => readInventoryPage(invalid, "Inventory/Home", "productsDB"));
    }
    assert.deepEqual(inventoryPartialHeaders({ component: "Inventory/Home", version: "asset123" }, ["productsDB", "filters"]), {
        Accept: "text/html, application/xhtml+xml", "X-Inertia": "true",
        "X-Inertia-Partial-Component": "Inventory/Home", "X-Inertia-Partial-Data": "productsDB,filters", "X-Inertia-Version": "asset123",
    });
});
