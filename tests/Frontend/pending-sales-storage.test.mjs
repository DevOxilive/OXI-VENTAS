import assert from "node:assert/strict";
import test from "node:test";
import { createPendingSalesStorage } from "../../resources/js/utils/pendingSalesStorage.mjs";

function memoryStorage() {
  const values = new Map();

  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, String(value)),
    removeItem: (key) => values.delete(key),
  };
}

test("las ventas en espera se separan por pestaña aunque compartan navegador", () => {
  const localStorage = memoryStorage();
  const first = createPendingSalesStorage({ localStorage, sessionStorage: memoryStorage() });
  const second = createPendingSalesStorage({ localStorage, sessionStorage: memoryStorage() });

  first.save([{ id: "primera" }]);
  second.save([{ id: "segunda" }]);

  assert.notEqual(first.key(), second.key());
  assert.equal(localStorage.getItem(first.key()), '[{"id":"primera"}]');
  assert.equal(localStorage.getItem(second.key()), '[{"id":"segunda"}]');
});

test("una recarga elimina únicamente las ventas temporales de su propia pestaña", () => {
  const localStorage = memoryStorage();
  const firstSession = memoryStorage();
  const first = createPendingSalesStorage({ localStorage, sessionStorage: firstSession });
  const second = createPendingSalesStorage({ localStorage, sessionStorage: memoryStorage() });

  first.save([{ id: "temporal" }]);
  second.save([{ id: "otra-caja" }]);
  first.clearForPageLoad();

  assert.equal(localStorage.getItem(first.key()), null);
  assert.equal(localStorage.getItem(second.key()), '[{"id":"otra-caja"}]');
});
