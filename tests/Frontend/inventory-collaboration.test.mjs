import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import vm from "node:vm";
import test from "node:test";
import { parse } from "@babel/parser";
import { parse as parseVue } from "@vue/compiler-sfc";
import { computed, ref, reactive, watch, effectScope, nextTick } from "vue";
import { createInventoryRealtimeState } from "../../resources/js/utils/inventoryRealtimeState.mjs";
import { createInventoryRealtimeBatch, createInventoryPageRefresh, readInventoryPage, inventoryPartialHeaders } from "../../resources/js/utils/inventoryRealtimeBatch.mjs";

function sourceWithoutImports(path) {
    const source = readFileSync(new URL(path, import.meta.url), "utf8");
    return parse(source, { sourceType: "module" }).program.body.filter((node) => node.type !== "ImportDeclaration")
        .map((node) => source.slice(node.declaration?.start ?? node.start, node.declaration?.end ?? node.end)).join("\n");
}

function deferredResponse() {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
}

test("the inventory composable groups events, refreshes the current page and preserves an open batch draft", async (t) => {
    const timers = new Map();
    let timerId = 0;
    const listeners = new Map();
    const mounts = [];
    const unmounts = [];
    const requests = [];
    const subscriptions = [];
    const routeCalls = [];
    const heldDetails = [];
    const scope = effectScope();
    t.after(() => scope.stop());
    const original = { id: 12, product_id: 7, branch_id: 1, stock: 100, product: { id: 7, name: "Coca 600" } };
    const props = reactive({ currentBranch: { id: 1 }, branchProductsDB: { current_page: 3, total: 60,
        data: [original], links: [] }, filters: { search: "coc", per_page: 25 }, inventoryAlerts: {}, inventoryStats: {} });
    const context = vm.createContext({
        computed, ref, reactive, watch, console, AbortController,
        onMounted: (callback) => mounts.push(callback), onBeforeUnmount: (callback) => unmounts.push(callback),
        router: { get: () => assert.fail("a collaborative event must not navigate") },
        usePage: () => ({ component: "Inventory/BranchInventory", version: "assets1" }),
        useGlobalTablePagination: () => ({ handlePageChange() {} }),
        useBatchAdjustmentModal: () => ({ form: reactive({ notes: "Captura sin guardar", adjustment_amount: 30 }),
            clearSelectedBatch() {} }),
        createInventoryRealtimeState, createInventoryPageRefresh, readInventoryPage, inventoryPartialHeaders,
        createInventoryRealtimeBatch: (options) => createInventoryRealtimeBatch({ ...options,
            setTimer: (callback) => { timers.set(++timerId, callback); return timerId; },
            clearTimer: (id) => timers.delete(id) }),
        REALTIME_CHANNELS: { inventoryBranch: (id) => `inventory.branch.${id}` },
        REALTIME_EVENTS: { stockUpdated: "stock", productChanged: "product" },
        subscribeRealtime: (channel, event, callback) => {
            const subscription = { channel, event, callback, active: true };
            subscriptions.push(subscription);
            listeners.set(event, callback);
            return () => { subscription.active = false; listeners.delete(event); };
        },
        subscribeRealtimeReconnect: (callback) => { listeners.set("reconnect", callback); return () => listeners.delete("reconnect"); },
        route: (name, params) => { routeCalls.push({ name, params }); return name; },
        window: { location: { href: "http://local/inventory/branches/1/inventory?search=coc&page=3", pathname: "/inventory/branches/1/inventory" },
            axios: { get: async (url, options) => {
                requests.push({ url, options });
                if (url === "inventory.branch-inventory.details") {
                    const held = heldDetails.shift();
                    if (held) return held.promise;
                    return { data: { id: 12, product_id: 7,
                        product: { id: 7 }, batches: [{ id: 50, quantity: 100 }], movements: [] } };
                }
                if (url === "inventory.branches.inventory.summary") return { data: { inventoryStats: { total_stock: 130 }, inventoryAlerts: {} } };
                return { data: { component: "Inventory/BranchInventory", props: { branchProductsDB: {
                    current_page: 3, total: 60, links: [], data: [],
                } } } };
            } } },
        setTimeout, clearTimeout,
    });
    const inventory = scope.run(() => vm.runInContext(`${sourceWithoutImports("../../resources/js/Composables/Inventory/useBranchInventory.js")}\nuseBranchInventory(__props)`, Object.assign(context, { __props: props })));
    mounts.forEach((callback) => callback());
    await inventory.openProductBatchesModal(inventory.visualProducts.value[0]);
    await nextTick();
    assert.equal(inventory.showProductBatchesModal.value, true);
    requests.length = 0;
    for (let i = 0; i < 100; i++) listeners.get("stock")({ branch_product_id: 12, product_id: 7 });
    assert.equal(timers.size, 1);
    await Promise.all([...timers.values()].map((callback) => callback()));
    timers.clear();
    await nextTick();
    assert.equal(requests.filter((item) => item.url.startsWith("http:")).length, 1);
    assert.equal(requests.filter((item) => item.url === "inventory.branches.inventory.summary").length, 1);
    assert.equal(requests.filter((item) => item.url === "inventory.branch-inventory.details").length, 1);
    assert.equal(requests.find((item) => item.url === "inventory.branch-inventory.details").options.params.include_movements, 0);
    assert.equal(inventory.inventoryPagination.value.current_page, 3);
    assert.equal(inventory.visualProducts.value.length, 0);
    assert.equal(inventory.showProductBatchesModal.value, true);
    assert.equal(inventory.liveSelectedBatchesProduct.value.id, 12);
    assert.equal(inventory.batchAdjustmentForm.notes, "Captura sin guardar");
    assert.equal(inventory.search.value, "coc");

    const firstDetails = deferredResponse();
    const trailingDetails = deferredResponse();
    heldDetails.push(firstDetails, trailingDetails);
    requests.length = 0;
    listeners.get("stock")({ branch_product_id: 12, product_id: 7 });
    const duringStream = Promise.all([...timers.values()].map((callback) => callback()));
    timers.clear();
    for (let i = 0; i < 30 && !requests.some((item) => item.url === "inventory.branch-inventory.details"); i++) await Promise.resolve();
    for (let i = 0; i < 100; i++) listeners.get("stock")({ branch_product_id: 12, product_id: 7 });
    firstDetails.resolve({ data: { id: 12, product_id: 7, product: { id: 7 }, batches: [{ id: 50, quantity: 130 }] } });
    await duringStream;
    for (let i = 0; i < 30 && requests.filter((item) => item.url === "inventory.branch-inventory.details").length < 2; i++) await Promise.resolve();
    assert.equal(inventory.liveSelectedBatchesProduct.value.batches[0].quantity, 130);
    assert.equal(requests.filter((item) => item.url === "inventory.branch-inventory.details").length, 2);
    trailingDetails.resolve({ data: { id: 12, product_id: 7, product: { id: 7 }, batches: [{ id: 50, quantity: 140 }] } });
    for (let i = 0; i < 30; i++) await Promise.resolve();
    assert.equal(inventory.liveSelectedBatchesProduct.value.batches[0].quantity, 140);
    assert.equal(timers.size, 0);

    const oldStockHandler = listeners.get("stock");
    const oldSubscriptions = subscriptions.slice();
    const pendingOpen = deferredResponse();
    heldDetails.push(pendingOpen);
    oldStockHandler({ branch_product_id: 12, product_id: 7 });
    const openingOldEntry = inventory.openEntryModal(inventory.liveSelectedBatchesProduct.value);
    props.currentBranch = { id: 2 };
    props.branchProductsDB = { current_page: 3, total: 20, links: [], data: [{ ...original, id: 22, product_id: 8, branch_id: 2 }] };
    context.window.location.href = "http://local/inventory/branches/2/inventory?search=coc&page=3";
    context.window.location.pathname = "/inventory/branches/2/inventory";
    await nextTick();
    pendingOpen.resolve({ data: { id: 12, product_id: 7, product: { id: 7 }, batches: [] } });
    await openingOldEntry;
    assert.equal(inventory.showEntryModal.value, false);
    assert.equal(oldSubscriptions.every((item) => !item.active), true);
    assert.equal(subscriptions.filter((item) => item.active && item.channel === "inventory.branch.2").length, 2);
    oldStockHandler({ branch_product_id: 12, product_id: 7 });
    assert.equal(timers.size, 0);
    requests.length = 0;
    routeCalls.length = 0;
    for (let i = 0; i < 100; i++) listeners.get("stock")({ branch_product_id: 22, product_id: 8 });
    assert.equal(timers.size, 1);
    await Promise.all([...timers.values()].map((callback) => callback()));
    timers.clear();
    assert.equal(requests.filter((item) => item.url.includes("/branches/2/inventory")).length, 1);
    assert.equal(routeCalls.find((item) => item.name === "inventory.branches.inventory.summary").params.branch, 2);
    assert.equal(requests.filter((item) => item.url === "inventory.branch-inventory.details").length, 0);
    assert.equal(inventory.showProductBatchesModal.value, true);
    assert.equal(inventory.liveSelectedBatchesProduct.value.id, 12);
    assert.equal(inventory.batchAdjustmentForm.notes, "Captura sin guardar");
    unmounts.forEach((callback) => callback());
    assert.equal(listeners.size, 0);
});

