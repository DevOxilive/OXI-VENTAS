import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";
import { parse } from "@babel/parser";
import { parse as parseVue } from "@vue/compiler-sfc";
import { computed, effectScope, ref, watch } from "vue";
import { PRODUCT_SEARCH_TIMEOUT_MS } from "../../resources/js/config/productSearch.js";

const file = new URL("../../resources/js/Pages/Ventas/Home.vue", import.meta.url);
const source = parseVue(readFileSync(file, "utf8")).descriptor.scriptSetup.content;
const declarations = new Set([
    "search", "remoteSearchProducts", "searchError", "searchLoading", "selectedBranchId",
    "highlightedSuggestionIndex", "searchDebounceTimer", "searchRequestId", "productSearchCacheVersion",
    "productSearchCache", "productSearchRequests", "PRODUCT_SEARCH_CACHE_TTL_MS", "PRODUCT_SEARCH_DEBOUNCE_MS",
    "filteredProducts", "searchSuggestions", "handleSearchKeydown", "clearPendingProductSearch",
    "fetchProductsForSearch", "selectSuggestion", "findExactProduct",
]);
const searchScript = parse(source, { sourceType: "module" }).program.body.filter(node => {
    if (declarations.has(node.id?.name ?? node.declarations?.[0]?.id.name)) return true;
    const call = node.expression;
    return call?.callee?.name === "watch" && call.arguments[0]?.body?.elements?.some(
        element => element.object?.name === "search"
    );
}).map(node => source.slice(node.start, node.end)).join("\n");

test("el escaneo ignora respuestas anteriores y distingue vacío, desconexión y demora", async t => {
    const requests = new Map();
    const added = [];
    const scope = effectScope();
    t.after(() => scope.stop());
    const navigator = { onLine: true };
    const context = vm.createContext({
        computed, ref, watch, navigator, PRODUCT_SEARCH_TIMEOUT_MS, route: () => "/buscar",
        props: { currentBranch: { id: 2 }, productsDB: [] },
        addProduct: item => added.push(item.barcode),
        window: { setTimeout: () => 1, clearTimeout() {}, axios: {
            get: (_url, { params, timeout }) => {
                assert.equal(timeout, 15_000);
                return new Promise((resolve, reject) => requests.set(params.search, { resolve, reject }));
            },
        } },
    });
    const ui = scope.run(() => vm.runInContext(searchScript +
        "\n;({search, searchSuggestions, searchLoading, searchError, handleSearchKeydown, fetchProductsForSearch})", context));
    const enter = () => ui.handleSearchKeydown({ key: "Enter", preventDefault() {} });
    const result = code => ({ data: { products: [{ barcode: code }] } });

    ui.search.value = "639936";
    const oldEnter = enter();
    ui.search.value = "458734";
    const newEnter = enter();
    assert.equal(ui.searchLoading.value, true);
    requests.get("458734").resolve(result("458734"));
    await newEnter;
    requests.get("639936").resolve(result("639936"));
    await oldEnter;
    assert.deepEqual(added, ["458734"]);
    assert.equal(ui.searchSuggestions.value[0].barcode, "458734");

    ui.search.value = "otro";
    assert.equal(ui.searchSuggestions.value.length, 0);
    const offline = enter();
    navigator.onLine = false;
    requests.get("otro").reject(new Error("network"));
    await offline;
    assert.equal(ui.searchError.value, "Sin conexión.");

    navigator.onLine = true;
    const timeout = ui.fetchProductsForSearch("otro");
    requests.get("otro").reject(Object.assign(new Error("timeout"), { code: "ECONNABORTED" }));
    await timeout;
    assert.equal(ui.searchError.value, "La búsqueda tardó demasiado.");

    const retry = ui.fetchProductsForSearch("otro");
    requests.get("otro").resolve({ data: { products: [] } });
    await retry;
    assert.equal(ui.searchLoading.value, false);
    assert.equal(ui.searchError.value, "");
    assert.equal(ui.searchSuggestions.value.length, 0);
    assert.deepEqual(added, ["458734"]);
});