test("movement filters request the whole server-side history and retain pagination totals", async (t) => {
    const scope = effectScope();
    t.after(() => scope.stop());
    const emissions = [];
    const cleanup = [];
    const props = reactive({ product: { name: "Coca", recentMovements: [{ id: 1, type: "ADJUSTMENT",
        reason: "INVENTORY_DIFFERENCE", quantity: -2, previous_stock: 100, new_stock: 98 }],
        movementsTotal: 1000, movementsPagination: { current_page: 3, total: 40, last_page: 4, has_more: true },
        movementsUsers: [{ id: 5, name: "Ana" }], movementsSummary: [{ key: "adjustments", count: 40 }] } });
    const context = vm.createContext({ computed, reactive, watch, console,
        onMounted() {}, onBeforeUnmount: (callback) => cleanup.push(callback),
        formatInventoryQuantity: (value) => String(value), setTimeout, clearTimeout,
        window: { addEventListener() {}, removeEventListener() {} },
    });
    const modal = scope.run(() => vm.runInContext(`${sourceWithoutImports("../../resources/js/Composables/Inventory/useProductMovementsModal.js")}\nuseProductMovementsModal(__props, __emit)`, Object.assign(context, { __props: props, __emit: (...args) => emissions.push(args) })));
    assert.equal(modal.totalMovements.value, 1000);
    assert.equal(modal.totalFilteredMovements.value, 40);
    assert.equal(modal.tableRows.value[0].displayQuantity, "-2");
    modal.filters.userId = "5";
    modal.filters.movementGroup = "adjustments";
    modal.filters.dateFrom = "2026-10-28";
    await nextTick();
    modal.loadPage(4);
    assert.equal(emissions.length, 1);
    assert.equal(emissions[0][0], "load-page");
    assert.equal(emissions[0][1].movement_user_id, "5");
    assert.equal(emissions[0][1].movement_group, "adjustments");
    assert.equal(emissions[0][1].movement_date_from, "2026-10-28");
    assert.equal(emissions[0][1].movements_page, 4);
    assert.equal(modal.tableRows.value.length, 1);
    assert.equal(modal.movementSummary.value[0].count, 40);
    cleanup.forEach((callback) => callback());
});

test("the product page replaces its branch subscriptions without losing an open form", async (t) => {
    const file = readFileSync(new URL("../../resources/js/Pages/Inventory/Home.vue", import.meta.url), "utf8");
    const source = parseVue(file).descriptor.scriptSetup.content;
    const script = parse(source, { sourceType: "module" }).program.body.filter((node) => node.type !== "ImportDeclaration")
        .map((node) => source.slice(node.start, node.end)).join("\n");
    const mounts = [];
    const unmounts = [];
    const subscriptions = [];
    const timers = new Map();
    const requests = [];
    let timerId = 0;
    const scope = effectScope();
    t.after(() => scope.stop());
    const props = reactive({ branch: { id: 1, slug: "san-cristobal" }, productsDB: { current_page: 2, data: [] },
        filters: { search: "coc" }, categoriesDB: [], productDepartmentsDB: [], branchesDB: [] });
    const showModal = ref(false);
    const context = vm.createContext({ computed, ref, watch, console,
        onMounted: (callback) => mounts.push(callback), onBeforeUnmount: (callback) => unmounts.push(callback),
        AdminLayout: {}, defineOptions() {}, defineProps: () => props, usePermissions: () => ({ can: () => true }),
        useProductActions: () => ({ showModal, modalMode: ref("create"), selectedProduct: ref(null) }),
        useBatchAdjustmentModal: () => ({}), useGlobalTablePagination: () => ({ handlePageChange() {} }),
        getProductToolbarConfig: () => ({ searchPlaceholder: "Buscar" }),
        router: { get: () => assert.fail("no navigation") }, usePage: () => ({ component: "Inventory/Home" }),
        createInventoryRealtimeState, createInventoryPageRefresh, readInventoryPage, inventoryPartialHeaders,
        createInventoryRealtimeBatch: (options) => createInventoryRealtimeBatch({ ...options,
            setTimer: (callback) => { timers.set(++timerId, callback); return timerId; }, clearTimer: (id) => timers.delete(id) }),
        REALTIME_CHANNELS: { inventoryProducts: "inventory.products", inventoryBranch: (id) => `inventory.branch.${id}` },
        REALTIME_EVENTS: { stockUpdated: "stock", productChanged: "product" },
        subscribeRealtime: (channel, event, callback) => {
            const item = { channel, event, callback, active: true };
            subscriptions.push(item);
            return () => { item.active = false; };
        },
        subscribeRealtimeReconnect: () => () => {}, route: (name) => name, setTimeout, clearTimeout,
        window: { location: { href: "http://local/inventory/branches/san-cristobal/products?search=coc&page=2" },
            axios: { get: async (url) => {
                requests.push(url);
                return { data: { component: "Inventory/Home", props: { productsDB: { current_page: 2, data: [] } } } };
            } } },
    });
    const home = scope.run(() => vm.runInContext(`${script}\n;({ selectedProductBranch, productsState })`, context));
    mounts.forEach((callback) => callback());
    showModal.value = true;
    await nextTick();
    assert.equal(home.selectedProductBranch.value.id, 1);
    const oldStock = subscriptions.find((item) => item.channel === "inventory.branch.1");
    oldStock.callback({ product_id: 7 });
    assert.equal(timers.size, 1);
    props.branch = { id: 2, slug: "otra-sucursal" };
    context.window.location.href = "http://local/inventory/branches/otra-sucursal/products?search=coc&page=2";
    await nextTick();
    assert.equal(oldStock.active, false);
    assert.equal(timers.size, 0);
    oldStock.callback({ product_id: 7 });
    assert.equal(timers.size, 0);
    const currentStock = subscriptions.find((item) => item.active && item.channel === "inventory.branch.2");
    for (let i = 0; i < 100; i++) currentStock.callback({ product_id: 8 });
    await Promise.all([...timers.values()].map((callback) => callback()));
    timers.clear();
    assert.equal(requests.length, 1);
    assert.match(requests[0], /otra-sucursal/);
    assert.equal(showModal.value, true);
    assert.equal(home.selectedProductBranch.value.id, 1);
    assert.equal(home.productsState.value.current_page, 2);
    unmounts.forEach((callback) => callback());
    assert.equal(subscriptions.every((item) => !item.active), true);
});

test("reconnect subscriptions reuse the existing connection and unbind on cleanup", () => {
    const source = readFileSync(new URL("../../resources/js/realtime.js", import.meta.url), "utf8");
    const declaration = parse(source, { sourceType: "module" }).program.body
        .find((node) => node.declaration?.id?.name === "subscribeRealtimeReconnect").declaration;
    for (const initialState of ["connected", "connecting"]) {
        const callbacks = new Map();
        let refreshed = 0;
        let clientReads = 0;
        const connection = { state: initialState,
            bind: (event, callback) => callbacks.set(event, callback),
            unbind: (event, callback) => { assert.equal(callbacks.get(event), callback); callbacks.delete(event); } };
        const context = vm.createContext({
            getRealtimeClient: () => { clientReads++; return { connector: { pusher: { connection } } }; },
        });
        const subscribe = vm.runInContext(`${source.slice(declaration.start, declaration.end)}\nsubscribeRealtimeReconnect`, context);
        const unsubscribe = subscribe(() => refreshed++);
        callbacks.get("state_change")({ current: "connected" });
        assert.equal(refreshed, initialState === "connected" ? 1 : 0);
        callbacks.get("state_change")({ current: "unavailable" });
        callbacks.get("state_change")({ current: "connected" });
        assert.equal(refreshed, initialState === "connected" ? 2 : 1);
        assert.equal(clientReads, 1);
        unsubscribe();
        assert.equal(callbacks.size, 0);
    }
});
